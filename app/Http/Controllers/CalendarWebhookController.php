<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCalendarWebhookJob;
use App\Models\CalendarSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class CalendarWebhookController extends Controller
{
    /**
     * Webhook do Google Calendar (channels). A notificacao vem em headers; o corpo e vazio.
     */
    public function google(Request $request): Response
    {
        if (!Schema::hasTable('calendar_subscriptions')) {
            return response('', 200);
        }

        $channelId = (string) $request->header('X-Goog-Channel-ID', '');
        $token = (string) $request->header('X-Goog-Channel-Token', '');
        $state = (string) $request->header('X-Goog-Resource-State', '');

        if ($channelId === '') {
            return response('', 200);
        }

        $subscription = CalendarSubscription::query()
            ->where('provider', 'google')
            ->where('subscription_id', $channelId)
            ->first();

        // Valida a origem pelo client_state (token do canal).
        if ($subscription && $subscription->client_state && hash_equals((string) $subscription->client_state, $token)) {
            if ($state !== 'sync') { // 'sync' e a mensagem inicial de handshake
                ProcessCalendarWebhookJob::dispatch((int) $subscription->connection_id, 'changes');
            }
        }

        return response('', 200);
    }

    /**
     * Webhook do Microsoft Graph (subscriptions). Trata o handshake de validacao e as notificacoes.
     */
    public function microsoft(Request $request): Response
    {
        // Handshake de validacao: o Graph envia ?validationToken=... e espera o echo em text/plain.
        $validationToken = (string) $request->query('validationToken', '');
        if ($validationToken !== '') {
            return response($validationToken, 200, ['Content-Type' => 'text/plain']);
        }

        if (!Schema::hasTable('calendar_subscriptions')) {
            return response('', 202);
        }

        foreach ((array) $request->input('value', []) as $notification) {
            $subscriptionId = (string) ($notification['subscriptionId'] ?? '');
            $clientState = (string) ($notification['clientState'] ?? '');
            $externalId = (string) ($notification['resourceData']['id'] ?? '');

            if ($subscriptionId === '' || $externalId === '') {
                continue;
            }

            $subscription = CalendarSubscription::query()
                ->where('provider', 'microsoft')
                ->where('subscription_id', $subscriptionId)
                ->first();

            if ($subscription && $subscription->client_state && hash_equals((string) $subscription->client_state, $clientState)) {
                ProcessCalendarWebhookJob::dispatch((int) $subscription->connection_id, 'single', $externalId);
            }
        }

        return response('', 202);
    }
}
