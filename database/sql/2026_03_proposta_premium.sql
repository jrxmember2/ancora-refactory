CREATE TABLE proposal_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proposal_templates_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposal_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposta_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    proposal_kind VARCHAR(180) NULL,
    client_display_name VARCHAR(255) NOT NULL,
    attention_to VARCHAR(255) NULL,
    attention_role VARCHAR(180) NULL,
    cover_subtitle VARCHAR(255) NULL,
    intro_context TEXT NULL,
    scope_intro TEXT NULL,
    closing_message TEXT NULL,
    validity_days INT UNSIGNED NOT NULL DEFAULT 30,
    show_institutional TINYINT(1) NOT NULL DEFAULT 1,
    show_services TINYINT(1) NOT NULL DEFAULT 1,
    show_extra_services TINYINT(1) NOT NULL DEFAULT 1,
    show_contacts_page TINYINT(1) NOT NULL DEFAULT 1,
    cover_image_path VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proposal_documents_proposta (proposta_id),
    KEY idx_proposal_documents_template (template_id),
    CONSTRAINT fk_proposal_documents_proposta
        FOREIGN KEY (proposta_id) REFERENCES propostas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_proposal_documents_template
        FOREIGN KEY (template_id) REFERENCES proposal_templates(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proposal_documents_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proposal_documents_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposal_document_options (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_document_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    scope_title VARCHAR(255) NULL,
    scope_html LONGTEXT NULL,
    fee_label VARCHAR(255) NULL,
    amount_value DECIMAL(12,2) NULL,
    amount_text VARCHAR(255) NULL,
    payment_terms TEXT NULL,
    is_recommended TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_proposal_document_options_document (proposal_document_id),
    CONSTRAINT fk_proposal_document_options_document
        FOREIGN KEY (proposal_document_id) REFERENCES proposal_documents(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposal_document_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_document_id BIGINT UNSIGNED NOT NULL,
    asset_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_proposal_document_assets_document (proposal_document_id),
    CONSTRAINT fk_proposal_document_assets_document
        FOREIGN KEY (proposal_document_id) REFERENCES proposal_documents(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO proposal_templates (slug, name, description, is_active)
VALUES (
    'aquarela-premium',
    'Aquarela Premium',
    'Template base da proposta premium inspirado no modelo 007.2026.',
    1
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
is_active = VALUES(is_active);