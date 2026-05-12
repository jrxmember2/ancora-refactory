<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiGlobalDocument;
use App\Models\AppSetting;
use App\Models\CobrancaMonetaryIndexFactor;
use App\Models\ClientCondominium;
use App\Models\Demand;
use App\Models\DemandCategory;
use App\Models\DemandTag;
use App\Models\FormaEnvio;
use App\Models\ProcessCaseOption;
use App\Models\RoutePermission;
use App\Models\Servico;
use App\Models\StatusRetorno;
use App\Models\SystemModule;
use App\Models\User;
use App\Models\ClientPortalUser;
use App\Services\Ai\AiService;
use App\Services\Ai\Knowledge\AiGlobalDocumentProcessor;
use App\Services\SharedServiceCatalogService;
use App\Support\AncoraAuth;
use App\Support\AiLegalDocumentCatalog;
use App\Support\AncoraRouteCatalog;
use App\Support\AncoraSettings;
use App\Support\AiProviderCatalog;
use Illuminate\Http\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Exception;

class ConfigController extends Controller
{
    public function index(): View
    {
        $this->ensureRoutePermissionsSynced();
        $serviceCatalog = app(SharedServiceCatalogService::class);
        $routeCatalog = AncoraRouteCatalog::groups();
        $catalogRouteNames = array_keys(AncoraRouteCatalog::flat());
        $tjesIndexStorageReady = $this->tjesIndexStorageReady();
        $logoLightPath = AppSetting::getValue('branding_logo_light_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $logoDarkPath = AppSetting::getValue('branding_logo_dark_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $faviconPath = AppSetting::getValue('branding_favicon_path', '/favicon.svg') ?: '/favicon.svg';
        $premiumLogoVariant = AppSetting::getValue('branding_premium_logo_variant', 'light') === 'dark' ? 'dark' : 'light';
        $routePermissions = RoutePermission::query()
            ->whereIn('route_name', $catalogRouteNames)
            ->orderBy('group_key')
            ->orderBy('label')
            ->get()
            ->groupBy('group_key');
        $users = User::query()->with(['modules', 'routePermissions'])->orderByDesc('is_protected')->orderBy('name')->get();
        $accessProfiles = $this->accessProfiles();

        foreach ($users as $listedUser) {
            $listedUser->access_mode_value = $this->resolveUserAccessMode($listedUser, $accessProfiles);
        }

        return view('pages.admin.config', [
            'title' => 'Configurações',
            'servicos' => $serviceCatalog->mirroredServices(),
            'statusRetorno' => StatusRetorno::query()->orderBy('sort_order')->orderBy('name')->get(),
            'formasEnvio' => FormaEnvio::query()->orderBy('sort_order')->orderBy('name')->get(),
            'processOptions' => ProcessCaseOption::query()->orderBy('group_key')->orderBy('sort_order')->orderBy('name')->get()->groupBy('group_key'),
            'processOptionLabels' => $this->processOptionLabels(),
            'demandTags' => DemandTag::query()->withCount('demands')->orderBy('sort_order')->orderBy('name')->get(),
            'demandCategories' => DemandCategory::query()->withCount('demands')->orderBy('sort_order')->orderBy('name')->get(),
            'demandTagSlaOptions' => DemandTag::slaOptions(),
            'demandStatusLabels' => Demand::statusLabels(),
            'tjesIndexStorageReady' => $tjesIndexStorageReady,
            'tjesIndexFactors' => $this->tjesIndexFactors(),
            'users' => $users,
            'modules' => SystemModule::query()->orderBy('sort_order')->orderBy('name')->get(),
            'routePermissionGroups' => $routePermissions,
            'routeCatalog' => $routeCatalog,
            'accessProfiles' => $accessProfiles,
            'branding' => [
                'company_name' => AppSetting::getValue('app_company', 'Serratech Soluções em TI') ?: '',
                'app_slogan' => AppSetting::getValue('app_slogan', '') ?: '',
                'company_address' => AppSetting::getValue('company_address', '') ?: '',
                'company_phone' => AppSetting::getValue('company_phone', '') ?: '',
                'company_email' => AppSetting::getValue('company_email', '') ?: '',
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
            'automation' => $this->automationSettings(),
            'systemAlert' => AncoraSettings::systemAlert(),
            'smtp' => AncoraSettings::smtp(),
            'billingSmtp' => AncoraSettings::billingSmtp(),
            'billingImap' => AncoraSettings::billingImap(),
        ]);
    }

    public function ai(): View
    {
        $this->ensureRoutePermissionsSynced();

        return view('pages.admin.config-ai', [
            'title' => 'Inteligencia Artificial',
            'settings' => $this->aiSettingsForView(),
            'catalog' => [
                'defaults' => AiProviderCatalog::defaults(),
                'tooltips' => AiProviderCatalog::tooltips(),
                'temperature_min' => AiProviderCatalog::temperatureMin(),
                'temperature_max' => AiProviderCatalog::temperatureMax(),
                'temperature_step' => AiProviderCatalog::temperatureStep(),
                'temperature_presets' => AiProviderCatalog::temperaturePresets(),
                'token_min' => AiProviderCatalog::tokenMin(),
                'token_presets' => AiProviderCatalog::tokenPresets(),
                'openai_chat_models' => AiProviderCatalog::openAiChatModels(),
                'gemini_chat_models' => AiProviderCatalog::geminiChatModels(),
                'openai_embedding_models' => AiProviderCatalog::openAiEmbeddingModels(),
                'gemini_embedding_models' => AiProviderCatalog::geminiEmbeddingModels(),
            ],
        ]);
    }

    public function aiLegalBase(): View
    {
        $this->ensureRoutePermissionsSynced();

        return view('pages.admin.config-ai-legal-base', [
            'title' => 'Base Legal Global',
            'documents' => AiGlobalDocument::query()
                ->with('creator')
                ->withCount('chunks')
                ->orderByDesc('is_active')
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->get(),
            'catalog' => [
                'document_types' => AiLegalDocumentCatalog::documentTypes(),
                'processing_statuses' => AiLegalDocumentCatalog::processingStatuses(),
                'processable_extension' => AiLegalDocumentCatalog::processableExtension(),
                'accepted_extensions' => AiLegalDocumentCatalog::acceptedExtensions(),
            ],
        ]);
    }

    public function aiChatHistory(Request $request): View
    {
        $this->ensureRoutePermissionsSynced();

        $filters = [
            'client_condominium_id' => $request->filled('client_condominium_id') ? (int) $request->input('client_condominium_id') : null,
            'client_portal_user_id' => $request->filled('client_portal_user_id') ? (int) $request->input('client_portal_user_id') : null,
            'provider' => trim((string) $request->input('provider', '')),
            'model' => trim((string) $request->input('model', '')),
            'status' => trim((string) $request->input('status', '')),
            'period_from' => trim((string) $request->input('period_from', '')),
            'period_to' => trim((string) $request->input('period_to', '')),
            'keyword' => trim((string) $request->input('keyword', '')),
        ];

        $query = AiChatMessage::query()
            ->with([
                'conversation.portalUser.entity',
                'conversation.condominium',
                'sources',
            ])
            ->whereIn('role', ['assistant', 'system'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['client_condominium_id']) {
            $query->whereHas('conversation', function ($conversationQuery) use ($filters) {
                $conversationQuery->where('client_condominium_id', $filters['client_condominium_id']);
            });
        }

        if ($filters['client_portal_user_id']) {
            $query->whereHas('conversation', function ($conversationQuery) use ($filters) {
                $conversationQuery->where('client_portal_user_id', $filters['client_portal_user_id']);
            });
        }

        if ($filters['provider'] !== '') {
            $query->where('provider', $filters['provider']);
        }

        if ($filters['model'] !== '') {
            $query->where('model', $filters['model']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['period_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['period_from']);
        }

        if ($filters['period_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['period_to']);
        }

        if ($filters['keyword'] !== '') {
            $term = $filters['keyword'];
            $query->where(function ($inner) use ($term) {
                $inner
                    ->where('content', 'like', "%{$term}%")
                    ->orWhere('error_message', 'like', "%{$term}%")
                    ->orWhere('meta_json', 'like', "%{$term}%")
                    ->orWhereHas('conversation', function ($conversationQuery) use ($term) {
                        $conversationQuery
                            ->where('title', 'like', "%{$term}%")
                            ->orWhereHas('portalUser', function ($userQuery) use ($term) {
                                $userQuery
                                    ->where('name', 'like', "%{$term}%")
                                    ->orWhere('login_key', 'like', "%{$term}%")
                                    ->orWhere('email', 'like', "%{$term}%");
                            })
                            ->orWhereHas('condominium', function ($condominiumQuery) use ($term) {
                                $condominiumQuery->where('name', 'like', "%{$term}%");
                            })
                            ->orWhereHas('messages', function ($messageQuery) use ($term) {
                                $messageQuery
                                    ->where('role', 'user')
                                    ->where('content', 'like', "%{$term}%");
                            });
                    })
                    ->orWhereHas('sources', function ($sourceQuery) use ($term) {
                        $sourceQuery
                            ->where('document_title', 'like', "%{$term}%")
                            ->orWhere('document_kind', 'like', "%{$term}%");
                    });
            });
        }

        $summaryQuery = clone $query;
        $messages = $query->paginate(20)->withQueryString();

        return view('pages.admin.config-ai-chat-history', [
            'title' => 'Historico de Consultas',
            'items' => $messages,
            'filters' => $filters,
            'condominiums' => ClientCondominium::query()
                ->whereHas('aiChatConversations')
                ->orderBy('name')
                ->get(['id', 'name']),
            'portalUsers' => ClientPortalUser::query()
                ->with('entity')
                ->whereHas('aiChatConversations')
                ->orderBy('name')
                ->get(),
            'providers' => AiChatMessage::query()
                ->whereNotNull('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider'),
            'models' => AiChatMessage::query()
                ->whereNotNull('model')
                ->distinct()
                ->orderBy('model')
                ->pluck('model'),
            'statusOptions' => [
                'success' => 'Sucesso',
                'completed' => 'Concluida',
                'error' => 'Erro',
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'errors' => (clone $summaryQuery)->where('status', 'error')->count(),
                'flagged' => (clone $summaryQuery)->where(function ($flaggedQuery) {
                    $flaggedQuery
                        ->where('is_relevant', true)
                        ->orWhere('requires_legal_review', true)
                        ->orWhere('is_faq_candidate', true);
                })->count(),
            ],
        ]);
    }

    public function aiChatHistoryShow(AiChatMessage $message): View
    {
        $this->ensureRoutePermissionsSynced();
        abort_unless(in_array($message->role, ['assistant', 'system'], true), 404);

        $message->load([
            'conversation.portalUser.entity',
            'conversation.condominium',
            'conversation.messages',
            'sources.chunk',
            'sources.clientAttachment',
            'sources.globalDocument',
        ]);

        return view('pages.admin.config-ai-chat-history-show', [
            'title' => 'Consulta de IA',
            'message' => $message,
            'question' => $message->questionText(),
            'documentSources' => $message->sources
                ->groupBy(fn ($source) => implode('|', [
                    $source->source_type,
                    (string) ($source->client_attachment_id ?? 0),
                    (string) ($source->ai_global_document_id ?? 0),
                    trim((string) $source->document_title),
                    trim((string) $source->document_kind),
                ]))
                ->map(function ($group) {
                    $first = $group->first();
                    $chunkIds = $group->pluck('chunk_id')->filter()->unique()->values()->all();

                    return [
                        'source' => $first,
                        'chunks_used' => $chunkIds,
                    ];
                })
                ->values(),
        ]);
    }

    public function updateAiChatHistoryReview(Request $request, AiChatMessage $message): RedirectResponse
    {
        abort_unless(in_array($message->role, ['assistant', 'system'], true), 404);

        $validated = $request->validate([
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $message->update([
            'is_relevant' => $request->boolean('is_relevant'),
            'requires_legal_review' => $request->boolean('requires_legal_review'),
            'is_faq_candidate' => $request->boolean('is_faq_candidate'),
            'internal_note' => filled($validated['internal_note'] ?? null) ? trim((string) $validated['internal_note']) : null,
        ]);

        return redirect()
            ->route('config.ai.chat-history.show', $message)
            ->with('success', 'Marcacoes da consulta atualizadas com sucesso.');
    }

    public function saveBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:180'],
            'app_slogan' => ['nullable', 'string', 'max:180'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:190'],
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
        $validated = $request->validate($this->smtpValidationRules('smtp'));

        $this->persistSmtpSettings($validated, 'smtp', 'sistema', $validated['smtp_from_name'] ?? 'Âncora');

        return back()->with('success', 'SMTP atualizado com sucesso.');
    }

    public function saveBillingSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->smtpValidationRules('billing_smtp'));

        $this->persistSmtpSettings(
            $validated,
            'billing_smtp',
            'cobrança',
            $validated['billing_smtp_from_name'] ?? 'Âncora Cobrança'
        );

        return back()->with('success', 'SMTP de cobrança atualizado com sucesso.');
    }

    public function saveBillingImap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'billing_imap_host' => ['nullable', 'string', 'max:190'],
            'billing_imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'billing_imap_username' => ['nullable', 'string', 'max:190'],
            'billing_imap_password' => ['nullable', 'string', 'max:190'],
            'billing_imap_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'billing_imap_sent_folder' => ['nullable', 'string', 'max:190'],
            'billing_imap_validate_cert' => ['nullable'],
        ]);

        $this->setMany([
            'billing_imap_host' => [$validated['billing_imap_host'] ?? '', 'Host IMAP da caixa de cobrança'],
            'billing_imap_port' => [(string) ($validated['billing_imap_port'] ?? 993), 'Porta IMAP da caixa de cobrança'],
            'billing_imap_username' => [$validated['billing_imap_username'] ?? '', 'Usuário IMAP da caixa de cobrança'],
            'billing_imap_password' => [$validated['billing_imap_password'] ?? '', 'Senha IMAP da caixa de cobrança'],
            'billing_imap_encryption' => [$validated['billing_imap_encryption'] ?? 'ssl', 'Criptografia IMAP da caixa de cobrança'],
            'billing_imap_sent_folder' => [$validated['billing_imap_sent_folder'] ?? 'Sent', 'Pasta de itens enviados da caixa de cobrança'],
            'billing_imap_validate_cert' => [$request->boolean('billing_imap_validate_cert') ? '1' : '0', 'Indica se o certificado IMAP da caixa de cobrança deve ser validado'],
        ]);

        return back()->with('success', 'IMAP de cobrança atualizado com sucesso.');
    }

    public function saveSystemAlert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_alert_enabled' => ['nullable'],
            'system_alert_level' => ['required', Rule::in(['info', 'warning', 'error', 'success'])],
            'system_alert_title' => ['nullable', 'string', 'max:160'],
            'system_alert_message' => ['nullable', 'string', 'max:3000'],
            'system_alert_visible_until' => ['nullable', 'date'],
        ]);

        $visibleUntil = trim((string) ($validated['system_alert_visible_until'] ?? ''));
        if ($visibleUntil !== '') {
            $visibleUntil = Carbon::parse($visibleUntil)->format('Y-m-d H:i:s');
        }

        $this->setMany([
            'system_alert_enabled' => [$request->boolean('system_alert_enabled') ? '1' : '0', 'Indica se o alerta global interno deve ser exibido aos usuarios'],
            'system_alert_level' => [(string) $validated['system_alert_level'], 'Nivel visual do alerta global interno'],
            'system_alert_title' => [trim((string) ($validated['system_alert_title'] ?? '')), 'Titulo do alerta global interno'],
            'system_alert_message' => [trim((string) ($validated['system_alert_message'] ?? '')), 'Mensagem do alerta global interno'],
            'system_alert_visible_until' => [$visibleUntil, 'Data limite de exibicao do alerta global interno'],
        ]);

        return back()->with('success', 'Alerta global atualizado com sucesso.');
    }

    public function storeTjesIndexFactor(Request $request): RedirectResponse
    {
        if (!$this->tjesIndexStorageReady()) {
            return back()
                ->with('error', 'A tabela de indices TJES ainda nao existe. Rode as migrations antes de cadastrar novos fatores.')
                ->with('open_tjes_indices', true);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1969', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'factor' => ['required', 'string', 'max:30'],
            'source_label' => ['nullable', 'string', 'max:180'],
        ]);

        $factor = $this->normalizeDecimalValue((string) $validated['factor'], 'factor', 10);

        CobrancaMonetaryIndexFactor::query()->updateOrCreate(
            [
                'index_code' => 'ATM',
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ],
            [
                'factor' => $factor,
                'source_label' => trim((string) ($validated['source_label'] ?? '')) ?: 'Atualizado manualmente pela tela de configuracoes',
            ]
        );

        return back()
            ->with('success', sprintf('Indice TJES salvo para %02d/%04d.', (int) $validated['month'], (int) $validated['year']))
            ->with('open_tjes_indices', true);
    }

    public function storeDemandTag(Request $request): RedirectResponse
    {
        $payload = $this->demandTagPayload($request);

        DB::transaction(function () use ($payload) {
            if ($payload['is_default']) {
                DemandTag::query()->update(['is_default' => false]);
            }

            DemandTag::query()->create($payload);
        });

        return back()->with('success', 'Tag de demanda cadastrada.');
    }

    public function updateDemandTag(Request $request, DemandTag $tag): RedirectResponse
    {
        $payload = $this->demandTagPayload($request, $tag);

        DB::transaction(function () use ($tag, $payload) {
            if ($payload['is_default']) {
                DemandTag::query()->where('id', '!=', $tag->id)->update(['is_default' => false]);
            }

            $tag->update($payload);
        });

        return back()->with('success', 'Tag de demanda atualizada.');
    }

    public function deleteDemandTag(DemandTag $tag): RedirectResponse
    {
        if ($tag->demands()->exists()) {
            return back()->with('error', 'Nao e possivel excluir tag com demandas vinculadas. Mova as demandas antes.');
        }

        $tag->delete();

        return back()->with('success', 'Tag de demanda excluida.');
    }

    public function storeDemandCategory(Request $request): RedirectResponse
    {
        $payload = $this->demandCategoryPayload($request);

        $category = DemandCategory::query()->create($payload);
        app(SharedServiceCatalogService::class)->syncDemandCategory($category);

        return back()->with('success', 'Servico cadastrado para Demandas e Propostas.');
    }

    public function updateDemandCategory(Request $request, DemandCategory $category): RedirectResponse
    {
        $payload = $this->demandCategoryPayload($request, $category);

        $category->update($payload);
        app(SharedServiceCatalogService::class)->syncDemandCategory($category->fresh());

        return back()->with('success', 'Servico atualizado para Demandas e Propostas.');
    }

    public function deleteDemandCategory(DemandCategory $category): RedirectResponse
    {
        if ($category->demands()->exists()) {
            return back()->with('error', 'Nao e possivel excluir servico com demandas vinculadas. Reclassifique as demandas antes.');
        }

        app(SharedServiceCatalogService::class)->releaseDemandCategory($category);
        $category->delete();

        return back()->with('success', 'Servico excluido da lista compartilhada.');
    }

    public function saveAi(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aiValidationRules());
        $settings = $this->aiSettingsFromRequest($request, $validated);
        $this->validateAiConfigurationForSave($settings);

        $this->setMany([
            'ai_enabled' => [$settings['ai_enabled'] ? '1' : '0', 'Indica se a camada central de Inteligencia Artificial esta ativa no sistema'],
            'ai_active_provider' => [$settings['ai_active_provider'], 'Provedor de IA atualmente selecionado'],
            'ai_default_temperature' => [(string) $settings['ai_default_temperature'], 'Temperatura padrao usada nas respostas da IA'],
            'ai_default_max_tokens' => [(string) $settings['ai_default_max_tokens'], 'Limite padrao de tokens por resposta da IA'],
            'ai_default_system_prompt' => [$settings['ai_default_system_prompt'], 'Prompt global padrao aplicado pela camada central de IA'],
            'ai_default_legal_notice' => [$settings['ai_default_legal_notice'], 'Aviso juridico padrao sugerido para respostas de IA'],
            'ai_default_budget_request_url' => [$settings['ai_default_budget_request_url'], 'Link padrao para solicitacao de orcamento'],
            'ai_old_document_alert_enabled' => [$settings['ai_old_document_alert_enabled'] ? '1' : '0', 'Indica se o alerta de documento antigo deve ficar ativo na IA'],
            'ai_old_document_alert_years' => [(string) $settings['ai_old_document_alert_years'], 'Quantidade de anos para considerar um documento antigo'],
            'ai_openai_enabled' => [$settings['openai_enabled'] ? '1' : '0', 'Indica se a integracao OpenAI esta habilitada'],
            'ai_openai_chat_model' => [$settings['openai_chat_model'], 'Modelo principal de chat da OpenAI'],
            'ai_openai_embedding_model' => [$settings['openai_embedding_model'], 'Modelo de embedding da OpenAI'],
            'ai_gemini_enabled' => [$settings['gemini_enabled'] ? '1' : '0', 'Indica se a integracao Gemini esta habilitada'],
            'ai_gemini_chat_model' => [$settings['gemini_chat_model'], 'Modelo principal de chat da Gemini'],
            'ai_gemini_embedding_model' => [$settings['gemini_embedding_model'], 'Modelo de embedding da Gemini'],
        ]);

        $this->persistAiSecret(
            $request,
            'openai_api_key',
            'ai_openai_api_key',
            'Chave de API criptografada da OpenAI'
        );
        $this->persistAiSecret(
            $request,
            'gemini_api_key',
            'ai_gemini_api_key',
            'Chave de API criptografada da Gemini'
        );

        return redirect()
            ->route('config.ai.index')
            ->with('success', 'Configuracoes de Inteligencia Artificial atualizadas com sucesso.');
    }

    public function storeAiGlobalDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aiGlobalDocumentValidationRules());
        $file = $request->file('document_file');

        if (!$file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document_file' => 'Envie um arquivo DOCX ou PDF para cadastrar a Base Legal Global.',
            ]);
        }

        $stored = $this->storeAiGlobalDocumentFile($file);

        AiGlobalDocument::query()->create([
            'name' => trim((string) $validated['name']),
            'document_type' => trim((string) $validated['document_type']),
            'document_date' => Carbon::parse((string) $validated['document_date'])->toDateString(),
            'original_name' => $stored['original_name'],
            'stored_name' => $stored['stored_name'],
            'relative_path' => $stored['relative_path'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            'processing_status' => 'pending',
            'processing_error' => null,
            'is_active' => $request->boolean('is_active'),
            'observation' => trim((string) ($validated['observation'] ?? '')) ?: null,
            'created_by' => AncoraAuth::user($request)?->id,
        ]);

        $message = $stored['extension'] === AiLegalDocumentCatalog::processableExtension()
            ? 'Documento global cadastrado com sucesso. Agora voce ja pode clicar em Processar documento.'
            : 'Documento global cadastrado com sucesso. Nesta fase, apenas DOCX entra no processamento de IA.';

        return redirect()
            ->route('config.ai.legal-base.index')
            ->with('success', $message);
    }

    public function updateAiGlobalDocument(Request $request, AiGlobalDocument $document): RedirectResponse
    {
        $validated = $request->validate($this->aiGlobalDocumentValidationRules(true));

        $document->forceFill([
            'name' => trim((string) $validated['name']),
            'document_type' => trim((string) $validated['document_type']),
            'document_date' => Carbon::parse((string) $validated['document_date'])->toDateString(),
            'is_active' => $request->boolean('is_active'),
            'observation' => trim((string) ($validated['observation'] ?? '')) ?: null,
        ])->save();

        $this->syncAiGlobalDocumentChunksMetadata($document);

        return redirect()
            ->route('config.ai.legal-base.index')
            ->with('success', 'Metadados do documento global atualizados com sucesso.');
    }

    public function downloadAiGlobalDocument(AiGlobalDocument $document): BinaryFileResponse
    {
        $path = $document->absolutePath();
        abort_unless(is_string($path) && is_file($path), 404, 'Arquivo nao encontrado.');

        return response()->download($path, $document->original_name);
    }

    public function processAiGlobalDocument(AiGlobalDocument $document, AiGlobalDocumentProcessor $processor): RedirectResponse
    {
        if (!$document->isDocx()) {
            return redirect()
                ->route('config.ai.legal-base.index')
                ->with('error', 'Somente arquivos DOCX podem ser processados nesta fase da Base Legal Global.');
        }

        try {
            $result = $processor->process($document);
        } catch (Exception $exception) {
            return redirect()
                ->route('config.ai.legal-base.index')
                ->with('error', 'Nao foi possivel processar o documento agora: ' . trim($exception->getMessage()));
        }

        return redirect()
            ->route('config.ai.legal-base.index')
            ->with('success', 'Documento processado com sucesso. ' . number_format((int) $result['chunks'], 0, ',', '.') . ' blocos pesquisaveis foram gerados.');
    }

    public function testAi(Request $request, AiService $aiService): JsonResponse
    {
        $validated = $request->validate($this->aiValidationRules(testing: true));
        $settings = $this->aiSettingsFromRequest($request, $validated);
        $this->validateAiConfigurationForTest($settings);

        $response = $aiService->testConnection($settings);
        if (!$response->ok()) {
            return response()->json([
                'success' => false,
                'message' => 'Falha na conexao: ' . ($response->error ?: 'Nao foi possivel obter resposta do provedor.'),
                'provider' => $response->provider,
                'model' => $response->model,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conexao funcionando com ' . strtoupper($response->provider) . '. Resposta: ' . $response->text,
            'provider' => $response->provider,
            'model' => $response->model,
            'tokens' => $response->tokenEstimate,
        ]);
    }

    public function saveAutomation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'internal_api_token' => ['nullable', 'string', 'max:190'],
            'internal_api_token_header' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9\\-]+$/'],
            'internal_api_allowed_ips' => ['nullable', 'string', 'max:4000'],
        ]);

        $defaultHeader = (string) config('automation.internal_api.token_header', 'X-Integration-Token');

        $this->setMany([
            'automation_internal_api_token' => [trim((string) ($validated['internal_api_token'] ?? '')), 'Token fixo da API interna de automacao'],
            'automation_internal_api_token_header' => [
                trim((string) ($validated['internal_api_token_header'] ?? '')) ?: $defaultHeader,
                'Header alternativo aceito pela API interna de automacao',
            ],
            'automation_internal_api_allowed_ips' => [
                $this->normalizeAllowedIps((string) ($validated['internal_api_allowed_ips'] ?? '')),
                'Allowlist de IPs da API interna de automacao',
            ],
        ]);

        return back()->with('success', 'Automacao WhatsApp atualizada com sucesso.');
    }

    public function automationDocumentation(): View
    {
        $path = base_path('docs/automation-whatsapp-integration.md');
        $markdown = is_file($path)
            ? (string) file_get_contents($path)
            : "# Documentacao indisponivel\n\nO arquivo docs/automation-whatsapp-integration.md nao foi encontrado.";

        return view('pages.admin.config-automation-documentation', [
            'title' => 'Documentacao da Automacao WhatsApp',
            'documentationPath' => 'docs/automation-whatsapp-integration.md',
            'documentationHtml' => Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }

    public function testSmtp(Request $request)
    {
        $validated = $request->validate([
            'smtp_host' => ['required', 'string', 'max:190'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'smtp_from_address' => ['required', 'email', 'max:190'],
            'smtp_from_name' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $mailer = app()->makeWith('mailer', ['name' => 'test_smtp']);
            $transport = \Symfony\Component\Mailer\Transport::fromDsn(
                ($validated['smtp_encryption'] === 'ssl' ? 'smtps' : 'smtp') . "://" .
                rawurlencode((string)$validated['smtp_username']) . ":" . rawurlencode((string)$validated['smtp_password']) . "@" .
                $validated['smtp_host'] . ":" . $validated['smtp_port']
            );
            $mailer->setSymfonyTransport($transport);

            $mailer->raw('Teste de configuração SMTP do sistema Âncora concluído com sucesso.', function ($message) use ($validated, $request) {
                $message->from($validated['smtp_from_address'], $validated['smtp_from_name'] ?? 'Âncora');
                $message->to(AncoraAuth::user($request)->email);
                $message->subject('Teste de Conexão SMTP');
            });

            return response()->json(['success' => true, 'message' => 'E-mail de teste enviado com sucesso para ' . AncoraAuth::user($request)->email]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Falha na conexão: ' . $e->getMessage()], 422);
        }
    }

    public function testBillingSmtp(Request $request)
    {
        $validated = $request->validate($this->smtpRequiredValidationRules('billing_smtp'));

        return $this->testSmtpConnection(
            $validated,
            $request,
            'billing_smtp',
            'Teste de configuração SMTP da cobrança concluído com sucesso.',
            $validated['billing_smtp_from_name'] ?? 'Âncora Cobrança',
            'Teste de Conexão SMTP - Cobrança'
        );
    }

    private function smtpValidationRules(string $prefix): array
    {
        return [
            $prefix . '_host' => ['nullable', 'string', 'max:190'],
            $prefix . '_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            $prefix . '_username' => ['nullable', 'string', 'max:190'],
            $prefix . '_password' => ['nullable', 'string', 'max:190'],
            $prefix . '_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            $prefix . '_from_address' => ['nullable', 'email', 'max:190'],
            $prefix . '_from_name' => ['nullable', 'string', 'max:190'],
        ];
    }

    private function smtpRequiredValidationRules(string $prefix): array
    {
        return [
            $prefix . '_host' => ['required', 'string', 'max:190'],
            $prefix . '_port' => ['required', 'integer', 'min:1', 'max:65535'],
            $prefix . '_username' => ['nullable', 'string', 'max:190'],
            $prefix . '_password' => ['nullable', 'string', 'max:190'],
            $prefix . '_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            $prefix . '_from_address' => ['required', 'email', 'max:190'],
            $prefix . '_from_name' => ['nullable', 'string', 'max:190'],
        ];
    }

    private function persistSmtpSettings(array $validated, string $prefix, string $label, string $defaultFromName): void
    {
        $this->setMany([
            $prefix . '_host' => [$validated[$prefix . '_host'] ?? '', 'Host SMTP do ' . $label],
            $prefix . '_port' => [(string) ($validated[$prefix . '_port'] ?? 587), 'Porta SMTP do ' . $label],
            $prefix . '_username' => [$validated[$prefix . '_username'] ?? '', 'Usuário SMTP do ' . $label],
            $prefix . '_password' => [$validated[$prefix . '_password'] ?? '', 'Senha SMTP do ' . $label],
            $prefix . '_encryption' => [$validated[$prefix . '_encryption'] ?? 'tls', 'Criptografia SMTP do ' . $label],
            $prefix . '_from_address' => [$validated[$prefix . '_from_address'] ?? '', 'E-mail remetente do ' . $label],
            $prefix . '_from_name' => [$validated[$prefix . '_from_name'] ?? $defaultFromName, 'Nome remetente do ' . $label],
        ]);
    }

    private function testSmtpConnection(array $validated, Request $request, string $prefix, string $body, string $defaultFromName, string $subject)
    {
        try {
            $mailer = app()->makeWith('mailer', ['name' => 'test_' . $prefix]);
            $transport = \Symfony\Component\Mailer\Transport::fromDsn(
                ($validated[$prefix . '_encryption'] === 'ssl' ? 'smtps' : 'smtp') . '://' .
                rawurlencode((string) ($validated[$prefix . '_username'] ?? '')) . ':' . rawurlencode((string) ($validated[$prefix . '_password'] ?? '')) . '@' .
                $validated[$prefix . '_host'] . ':' . $validated[$prefix . '_port']
            );
            $mailer->setSymfonyTransport($transport);

            $mailer->raw($body, function ($message) use ($validated, $prefix, $request, $defaultFromName, $subject) {
                $message->from($validated[$prefix . '_from_address'], $validated[$prefix . '_from_name'] ?? $defaultFromName);
                $message->to(AncoraAuth::user($request)->email);
                $message->subject($subject);
            });

            return response()->json([
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso para ' . AncoraAuth::user($request)->email,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Falha na conexão: ' . $e->getMessage()], 422);
        }
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

    private function catalogResponse(Request $request, string $message)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $serviceCatalog = app(SharedServiceCatalogService::class);

            return view('pages.admin.partials.config-catalog', [
                'servicos' => $serviceCatalog->mirroredServices(),
                'statusRetorno' => StatusRetorno::query()->orderBy('sort_order')->orderBy('name')->get(),
                'formasEnvio' => FormaEnvio::query()->orderBy('sort_order')->orderBy('name')->get(),
                'processOptions' => ProcessCaseOption::query()->orderBy('group_key')->orderBy('sort_order')->orderBy('name')->get()->groupBy('group_key'),
                'processOptionLabels' => $this->processOptionLabels(),
                'tjesIndexStorageReady' => $this->tjesIndexStorageReady(),
                'tjesIndexFactors' => $this->tjesIndexFactors(),
            ]);
        }
        return back()->with('success', $message);
    }

    public function storeServico(Request $request)
    {
        if (!Schema::hasTable('demand_categories')) {
            Servico::query()->create($this->servicoPayload($request));
            return $this->catalogResponse($request, 'Serviço cadastrado.');
        }

        $request->merge([
            'slug' => trim((string) $request->input('slug')) !== '' ? $request->input('slug') : Str::slug((string) $request->input('name')),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->has('is_active') ? $request->input('is_active') : '1',
        ]);

        $category = DemandCategory::query()->create($this->demandCategoryPayload($request));
        app(SharedServiceCatalogService::class)->syncDemandCategory($category);

        return $this->catalogResponse($request, 'Serviço cadastrado.');
    }

    public function updateServico(Request $request, Servico $servico)
    {
        $category = Schema::hasTable('demand_categories') && !empty($servico->demand_category_id)
            ? DemandCategory::query()->find($servico->demand_category_id)
            : null;

        if (!$category) {
            $servico->update($this->servicoPayload($request, $servico));
            return $this->catalogResponse($request, 'Serviço atualizado.');
        }

        $request->merge([
            'slug' => trim((string) $request->input('slug')) !== '' ? $request->input('slug') : ($category->slug ?: Str::slug((string) $request->input('name'))),
            'sort_order' => $request->input('sort_order', $category->sort_order),
            'is_active' => $request->has('is_active') ? $request->input('is_active') : ($category->is_active ? '1' : '0'),
            'color_hex' => $request->input('color_hex', $category->color_hex),
        ]);

        $category->update($this->demandCategoryPayload($request, $category));
        app(SharedServiceCatalogService::class)->syncDemandCategory($category->fresh());

        return $this->catalogResponse($request, 'Serviço atualizado.');
    }

    public function deleteServico(Request $request, Servico $servico)
    {
        $category = Schema::hasTable('demand_categories') && !empty($servico->demand_category_id)
            ? DemandCategory::query()->find($servico->demand_category_id)
            : null;

        if ($category) {
            if ($category->demands()->exists()) {
                return back()->with('error', 'Nao e possivel excluir servico com demandas vinculadas. Reclassifique as demandas antes.');
            }

            app(SharedServiceCatalogService::class)->releaseDemandCategory($category);
            $category->delete();

            return $this->catalogResponse($request, 'Serviço excluído.');
        }

        $servico->delete();
        return $this->catalogResponse($request, 'Serviço excluído.');
    }

    public function storeStatus(Request $request)
    {
        StatusRetorno::query()->create($this->statusPayload($request));
        return $this->catalogResponse($request, 'Status cadastrado.');
    }

    public function updateStatus(Request $request, StatusRetorno $status)
    {
        $status->update($this->statusPayload($request, $status));
        return $this->catalogResponse($request, 'Status atualizado.');
    }

    public function deleteStatus(Request $request, StatusRetorno $status)
    {
        $status->delete();
        return $this->catalogResponse($request, 'Status excluído.');
    }

    public function storeFormaEnvio(Request $request)
    {
        FormaEnvio::query()->create($this->formaPayload($request));
        return $this->catalogResponse($request, 'Forma de envio cadastrada.');
    }

    public function updateFormaEnvio(Request $request, FormaEnvio $forma)
    {
        $forma->update($this->formaPayload($request, $forma));
        return $this->catalogResponse($request, 'Forma de envio atualizada.');
    }

    public function deleteFormaEnvio(Request $request, FormaEnvio $forma)
    {
        $forma->delete();
        return $this->catalogResponse($request, 'Forma de envio excluída.');
    }

    public function storeProcessOption(Request $request)
    {
        ProcessCaseOption::query()->create($this->processOptionPayload($request));
        return $this->catalogResponse($request, 'Configuracao de processo cadastrada.');
    }

    public function updateProcessOption(Request $request, ProcessCaseOption $option)
    {
        $option->update($this->processOptionPayload($request, $option));
        return $this->catalogResponse($request, 'Configuracao de processo atualizada.');
    }

    public function deleteProcessOption(Request $request, ProcessCaseOption $option)
    {
        $option->delete();
        return $this->catalogResponse($request, 'Configuracao de processo excluida.');
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

    private function automationSettings(): array
    {
        $storedToken = trim((string) AppSetting::getValue('automation_internal_api_token', ''));
        $storedHeader = trim((string) AppSetting::getValue('automation_internal_api_token_header', ''));
        $defaultHeader = (string) config('automation.internal_api.token_header', 'X-Integration-Token');

        return [
            'internal_api_endpoint' => url('/api/internal/automation/whatsapp/process-message'),
            'internal_api_token' => $storedToken,
            'internal_api_token_header' => $storedHeader !== '' ? $storedHeader : $defaultHeader,
            'internal_api_allowed_ips' => $this->formatAllowedIpsForTextarea((string) AppSetting::getValue('automation_internal_api_allowed_ips', '')),
            'documentation_url' => route('config.automation.documentation'),
            'has_token_override' => $storedToken !== '',
        ];
    }

    private function tjesIndexStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_monetary_index_factors');
        } catch (\Throwable) {
            return false;
        }
    }

    private function tjesIndexFactors()
    {
        if (!$this->tjesIndexStorageReady()) {
            return collect();
        }

        return CobrancaMonetaryIndexFactor::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
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

    private function servicoPayload(Request $request, ?Servico $servico = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($servico?->is_active ?? true);
        $data['sort_order'] = $request->filled('sort_order')
            ? (int) $request->integer('sort_order')
            : (int) ($servico?->sort_order ?? 0);
        return $data;
    }

    private function statusPayload(Request $request, ?StatusRetorno $status = null): array
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
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($status?->is_active ?? true);
        $data['sort_order'] = $request->filled('sort_order')
            ? (int) $request->integer('sort_order')
            : (int) ($status?->sort_order ?? 0);
        return $data;
    }

    private function formaPayload(Request $request, ?FormaEnvio $forma = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon_class' => ['required', 'string', 'max:120'],
            'color_hex' => ['required', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($forma?->is_active ?? true);
        $data['sort_order'] = $request->filled('sort_order')
            ? (int) $request->integer('sort_order')
            : (int) ($forma?->sort_order ?? 0);
        return $data;
    }

    private function processOptionPayload(Request $request, ?ProcessCaseOption $current = null): array
    {
        $allowedGroups = array_keys($this->processOptionLabels());
        $data = $request->validate([
            'group_key' => ['required', Rule::in($allowedGroups)],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'color_hex' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        $data['slug'] = $slug !== '' ? $slug : Str::random(8);

        $slugExists = ProcessCaseOption::query()
            ->where('group_key', $data['group_key'])
            ->where('slug', $data['slug'])
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages([
                'slug' => 'Ja existe uma configuracao neste catalogo com este slug.',
            ]);
        }

        $data['color_hex'] = $data['color_hex'] ?: null;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) $request->integer('sort_order');

        return $data;
    }

    private function demandTagPayload(Request $request, ?DemandTag $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'color_hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status_key' => ['required', Rule::in(array_keys(Demand::statusLabels()))],
            'portal_label' => ['nullable', 'string', 'max:120'],
            'show_on_portal' => ['nullable'],
            'sla_hours' => ['nullable', Rule::in(array_keys(DemandTag::slaOptions()))],
            'is_default' => ['nullable'],
            'is_closing' => ['nullable'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:-1000', 'max:10000'],
        ]);

        $slug = DemandTag::normalizeSlug($data['name'], $data['slug'] ?? null);
        $slugExists = DemandTag::query()
            ->where('slug', $slug)
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages(['slug' => 'Ja existe uma tag com este identificador.']);
        }

        $slaHours = trim((string) ($data['sla_hours'] ?? ''));

        $payload = [
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'color_hex' => strtoupper((string) $data['color_hex']),
            'status_key' => (string) $data['status_key'],
            'portal_label' => trim((string) ($data['portal_label'] ?? '')) ?: null,
            'show_on_portal' => $request->boolean('show_on_portal'),
            'sla_hours' => $slaHours !== '' ? (int) $slaHours : null,
            'is_default' => $request->boolean('is_default'),
            'is_closing' => $request->boolean('is_closing'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->integer('sort_order'),
        ];

        if (!$payload['is_active']) {
            $payload['is_default'] = false;
        }

        return $payload;
    }

    private function demandCategoryPayload(Request $request, ?DemandCategory $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:-1000', 'max:10000'],
            'is_active' => ['nullable'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) $data['name']);
        }

        if ($slug === '') {
            $slug = Str::random(8);
        }

        $slugExists = DemandCategory::query()
            ->where('slug', $slug)
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();

        $nameExists = DemandCategory::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim((string) $data['name']))])
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages(['slug' => 'Ja existe um servico com este identificador.']);
        }

        if ($nameExists) {
            throw ValidationException::withMessages(['name' => 'Ja existe um servico com este nome.']);
        }

        return [
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'color_hex' => !empty($data['color_hex']) ? strtoupper((string) $data['color_hex']) : null,
            'sort_order' => (int) $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function processOptionLabels(): array
    {
        return [
            'status' => 'Status',
            'action_type' => 'Tipo de acao',
            'process_type' => 'Tipo de processo',
            'client_position' => 'Posicao do cliente',
            'adverse_position' => 'Posicao do adverso',
            'nature' => 'Natureza',
            'win_probability' => 'Possibilidade de ganho',
            'closure_type' => 'Tipo de encerramento',
            'datajud_court' => 'Tribunal DataJud',
        ];
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

    private function aiSettingsForView(): array
    {
        $defaults = AiProviderCatalog::defaults();
        $openAiKey = AppSetting::getDecryptedValue('ai_openai_api_key', '');
        $geminiKey = AppSetting::getDecryptedValue('ai_gemini_api_key', '');

        return [
            'ai_enabled' => AppSetting::getValue('ai_enabled', '0') === '1',
            'ai_active_provider' => trim((string) AppSetting::getValue('ai_active_provider', 'openai')) === 'gemini' ? 'gemini' : 'openai',
            'ai_default_temperature' => (string) AppSetting::getValue('ai_default_temperature', $defaults['ai_default_temperature']),
            'ai_default_max_tokens' => (string) AppSetting::getValue('ai_default_max_tokens', $defaults['ai_default_max_tokens']),
            'ai_default_system_prompt' => (string) AppSetting::getValue('ai_default_system_prompt', $defaults['ai_default_system_prompt']),
            'ai_default_legal_notice' => (string) AppSetting::getValue('ai_default_legal_notice', $defaults['ai_default_legal_notice']),
            'ai_default_budget_request_url' => (string) AppSetting::getValue('ai_default_budget_request_url', $defaults['ai_default_budget_request_url']),
            'ai_old_document_alert_enabled' => AppSetting::getValue('ai_old_document_alert_enabled', '1') === '1',
            'ai_old_document_alert_years' => (string) AppSetting::getValue('ai_old_document_alert_years', $defaults['ai_old_document_alert_years']),
            'openai_enabled' => AppSetting::getValue('ai_openai_enabled', '1') === '1',
            'openai_chat_model' => (string) AppSetting::getValue('ai_openai_chat_model', $defaults['openai_chat_model']),
            'openai_embedding_model' => (string) AppSetting::getValue('ai_openai_embedding_model', $defaults['openai_embedding_model']),
            'openai_api_key_masked' => $this->maskSecretValue($openAiKey),
            'openai_has_api_key' => trim($openAiKey) !== '',
            'gemini_enabled' => AppSetting::getValue('ai_gemini_enabled', '1') === '1',
            'gemini_chat_model' => (string) AppSetting::getValue('ai_gemini_chat_model', $defaults['gemini_chat_model']),
            'gemini_embedding_model' => (string) AppSetting::getValue('ai_gemini_embedding_model', $defaults['gemini_embedding_model']),
            'gemini_api_key_masked' => $this->maskSecretValue($geminiKey),
            'gemini_has_api_key' => trim($geminiKey) !== '',
        ];
    }

    private function aiGlobalDocumentValidationRules(bool $updating = false): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'document_type' => ['required', Rule::in(AiLegalDocumentCatalog::documentTypeKeys())],
            'document_date' => ['required', 'date'],
            'document_file' => [$updating ? 'nullable' : 'required', 'file', 'mimes:docx,pdf', 'max:20480'],
            'is_active' => ['nullable'],
            'observation' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function aiValidationRules(bool $testing = false): array
    {
        return [
            'ai_enabled' => ['nullable'],
            'ai_active_provider' => ['required', Rule::in(['openai', 'gemini'])],
            'ai_default_temperature' => [$testing ? 'nullable' : 'required', 'numeric', 'min:' . AiProviderCatalog::temperatureMin(), 'max:' . AiProviderCatalog::temperatureMax()],
            'ai_default_max_tokens' => [$testing ? 'nullable' : 'required', 'integer', 'min:' . AiProviderCatalog::tokenMin(), 'max:272000'],
            'ai_default_system_prompt' => ['nullable', 'string', 'max:20000'],
            'ai_default_legal_notice' => ['nullable', 'string', 'max:12000'],
            'ai_default_budget_request_url' => ['nullable', 'url', 'max:500'],
            'ai_old_document_alert_enabled' => ['nullable'],
            'ai_old_document_alert_years' => [$testing ? 'nullable' : 'required', 'integer', 'min:1', 'max:100'],
            'openai_api_key' => ['nullable', 'string', 'max:1000'],
            'openai_chat_model' => ['nullable', Rule::in(AiProviderCatalog::openAiChatModelIds())],
            'openai_embedding_model' => ['nullable', Rule::in(AiProviderCatalog::openAiEmbeddingModelIds())],
            'gemini_api_key' => ['nullable', 'string', 'max:1000'],
            'gemini_chat_model' => ['nullable', Rule::in(AiProviderCatalog::geminiChatModelIds())],
            'gemini_embedding_model' => ['nullable', Rule::in(AiProviderCatalog::geminiEmbeddingModelIds())],
        ];
    }

    private function aiSettingsFromRequest(Request $request, array $validated): array
    {
        $defaults = AiProviderCatalog::defaults();
        $openAiApiKey = trim((string) ($validated['openai_api_key'] ?? ''));
        if ($openAiApiKey === '') {
            $openAiApiKey = (string) AppSetting::getDecryptedValue('ai_openai_api_key', '');
        }

        $geminiApiKey = trim((string) ($validated['gemini_api_key'] ?? ''));
        if ($geminiApiKey === '') {
            $geminiApiKey = (string) AppSetting::getDecryptedValue('ai_gemini_api_key', '');
        }

        $provider = trim((string) ($validated['ai_active_provider'] ?? 'openai')) === 'gemini' ? 'gemini' : 'openai';

        return [
            'ai_enabled' => $request->boolean('ai_enabled'),
            'ai_active_provider' => $provider,
            'ai_default_temperature' => round((float) ($validated['ai_default_temperature'] ?? AppSetting::getValue('ai_default_temperature', $defaults['ai_default_temperature'])), 2),
            'ai_default_max_tokens' => (int) ($validated['ai_default_max_tokens'] ?? AppSetting::getValue('ai_default_max_tokens', $defaults['ai_default_max_tokens'])),
            'ai_default_system_prompt' => trim((string) ($validated['ai_default_system_prompt'] ?? $defaults['ai_default_system_prompt'])),
            'ai_default_legal_notice' => trim((string) ($validated['ai_default_legal_notice'] ?? $defaults['ai_default_legal_notice'])),
            'ai_default_budget_request_url' => trim((string) ($validated['ai_default_budget_request_url'] ?? $defaults['ai_default_budget_request_url'])),
            'ai_old_document_alert_enabled' => $request->boolean('ai_old_document_alert_enabled'),
            'ai_old_document_alert_years' => (int) ($validated['ai_old_document_alert_years'] ?? AppSetting::getValue('ai_old_document_alert_years', $defaults['ai_old_document_alert_years'])),
            'openai_enabled' => $provider === 'openai',
            'openai_api_key' => $openAiApiKey,
            'openai_chat_model' => trim((string) ($validated['openai_chat_model'] ?? $defaults['openai_chat_model'])),
            'openai_embedding_model' => trim((string) ($validated['openai_embedding_model'] ?? $defaults['openai_embedding_model'])),
            'gemini_enabled' => $provider === 'gemini',
            'gemini_api_key' => $geminiApiKey,
            'gemini_chat_model' => trim((string) ($validated['gemini_chat_model'] ?? $defaults['gemini_chat_model'])),
            'gemini_embedding_model' => trim((string) ($validated['gemini_embedding_model'] ?? $defaults['gemini_embedding_model'])),
        ];
    }

    private function validateAiConfigurationForSave(array $settings): void
    {
        $this->validateAiTokenLimitForProvider($settings);

        if (!$settings['ai_enabled']) {
            return;
        }

        $provider = $settings['ai_active_provider'];

        if ($provider === 'openai') {
            if ($settings['openai_api_key'] === '') {
                throw ValidationException::withMessages(['openai_api_key' => 'Informe a API Key da OpenAI para ativar este provedor.']);
            }

            if ($settings['openai_chat_model'] === '') {
                throw ValidationException::withMessages(['openai_chat_model' => 'Informe o modelo de chat da OpenAI para ativar este provedor.']);
            }

            return;
        }

        if ($settings['gemini_api_key'] === '') {
            throw ValidationException::withMessages(['gemini_api_key' => 'Informe a API Key da Gemini para ativar este provedor.']);
        }

        if ($settings['gemini_chat_model'] === '') {
            throw ValidationException::withMessages(['gemini_chat_model' => 'Informe o modelo de chat da Gemini para ativar este provedor.']);
        }
    }

    private function validateAiConfigurationForTest(array $settings): void
    {
        $this->validateAiTokenLimitForProvider($settings);

        $provider = $settings['ai_active_provider'];

        if ($provider === 'openai') {
            if ($settings['openai_api_key'] === '') {
                throw ValidationException::withMessages(['openai_api_key' => 'Informe a API Key da OpenAI antes de testar a conexao.']);
            }

            if ($settings['openai_chat_model'] === '') {
                throw ValidationException::withMessages(['openai_chat_model' => 'Informe o modelo de chat da OpenAI antes de testar a conexao.']);
            }

            return;
        }

        if ($settings['gemini_api_key'] === '') {
            throw ValidationException::withMessages(['gemini_api_key' => 'Informe a API Key da Gemini antes de testar a conexao.']);
        }

        if ($settings['gemini_chat_model'] === '') {
            throw ValidationException::withMessages(['gemini_chat_model' => 'Informe o modelo de chat da Gemini antes de testar a conexao.']);
        }
    }

    private function validateAiTokenLimitForProvider(array $settings): void
    {
        $provider = $settings['ai_active_provider'];
        $model = $provider === 'gemini'
            ? (string) ($settings['gemini_chat_model'] ?? '')
            : (string) ($settings['openai_chat_model'] ?? '');

        if ($model === '') {
            return;
        }

        $maxAllowed = AiProviderCatalog::maxOutputTokensFor($provider, $model);
        $requested = (int) ($settings['ai_default_max_tokens'] ?? 0);

        if ($requested < AiProviderCatalog::tokenMin()) {
            throw ValidationException::withMessages([
                'ai_default_max_tokens' => 'Use pelo menos ' . AiProviderCatalog::tokenMin() . ' tokens por resposta.',
            ]);
        }

        if ($requested > $maxAllowed) {
            throw ValidationException::withMessages([
                'ai_default_max_tokens' => 'O modelo selecionado suporta no maximo ' . number_format($maxAllowed, 0, ',', '.') . ' tokens de saida.',
            ]);
        }
    }

    private function persistAiSecret(Request $request, string $inputName, string $settingKey, string $description): void
    {
        $value = trim((string) $request->input($inputName, ''));
        if ($value === '') {
            return;
        }

        AppSetting::setEncryptedValue($settingKey, $value, $description);
    }

    private function maskSecretValue(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $length = mb_strlen($normalized);
        if ($length <= 8) {
            return mb_substr($normalized, 0, 2) . str_repeat('*', max($length - 2, 2));
        }

        return mb_substr($normalized, 0, 4)
            . str_repeat('*', max($length - 8, 4))
            . mb_substr($normalized, -4);
    }

    /**
     * @return array{original_name:string,stored_name:string,relative_path:string,mime_type:?string,file_size:?int,extension:string}
     */
    private function storeAiGlobalDocumentFile(UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'bin'));
        $storedName = 'legal-base-' . now()->format('Ymd-His') . '-' . Str::random(10) . '.' . $extension;
        $directory = 'ai/global-documents';

        Storage::disk('public')->putFileAs($directory, $file, $storedName);

        return [
            'original_name' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
            'stored_name' => $storedName,
            'relative_path' => $directory . '/' . $storedName,
            'mime_type' => Str::limit((string) $file->getClientMimeType(), 120, '') ?: null,
            'file_size' => $file->getSize() ?: null,
            'extension' => $extension,
        ];
    }

    private function syncAiGlobalDocumentChunksMetadata(AiGlobalDocument $document): void
    {
        $payload = [
            'source_document_type' => (string) $document->document_type,
            'is_active' => (bool) $document->is_active,
        ];

        if (Schema::hasColumn('ai_document_chunks', 'document_kind')) {
            $payload['document_kind'] = (string) $document->document_type;
        }

        if (Schema::hasColumn('ai_document_chunks', 'document_date')) {
            $payload['document_date'] = $document->document_date?->toDateString();
        }

        $document->chunks()->update($payload);
    }

    private function setMany(array $items): void
    {
        foreach ($items as $key => [$value, $description]) {
            AppSetting::setValue($key, $value, $description);
        }
    }

    private function normalizeAllowedIps(string $value): string
    {
        $items = preg_split('/[\r\n,;]+/', $value) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $ip = trim($item);
            if ($ip === '') {
                continue;
            }

            $normalized[$ip] = $ip;
        }

        return implode(PHP_EOL, array_values($normalized));
    }

    private function formatAllowedIpsForTextarea(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        return $this->normalizeAllowedIps($value);
    }

    private function normalizeDecimalValue(string $value, string $field, int $scale = 10): string
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($normalized === '') {
            throw ValidationException::withMessages([$field => 'Informe um valor numerico valido.']);
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
        }

        $normalized = str_replace(',', '.', $normalized);

        if (str_starts_with($normalized, '.')) {
            $normalized = '0' . $normalized;
        }

        if (!preg_match('/^\d+(\.\d{1,' . $scale . '})?$/', $normalized)) {
            throw ValidationException::withMessages([$field => 'Informe um valor numerico valido.']);
        }

        [$integerPart, $decimalPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $integerPart = ltrim($integerPart, '0');
        if ($integerPart === '') {
            $integerPart = '0';
        }

        $decimalPart = substr(str_pad($decimalPart, $scale, '0'), 0, $scale);
        $result = $integerPart . '.' . $decimalPart;

        if ((float) $result <= 0) {
            throw ValidationException::withMessages([$field => 'O valor precisa ser maior que zero.']);
        }

        return $result;
    }
}
