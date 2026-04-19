<?php

namespace App\Support;

use App\Models\ProcessCasePhase;
use App\Models\ProcessMovementNotificationRead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcessMovementNotifier
{
    public function forUser(User $user): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $acknowledgedAt = ProcessMovementNotificationRead::query()
            ->where('user_id', $user->id)
            ->value('last_acknowledged_at');

        $since = $acknowledgedAt ? Carbon::parse($acknowledgedAt) : Carbon::create(1970, 1, 1, 0, 0, 0, config('app.timezone'));

        $phases = ProcessCasePhase::query()
            ->with(['processCase.statusOption'])
            ->where('created_at', '>', $since)
            ->whereHas('processCase', fn ($query) => $this->applyProcessVisibility($query, $user))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        if ($phases->isEmpty()) {
            return null;
        }

        $cases = $phases
            ->groupBy('process_case_id')
            ->map(function ($group) {
                $latest = $group->first();
                $case = $latest->processCase;

                if (!$case) {
                    return null;
                }

                return [
                    'id' => $case->id,
                    'title' => $case->process_number ?: 'Processo #' . $case->id,
                    'client' => $case->client_name_snapshot ?: 'Cliente nao informado',
                    'adverse' => $case->adverse_name ?: 'Adverso nao informado',
                    'status' => $case->statusOption?->name ?: 'Sem status',
                    'count' => $group->count(),
                    'latest_description' => $latest->description,
                    'latest_at' => $latest->created_at,
                    'url' => route('processos.show', ['processo' => $case, 'tab' => 'fases']),
                ];
            })
            ->filter()
            ->values();

        if ($cases->isEmpty()) {
            return null;
        }

        return [
            'count' => $phases->count(),
            'case_count' => $cases->count(),
            'cases' => $cases,
            'latest_at' => $phases->max('created_at'),
        ];
    }

    public function acknowledge(User $user): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        ProcessMovementNotificationRead::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_acknowledged_at' => now()]
        );
    }

    private function applyProcessVisibility($query, User $user): void
    {
        if ($user->isSuperadmin()) {
            return;
        }

        $userName = $this->normalize($user->name);
        $userEmail = $this->normalize($user->email);

        $query->where(function ($inner) use ($user, $userName, $userEmail) {
            $inner->where('is_private', false)
                ->orWhere('created_by', $user->id);

            if ($userName !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $userName . '%']);
            }

            if ($userEmail !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $userEmail . '%']);
            }
        });
    }

    private function normalize(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))->lower()->squish()->toString();
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('process_cases')
            && Schema::hasTable('process_case_phases')
            && Schema::hasTable('process_movement_notification_reads');
    }
}
