CREATE TABLE IF NOT EXISTS `cobranca_agreement_terms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cobranca_case_id` bigint unsigned NOT NULL,
  `template_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'extrajudicial',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json DEFAULT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobranca_agreement_terms_case` (`cobranca_case_id`),
  KEY `idx_cobranca_agreement_terms_generated_by` (`generated_by`),
  KEY `idx_cobranca_agreement_terms_updated_by` (`updated_by`),
  CONSTRAINT `fk_cobranca_agreement_terms_case`
    FOREIGN KEY (`cobranca_case_id`) REFERENCES `cobranca_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_agreement_terms_generated_by`
    FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobranca_agreement_terms_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.agreement.edit', 'Editar termo de acordo da OS', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.agreement.edit');

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.agreement.save', 'Salvar customização do termo de acordo', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.agreement.save');

INSERT INTO `route_permissions` (`group_key`, `route_name`, `label`, `created_at`)
SELECT 'cobrancas', 'cobrancas.agreement.pdf', 'Gerar PDF do termo de acordo', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `route_permissions` WHERE `route_name` = 'cobrancas.agreement.pdf');
