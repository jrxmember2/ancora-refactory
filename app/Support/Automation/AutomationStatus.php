<?php

namespace App\Support\Automation;

final class AutomationStatus
{
    public const ACTIVE = 'active';
    public const HANDOVER_HUMAN = 'handover_human';
    public const CLOSED = 'closed';
    public const EXPIRED = 'expired';

    private function __construct()
    {
    }
}
