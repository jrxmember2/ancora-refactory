<?php

namespace App\Http\Controllers;

use App\Models\Administradora;
use App\Models\AppSetting;
use App\Models\FormaEnvio;
use App\Models\RoutePermission;
use App\Models\Servico;
use App\Models\StatusRetorno;
use App\Models\SystemModule;
use App\Models\User;
use App\Support\AncoraAuth;
use App\Support\AncoraRouteCatalog;
use App\Support\AncoraSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function index(): View
    {
        $this->ensureRoutePermissionsSynced();
        $logoLightPath = AppSetting::getValue('branding_logo_light_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $logoDarkPath = AppSetting::getValue('branding_logo_dark_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $faviconPath = AppSetting::getValue('branding_favicon_path', '/favicon.svg') ?: '/favicon.svg';
        $premiumLogoVariant = AppSetting::getValue('branding_premium_logo_variant', 'light') === 'dark' ? 'dark' : 'light';
        $routePermissions = RoutePermission::query()->orderBy('group_key')->orderBy('label')->get()->groupBy('group_key');
        $users = User::query()->with(['modules', 'routePermissions'])->orderByDesc('is_protected')->orderBy('name')->get();
        $accessProfiles = $this->accessProfiles();

        foreach ($users as $listedUser) {
            $listedUser->access_mode_value = $this->resolveUserAccessMode($listedUser, $accessProfiles);
        }

        return view('pages.admin.config', [
            'title' => 'Configurações',
            'servicos' => Servico::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statusRetorno' => StatusRetorno::query()->orderBy('sort_order')->orderBy('name')->get(),
            'formasEnvio' => FormaEnvio::query()->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $users,
            'modules' => SystemModule::query()->orderBy('sort_order')->orderBy('name')->get(),
            'routePermissionGroups' => $routePermissions,
            'routeCatalog' => AncoraRouteCatalog::groups(),
            'accessProfiles' => $accessProfiles,
            'branding' => [
                'company_name' => AppSetting::getValue('app_company', 'Serratech Soluções em TI') ?: '',
                'app_slogan' => AppSetting::getValue('app_slogan', '') ?: '',
                'company_address' => AppSetting::getValue('company_address', '') ?: '',
                'company_phone' => AppSetting::getValue('company_phone', '') ?: '',
                'company_email' => AppSetting::getValue('company_email', '') ?: '',
                'company_website' => AppSetting::getValue('company_website', config('app.url')) ?: (config('app.url') ?: ''),
                'company_social_primary' => AppSetting::getValue('company_social_primary', '') ?: '',
                'company_social_secondary' => AppSetting::getValue('company_social_secondary', '') ?: '',
                'logo_light_path' => $logoLightPath,
                'logo_light_url' => $this->brandingAssetUrl($logoLightPath, '/imgs/logomarca.svg'),
                'logo_dark_path' => $logoDarkPath,
                'logo_dark_url' => $this->brandingAssetUrl($logoDarkPath, '/imgs/logomarca.svg'),
                'premium_logo_variant' => $premiumLogoVariant,
                'logo_height_desktop' => (int) AppSetting::getValue('branding_logo_height_desktop', '44'),
                'logo_height_mobile' => (int) AppSetting::getValue('branding_logo_height_mobile', '36'),
                'logo_height_login' => (int) AppSetting::getValue('branding_logo_height_login', '82'),
                'favicon_path' => $faviconPath,
                'favicon_url' => $this->brandingAssetUrl($faviconPath, '/favicon.svg'),
            ],
            'smtp' => AncoraSettings::smtp(),
        ]);
    }

    public function saveBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:180'],
            'app_slogan' => ['nullable', 'string', 'max:180'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:190'],
            'company_website' => ['nullable', 'string', 'max:190'],
            'company_social_primary' => ['nullable', 'string', 'max:120'],
            'company_social_secondary' => ['nullable', 'string', 'max:120'],
            'premium_logo_variant' => ['nullable', Rule::in(['light', 'dark'])],
            'logo_height_desktop' => ['nullable', 'integer', 'min:20', 'max:140'],
            'logo_height_mobile' => ['nullable', 'integer', 'min:20', 'max:120'],
            'logo_height_login' => ['nullable', 'integer', 'min:30', 'max:220'],
            'branding_logo_light' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:3072'],
            'branding_logo_dark' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:3072'],
        ]);

        $currentLightPath = AppSetting::getValue('branding_logo_light_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $currentDarkPath = AppSetting::getValue('branding_logo_dark_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';

        $newLightPath = $currentLightPath;
        $newDarkPath = $currentDarkPath;

        if ($request->hasFile('branding_logo_light')) {
            $newLightPath = $this->storeBrandingAsset($request->file('branding_logo_light'), 'logo-light', $currentLightPath);
        }
        if ($request->hasFile('branding_logo_dark')) {
            $newDarkPath = $this->storeBrandingAsset($request->file('branding_logo_dark'), 'logo-dark', $currentDarkPath);
        }

        $this->setMany([
            'app_company' => [$validated['company_name'] ?? '', 'Nome da empresa exibido no sistema'],
            'app_slogan' => [$validated['app_slogan'] ?? '', 'Slogan institucional do sistema'],
            'company_address' => [$validated['company_address'] ?? '', 'Endereço exibido no rodapé e PDF'],
            'company_phone' => [$validated['company_phone'] ?? '', 'Telefone exibido no rodapé e PDF'],
            'company_email' => [$validated['company_email'] ?? '', 'E-mail exibido no rodapé e PDF'],
            'company_website' => [$validated['company_website'] ?? '', 'Site exibido no rodapé e PDF'],
            'company_social_primary' => [$validated['company_social_primary'] ?? '', 'Rede social principal exibida no PDF'],
            'company_social_secondary' => [$validated['company_social_secondary'] ?? '', 'Rede social secundária exibida no PDF'],
            'branding_logo_light_path' => [$newLightPath, 'Logo usada no tema claro'],
            'branding_logo_dark_path' => [$newDarkPath, 'Logo usada no tema escuro'],
            'branding_premium_logo_variant' => [$validated['premium_logo_variant'] ?? 'light', 'Logo escolhida para o preview/PDF premium'],
            'branding_logo_height_desktop' => [(string) ($validated['logo_height_desktop'] ?? 44), 'Altura da logo no header desktop'],
            'branding_logo_height_mobile' => [(string) ($validated['logo_height_mobile'] ?? 36), 'Altura da logo no header mobile'],
            'branding_logo_height_login' => [(string) ($validated['logo_height_login'] ?? 82), 'Altura da logo na tela de login'],
            'powered_by_name' => [AppSetting::getValue('powered_by_name', 'Serratech Soluções em TI') ?: 'Serratech Soluções em TI', 'Créditos de desenvolvimento'],
            'powered_by_url' => [AppSetting::getValue('powered_by_url', 'https://serratech.tec.br') ?: 'https://serratech.tec.br', 'URL dos créditos de desenvolvimento'],
        ]);

        return back()->with('success', 'Branding atualizado com sucesso.');
    }

    public function saveFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'branding_favicon' => ['required', 'file', 'mimes:ico,png,svg', 'max:1024'],
        ]);

        $currentPath = AppSetting::getValue('branding_favicon_path', '/favicon.svg') ?: '/favicon.svg';
        $newPath = $this->storeBrandingAsset($request->file('branding_favicon'), 'favicon', $currentPath);
        AppSetting::setValue('branding_favicon_path', $newPath, 'Caminho público do favicon do sistema');

        return back()->with('success', 'Favicon atualizado com sucesso.');
    }

    public function saveModules(Request $request): RedirectResponse
    {
        $enabledIds = array_map('intval', (array) $request->input('enabled_modules', []));

        foreach (SystemModule::query()->get() as $module) {
            $mustStayEnabled = in_array($module->slug, ['dashboard', 'propostas', 'config'], true);
            $module->update(['is_enabled' => $mustStayEnabled || in_array((int) $module->id, $enabledIds, true)]);
        }

        return back()->with('success', 'Módulos atualizados com sucesso.');
    }

    public function saveSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:190'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'smtp_from_address' => ['nullable', 'email', 'max:190'],
            'smtp_from_name' => ['nullable', 'string', 'max:190'],
        ]);

        $this->setMany([
            'smtp_host' => [$validated['smtp_host'] ?? '', 'Host SMTP do sistema'],
            'smtp_port' => [(string) ($validated['smtp_port'] ?? 587), 'Porta SMTP do sistema'],
            'smtp_username' => [$validated['smtp_username'] ?? '', 'Usuário SMTP do sistema'],
            'smtp_password' => [$validated['smtp_password'] ?? '', 'Senha SMTP do sistema'],
            'smtp_encryption' => [$validated['smtp_encryption'] ?? 'tls', 'Criptografia SMTP do sistema'],
            'smtp_from_address' => [$validated['smtp_from_address'] ?? '', 'E-mail remetente do sistema'],
            'smtp_from_name' => [$validated['smtp_from_name'] ?? 'Âncora', 'Nome remetente do sistema'],
        ]);

        return back()->with('success', 'SMTP atualizado com sucesso.');
    }

    public function saveAccessProfiles(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_slug' => ['required', 'string', 'max:80'],
            'profile_name' => ['required', 'string', 'max:120'],
            'profile_modules' => ['array'],
            'profile_routes' => ['array'],
        ]);

        $profiles = collect($this->accessProfiles())->keyBy('slug');
        $slug = Str::slug($validated['profile_slug']);
        if ($slug === '') {
            $slug = Str::slug($validated['profile_name']);
        }

        $profiles[$slug] = [
            'slug' => $slug,
            'name' => $validated['profile_name'],
            'module_ids' => array_values(array_map('intval', (array) ($validated['profile_modules'] ?? []))),
            'route_ids' => array_values(array_map('intval', (array) ($validated['profile_routes'] ?? []))),
        ];

        AppSetting::setValue('access_profiles_json', json_encode($profiles->values()->all(), JSON_UNESCAPED_UNICODE), 'Perfis de acesso parametrizados');
        return back()->with('success', 'Perfil de acesso salvo com sucesso.');
    }

    public function deleteAccessProfile(string $slug): RedirectResponse
    {
        $profiles = collect($this->accessProfiles())->reject(fn (array $profile) => $profile['slug'] === $slug)->values()->all();
        AppSetting::setValue('access_profiles_json', json_encode($profiles, JSON_UNESCAPED_UNICODE), 'Perfis de acesso parametrizados');
        return back()->with('success', 'Perfil de acesso excluído.');
    }

    public function storeAdministradora(Request $request): RedirectResponse
    {
        Administradora::query()->create($this->administradoraPayload($request, null));
        return back()->with('success', 'Administradora cadastrada.');
    }

    public function updateAdministradora(Request $request, Administradora $administradora): RedirectResponse
    {
        $administradora->update($this->administradoraPayload($request, $administradora));
        return back()->with('success', 'Administradora atualizada.');
    }

    public function deleteAdministradora(Administradora $administradora): RedirectResponse
    {
        $administradora->delete();
        return back()->with('success', 'Administradora excluída.');
    }

    public function storeServico(Request $request): RedirectResponse
    {
        Servico::query()->create($this->servicoPayload($request));
        return back()->with('success', 'Serviço cadastrado.');
    }

    public function updateServico(Request $request, Servico $servico): RedirectResponse
    {
        $servico->update($this->servicoPayload($request));
        return back()->with('success', 'Serviço atualizado.');
    }

    public function deleteServico(Servico $servico): RedirectResponse
    {
        $servico->delete();
        return back()->with('success', 'Serviço excluído.');
    }

    public function storeStatus(Request $request): RedirectResponse
    {
        StatusRetorno::query()->create($this->statusPayload($request));
        return back()->with('success', 'Status cadastrado.');
    }

    public function updateStatus(Request $request, StatusRetorno $status): RedirectResponse
    {
        $status->update($this->statusPayload($request));
        return back()->with('success', 'Status atualizado.');
    }

    public function deleteStatus(StatusRetorno $status): RedirectResponse
    {
        $status->delete();
        return back()->with('success', 'Status excluído.');
    }

    public function storeFormaEnvio(Request $request): RedirectResponse
    {
        FormaEnvio::query()->create($this->formaPayload($request));
        return back()->with('success', 'Forma de envio cadastrada.');
    }

    public function updateFormaEnvio(Request $request, FormaEnvio $forma): RedirectResponse
    {
        $forma->update($this->formaPayload($request));
        return back()->with('success', 'Forma de envio atualizada.');
    }

    public function deleteFormaEnvio(FormaEnvio $forma): RedirectResponse
    {
        $forma->delete();
        return back()->with('success', 'Forma de envio excluída.');
    }

    public function storeUsuario(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'access_mode' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable'],
        ]);

        [$role, $profileSlug] = $this->parseAccessMode((string) $validated['access_mode']);

        DB::transaction(function () use ($request, $validated, $role, $profileSlug) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'theme_preference' => 'dark',
                'password_hash' => password_hash($validated['password'], PASSWORD_DEFAULT),
                'role' => $role,
                'is_active' => $request->boolean('is_active'),
                'is_protected' => 0,
            ]);
            $this->syncUserPermissions($user, $request, $profileSlug);
        });

        return back()->with('success', 'Usuário cadastrado.');
    }

    public function updateUsuario(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'access_mode' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable'],
        ]);

        [$role, $profileSlug] = $this->parseAccessMode((string) $validated['access_mode']);
        $shouldRemainActive = $user->is_protected ? true : $request->boolean('is_active');
        $demotingSuperadmin = $user->role === 'superadmin' && $role !== 'superadmin';

        if (!$shouldRemainActive && $user->role === 'superadmin' && !$this->hasAnotherSuperadmin($user)) {
            return back()->with('error', 'É necessário manter ao menos um superadmin ativo no sistema.');
        }

        if ($demotingSuperadmin && !$this->hasAnotherSuperadmin($user)) {
            return back()->with('error', 'Crie ou mantenha outro superadmin antes de alterar este usuário para perfil comum.');
        }

        DB::transaction(function () use ($request, $validated, $user, $role, $profileSlug, $shouldRemainActive, $demotingSuperadmin) {
            if ($demotingSuperadmin && $user->is_protected) {
                $user->update(['is_protected' => 0]);
                $user->refresh();
            }

            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $role,
                'is_active' => $shouldRemainActive,
            ];
            if (!empty($validated['password'])) {
                $payload['password_hash'] = password_hash($validated['password'], PASSWORD_DEFAULT);
            }
            $user->update($payload);
            $this->syncUserPermissions($user, $request, $profileSlug);
        });

        if (AncoraAuth::user($request)?->id === $user->id) {
            AncoraAuth::cacheSessionUser($request, $user->fresh(['modules', 'routePermissions']));
        }

        return back()->with('success', 'Usuário atualizado.');
    }

    public function deleteUsuario(User $user): RedirectResponse
    {
        if ($user->is_protected) {
            return back()->with('error', 'Os superadmins principais não podem ser excluídos.');
        }
        if ($user->role === 'superadmin' && !$this->hasAnotherSuperadmin($user)) {
            return back()->with('error', 'É necessário manter ao menos um superadmin ativo no sistema.');
        }
        $user->delete();
        return back()->with('success', 'Usuário excluído.');
    }

    private function ensureRoutePermissionsSynced(): void
    {
        foreach (AncoraRouteCatalog::groups() as $groupKey => $group) {
            foreach ($group['routes'] as $routeName => $label) {
                RoutePermission::query()->updateOrCreate(
                    ['route_name' => $routeName],
                    ['group_key' => $groupKey, 'label' => $label]
                );
            }
        }
    }

    private function syncUserPermissions(User $user, Request $request, ?string $forcedProfileSlug = null): void
    {
        if ($user->role === 'superadmin') {
            $user->modules()->sync([]);
            $user->routePermissions()->sync([]);
            return;
        }

        $profileSlug = trim((string) ($forcedProfileSlug ?? $request->input('access_profile_slug', '')));
        if ($profileSlug !== '') {
            $profile = collect($this->accessProfiles())->firstWhere('slug', $profileSlug);
            if ($profile) {
                $user->modules()->sync(array_map('intval', $profile['module_ids'] ?? []));
                $user->routePermissions()->sync(array_map('intval', $profile['route_ids'] ?? []));
                return;
            }
        }

        $moduleIds = array_map('intval', (array) $request->input('module_permissions', []));
        $routeIds = array_map('intval', (array) $request->input('route_permissions', []));
        $user->modules()->sync($moduleIds);
        $user->routePermissions()->sync($routeIds);
    }

    private function accessProfiles(): array
    {
        return AncoraSettings::getJson('access_profiles_json', []);
    }


    private function parseAccessMode(string $mode): array
    {
        $value = trim($mode);
        if ($value === 'superadmin') {
            return ['superadmin', null];
        }
        if (str_starts_with($value, 'profile:')) {
            return ['comum', substr($value, 8) ?: null];
        }

        return ['comum', null];
    }

    private function resolveUserAccessMode(User $user, array $profiles): string
    {
        if ($user->role === 'superadmin') {
            return 'superadmin';
        }

        $currentModuleIds = $user->modules->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $currentRouteIds = $user->routePermissions->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        foreach ($profiles as $profile) {
            $profileModuleIds = collect($profile['module_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            $profileRouteIds = collect($profile['route_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($profileModuleIds === $currentModuleIds && $profileRouteIds === $currentRouteIds) {
                return 'profile:' . ($profile['slug'] ?? '');
            }
        }

        return 'comum';
    }

    private function hasAnotherSuperadmin(User $user): bool
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->where('role', 'superadmin')
            ->where('is_active', 1)
            ->exists();
    }

    private function brandingAssetUrl(?string $path, string $fallback): string
    {
        $relative = '/' . ltrim((string) $path, '/');
        $absolute = public_path(ltrim($relative, '/'));
        if ($relative !== '/' && is_file($absolute)) {
            return asset(ltrim($relative, '/'));
        }

        return asset(ltrim($fallback, '/'));
    }

    private function administradoraPayload(Request $request, ?Administradora $current): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('administradoras', 'name')->ignore($current?->id)->where(fn ($q) => $q->where('type', $request->input('type', 'administradora')))],
            'type' => ['required', Rule::in(['administradora', 'sindico'])],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->integer('sort_order'),
        ];
    }

    private function servicoPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) $request->integer('sort_order');
        return $data;
    }

    private function statusPayload(Request $request): array
    {
        $data = $request->validate([
            'system_key' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'color_hex' => ['required', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['requires_closed_value'] = $request->boolean('requires_closed_value');
        $data['requires_refusal_reason'] = $request->boolean('requires_refusal_reason');
        $data['stop_followup_alert'] = $request->boolean('stop_followup_alert');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) $request->integer('sort_order');
        return $data;
    }

    private function formaPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon_class' => ['required', 'string', 'max:120'],
            'color_hex' => ['required', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) $request->integer('sort_order');
        return $data;
    }

    private function storeBrandingAsset($file, string $prefix, string $currentPath): string
    {
        $dir = public_path('assets/uploads/branding');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = $prefix . '-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.' . $extension;
        $file->move($dir, $name);
        if (str_starts_with($currentPath, '/assets/uploads/branding/')) {
            $old = public_path(ltrim($currentPath, '/'));
            if (is_file($old)) {
                @unlink($old);
            }
        }
        return '/assets/uploads/branding/' . $name;
    }

    private function setMany(array $items): void
    {
        foreach ($items as $key => [$value, $description]) {
            AppSetting::setValue($key, $value, $description);
        }
    }
}
