<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Hub\HubNotificationService;
use Illuminate\Console\Command;

class HubPushTestCommand extends Command
{
    protected $signature = 'hub:push:test
        {user_id : ID do usuário interno}
        {--title=Teste de push}
        {--body=Seu app Âncora Hub recebeu uma notificação de teste.}
        {--type=notificacao_geral}
        {--route=notifications}
        {--module=hub}';

    protected $description = 'Registra uma notificação de teste para um usuário interno do Âncora Hub';

    public function handle(HubNotificationService $notifications): int
    {
        $user = User::query()->active()->find((int) $this->argument('user_id'));
        if (!$user) {
            $this->error('Usuário interno não encontrado ou inativo.');

            return self::FAILURE;
        }

        $notification = $notifications->createGeneralNoticeForUser(
            user: $user,
            title: (string) $this->option('title'),
            body: (string) $this->option('body'),
            data: [
                'route' => (string) $this->option('route'),
                'module' => (string) $this->option('module'),
                'source' => 'hub:push:test',
            ],
            type: (string) $this->option('type'),
            module: (string) $this->option('module'),
        );

        $this->info('Notificação registrada com ID #' . $notification->id . '.');

        return self::SUCCESS;
    }
}
