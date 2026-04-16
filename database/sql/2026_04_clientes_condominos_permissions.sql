INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
VALUES ('clientes', 'clientes.condominos', 'Listar condôminos', NOW())
ON DUPLICATE KEY UPDATE
  `group_key` = VALUES(`group_key`),
  `label` = VALUES(`label`);
