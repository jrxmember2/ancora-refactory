<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogPresenter
{
    public static function actionLabel(?string $action): string
    {
        $action = trim((string) $action);
        if ($action === '') {
            return 'Ação não identificada';
        }

        $labels = [
            'login' => 'Login realizado',
            'logout' => 'Logout realizado',

            'clientes.avulsos.store' => 'Criou cliente avulso',
            'clientes.avulsos.update' => 'Atualizou cliente avulso',
            'clientes.avulsos.delete' => 'Excluiu cliente avulso',
            'clientes.contatos.store' => 'Criou parceiro/fornecedor',
            'clientes.contatos.update' => 'Atualizou parceiro/fornecedor',
            'clientes.contatos.delete' => 'Excluiu parceiro/fornecedor',
            'clientes.portal-users.store' => 'Criou usuário do portal',
            'clientes.portal-users.update' => 'Atualizou usuário do portal',
            'clientes.portal-users.delete' => 'Excluiu usuário do portal',
            'clientes.condominios.store' => 'Criou condomínio',
            'clientes.condominios.update' => 'Atualizou condomínio',
            'clientes.condominios.delete' => 'Excluiu condomínio',
            'clientes.unidades.store' => 'Criou unidade',
            'clientes.unidades.update' => 'Atualizou unidade',
            'clientes.unidades.delete' => 'Excluiu unidade',
            'clientes.unidades.import' => 'Gerou prévia de importação de unidades',
            'clientes.unidades.import.preview' => 'Gerou prévia de importação de unidades',
            'clientes.unidades.import.execute' => 'Importou unidades',
            'clientes.unidades.bulk-delete' => 'Excluiu unidades em massa',
            'clientes.config.types.store' => 'Criou configuração de cliente',
            'clientes.attachments.delete' => 'Excluiu anexo de cliente',

            'cobrancas.store' => 'Criou OS de cobrança',
            'cobrancas.update' => 'Atualizou OS de cobrança',
            'cobrancas.delete' => 'Excluiu OS de cobrança',
            'cobrancas.import.preview' => 'Gerou prévia de inadimplência',
            'cobrancas.import.process' => 'Processou inadimplência',
            'cobrancas.import.resolve' => 'Resolveu conflito da importação de inadimplência',
            'cobrancas.import.cancel' => 'Cancelou importação de inadimplência',
            'cobrancas.import.report' => 'Exportou relatório da importação de inadimplência',
            'process_cobranca_import' => 'Processou inadimplência',
            'cobrancas.agreement.save' => 'Salvou termo de acordo',
            'cobrancas.monetary.preview' => 'Simulou atualização monetária',
            'cobrancas.monetary.store' => 'Salvou atualização monetária',
            'cobrancas.monetary.apply' => 'Aplicou atualização monetária',
            'cobrancas.timeline.store' => 'Registrou andamento da cobrança',
            'cobrancas.attachments.upload' => 'Anexou arquivo na cobrança',
            'cobrancas.attachments.delete' => 'Excluiu anexo da cobrança',

            'processos.store' => 'Criou processo',
            'processos.update' => 'Atualizou processo',
            'processos.delete' => 'Excluiu processo',
            'processos.import.template' => 'Baixou modelo de importacao de processos',
            'processos.import.preview' => 'Gerou previa de importacao de processos',
            'processos.import.execute' => 'Importou processos',
            'processos.phases.store' => 'Cadastrou fase do processo',
            'processos.attachments.upload' => 'Anexou arquivo no processo',
            'processos.attachments.delete' => 'Excluiu anexo do processo',
            'processos.datajud.sync' => 'Sincronizou processo com DataJud',
            'processos.datajud.schedule' => 'Executou sincronizacao agendada do DataJud',

            'demandas.update' => 'Atualizou demanda',
            'demandas.tag.update' => 'Moveu demanda no kanban',
            'demandas.reply' => 'Respondeu demanda',
            'demandas.attachments.download' => 'Baixou anexo de demanda',

            'propostas.store' => 'Criou proposta',
            'propostas.update' => 'Atualizou proposta',
            'propostas.delete' => 'Excluiu proposta',
            'create_proposta' => 'Criou proposta',
            'update_proposta' => 'Atualizou proposta',
            'delete_proposta' => 'Excluiu proposta',
            'propostas.attachments.upload' => 'Anexou PDF na proposta',
            'propostas.attachments.delete' => 'Excluiu PDF da proposta',
            'upload_attachment' => 'Anexou arquivo',
            'delete_attachment' => 'Excluiu anexo',
            'propostas.document.save' => 'Salvou documento da proposta',

            'contratos.store' => 'Criou contrato',
            'contratos.update' => 'Atualizou contrato',
            'contratos.delete' => 'Excluiu contrato',
            'contratos.restore' => 'Restaurou contrato',
            'contratos.duplicate' => 'Duplicou contrato',
            'contratos.archive' => 'Arquivou contrato',
            'contratos.rescind' => 'Rescindiu contrato',
            'contratos.generate-pdf' => 'Gerou PDF do contrato',
            'contratos.attachments.upload' => 'Anexou arquivo no contrato',
            'contratos.attachments.delete' => 'Excluiu anexo do contrato',
            'contratos.templates.store' => 'Criou template de contrato',
            'contratos.templates.update' => 'Atualizou template de contrato',
            'contratos.templates.delete' => 'Excluiu template de contrato',
            'contratos.categories.store' => 'Criou categoria de contrato',
            'contratos.categories.update' => 'Atualizou categoria de contrato',
            'contratos.categories.delete' => 'Excluiu categoria de contrato',
            'contratos.variables.update' => 'Atualizou variável de contrato',
            'contratos.settings.save' => 'Atualizou configurações de contratos',

            'config.branding.save' => 'Atualizou identidade visual',
            'config.favicon.save' => 'Atualizou favicon',
            'config.modules.save' => 'Atualizou módulos',
            'config.automation.documentation' => 'Abriu documentacao da automacao WhatsApp',
            'config.automation.save' => 'Atualizou automacao WhatsApp',
            'config.system-alert.save' => 'Atualizou alerta global',
            'config.smtp.save' => 'Atualizou SMTP',
            'config.access-profiles.save' => 'Atualizou perfis de acesso',
            'config.access-profiles.delete' => 'Excluiu perfil de acesso',
            'config.tjes-factors.store' => 'Salvou indice TJES',
            'config.demand-tags.store' => 'Criou tag de demanda',
            'config.demand-tags.update' => 'Atualizou tag de demanda',
            'config.demand-tags.delete' => 'Excluiu tag de demanda',
            'config.servicos.store' => 'Criou serviço',
            'config.servicos.update' => 'Atualizou serviço',
            'config.servicos.delete' => 'Excluiu serviço',
            'config.status.store' => 'Criou status',
            'config.status.update' => 'Atualizou status',
            'config.status.delete' => 'Excluiu status',
            'config.formas.store' => 'Criou forma de envio',
            'config.formas.update' => 'Atualizou forma de envio',
            'config.formas.delete' => 'Excluiu forma de envio',
            'config.users.store' => 'Criou usuário',
            'config.users.update' => 'Atualizou usuário',
            'config.users.delete' => 'Excluiu usuário',

            'profile.update' => 'Atualizou meus dados',
            'profile.theme' => 'Alterou tema do sistema',
            'password.reset.update' => 'Redefiniu senha',
        ];

        if (isset($labels[$action])) {
            return $labels[$action];
        }

        if (Str::startsWith($action, ['http://', 'https://'])) {
            return 'Executou ação do sistema';
        }

        return Str::of($action)
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }

    public static function moduleLabel(?string $entityType): string
    {
        $entityType = trim((string) $entityType);
        if ($entityType === '') {
            return 'Sistema';
        }

        $labels = [
            'clientes' => 'Clientes',
            'client_entities' => 'Pessoas e contatos',
            'client_condominiums' => 'Condomínios',
            'client_units' => 'Unidades',
            'client_blocks' => 'Blocos',
            'client_portal_users' => 'Usuários do portal',
            'cobrancas' => 'Cobranças',
            'demands' => 'Demandas',
            'demand_messages' => 'Mensagens de demandas',
            'processos' => 'Processos',
            'process_cases' => 'Processos',
            'contracts' => 'Contratos',
            'contract_templates' => 'Templates de contrato',
            'contract_categories' => 'Categorias de contrato',
            'contract_variables' => 'Variáveis de contrato',
            'propostas' => 'Propostas',
            'users' => 'Usuários',
            'config' => 'Configurações',
            'password' => 'Senha',
        ];

        return $labels[$entityType] ?? Str::of($entityType)->replace(['_', '-'], ' ')->title()->toString();
    }

    public static function entityTypeForRoute(string $routeName, ?string $fallback = null): ?string
    {
        return match (true) {
            Str::startsWith($routeName, 'clientes.condominios') => 'client_condominiums',
            Str::startsWith($routeName, 'clientes.unidades') => 'client_units',
            Str::startsWith($routeName, 'clientes.portal-users') => 'client_portal_users',
            Str::startsWith($routeName, ['clientes.avulsos', 'clientes.contatos']) => 'client_entities',
            Str::startsWith($routeName, 'clientes.config') => 'clientes',
            Str::startsWith($routeName, 'clientes.attachments') => 'clientes',
            Str::startsWith($routeName, 'cobrancas') => 'cobrancas',
            Str::startsWith($routeName, 'demandas') => 'demands',
            Str::startsWith($routeName, 'processos') => 'process_cases',
            Str::startsWith($routeName, 'contratos.templates') => 'contract_templates',
            Str::startsWith($routeName, 'contratos.categories') => 'contract_categories',
            Str::startsWith($routeName, 'contratos.variables') => 'contract_variables',
            Str::startsWith($routeName, 'contratos') => 'contracts',
            Str::startsWith($routeName, 'propostas') => 'propostas',
            Str::startsWith($routeName, 'profile') => 'users',
            Str::startsWith($routeName, 'config.users') => 'users',
            Str::startsWith($routeName, 'config') => 'config',
            Str::startsWith($routeName, 'password') => 'password',
            default => $fallback,
        };
    }

    public static function detailsForDisplay(AuditLog $log): string
    {
        $details = trim((string) $log->details);
        if ($details !== '' && !self::isTechnicalRequestDetail($details)) {
            return $details;
        }

        $label = self::actionLabel($log->action);
        $module = self::moduleLabel($log->entity_type);
        $entity = $log->entity_id ? ' #' . $log->entity_id : '';
        $record = self::genericRecordFromAction((string) $log->action);

        if ($record !== '') {
            return self::verbForRoute((string) $log->action, false) . " {$record}{$entity}.";
        }

        return "{$label} em {$module}{$entity}.";
    }

    public static function detailsFromRequest(Request $request, string $routeName, int $status): string
    {
        $failed = $status >= 400 || self::requestHasErrorFlash($request);
        $record = self::recordLabelFromRequest($request, $routeName);
        $verb = self::verbForRoute($routeName, $failed);

        if ($record !== '') {
            return "{$verb} {$record}.";
        }

        return sprintf(
            '%s em %s.',
            self::actionLabel($routeName),
            self::moduleLabel(self::entityTypeForRoute($routeName, explode('.', $routeName)[0] ?: null))
        );
    }

    public static function labelMatches(?string $value, string $term): bool
    {
        return str_contains(self::normalize($value), self::normalize($term));
    }

    private static function verbForRoute(string $routeName, bool $failed): string
    {
        if ($failed) {
            return match (true) {
                Str::endsWith($routeName, '.store') => 'Tentou criar registro',
                Str::endsWith($routeName, '.update') => 'Tentou atualizar registro',
                Str::endsWith($routeName, '.delete') => 'Tentou excluir registro',
                Str::endsWith($routeName, '.restore') => 'Tentou restaurar registro',
                Str::endsWith($routeName, '.save') => 'Tentou salvar registro',
                default => 'Tentou alterar',
            };
        }

        return match (true) {
            Str::endsWith($routeName, '.store') => 'Criou registro',
            Str::endsWith($routeName, '.update') => 'Atualizou registro',
            Str::endsWith($routeName, '.delete') => 'Excluiu registro',
            Str::endsWith($routeName, '.restore') => 'Restaurou registro',
            Str::endsWith($routeName, '.save') => 'Salvou registro',
            Str::endsWith($routeName, '.upload') => 'Anexou arquivo em',
            Str::contains($routeName, '.import.preview') => 'Gerou prévia de',
            Str::contains($routeName, '.import.execute') => 'Executou importação de',
            Str::contains($routeName, '.bulk-delete') => 'Excluiu em massa',
            default => 'Registrou ação em',
        };
    }

    private static function recordLabelFromRequest(Request $request, string $routeName): string
    {
        return match (true) {
            Str::startsWith($routeName, 'clientes.condominios') => self::namedRecord('CONDOMÍNIO', self::inputOrRouteValue($request, ['name'], ['condominio'], ['name'])),
            Str::startsWith($routeName, 'clientes.avulsos') => self::namedRecord('CLIENTE AVULSO', self::inputOrRouteValue($request, ['display_name'], ['avulso'], ['display_name'])),
            Str::startsWith($routeName, 'clientes.contatos') => self::namedRecord('PARCEIRO/FORNECEDOR', self::inputOrRouteValue($request, ['display_name'], ['contato'], ['display_name'])),
            Str::startsWith($routeName, 'clientes.portal-users') => self::namedRecord('USUÁRIO DO PORTAL', self::inputOrRouteValue($request, ['name', 'login_key'], ['portalUser'], ['name', 'login_key'])),
            Str::startsWith($routeName, 'clientes.unidades') => self::unitRecord($request),
            Str::startsWith($routeName, 'config.users') => self::namedRecord('USUÁRIO', self::inputOrRouteValue($request, ['email'], ['user'], ['email'])),
            Str::startsWith($routeName, 'config.system-alert') => self::namedRecord('ALERTA DO SISTEMA', self::inputOrRouteValue($request, ['system_alert_title'], [], [])),
            Str::startsWith($routeName, 'config.tjes-factors') => self::namedRecord('INDICE TJES', self::competenceLabelFromRequest($request)),
            Str::startsWith($routeName, 'config.demand-tags') => self::namedRecord('TAG DE DEMANDA', self::inputOrRouteValue($request, ['name'], ['tag'], ['name'])),
            Str::startsWith($routeName, 'cobrancas') => self::namedRecord('OS DE COBRANÇA', self::inputOrRouteValue($request, ['os_number'], ['cobranca'], ['os_number'])),
            Str::startsWith($routeName, 'demandas') => self::namedRecord('DEMANDA', self::inputOrRouteValue($request, ['subject'], ['demanda'], ['protocol', 'subject'])),
            Str::startsWith($routeName, 'processos') => self::namedRecord('PROCESSO', self::inputOrRouteValue($request, ['process_number', 'client_name'], ['processo'], ['process_number', 'client_name_snapshot'])),
            Str::startsWith($routeName, 'contratos.templates') => self::namedRecord('TEMPLATE DE CONTRATO', self::inputOrRouteValue($request, ['name'], ['template'], ['name'])),
            Str::startsWith($routeName, 'contratos.categories') => self::namedRecord('CATEGORIA DE CONTRATO', self::inputOrRouteValue($request, ['name'], ['category'], ['name'])),
            Str::startsWith($routeName, 'contratos.variables') => self::namedRecord('VARIÁVEL DE CONTRATO', self::inputOrRouteValue($request, ['label'], ['variable'], ['label'])),
            Str::startsWith($routeName, 'contratos') => self::namedRecord('CONTRATO', self::inputOrRouteValue($request, ['title', 'code'], ['contrato'], ['title', 'code'])),
            Str::startsWith($routeName, 'propostas') => self::namedRecord('PROPOSTA', self::inputOrRouteValue($request, ['proposal_code'], ['proposta'], ['proposal_code'])),
            default => '',
        };
    }

    private static function genericRecordFromAction(string $action): string
    {
        return match (true) {
            Str::startsWith($action, 'clientes.condominios') => 'CONDOMÍNIO',
            Str::startsWith($action, 'clientes.unidades') && !Str::contains($action, '.import') => 'UNIDADE',
            Str::startsWith($action, 'clientes.avulsos') => 'CLIENTE AVULSO',
            Str::startsWith($action, 'clientes.contatos') => 'PARCEIRO/FORNECEDOR',
            Str::startsWith($action, 'clientes.portal-users') => 'USUÁRIO DO PORTAL',
            Str::startsWith($action, 'config.users') => 'USUÁRIO',
            Str::startsWith($action, 'config.system-alert') => 'ALERTA DO SISTEMA',
            Str::startsWith($action, 'config.tjes-factors') => 'INDICE TJES',
            Str::startsWith($action, 'config.demand-tags') => 'TAG DE DEMANDA',
            Str::startsWith($action, 'cobrancas') && !Str::contains($action, '.import') => 'OS DE COBRANÇA',
            Str::startsWith($action, 'demandas') => 'DEMANDA',
            Str::startsWith($action, 'processos') => 'PROCESSO',
            Str::startsWith($action, 'contratos.templates') => 'TEMPLATE DE CONTRATO',
            Str::startsWith($action, 'contratos.categories') => 'CATEGORIA DE CONTRATO',
            Str::startsWith($action, 'contratos.variables') => 'VARIÁVEL DE CONTRATO',
            Str::startsWith($action, 'contratos') => 'CONTRATO',
            Str::startsWith($action, 'propostas') => 'PROPOSTA',
            default => '',
        };
    }

    private static function unitRecord(Request $request): string
    {
        $unit = self::routeObject($request, ['unidade']);
        $unitNumber = self::firstFilledInput($request, ['unit_number']) ?: self::objectValue($unit, ['unit_number']);
        $condominium = self::objectValue($unit?->condominium ?? null, ['name']);
        $block = self::objectValue($unit?->block ?? null, ['name']);

        if ($condominium === '') {
            $condominiumId = (int) $request->input('condominium_id', 0);
            $condominium = $condominiumId > 0 ? (string) ClientCondominium::query()->whereKey($condominiumId)->value('name') : '';
        }

        if ($block === '') {
            $blockId = (int) $request->input('block_id', 0);
            $block = $blockId > 0 ? (string) ClientBlock::query()->whereKey($blockId)->value('name') : '';
        }

        $parts = [];
        if ($unitNumber !== '') {
            $parts[] = 'UNIDADE ' . self::upper($unitNumber);
        }
        if ($condominium !== '') {
            $parts[] = 'CONDOMÍNIO ' . self::upper($condominium);
        }
        if ($block !== '') {
            $parts[] = 'BLOCO ' . self::upper($block);
        }

        return implode(' · ', $parts);
    }

    private static function namedRecord(string $type, ?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? "{$type} " . self::upper($name) : $type;
    }

    private static function competenceLabelFromRequest(Request $request): string
    {
        $month = (int) $request->input('month', 0);
        $year = (int) $request->input('year', 0);

        if ($month < 1 || $month > 12 || $year <= 0) {
            return '';
        }

        return sprintf('%02d/%04d', $month, $year);
    }

    private static function inputOrRouteValue(Request $request, array $inputKeys, array $routeKeys, array $modelFields): string
    {
        $value = self::firstFilledInput($request, $inputKeys);
        if ($value !== '') {
            return $value;
        }

        $object = self::routeObject($request, $routeKeys);
        return self::objectValue($object, $modelFields);
    }

    private static function firstFilledInput(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function routeObject(Request $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            $parameter = $request->route($key);
            if (is_object($parameter)) {
                return $parameter;
            }
        }

        return null;
    }

    private static function objectValue(mixed $object, array $fields): string
    {
        if (!is_object($object)) {
            return '';
        }

        foreach ($fields as $field) {
            $value = trim((string) ($object->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function requestHasErrorFlash(Request $request): bool
    {
        try {
            return $request->hasSession()
                && ($request->session()->has('errors')
                    || $request->session()->has('errors_list')
                    || $request->session()->has('error'));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function isTechnicalRequestDetail(string $details): bool
    {
        return (bool) preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+\/.+HTTP\s+\d{3}$/i', $details);
    }

    private static function normalize(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))->lower()->squish()->toString();
    }

    private static function upper(string $value): string
    {
        return mb_strtoupper(trim($value), 'UTF-8');
    }
}
