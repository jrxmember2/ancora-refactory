<?php

namespace App\Services\Calendar;

class CalendarProviders
{
    /** @var array<string, CalendarProviderInterface> */
    private array $providers;

    /**
     * @param array<string, CalendarProviderInterface>|null $providers Permite injetar provedores
     *        (ex.: em testes). Quando null, usa Google e Microsoft reais.
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? [
            'google' => new GoogleCalendarProvider(),
            'microsoft' => new MicrosoftCalendarProvider(),
        ];
    }

    public function get(?string $key): ?CalendarProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    /** @return array<string, CalendarProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return array<string, CalendarProviderInterface> Apenas provedores com credenciais configuradas. */
    public function configured(): array
    {
        return array_filter($this->providers, fn (CalendarProviderInterface $p) => $p->isConfigured());
    }

    public function hasAnyConfigured(): bool
    {
        return $this->configured() !== [];
    }
}
