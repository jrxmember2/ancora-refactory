<?php

namespace App\Support\Automation;

final class AutomationStep
{
    public const MENU = 'menu';
    public const COLLECTION_CHOOSE_CONDOMINIUM = 'collection_choose_condominium';
    public const COLLECTION_CONFIRM_CONDOMINIUM = 'collection_confirm_condominium';
    public const COLLECTION_CHOOSE_BLOCK = 'collection_choose_block';
    public const COLLECTION_CHOOSE_UNIT = 'collection_choose_unit';
    public const COLLECTION_VALIDATE_NAME = 'collection_validate_name';
    public const COLLECTION_VALIDATE_CPF = 'collection_validate_cpf';
    public const COLLECTION_CONFIRM_INTERLOCUTOR = 'collection_confirm_interlocutor';
    public const COLLECTION_CAPTURE_INTERLOCUTOR_NAME = 'collection_capture_interlocutor_name';
    public const COLLECTION_SHOW_DEBTS = 'collection_show_debts';
    public const COLLECTION_OFFER_AGREEMENT = 'collection_offer_agreement';
    public const COLLECTION_CHOOSE_PAYMENT_MODE = 'collection_choose_payment_mode';
    public const COLLECTION_CHOOSE_INSTALLMENTS = 'collection_choose_installments';
    public const COLLECTION_CHOOSE_FIRST_DUE_DATE = 'collection_choose_first_due_date';
    public const COLLECTION_FINISH_AND_OPEN_DEMAND = 'collection_finish_and_open_demand';
    public const HANDOVER_HUMAN = 'handover_human';
    public const CLOSED = 'closed';
    public const EXPIRED = 'expired';

    private function __construct()
    {
    }
}
