INSERT INTO system_modules (slug, name, icon_class, route_prefix, is_enabled, sort_order)
SELECT 'cobrancas', 'Cobrança', 'fa-solid fa-money-bill-wave', '/cobrancas', 1, 21
WHERE NOT EXISTS (SELECT 1 FROM system_modules WHERE slug = 'cobrancas');

UPDATE system_modules
SET name = 'Cobrança',
    icon_class = 'fa-solid fa-money-bill-wave',
    route_prefix = '/cobrancas',
    is_enabled = 1,
    sort_order = 21
WHERE slug = 'cobrancas';

CREATE TABLE IF NOT EXISTS `cobranca_cases` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `charge_year` smallint UNSIGNED NOT NULL,
  `charge_seq` int UNSIGNED NOT NULL,
  `os_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condominium_id` int DEFAULT NULL,
  `block_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `debtor_entity_id` int DEFAULT NULL,
  `debtor_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'owner',
  `debtor_name_snapshot` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `debtor_document_snapshot` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debtor_email_snapshot` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debtor_phone_snapshot` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charge_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'extrajudicial',
  `agreement_total` decimal(12,2) DEFAULT NULL,
  `billing_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_faturar',
  `billing_date` date DEFAULT NULL,
  `alert_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `situation` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processo_aberto',
  `workflow_stage` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'triagem',
  `entry_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_due_date` date DEFAULT NULL,
  `entry_amount` decimal(12,2) DEFAULT NULL,
  `fees_amount` decimal(12,2) DEFAULT NULL,
  `judicial_case_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calc_base_date` date DEFAULT NULL,
  `last_progress_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobranca_cases_os_number` (`os_number`),
  UNIQUE KEY `uq_cobranca_cases_year_seq` (`charge_year`,`charge_seq`),
  KEY `idx_cobranca_cases_stage_situation` (`workflow_stage`,`situation`),
  KEY `idx_cobranca_cases_condo_unit` (`condominium_id`,`unit_id`),
  KEY `idx_cobranca_cases_debtor_name` (`debtor_name_snapshot`),
  KEY `idx_cobranca_cases_block` (`block_id`),
  KEY `idx_cobranca_cases_debtor` (`debtor_entity_id`),
  KEY `idx_cobranca_cases_created_by` (`created_by`),
  KEY `idx_cobranca_cases_updated_by` (`updated_by`),
  CONSTRAINT `fk_cobranca_cases_condominium` FOREIGN KEY (`condominium_id`) REFERENCES `client_condominiums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_cases_block` FOREIGN KEY (`block_id`) REFERENCES `client_condominium_blocks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_cases_unit` FOREIGN KEY (`unit_id`) REFERENCES `client_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_cases_debtor` FOREIGN KEY (`debtor_entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_cases_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_cases_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_case_contacts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint UNSIGNED NOT NULL,
  `contact_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_whatsapp` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_case_contacts_case_type` (`cobranca_case_id`,`contact_type`),
  CONSTRAINT `fk_cobranca_case_contacts_case` FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_case_quotas` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint UNSIGNED NOT NULL,
  `reference_label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` date NOT NULL,
  `original_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `updated_amount` decimal(12,2) DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
  `notes` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_case_quotas_case_due` (`cobranca_case_id`,`due_date`),
  CONSTRAINT `fk_cobranca_case_quotas_case` FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_case_installments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint UNSIGNED NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installment_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'parcela',
  `installment_number` int UNSIGNED DEFAULT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_case_installments_case_due` (`cobranca_case_id`,`due_date`),
  CONSTRAINT `fk_cobranca_case_installments_case` FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_case_timelines` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint UNSIGNED NOT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_case_timelines_case_created` (`cobranca_case_id`,`created_at`),
  KEY `idx_cobranca_case_timelines_user` (`user_id`),
  CONSTRAINT `fk_cobranca_case_timelines_case` FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_case_timelines_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobranca_case_attachments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint UNSIGNED NOT NULL,
  `file_role` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `relative_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uploaded_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cobranca_case_attachments_case` (`cobranca_case_id`),
  KEY `idx_cobranca_case_attachments_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_cobranca_case_attachments_case` FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_case_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
