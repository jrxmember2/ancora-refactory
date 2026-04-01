-- Âncora - SQL completo corrigido para instalação limpa
-- Observação: este arquivo recria as tabelas do sistema em uma base vazia
-- ou substitui uma importação parcial anterior.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS `trg_users_protect_delete`;
DROP TRIGGER IF EXISTS `trg_users_protect_update`;

DROP TABLE IF EXISTS `notification_dispatches`;
DROP TABLE IF EXISTS `proposal_document_assets`;
DROP TABLE IF EXISTS `proposal_document_options`;
DROP TABLE IF EXISTS `proposal_documents`;
DROP TABLE IF EXISTS `proposta_attachments`;
DROP TABLE IF EXISTS `proposta_history`;
DROP TABLE IF EXISTS `propostas`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `user_module_permissions`;
DROP TABLE IF EXISTS `client_timelines`;
DROP TABLE IF EXISTS `client_attachments`;
DROP TABLE IF EXISTS `client_units`;
DROP TABLE IF EXISTS `client_condominium_blocks`;
DROP TABLE IF EXISTS `client_condominiums`;
DROP TABLE IF EXISTS `client_entities`;
DROP TABLE IF EXISTS `client_types`;
DROP TABLE IF EXISTS `proposal_templates`;
DROP TABLE IF EXISTS `formas_envio`;
DROP TABLE IF EXISTS `status_retorno`;
DROP TABLE IF EXISTS `servicos`;
DROP TABLE IF EXISTS `administradoras`;
DROP TABLE IF EXISTS `system_modules`;
DROP TABLE IF EXISTS `app_settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `administradoras` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('administradora','sindico') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'administradora',
  `contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_administradoras_name_type` (`name`,`type`),
  KEY `idx_administradoras_active` (`is_active`),
  KEY `idx_administradoras_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_modules` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_class` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_prefix` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_modules_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','comum') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'comum',
  `theme_preference` enum('light','dark') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dark',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_protected` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `formas_envio` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_class` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#999999',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_formas_envio_name` (`name`),
  KEY `idx_formas_envio_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servicos` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servicos_name` (`name`),
  KEY `idx_servicos_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `status_retorno` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `system_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#999999',
  `requires_closed_value` tinyint(1) NOT NULL DEFAULT '0',
  `requires_refusal_reason` tinyint(1) NOT NULL DEFAULT '0',
  `stop_followup_alert` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_status_retorno_system_key` (`system_key`),
  UNIQUE KEY `uq_status_retorno_name` (`name`),
  KEY `idx_status_retorno_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal_templates` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proposal_templates_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scope` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '999',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_types_scope_name` (`scope`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_entities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` enum('pf','pj') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pf',
  `profile_scope` enum('avulso','contato') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'avulso',
  `role_tag` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `display_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf_cnpj` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rg_ie` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `profession` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marital_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pis` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `children_info` text COLLATE utf8mb4_unicode_ci,
  `ctps` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnae` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_registration` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipal_registration` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_date` date DEFAULT NULL,
  `legal_representative` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phones_json` longtext COLLATE utf8mb4_unicode_ci,
  `emails_json` longtext COLLATE utf8mb4_unicode_ci,
  `primary_address_json` longtext COLLATE utf8mb4_unicode_ci,
  `billing_address_json` longtext COLLATE utf8mb4_unicode_ci,
  `shareholders_json` longtext COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `inactive_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_entities_scope` (`profile_scope`),
  KEY `idx_client_entities_role` (`role_tag`),
  KEY `idx_client_entities_active` (`is_active`),
  KEY `idx_client_entities_document` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_condominiums` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condominium_type_id` int DEFAULT NULL,
  `has_blocks` tinyint(1) NOT NULL DEFAULT '0',
  `cnpj` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnae` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_registration` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipal_registration` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_json` longtext COLLATE utf8mb4_unicode_ci,
  `syndico_entity_id` int DEFAULT NULL,
  `administradora_entity_id` int DEFAULT NULL,
  `bank_details` text COLLATE utf8mb4_unicode_ci,
  `characteristics` longtext COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `inactive_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_condominiums_name` (`name`),
  KEY `idx_client_condominiums_type` (`condominium_type_id`),
  KEY `idx_client_condominiums_syndic` (`syndico_entity_id`),
  KEY `idx_client_condominiums_admin` (`administradora_entity_id`),
  CONSTRAINT `fk_client_condominium_type` FOREIGN KEY (`condominium_type_id`) REFERENCES `client_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_condominium_syndic` FOREIGN KEY (`syndico_entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_condominium_admin` FOREIGN KEY (`administradora_entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_condominium_blocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condominium_id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_blocks_condo` (`condominium_id`),
  CONSTRAINT `fk_client_blocks_condo` FOREIGN KEY (`condominium_id`) REFERENCES `client_condominiums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condominium_id` int NOT NULL,
  `block_id` int DEFAULT NULL,
  `unit_type_id` int DEFAULT NULL,
  `unit_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_entity_id` int DEFAULT NULL,
  `tenant_entity_id` int DEFAULT NULL,
  `owner_notes` text COLLATE utf8mb4_unicode_ci,
  `tenant_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_units_condo` (`condominium_id`),
  KEY `idx_client_units_block` (`block_id`),
  KEY `idx_client_units_type` (`unit_type_id`),
  KEY `idx_client_units_owner` (`owner_entity_id`),
  KEY `idx_client_units_tenant` (`tenant_entity_id`),
  CONSTRAINT `fk_client_units_condo` FOREIGN KEY (`condominium_id`) REFERENCES `client_condominiums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_units_block` FOREIGN KEY (`block_id`) REFERENCES `client_condominium_blocks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_units_type` FOREIGN KEY (`unit_type_id`) REFERENCES `client_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_units_owner` FOREIGN KEY (`owner_entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_units_tenant` FOREIGN KEY (`tenant_entity_id`) REFERENCES `client_entities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `propostas` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_year` smallint UNSIGNED NOT NULL,
  `proposal_seq` int UNSIGNED NOT NULL,
  `proposal_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposal_date` date NOT NULL,
  `client_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `administradora_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `proposal_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `closed_total` decimal(12,2) DEFAULT NULL,
  `requester_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_referral` tinyint(1) NOT NULL DEFAULT '0',
  `referral_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_method_id` bigint UNSIGNED NOT NULL,
  `response_status_id` bigint UNSIGNED NOT NULL,
  `refusal_reason` text COLLATE utf8mb4_unicode_ci,
  `followup_date` date DEFAULT NULL,
  `validity_days` int UNSIGNED NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_propostas_code` (`proposal_code`),
  UNIQUE KEY `uq_propostas_year_seq` (`proposal_year`,`proposal_seq`),
  KEY `idx_propostas_proposal_date` (`proposal_date`),
  KEY `idx_propostas_followup_date` (`followup_date`),
  KEY `idx_propostas_status` (`response_status_id`),
  KEY `idx_propostas_administradora` (`administradora_id`),
  KEY `idx_propostas_servico` (`service_id`),
  KEY `idx_propostas_created_by` (`created_by`),
  KEY `fk_propostas_forma_envio` (`send_method_id`),
  KEY `fk_propostas_updated_by` (`updated_by`),
  CONSTRAINT `fk_propostas_administradora` FOREIGN KEY (`administradora_id`) REFERENCES `administradoras` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_propostas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_propostas_forma_envio` FOREIGN KEY (`send_method_id`) REFERENCES `formas_envio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_propostas_servico` FOREIGN KEY (`service_id`) REFERENCES `servicos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_propostas_status_retorno` FOREIGN KEY (`response_status_id`) REFERENCES `status_retorno` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_propostas_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal_documents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposta_id` bigint UNSIGNED NOT NULL,
  `template_id` bigint UNSIGNED NOT NULL,
  `document_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposal_kind` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attention_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attention_role` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intro_context` text COLLATE utf8mb4_unicode_ci,
  `scope_intro` text COLLATE utf8mb4_unicode_ci,
  `closing_message` text COLLATE utf8mb4_unicode_ci,
  `validity_days` int UNSIGNED NOT NULL DEFAULT '30',
  `show_institutional` tinyint(1) NOT NULL DEFAULT '1',
  `show_services` tinyint(1) NOT NULL DEFAULT '1',
  `show_extra_services` tinyint(1) NOT NULL DEFAULT '1',
  `show_contacts_page` tinyint(1) NOT NULL DEFAULT '1',
  `cover_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proposal_documents_proposta` (`proposta_id`),
  KEY `idx_proposal_documents_template` (`template_id`),
  KEY `fk_proposal_documents_created_by` (`created_by`),
  KEY `fk_proposal_documents_updated_by` (`updated_by`),
  CONSTRAINT `fk_proposal_documents_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_proposal_documents_proposta` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_proposal_documents_template` FOREIGN KEY (`template_id`) REFERENCES `proposal_templates` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_proposal_documents_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal_document_assets` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_document_id` bigint UNSIGNED NOT NULL,
  `asset_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proposal_document_assets_document` (`proposal_document_id`),
  CONSTRAINT `fk_proposal_document_assets_document` FOREIGN KEY (`proposal_document_id`) REFERENCES `proposal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal_document_options` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_document_id` bigint UNSIGNED NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_html` longtext COLLATE utf8mb4_unicode_ci,
  `fee_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_value` decimal(12,2) DEFAULT NULL,
  `amount_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms` text COLLATE utf8mb4_unicode_ci,
  `is_recommended` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proposal_document_options_document` (`proposal_document_id`),
  CONSTRAINT `fk_proposal_document_options_document` FOREIGN KEY (`proposal_document_id`) REFERENCES `proposal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposta_attachments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposta_id` bigint UNSIGNED NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `relative_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/pdf',
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `uploaded_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proposta_attachments_proposta` (`proposta_id`),
  KEY `idx_proposta_attachments_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_proposta_attachments_proposta` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_proposta_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposta_history` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposta_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proposta_history_proposta` (`proposta_id`),
  KEY `idx_proposta_history_action` (`action`),
  KEY `fk_proposta_history_user` (`user_id`),
  CONSTRAINT `fk_proposta_history_proposta` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_proposta_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_dispatches` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposta_id` bigint UNSIGNED NOT NULL,
  `alert_type` enum('followup','validity') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispatch_date` date NOT NULL,
  `recipient_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispatch_status` enum('sent','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_dispatch_daily` (`proposta_id`,`alert_type`,`dispatch_date`,`recipient_email`),
  KEY `idx_notification_dispatch_date` (`dispatch_date`),
  CONSTRAINT `fk_notification_dispatch_proposta` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_module_permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `module_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_module_permissions_user_module` (`user_id`,`module_id`),
  KEY `idx_user_module_permissions_module` (`module_id`),
  CONSTRAINT `fk_user_module_permissions_module` FOREIGN KEY (`module_id`) REFERENCES `system_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_module_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `related_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_id` int NOT NULL,
  `file_role` enum('documento','contrato','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `relative_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int NOT NULL DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_attachments_related` (`related_type`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_timelines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `related_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_id` int NOT NULL,
  `note` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `user_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_timelines_related` (`related_type`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER `trg_users_protect_delete`
BEFORE DELETE ON `users`
FOR EACH ROW
BEGIN
    IF OLD.is_protected = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este usuário protegido não pode ser excluído.';
    END IF;
END
$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_users_protect_update`
BEFORE UPDATE ON `users`
FOR EACH ROW
BEGIN
    IF OLD.is_protected = 1 AND NEW.role <> 'superadmin' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Usuário protegido não pode perder o perfil de superadmin.';
    END IF;

    IF OLD.is_protected = 1 AND NEW.is_active = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Usuário protegido não pode ser desativado.';
    END IF;
END
$$
DELIMITER ;

INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'app_name', 'Âncora', 'Nome do sistema', NOW(), NOW()),
(2, 'app_company', 'Serratech Soluções em TI', 'Nome da empresa exibido no sistema', NOW(), NOW()),
(3, 'company_address', '', 'Endereço exibido no rodapé e PDF', NOW(), NOW()),
(4, 'company_phone', '', 'Telefone exibido no rodapé e PDF', NOW(), NOW()),
(5, 'company_email', 'junior@serratech.br', 'E-mail exibido no rodapé e PDF', NOW(), NOW()),
(6, 'branding_logo_light_path', '/imgs/logomarca.svg', 'Logo usada no tema claro', NOW(), NOW()),
(7, 'branding_logo_dark_path', '/imgs/logomarca.svg', 'Logo usada no tema escuro', NOW(), NOW()),
(8, 'branding_logo_height_desktop', '44', 'Altura da logo no header desktop', NOW(), NOW()),
(9, 'branding_logo_height_mobile', '36', 'Altura da logo no header mobile', NOW(), NOW()),
(10, 'branding_logo_height_login', '82', 'Altura da logo na tela de login', NOW(), NOW()),
(11, 'branding_favicon_path', '/favicon.svg', 'Caminho público do favicon do sistema', NOW(), NOW()),
(12, 'branding_premium_logo_variant', 'light', 'Logo escolhida para o preview/PDF premium', NOW(), NOW());

INSERT INTO `formas_envio` (`id`, `name`, `icon_class`, `color_hex`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'WhatsApp', 'fa-brands fa-whatsapp', '#25D366', 1, 1, NOW(), NOW()),
(2, 'E-mail', 'fa-solid fa-envelope', '#2563EB', 1, 2, NOW(), NOW()),
(3, 'Telefone', 'fa-solid fa-phone', '#7C3AED', 1, 3, NOW(), NOW()),
(4, 'Presencial', 'fa-solid fa-handshake', '#D97706', 1, 4, NOW(), NOW());

INSERT INTO `proposal_templates` (`id`, `slug`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'aquarela', 'Aquarela Premium', 'Template premium padrão do módulo de propostas.', 1, NOW(), NOW());

INSERT INTO `servicos` (`id`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Assessoria Condominial', 'Atuação consultiva e preventiva para condomínios.', 1, 1, NOW(), NOW()),
(2, 'Cobrança Condominial', 'Cobrança extrajudicial e judicial de inadimplência.', 1, 2, NOW(), NOW()),
(3, 'Regularização Documental', 'Adequação de convenção, regimento e documentos correlatos.', 1, 3, NOW(), NOW());

INSERT INTO `status_retorno` (`id`, `system_key`, `name`, `color_hex`, `requires_closed_value`, `requires_refusal_reason`, `stop_followup_alert`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'pendente', 'Pendente', '#F59E0B', 0, 0, 0, 1, 1, NOW(), NOW()),
(2, 'em_negociacao', 'Em negociação', '#3B82F6', 0, 0, 0, 1, 2, NOW(), NOW()),
(3, 'fechada', 'Fechada', '#10B981', 1, 0, 1, 1, 3, NOW(), NOW()),
(4, 'recusada', 'Recusada', '#EF4444', 0, 1, 1, 1, 4, NOW(), NOW());

INSERT INTO `system_modules` (`id`, `slug`, `name`, `icon_class`, `route_prefix`, `is_enabled`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'dashboard', 'Dashboard Executivo', 'fa-solid fa-chart-line', '/dashboard', 0, 1, NOW(), NOW()),
(2, 'propostas', 'Propostas', 'fa-solid fa-file-signature', '/propostas', 1, 2, NOW(), NOW()),
(3, 'busca', 'Busca', 'fa-solid fa-magnifying-glass', '/busca', 1, 3, NOW(), NOW()),
(4, 'clientes', 'Clientes', 'fa-solid fa-users', '/clientes', 1, 14, NOW(), NOW()),
(5, 'config', 'Configurações', 'fa-solid fa-gear', '/config', 1, 98, NOW(), NOW()),
(6, 'logs', 'Logs e Auditoria', 'fa-solid fa-clock-rotate-left', '/logs', 1, 99, NOW(), NOW());

-- Login inicial: junior@serratech.br / Ancora@123
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `theme_preference`, `is_active`, `is_protected`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Administrador Âncora', 'junior@serratech.br', '$2y$12$iweYdm23ZBJNRKPymsPBQODueoEZVkNM.jU0C8zwQFLSRq0h6ho0G', 'superadmin', 'dark', 1, 1, NULL, NOW(), NOW());

INSERT INTO `user_module_permissions` (`user_id`, `module_id`, `created_at`) VALUES
(1, 1, NOW()),
(1, 2, NOW()),
(1, 3, NOW()),
(1, 4, NOW()),
(1, 5, NOW()),
(1, 6, NOW());

INSERT INTO `client_types` (`scope`, `name`, `is_active`, `sort_order`) VALUES
('condominium', 'Residencial', 1, 1),
('condominium', 'Comercial', 1, 2),
('condominium', 'Misto', 1, 3),
('unit', 'Apartamento', 1, 1),
('unit', 'Sala', 1, 2),
('unit', 'Loja', 1, 3);

INSERT INTO `client_entities` (
    `entity_type`,
    `profile_scope`,
    `role_tag`,
    `display_name`,
    `legal_name`,
    `cpf_cnpj`,
    `phones_json`,
    `emails_json`,
    `notes`,
    `is_active`,
    `created_by`,
    `updated_by`
)
SELECT
    'pf' AS `entity_type`,
    'contato' AS `profile_scope`,
    CASE
        WHEN a.`type` = 'sindico' THEN 'sindico'
        ELSE 'administradora'
    END AS `role_tag`,
    a.`name` AS `display_name`,
    a.`name` AS `legal_name`,
    NULL AS `cpf_cnpj`,
    JSON_ARRAY(JSON_OBJECT('label','Principal','number',COALESCE(a.`phone`,''))) AS `phones_json`,
    JSON_ARRAY(JSON_OBJECT('label','Principal','email',COALESCE(a.`email`,''))) AS `emails_json`,
    a.`contact_name` AS `notes`,
    1,
    1,
    1
FROM `administradoras` a;

COMMIT;
