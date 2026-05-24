<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\HubNotification;
use App\Support\Hub\HubApiContext;
use App\Support\Hub\HubApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $filter = strtolower(trim((string) $request->query('filter', 'all')));
        $query = HubNotification::query()
            ->where('user_id', $user->id)
            ->latest('created_at');

        if ($filter === 'unread' || $request->boolean('unread_only')) {
            $query->whereNull('read_at');
            $filter = 'unread';
        } else {
            $filter = 'all';
        }

        $items = $query->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (HubNotification $item) => HubApiPresenter::notification($item))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'filter' => $filter,
                'unread_count' => HubNotification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function read(Request $request, HubNotification $notification): JsonResponse
    {
        $user = HubApiContext::user($request);

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        if ((int) $notification->user_id !== (int) $user->id) {
            return $this->notFoundResponse('Notificação não encontrada.');
        }

        if (!$notification->read_at) {
            $notification->forceFill([
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Notificação marcada como lida.',
            'item' => HubApiPresenter::notification($notification->fresh()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        HubNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Todas as notificações foram marcadas como lidas.',
        ]);
    }
}
