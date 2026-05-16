<?php

namespace App\Console\Commands;

use App\Models\ClientPortalUser;
use App\Services\Mobile\ClientPortalNotificationService;
use Illuminate\Console\Command;

class MobilePushTestCommand extends Command
{
    protected $signature = 'mobile:push:test {client_portal_user_id} {--title=Teste de push} {--body=Seu app Ancora Clientes recebeu uma notificacao de teste.}';

    protected $description = 'Envia uma notificacao de teste para um usuario do Portal do Cliente';

    public function handle(ClientPortalNotificationService $notifications): int
    {
        $user = ClientPortalUser::query()->active()->find((int) $this->argument('client_portal_user_id'));
        if (!$user) {
            $this->error('Usuario do portal nao encontrado ou inativo.');

            return self::FAILURE;
        }

        $notification = $notifications->createGeneralNoticeForUser(
            user: $user,
            title: (string) $this->option('title'),
            body: (string) $this->option('body'),
            data: [
                'screen' => 'notifications',
                'source' => 'mobile:push:test',
            ],
        );

        $this->info('Notificacao registrada com ID #' . $notification->id . '.');

        return self::SUCCESS;
    }
}
