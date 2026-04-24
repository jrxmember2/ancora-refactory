@php
    $alert = $globalSystemAlert ?? [];
    $alertKey = md5(
        implode('|', [
            (string) ($alert['title'] ?? ''),
            (string) ($alert['message'] ?? ''),
            (string) ($alert['level'] ?? ''),
            (string) ($alert['visible_until_input'] ?? ''),
        ])
    );

    $levelStyles = [
        'info' => [
            'wrap' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/40 dark:bg-sky-500/10 dark:text-sky-200',
            'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
            'icon' => 'fa-solid fa-circle-info',
            'label' => 'Informativo',
        ],
        'warning' => [
            'wrap' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-200',
            'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'label' => 'Atenção',
        ],
        'error' => [
            'wrap' => 'border-error-200 bg-error-50 text-error-800 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-200',
            'badge' => 'bg-error-100 text-error-700 dark:bg-error-500/20 dark:text-error-200',
            'icon' => 'fa-solid fa-circle-exclamation',
            'label' => 'Urgente',
        ],
        'success' => [
            'wrap' => 'border-success-200 bg-success-50 text-success-800 dark:border-success-900/40 dark:bg-success-500/10 dark:text-success-200',
            'badge' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-200',
            'icon' => 'fa-solid fa-circle-check',
            'label' => 'Comunicado',
        ],
    ];

    $level = $alert['level'] ?? 'warning';
    $style = $levelStyles[$level] ?? $levelStyles['warning'];
@endphp

@if(($alert['is_active'] ?? false) && (($alert['title'] ?? '') !== '' || ($alert['message'] ?? '') !== ''))
    <div
        x-data="{
            hidden: window.sessionStorage ? sessionStorage.getItem('ancora-system-alert-{{ $alertKey }}') === '1' : false,
            dismiss() {
                this.hidden = true;
                if (window.sessionStorage) {
                    sessionStorage.setItem('ancora-system-alert-{{ $alertKey }}', '1');
                }
            }
        }"
        x-show="!hidden"
        class="mb-6 rounded-2xl border px-5 py-4 {{ $style['wrap'] }}"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <i class="{{ $style['icon'] }} mt-0.5"></i>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $style['badge'] }}">{{ $style['label'] }}</span>
                        @if(!empty($alert['visible_until']))
                            <span class="text-xs opacity-80">Exibido até {{ optional($alert['visible_until'])->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    @if(!empty($alert['title']))
                        <div class="mt-2 font-semibold">{{ $alert['title'] }}</div>
                    @endif
                    @if(!empty($alert['message']))
                        <div class="mt-1 text-sm leading-6">{!! nl2br(e($alert['message'])) !!}</div>
                    @endif
                </div>
            </div>

            <button type="button" @click="dismiss()" class="rounded-xl border border-current/15 px-3 py-2 text-xs font-medium hover:bg-white/40 dark:hover:bg-white/5">
                Fechar
            </button>
        </div>
    </div>
@endif
