<?php

namespace App\Services;

use App\Models\AgendaEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class AgendaService
{
    /**
     * Resumo de prazos/compromissos do usuario para o indicador do painel:
     * contadores de atrasados, prazos fatais em aberto e proximos, e uma lista curta.
     */
    public function panelSummary(User $user, int $upcomingDays = 7): array
    {
        $events = AgendaEvent::query()
            ->open()
            ->forUser((int) $user->id)
            ->where('start_at', '<=', now()->copy()->addDays(max(1, $upcomingDays))->endOfDay())
            ->orderBy('start_at')
            ->limit(50)
            ->get();

        $overdue = $events->filter(fn (AgendaEvent $event) => $event->isOverdue());
        $upcoming = $events->reject(fn (AgendaEvent $event) => $event->isOverdue());
        $fatalOpen = $events->filter(fn (AgendaEvent $event) => $event->is_fatal);

        $list = $events
            ->take(8)
            ->map(fn (AgendaEvent $event) => [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'type' => (string) $event->type,
                'is_fatal' => (bool) $event->is_fatal,
                'overdue' => $event->isOverdue(),
                'start_at' => $event->start_at,
            ])
            ->values();

        return [
            'total' => $events->count(),
            'overdue_count' => $overdue->count(),
            'upcoming_count' => $upcoming->count(),
            'fatal_count' => $fatalOpen->count(),
            'events' => $list,
        ];
    }
}
