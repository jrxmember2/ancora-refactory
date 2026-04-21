<?php

namespace App\Services\Automation;

use App\Models\AutomationDebtSnapshot;
use App\Models\AutomationSession;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientUnit;
use App\Support\Automation\AutomationFlow;
use App\Support\Automation\AutomationStatus;
use App\Support\Automation\AutomationStep;
use App\Support\Automation\AutomationText;
use RuntimeException;

class AutomationConversationService
{
    public function __construct(
        private readonly AutomationSessionService $sessions,
        private readonly AutomationResponseBuilder $responses,
        private readonly AutomationAuditService $audit,
        private readonly AutomationLookupService $lookup,
        private readonly AutomationValidationService $validation,
        private readonly AutomationDebtService $debts,
        private readonly AutomationAgreementService $agreements,
        private readonly AutomationDemandService $demands,
    ) {
    }

    public function process(IncomingAutomationMessageData $message): array
    {
        if ($duplicate = $this->sessions->findDuplicateResponse($message)) {
            return $duplicate;
        }

        $session = $this->sessions->findOrCreateSession($message);
        $inbound = $this->sessions->recordInboundMessage($session, $message);

        try {
            $response = $this->handle($session->fresh(), $message);
            $this->sessions->recordOutboundMessage($session->fresh(), $response);
            $this->sessions->attachResponse($inbound, $response);

            return $response;
        } catch (\Throwable $exception) {
            $this->audit->exception($exception, 'automation.process_message_failed', [
                'payload' => $message->toPayload(),
            ], $session);

            $response = $this->responses->error('Não foi possível processar a mensagem agora.', 500);
            $this->sessions->attachResponse($inbound, $response);

            return $response;
        }
    }

    private function handle(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $normalizedText = AutomationText::normalize($message->messageText);

        if ($this->shouldResetToMenu($normalizedText)) {
            $session = $this->sessions->transition($session, AutomationFlow::MENU, AutomationStep::MENU, [
                'status' => AutomationStatus::ACTIVE,
                'closed_at' => null,
            ], [
                'menu_presented_at' => now()->toIso8601String(),
                'condominium_match_ids' => [],
                'block_match_ids' => [],
            ]);

            return $this->replyMenu($session);
        }

        if (!data_get($session->metadata, 'menu_presented_at')) {
            $session = $this->sessions->mergeMetadata($session, [
                'menu_presented_at' => now()->toIso8601String(),
            ]);

            return $this->replyMenu($session);
        }

        return match ($session->current_step) {
            AutomationStep::MENU => $this->handleMenuSelection($session, $message),
            AutomationStep::COLLECTION_CHOOSE_CONDOMINIUM => $this->handleChooseCondominium($session, $message),
            AutomationStep::COLLECTION_CONFIRM_CONDOMINIUM => $this->handleConfirmCondominium($session, $message),
            AutomationStep::COLLECTION_CHOOSE_BLOCK => $this->handleChooseBlock($session, $message),
            AutomationStep::COLLECTION_CHOOSE_UNIT => $this->handleChooseUnit($session, $message),
            AutomationStep::COLLECTION_VALIDATE_NAME => $this->handleValidateName($session, $message),
            AutomationStep::COLLECTION_VALIDATE_CPF => $this->handleValidateCpf($session, $message),
            AutomationStep::COLLECTION_CONFIRM_INTERLOCUTOR => $this->handleConfirmInterlocutor($session, $message),
            AutomationStep::COLLECTION_CAPTURE_INTERLOCUTOR_NAME => $this->handleCaptureInterlocutorName($session, $message),
            AutomationStep::COLLECTION_OFFER_AGREEMENT => $this->handleOfferAgreement($session, $message),
            AutomationStep::COLLECTION_CHOOSE_PAYMENT_MODE => $this->handleChoosePaymentMode($session, $message),
            AutomationStep::COLLECTION_CHOOSE_INSTALLMENTS => $this->handleChooseInstallments($session, $message),
            AutomationStep::COLLECTION_CHOOSE_FIRST_DUE_DATE => $this->handleChooseFirstDueDate($session, $message),
            default => $this->replyMenu($this->sessions->transition($session, AutomationFlow::MENU, AutomationStep::MENU)),
        };
    }

    private function handleMenuSelection(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $normalized = AutomationText::normalize($message->messageText);

        if (in_array($normalized, ['2', 'cobranca', 'cobrancas'], true)) {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_CONDOMINIUM);

            return $this->responses->reply(
                $session,
                (string) config('automation.messages.choose_condominium'),
                [],
                ['condominium' => null]
            );
        }

        if (in_array($normalized, ['1', 'atendimento', '3', 'duvidas', 'duvida'], true)) {
            return $this->responses->reply(
                $session,
                (string) config('automation.messages.flow_unavailable'),
                $this->menuOptions()
            );
        }

        return $this->responses->reply(
            $session,
            (string) config('automation.messages.invalid_selection'),
            $this->menuOptions()
        );
    }

    private function handleChooseCondominium(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $results = $this->lookup->searchCondominiums($message->messageText, (int) config('automation.collection.search_limit', 10));

        if ($results->isEmpty()) {
            return $this->responses->reply($session, (string) config('automation.messages.condominium_not_found'));
        }

        if ($results->count() === 1) {
            /** @var ClientCondominium $condominium */
            $condominium = $results->first();
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CONFIRM_CONDOMINIUM, [], [
                'condominium_confirmation_mode' => 'single',
                'pending_condominium_id' => $condominium->id,
                'condominium_match_ids' => [$condominium->id],
            ]);

            return $this->responses->reply(
                $session,
                str_replace(':name', $condominium->name, (string) config('automation.messages.condominium_confirm')) . "\n1 - Sim\n2 - Não",
                [
                    ['value' => '1', 'label' => 'Sim'],
                    ['value' => '2', 'label' => 'Não'],
                ],
                ['condominium' => $this->condominiumData($condominium)]
            );
        }

        $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CONFIRM_CONDOMINIUM, [], [
            'condominium_confirmation_mode' => 'list',
            'condominium_match_ids' => $results->pluck('id')->values()->all(),
        ]);

        return $this->responses->reply(
            $session,
            "Encontrei mais de um condomínio. Escolha uma opção:\n" . $results->values()->map(
                fn (ClientCondominium $item, int $index) => ($index + 1) . ' - ' . $item->name
            )->implode("\n"),
            $results->values()->map(
                fn (ClientCondominium $item, int $index) => ['value' => (string) ($index + 1), 'label' => $item->name]
            )->all()
        );
    }

    private function handleConfirmCondominium(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $mode = (string) data_get($session->metadata, 'condominium_confirmation_mode', 'single');
        $matchIds = collect((array) data_get($session->metadata, 'condominium_match_ids', []))->filter()->values();

        if ($mode === 'single') {
            $answer = AutomationText::parseYesNo($message->messageText);
            if ($answer === true) {
                $condominium = ClientCondominium::query()->with('blocks')->find((int) data_get($session->metadata, 'pending_condominium_id'));
                if (!$condominium) {
                    throw new RuntimeException('Condomínio pendente não encontrado para a sessão.');
                }

                return $this->afterCondominiumSelected($session, $condominium);
            }

            if ($answer === false) {
                $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_CONDOMINIUM, [], [
                    'pending_condominium_id' => null,
                    'condominium_match_ids' => [],
                ]);

                return $this->responses->reply($session, (string) config('automation.messages.choose_condominium'));
            }

            return $this->responses->reply($session, (string) config('automation.messages.invalid_selection'));
        }

        $option = AutomationText::parseOption($message->messageText, $matchIds->count());
        if ($option === null) {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_CONDOMINIUM);

            return $this->responses->reply($session, (string) config('automation.messages.choose_condominium'));
        }

        $condominiumId = (int) $matchIds->values()->get($option - 1);
        $condominium = ClientCondominium::query()->with('blocks')->find($condominiumId);
        if (!$condominium) {
            throw new RuntimeException('Condomínio selecionado não encontrado.');
        }

        return $this->afterCondominiumSelected($session, $condominium);
    }

    private function handleChooseBlock(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $condominium = ClientCondominium::query()->with('blocks')->findOrFail((int) $session->condominium_id);
        $matchIds = collect((array) data_get($session->metadata, 'block_match_ids', []))->filter()->values();
        if ($matchIds->isNotEmpty()) {
            $option = AutomationText::parseOption($message->messageText, $matchIds->count());
            if ($option !== null) {
                $block = ClientBlock::query()->find((int) $matchIds->get($option - 1));
                if ($block) {
                    return $this->afterBlockSelected($session, $block);
                }
            }
        }

        $blocks = $this->lookup->searchBlocks($condominium, $message->messageText);
        if ($blocks->isEmpty()) {
            return $this->responses->reply($session, (string) config('automation.messages.block_not_found'));
        }

        if ($blocks->count() === 1) {
            return $this->afterBlockSelected($session, $blocks->first());
        }

        $session = $this->sessions->mergeMetadata($session, [
            'block_match_ids' => $blocks->pluck('id')->values()->all(),
        ]);

        return $this->responses->reply(
            $session,
            "Encontrei mais de um bloco/torre. Escolha uma opção:\n" . $blocks->values()->map(
                fn (ClientBlock $item, int $index) => ($index + 1) . ' - ' . $item->name
            )->implode("\n"),
            $blocks->values()->map(
                fn (ClientBlock $item, int $index) => ['value' => (string) ($index + 1), 'label' => $item->name]
            )->all()
        );
    }

    private function handleChooseUnit(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $condominium = ClientCondominium::query()->findOrFail((int) $session->condominium_id);
        $block = $session->block_id ? ClientBlock::query()->find((int) $session->block_id) : null;
        $unit = $this->lookup->findUnit($condominium, $block, $message->messageText);
        if (!$unit) {
            return $this->responses->reply($session, (string) config('automation.messages.unit_not_found'));
        }

        return $this->afterUnitSelected($session, $unit);
    }

    private function handleValidateName(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $challenge = $this->validation->latestPendingChallenge($session, 'name');
        if (!$challenge) {
            throw new RuntimeException('Desafio de nome não encontrado para a sessão.');
        }

        $result = $this->validation->validateSelection($challenge, $message->messageText);
        if ($result['status'] === 'solved') {
            $person = $session->validatedPerson ?: throw new RuntimeException('Pessoa validada não encontrada.');
            $cpfChallenge = $this->validation->createCpfChallenge($session, $person);
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_VALIDATE_CPF);

            return $this->responses->reply($session, $this->validation->renderChallengePrompt($cpfChallenge));
        }

        if ($result['status'] === 'failed') {
            return $this->forwardToHuman($session, 'validation_name_failed');
        }

        return $this->responses->reply(
            $session,
            $this->validation->renderChallengePrompt($challenge->fresh()) . "\nTentativas restantes: " . $result['attempts_left']
        );
    }

    private function handleValidateCpf(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $challenge = $this->validation->latestPendingChallenge($session, 'cpf_final');
        if (!$challenge) {
            throw new RuntimeException('Desafio de CPF não encontrado para a sessão.');
        }

        $result = $this->validation->validateSelection($challenge, $message->messageText);
        if ($result['status'] === 'solved') {
            $name = AutomationText::firstName($session->validatedPerson?->display_name);
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CONFIRM_INTERLOCUTOR);

            return $this->responses->reply(
                $session,
                str_replace(':name', $name, (string) config('automation.messages.confirm_interlocutor')) . "\n1 - Sim\n2 - Não",
                [
                    ['value' => '1', 'label' => 'Sim'],
                    ['value' => '2', 'label' => 'Não'],
                ]
            );
        }

        if ($result['status'] === 'failed') {
            return $this->forwardToHuman($session, 'validation_cpf_failed');
        }

        return $this->responses->reply(
            $session,
            $this->validation->renderChallengePrompt($challenge->fresh()) . "\nTentativas restantes: " . $result['attempts_left']
        );
    }

    private function handleConfirmInterlocutor(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $answer = AutomationText::parseYesNo($message->messageText);
        if ($answer === true) {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_OFFER_AGREEMENT, [
                'interlocutor_name' => $session->validatedPerson?->display_name,
                'interlocutor_confirmed_at' => now(),
            ]);

            return $this->replyWithDebtsOrClose($session);
        }

        if ($answer === false) {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CAPTURE_INTERLOCUTOR_NAME);

            return $this->responses->reply($session, (string) config('automation.messages.capture_interlocutor_name'));
        }

        return $this->responses->reply($session, (string) config('automation.messages.invalid_selection'));
    }

    private function handleCaptureInterlocutorName(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $name = trim($message->messageText);
        if ($name === '') {
            return $this->responses->reply($session, (string) config('automation.messages.capture_interlocutor_name'));
        }

        $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_OFFER_AGREEMENT, [
            'interlocutor_name' => $name,
            'interlocutor_confirmed_at' => now(),
        ]);

        return $this->replyWithDebtsOrClose($session);
    }

    private function handleOfferAgreement(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $answer = AutomationText::parseYesNo($message->messageText);
        if ($answer === true) {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_PAYMENT_MODE);

            return $this->responses->reply(
                $session,
                (string) config('automation.messages.choose_payment_mode'),
                [
                    ['value' => '1', 'label' => 'À vista'],
                    ['value' => '2', 'label' => 'Parcelado'],
                ]
            );
        }

        if ($answer === false) {
            $session = $this->sessions->close($session);

            return $this->responses->reply(
                $session,
                'Entendido. Se precisar, nossa equipe interna pode continuar o atendimento.',
                [],
                [],
                false,
                true
            );
        }

        return $this->responses->reply($session, (string) config('automation.messages.invalid_selection'));
    }

    private function handleChoosePaymentMode(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $paymentMode = $this->agreements->normalizePaymentMode($message->messageText);
        if (!$paymentMode) {
            return $this->responses->reply($session, (string) config('automation.messages.invalid_selection'));
        }

        $session = $this->sessions->mergeMetadata($session, ['payment_mode' => $paymentMode]);

        if ($paymentMode === 'cash') {
            $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_FIRST_DUE_DATE);

            return $this->responses->reply($session, (string) config('automation.messages.choose_first_due_date'));
        }

        $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_INSTALLMENTS);

        return $this->responses->reply($session, (string) config('automation.messages.choose_installments'));
    }

    private function handleChooseInstallments(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $installments = $this->agreements->normalizeInstallments($message->messageText);
        if (!$installments || !$this->agreements->isInstallmentCountValid($installments)) {
            $min = (int) config('automation.collection.installments.min', 2);
            $max = (int) config('automation.collection.installments.max', 12);

            return $this->responses->reply($session, "Informe uma quantidade de parcelas entre {$min} e {$max}.");
        }

        $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_CHOOSE_FIRST_DUE_DATE, [], [
            'installments' => $installments,
        ]);

        return $this->responses->reply($session, (string) config('automation.messages.choose_first_due_date'));
    }

    private function handleChooseFirstDueDate(AutomationSession $session, IncomingAutomationMessageData $message): array
    {
        $date = AutomationText::parseDate($message->messageText);
        if (!$date) {
            return $this->responses->reply($session, (string) config('automation.messages.choose_first_due_date'));
        }

        if ($error = $this->agreements->validateFirstDueDate($date)) {
            return $this->responses->reply($session, $error);
        }

        $paymentMode = (string) data_get($session->metadata, 'payment_mode', 'cash');
        $installments = $paymentMode === 'installments' ? (int) data_get($session->metadata, 'installments') : null;

        $proposal = $this->agreements->createProposal($session, $paymentMode, $installments, $date);
        $snapshot = $session->debtSnapshots()->latest('id')->first();
        $demand = $this->demands->createDemand($session, $proposal, $snapshot);

        $session = $this->sessions->close($this->sessions->transition(
            $session,
            AutomationFlow::COLLECTION,
            AutomationStep::COLLECTION_FINISH_AND_OPEN_DEMAND
        ));

        return $this->responses->reply(
            $session,
            str_replace(
                [':session_protocol', ':demand_protocol'],
                [$session->protocol, $demand->protocol],
                (string) config('automation.messages.agreement_created')
            ),
            [],
            [
                'proposal' => [
                    'payment_mode' => $proposal->payment_mode,
                    'installments' => $proposal->installments,
                    'first_due_date' => optional($proposal->first_due_date)->format('Y-m-d'),
                    'updated_total' => (float) $proposal->updated_total,
                    'demand_protocol' => $demand->protocol,
                    'calculation_memory' => $proposal->calculation_memory,
                ],
            ],
            false,
            true
        );
    }

    private function afterCondominiumSelected(AutomationSession $session, ClientCondominium $condominium): array
    {
        $hasBlocks = (bool) $condominium->has_blocks && $condominium->blocks()->count() > 0;

        $session = $this->sessions->transition(
            $session,
            AutomationFlow::COLLECTION,
            $hasBlocks ? AutomationStep::COLLECTION_CHOOSE_BLOCK : AutomationStep::COLLECTION_CHOOSE_UNIT,
            [
                'condominium_id' => $condominium->id,
                'block_id' => null,
                'unit_id' => null,
                'validated_person_id' => null,
                'cobranca_case_id' => null,
            ],
            [
                'condominium_match_ids' => [],
                'pending_condominium_id' => null,
                'block_match_ids' => [],
            ]
        );

        return $this->responses->reply(
            $session,
            $hasBlocks ? (string) config('automation.messages.choose_block') : (string) config('automation.messages.choose_unit'),
            [],
            ['condominium' => $this->condominiumData($condominium)]
        );
    }

    private function afterBlockSelected(AutomationSession $session, ClientBlock $block): array
    {
        $session = $this->sessions->transition(
            $session,
            AutomationFlow::COLLECTION,
            AutomationStep::COLLECTION_CHOOSE_UNIT,
            ['block_id' => $block->id],
            ['block_match_ids' => []]
        );

        return $this->responses->reply(
            $session,
            (string) config('automation.messages.choose_unit'),
            [],
            ['unit' => ['block' => $block->name]]
        );
    }

    private function afterUnitSelected(AutomationSession $session, ClientUnit $unit): array
    {
        $case = $this->debts->findOpenCaseForUnit($unit);
        $person = $this->validation->resolveValidatedPerson($unit, $case);
        if (!$person) {
            return $this->forwardToHuman($session, 'validated_person_not_found');
        }

        $session = $this->sessions->transition(
            $session,
            AutomationFlow::COLLECTION,
            AutomationStep::COLLECTION_VALIDATE_NAME,
            [
                'unit_id' => $unit->id,
                'cobranca_case_id' => $case?->id,
                'validated_person_id' => $person->id,
            ]
        );

        $challenge = $this->validation->createNameChallenge($session, $unit->loadMissing(['owner', 'tenant']), $person);

        return $this->responses->reply(
            $session,
            $this->validation->renderChallengePrompt($challenge),
            [],
            ['unit' => $this->unitData($unit)]
        );
    }

    private function replyWithDebtsOrClose(AutomationSession $session): array
    {
        $snapshot = $this->debts->captureSnapshot($session, $session->unit()->with(['condominium', 'block'])->firstOrFail());
        if (!$this->debts->hasOpenDebts($snapshot)) {
            $session = $this->sessions->close($session);

            return $this->responses->reply(
                $session,
                'Olá, ' . AutomationText::firstName($session->interlocutor_name) . '. ' . (string) config('automation.messages.no_debts'),
                [],
                ['debts' => ['has_open_debts' => false, 'items' => []]],
                false,
                true
            );
        }

        $session = $this->sessions->transition($session, AutomationFlow::COLLECTION, AutomationStep::COLLECTION_OFFER_AGREEMENT);

        return $this->responses->reply(
            $session,
            $this->debts->renderDebtMessage(AutomationText::firstName($session->interlocutor_name), $snapshot),
            [],
            [
                'debts' => [
                    'has_open_debts' => true,
                    'items' => $this->debts->presentableDebts($snapshot),
                ],
            ]
        );
    }

    private function forwardToHuman(AutomationSession $session, string $event): array
    {
        $session = $this->sessions->handover($session);
        $this->audit->warning($event, 'Sessão direcionada para atendimento humano.', [], $session);

        return $this->responses->reply(
            $session,
            (string) config('automation.messages.validation_failed') . "\n" . (string) config('automation.messages.human_handover'),
            [],
            [],
            true,
            true
        );
    }

    private function replyMenu(AutomationSession $session): array
    {
        return $this->responses->reply(
            $session,
            (string) config('automation.messages.menu'),
            $this->menuOptions()
        );
    }

    private function menuOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Atendimento'],
            ['value' => '2', 'label' => 'Cobrança'],
            ['value' => '3', 'label' => 'Dúvidas'],
        ];
    }

    private function shouldResetToMenu(string $normalizedText): bool
    {
        return in_array($normalizedText, ['menu', 'inicio', 'reiniciar', 'reiniciar atendimento'], true);
    }

    private function condominiumData(ClientCondominium $condominium): array
    {
        return [
            'id' => $condominium->id,
            'name' => $condominium->name,
            'has_blocks' => (bool) $condominium->has_blocks,
        ];
    }

    private function unitData(ClientUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'block' => $unit->block?->name,
        ];
    }
}
