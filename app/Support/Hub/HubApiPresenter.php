<?php

namespace App\Support\Hub;

use App\Models\HubNotification;
use App\Models\SystemModule;
use App\Models\User;
use App\Support\AncoraRouteCatalog;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HubApiPresenter
{
    public static function authPayload(
        User $user,
        string $plainTextToken,
        DateTimeInterface|string|null $expiresAt,
        array $sessionPolicy,
    ): array {
        return [
            'token_type' => 'Bearer',
            'token' => $plainTextToken,
            'expires_at' => self::formatDate($expiresAt),
            'user' => self::user($user),
            'modules' => self::modules($user),
            'permissions' => self::permissions($user),
            'session_policy' => $sessionPolicy,
        ];
    }

    public static function profilePayload(
        User $user,
        DateTimeInterface|string|null $expiresAt,
        array $sessionPolicy,
    ): array {
        return [
            'expires_at' => self::formatDate($expiresAt),
            'user' => self::user($user),
            'modules' => self::modules($user),
            'permissions' => self::permissions($user),
            'session_policy' => $sessionPolicy,
        ];
    }

    public static function user(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
            'is_superadmin' => $user->isSuperadmin(),
            'is_active' => (bool) $user->is_active,
            'theme_preference' => $user->theme_preference ? (string) $user->theme_preference : 'dark',
            'avatar_url' => $user->avatar_url,
            'initials' => $user->initials,
            'last_login_at' => $user->last_login_at?->toAtomString(),
            'last_seen_at' => $user->last_seen_at?->toAtomString(),
        ];
    }

    public static function modules(User $user): array
    {
        $modules = self::accessibleModules($user);

        return $modules
            ->map(function (SystemModule $module) {
                $meta = self::moduleCatalog()[$module->slug] ?? [];

                return [
                    'id' => (int) $module->id,
                    'slug' => (string) $module->slug,
                    'name' => (string) $module->name,
                    'display_name' => (string) ($meta['display_name'] ?? $module->name),
                    'icon_class' => (string) ($module->icon_class ?: ($meta['icon_class'] ?? 'fa-solid fa-cube')),
                    'route_prefix' => $module->route_prefix ? (string) $module->route_prefix : null,
                    'entry_route_name' => $meta['entry_route_name'] ?? null,
                    'accent' => (string) ($meta['accent'] ?? 'brand'),
                    'app_route' => self::appRouteForModule((string) $module->slug),
                    'enabled' => (bool) $module->is_enabled,
                ];
            })
            ->values()
            ->all();
    }

    public static function permissions(User $user): array
    {
        if ($user->isSuperadmin()) {
            return [
                'grants_all_routes' => true,
                'group_keys' => array_keys(AncoraRouteCatalog::groups()),
                'route_names' => array_keys(AncoraRouteCatalog::flat()),
            ];
        }

        $permissions = self::routePermissions($user);

        return [
            'grants_all_routes' => false,
            'group_keys' => $permissions
                ->pluck('group_key')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->unique()
                ->values()
                ->all(),
            'route_names' => $permissions
                ->pluck('route_name')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->values()
                ->all(),
        ];
    }

    public static function notification(HubNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'title' => (string) $notification->title,
            'body' => (string) $notification->body,
            'type' => $notification->type ? (string) $notification->type : null,
            'module' => $notification->module ? (string) $notification->module : null,
            'entity_type' => $notification->entity_type ? (string) $notification->entity_type : null,
            'entity_id' => $notification->entity_id ? (int) $notification->entity_id : null,
            'action_url' => $notification->action_url ? (string) $notification->action_url : null,
            'route' => self::notificationRouteValue($notification),
            'action_label' => 'Ver detalhes',
            'data' => $notification->data_json ?? [],
            'read_at' => $notification->read_at?->toAtomString(),
            'created_at' => $notification->created_at?->toAtomString(),
            'created_at_br' => $notification->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function notificationRouteValue(HubNotification $notification): ?string
    {
        $data = $notification->data_json ?? [];
        $candidate = self::routeCandidateValue(
            route: $data['route'] ?? null,
            screen: $data['screen'] ?? null,
            module: $data['module'] ?? $notification->module,
            type: $notification->type,
            actionUrl: $notification->action_url,
        );

        return self::normalizeRouteAlias($candidate);
    }

    public static function appRouteForModule(?string $slug): ?string
    {
        return self::normalizeRouteAlias($slug);
    }

    public static function moduleCatalog(): array
    {
        return [
            'dashboard' => [
                'display_name' => 'Hub',
                'icon_class' => 'fa-solid fa-house',
                'entry_route_name' => 'dashboard',
                'accent' => 'brand',
            ],
            'propostas' => [
                'display_name' => 'Propostas',
                'icon_class' => 'fa-solid fa-file-signature',
                'entry_route_name' => 'propostas.dashboard',
                'accent' => 'brand',
            ],
            'busca' => [
                'display_name' => 'Busca',
                'icon_class' => 'fa-solid fa-magnifying-glass',
                'entry_route_name' => 'busca.index',
                'accent' => 'info',
            ],
            'config' => [
                'display_name' => 'Configuração',
                'icon_class' => 'fa-solid fa-gear',
                'entry_route_name' => 'config.index',
                'accent' => 'neutral',
            ],
            'logs' => [
                'display_name' => 'Logs',
                'icon_class' => 'fa-solid fa-clock-rotate-left',
                'entry_route_name' => 'logs.index',
                'accent' => 'neutral',
            ],
            'clientes' => [
                'display_name' => 'Clientes',
                'icon_class' => 'fa-solid fa-users',
                'entry_route_name' => 'clientes.index',
                'accent' => 'success',
            ],
            'cobrancas' => [
                'display_name' => 'Cobranças',
                'icon_class' => 'fa-solid fa-money-bill-wave',
                'entry_route_name' => 'cobrancas.dashboard',
                'accent' => 'warning',
            ],
            'demandas' => [
                'display_name' => 'Demandas',
                'icon_class' => 'fa-solid fa-inbox',
                'entry_route_name' => 'demandas.dashboard',
                'accent' => 'info',
            ],
            'processos' => [
                'display_name' => 'Processos',
                'icon_class' => 'fa-solid fa-scale-balanced',
                'entry_route_name' => 'processos.dashboard',
                'accent' => 'brand',
            ],
            'contratos' => [
                'display_name' => 'Contratos',
                'icon_class' => 'fa-solid fa-file-contract',
                'entry_route_name' => 'contratos.dashboard',
                'accent' => 'info',
            ],
            'assinador' => [
                'display_name' => 'Assinaturas',
                'icon_class' => 'fa-solid fa-signature',
                'entry_route_name' => 'assinador.dashboard',
                'accent' => 'brand',
            ],
            'financeiro' => [
                'display_name' => 'Financeiro',
                'icon_class' => 'fa-solid fa-chart-pie',
                'entry_route_name' => 'financeiro.dashboard',
                'accent' => 'success',
            ],
            'ia' => [
                'display_name' => 'Leme IA',
                'icon_class' => 'fa-solid fa-comments',
                'entry_route_name' => 'ia.office-chat.index',
                'accent' => 'brand',
            ],
        ];
    }

    private static function routeCandidateValue(
        mixed $route,
        mixed $screen,
        mixed $module,
        mixed $type,
        mixed $actionUrl,
    ): ?string {
        foreach ([$route, $screen, $module] as $candidate) {
            $normalized = self::normalizeRouteAlias($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $typeCandidate = self::normalizeRouteAlias(self::routeAliasFromType($type));
        if ($typeCandidate !== null) {
            return $typeCandidate;
        }

        if (is_string($actionUrl) && trim($actionUrl) !== '') {
            $normalized = self::normalizeRouteAlias(Str::of($actionUrl)->afterLast('/')->toString());
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private static function routeAliasFromType(mixed $type): ?string
    {
        $value = Str::of((string) $type)->lower()->ascii()->replace([' ', '-'], '_')->toString();

        return match ($value) {
            'nova_demanda', 'demand_created', 'demand_status_changed', 'demand_new_message' => 'demands',
            'novo_andamento_processual', 'process_new_phase', 'processo_atualizado', 'process_status_changed' => 'processes',
            'acordo_vencido', 'conta_vencida' => 'collections',
            'assinatura_concluida' => 'signer',
            'contrato_pendente' => 'contracts',
            default => null,
        };
    }

    private static function normalizeRouteAlias(mixed $value): ?string
    {
        $candidate = Str::of((string) $value)
            ->trim()
            ->lower()
            ->ascii()
            ->replace('.', '-')
            ->replace('_', '-')
            ->toString();

        if ($candidate === '') {
            return null;
        }

        return match ($candidate) {
            'dashboard', 'inicio', 'home' => 'dashboard',
            'notifications', 'notificacoes', 'notification' => 'notifications',
            'profile', 'perfil' => 'profile',
            'demands', 'demandas', 'demanda' => 'demands',
            'processes', 'processos', 'processo' => 'processes',
            'collections', 'cobrancas', 'cobranca' => 'collections',
            'clients', 'clientes', 'cliente' => 'clients',
            'proposals', 'propostas', 'proposta' => 'proposals',
            'contracts', 'contratos', 'contrato' => 'contracts',
            'signer', 'assinador', 'assinaturas', 'assinatura' => 'signer',
            'finance', 'financeiro', 'financeiro-360', 'financeiro360' => 'finance',
            'leme-ia', 'lemeia', 'ia' => 'leme-ia',
            'settings', 'configuracoes', 'configuracao', 'config' => 'settings',
            'more', 'mais' => 'more',
            default => null,
        };
    }

    private static function accessibleModules(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return SystemModule::query()->enabled()->get();
        }

        if ($user->relationLoaded('modules')) {
            return $user->modules
                ->filter(fn (SystemModule $module) => (bool) $module->is_enabled)
                ->sortBy(fn (SystemModule $module) => sprintf(
                    '%08d-%s',
                    (int) ($module->sort_order ?? 0),
                    mb_strtolower((string) $module->name)
                ))
                ->values();
        }

        return $user->modules()
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private static function routePermissions(User $user): Collection
    {
        if ($user->relationLoaded('routePermissions')) {
            return $user->routePermissions
                ->sortBy(fn ($permission) => mb_strtolower(
                    trim((string) $permission->group_key) . '|' . trim((string) $permission->route_name)
                ))
                ->values();
        }

        return $user->routePermissions()
            ->orderBy('route_permissions.group_key')
            ->orderBy('route_permissions.route_name')
            ->get();
    }

    private static function formatDate(DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : ($value ? (string) $value : null);
    }
}
