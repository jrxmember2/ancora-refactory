CREATE TABLE IF NOT EXISTS `cobranca_import_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `sheet_name` varchar(180) DEFAULT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'parsed',
  `total_rows` int unsigned NOT NULL DEFAULT 0,
  `ready_rows` int unsigned NOT NULL DEFAULT 0,
  `pending_rows` int unsigned NOT NULL DEFAULT 0,
  `duplicate_rows` int unsigned NOT NULL DEFAULT 0,
  `created_cases` int unsigned NOT NULL DEFAULT 0,
  `updated_cases` int unsigned NOT NULL DEFAULT 0,
  `created_quotas` int unsigned NOT NULL DEFAULT 0,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `summary_json` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_import_batches_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_cobranca_import_batches_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_import_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned NOT NULL,
  `row_number` int unsigned NOT NULL,
  `raw_payload_json` json DEFAULT NULL,
  `condominium_input` varchar(180) DEFAULT NULL,
  `block_input` varchar(120) DEFAULT NULL,
  `unit_input` varchar(80) DEFAULT NULL,
  `reference_input` varchar(30) DEFAULT NULL,
  `due_date_input` varchar(40) DEFAULT NULL,
  `amount_value` decimal(12,2) DEFAULT NULL,
  `matched_unit_id` int DEFAULT NULL,
  `matched_case_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ready',
  `message` varchar(255) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_import_rows_batch_status` (`batch_id`,`status`),
  KEY `idx_cobranca_import_rows_match_ref_due` (`matched_unit_id`,`reference_input`,`due_date_input`),
  CONSTRAINT `fk_cobranca_import_rows_batch` FOREIGN KEY (`batch_id`) REFERENCES `cobranca_import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_import_rows_unit` FOREIGN KEY (`matched_unit_id`) REFERENCES `client_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_import_rows_case` FOREIGN KEY (`matched_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.import.index', 'Acessar importação de inadimplência', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.import.index');

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.import.preview', 'Analisar planilha de inadimplência', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.import.preview');

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.import.show', 'Visualizar lote de importação de inadimplência', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.import.show');

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.import.process', 'Processar lote de inadimplência', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.import.process');
