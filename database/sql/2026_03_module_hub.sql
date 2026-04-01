INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'dashboard', 'Dashboard Executivo', 'fa-solid fa-chart-line', '/dashboard', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'dashboard');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'propostas', 'Propostas', 'fa-solid fa-file-signature', '/propostas', 1, 2
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'propostas');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'busca', 'Busca Inteligente', 'fa-solid fa-magnifying-glass', '/busca', 1, 3
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'busca');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'config', 'Administração', 'fa-solid fa-gear', '/config', 1, 4
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'config');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'logs', 'Auditoria e Logs', 'fa-solid fa-clock-rotate-left', '/logs', 1, 5
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'logs');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'clientes', 'Clientes e Leads', 'fa-solid fa-users', '/clientes', 0, 20
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'clientes');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'cobrancas', 'Cobranças', 'fa-solid fa-money-bill-wave', '/cobrancas', 0, 21
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'cobrancas');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'documentos', 'Documentos', 'fa-solid fa-folder-open', '/documentos', 0, 22
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'documentos');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'agenda', 'Agenda e Tarefas', 'fa-solid fa-calendar-days', '/agenda', 0, 23
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'agenda');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'financeiro', 'Financeiro', 'fa-solid fa-chart-pie', '/financeiro', 0, 24
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'financeiro');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'relatorios', 'Relatórios', 'fa-solid fa-chart-column', '/relatorios', 0, 25
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'relatorios');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'atendimento', 'Atendimento', 'fa-solid fa-headset', '/atendimento', 0, 26
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'atendimento');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'automacoes', 'Automações', 'fa-solid fa-robot', '/automacoes', 0, 27
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'automacoes');
