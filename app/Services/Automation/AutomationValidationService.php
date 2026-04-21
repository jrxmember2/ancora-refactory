<?php

namespace App\Services\Automation;

use App\Models\AutomationSession;
use App\Models\AutomationValidationChallenge;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Support\Automation\AutomationText;
use Illuminate\Support\Collection;
use RuntimeException;

class AutomationValidationService
{
    public function __construct(private readonly AutomationLookupService $lookup)
    {
    }

    public function resolveValidatedPerson(ClientUnit $unit, ?CobrancaCase $case): ?ClientEntity
    {
        if ($case?->debtor_entity_id) {
            return ClientEntity::query()->find($case->debtor_entity_id);
        }

        return $unit->owner ?: $unit->tenant;
    }

    public function createNameChallenge(AutomationSession $session, ClientUnit $unit, ClientEntity $target): AutomationValidationChallenge
    {
        $optionCount = max(2, (int) config('automation.collection.challenge_option_count', 5));
        $decoys = $this->lookup
            ->decoyEntitiesForUnit($unit, (int) $target->id, $optionCount - 1)
            ->pluck('display_name')
            ->filter()
            ->values();

        while ($decoys->count() < ($optionCount - 1)) {
            $fallback = collect(config('automation.fallback_decoy_names', []))
                ->first(function (string $name) use ($target, $decoys) {
                    return AutomationText::similarity($name, $target->display_name) < 85
                        && !$decoys->contains($name)
                        && $name !== $target->display_name;
                });

            if (!$fallback) {
                break;
            }

            $decoys->push($fallback);
        }

        $options = $decoys
            ->take($optionCount - 1)
            ->push($target->display_name)
            ->shuffle()
            ->values();

        $correctIndex = (int) $options->search($target->display_name, true) + 1;

        return AutomationValidationChallenge::query()->create([
            'session_id' => $session->id,
            'type' => 'name',
            'correct_value_hash' => hash('sha256', $target->id . '|' . $target->display_name),
            'displayed_options' => $options->all(),
            'correct_option_index' => $correctIndex,
            'attempts' => 0,
            'max_attempts' => (int) config('automation.collection.max_attempts', 3),
        ]);
    }

    public function createCpfChallenge(AutomationSession $session, ClientEntity $target): AutomationValidationChallenge
    {
        $correctCpfFinal = $this->cpfFinal($target->cpf_cnpj);
        if (!$correctCpfFinal) {
            throw new RuntimeException('Não foi possível gerar o desafio de CPF para a unidade selecionada.');
        }

        $optionCount = max(2, (int) config('automation.collection.challenge_option_count', 5));
        $decoys = $this->lookup->cpfFinalCandidates((int) $target->id, $optionCount * 4)
            ->filter(fn (string $cpfFinal) => $cpfFinal !== $correctCpfFinal)
            ->unique()
            ->take($optionCount - 1)
            ->values();

        while ($decoys->count() < ($optionCount - 1)) {
            $candidate = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if ($candidate !== $correctCpfFinal && !$decoys->contains($candidate)) {
                $decoys->push($candidate);
            }
        }

        $options = $decoys
            ->take($optionCount - 1)
            ->push($correctCpfFinal)
            ->shuffle()
            ->values();

        $correctIndex = (int) $options->search($correctCpfFinal, true) + 1;

        return AutomationValidationChallenge::query()->create([
            'session_id' => $session->id,
            'type' => 'cpf_final',
            'correct_value_hash' => hash('sha256', $correctCpfFinal),
            'displayed_options' => $options->all(),
            'correct_option_index' => $correctIndex,
            'attempts' => 0,
            'max_attempts' => (int) config('automation.collection.max_attempts', 3),
        ]);
    }

    public function latestPendingChallenge(AutomationSession $session, string $type): ?AutomationValidationChallenge
    {
        return AutomationValidationChallenge::query()
            ->where('session_id', $session->id)
            ->where('type', $type)
            ->whereNull('solved_at')
            ->whereNull('failed_at')
            ->latest('id')
            ->first();
    }

    public function validateSelection(AutomationValidationChallenge $challenge, ?string $messageText): array
    {
        $selected = AutomationText::parseOption($messageText, count($challenge->displayed_options ?? []));
        $attempts = (int) $challenge->attempts + 1;

        if ($selected !== null && $selected === (int) $challenge->correct_option_index) {
            $challenge->update([
                'attempts' => $attempts,
                'solved_at' => now(),
            ]);

            return [
                'status' => 'solved',
                'attempts' => $attempts,
                'attempts_left' => max(0, (int) $challenge->max_attempts - $attempts),
            ];
        }

        $payload = ['attempts' => $attempts];
        $status = 'retry';
        if ($attempts >= (int) $challenge->max_attempts) {
            $payload['failed_at'] = now();
            $status = 'failed';
        }

        $challenge->update($payload);

        return [
            'status' => $status,
            'attempts' => $attempts,
            'attempts_left' => max(0, (int) $challenge->max_attempts - $attempts),
        ];
    }

    public function renderChallengePrompt(AutomationValidationChallenge $challenge): string
    {
        $intro = $challenge->type === 'name'
            ? 'Para continuar, selecione o nome vinculado à unidade:'
            : 'Agora selecione os 5 últimos dígitos do CPF vinculado à unidade:';

        $options = collect($challenge->displayed_options ?? [])
            ->values()
            ->map(fn (string $option, int $index) => ($index + 1) . ' - ' . $option)
            ->implode("\n");

        return $intro . "\n" . $options;
    }

    private function cpfFinal(?string $document): ?string
    {
        $digits = AutomationText::digits($document);
        if (strlen($digits) !== 11) {
            return null;
        }

        return substr($digits, -5);
    }
}
