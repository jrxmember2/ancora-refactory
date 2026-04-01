-- Atualiza URL base, central desktop e permissões por módulo
UPDATE app_settings
SET setting_value = 'https://ancora.rebecamedina.com.br'
WHERE setting_key = 'app_base_url';

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'dashboard', 'Dashboard', 'fa-solid fa-chart-line', '/dashboard', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'dashboard');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'propostas', 'Propostas', 'fa-solid fa-file-signature', '/propostas', 1, 2
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'propostas');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'busca', 'Busca', 'fa-solid fa-magnifying-glass', '/busca', 1, 3
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'busca');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'config', 'Configurações', 'fa-solid fa-gear', '/config', 1, 98
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'config');

INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'logs', 'Logs e Auditoria', 'fa-solid fa-clock-rotate-left', '/logs', 1, 99
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'logs');

CREATE TABLE IF NOT EXISTS user_module_permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  module_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_module_permissions_user_module (user_id, module_id),
  KEY idx_user_module_permissions_module (module_id),
  CONSTRAINT fk_user_module_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_module_permissions_module FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT IGNORE INTO user_module_permissions (user_id, module_id)
SELECT u.id, m.id
FROM users u
JOIN system_modules m ON m.slug IN ('dashboard', 'propostas', 'busca')
WHERE u.role = 'comum';
