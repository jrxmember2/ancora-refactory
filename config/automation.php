<?php

return [
    'flows' => [
        'menu' => true,
        'collection' => true,
        'support' => false,
        'faq' => false,
    ],

    'internal_api' => [
        'token' => env('AUTOMATION_INTERNAL_TOKEN'),
        'token_header' => env('AUTOMATION_INTERNAL_TOKEN_HEADER', 'X-Integration-Token'),
        'allowed_ips' => array_values(array_filter(array_map(
            static fn (string $ip) => trim($ip),
            explode(',', (string) env('AUTOMATION_INTERNAL_ALLOWED_IPS', ''))
        ))),
    ],

    'session' => [
        'timeout_minutes' => (int) env('AUTOMATION_SESSION_TIMEOUT_MINUTES', 30),
        'protocol_prefix' => env('AUTOMATION_PROTOCOL_PREFIX', 'AUT'),
    ],

    'collection' => [
        'search_limit' => (int) env('AUTOMATION_COLLECTION_SEARCH_LIMIT', 10),
        'challenge_option_count' => (int) env('AUTOMATION_COLLECTION_CHALLENGE_OPTION_COUNT', 5),
        'max_attempts' => (int) env('AUTOMATION_COLLECTION_MAX_ATTEMPTS', 3),
        'installments' => [
            'min' => (int) env('AUTOMATION_COLLECTION_INSTALLMENTS_MIN', 2),
            'max' => (int) env('AUTOMATION_COLLECTION_INSTALLMENTS_MAX', 12),
        ],
        'first_due_date' => [
            'days_from_today_limit' => (int) env('AUTOMATION_COLLECTION_FIRST_DUE_MAX_DAYS', 15),
            'month_end_cutoff_days' => (int) env('AUTOMATION_COLLECTION_MONTH_END_CUTOFF_DAYS', 2),
        ],
        'monetary' => [
            'index_code' => env('AUTOMATION_COLLECTION_INDEX_CODE', 'ATM'),
            'interest_type' => env('AUTOMATION_COLLECTION_INTEREST_TYPE', 'legal'),
            'fine_percent' => (float) env('AUTOMATION_COLLECTION_FINE_PERCENT', 2),
        ],
        'attorney_fees' => [
            'extrajudicial_percent' => (float) env('AUTOMATION_COLLECTION_ATTORNEY_FEE_EXTRAJUDICIAL', 10),
            'judicial_percent' => (float) env('AUTOMATION_COLLECTION_ATTORNEY_FEE_JUDICIAL', 20),
        ],
        'boleto_fees' => [
            'enabled' => env('AUTOMATION_COLLECTION_BOLETO_FEES_ENABLED', true),
            'cancellation_enabled' => env('AUTOMATION_COLLECTION_BOLETO_CANCELLATION_FEES_ENABLED', true),
        ],
    ],

    'demand' => [
        'origin' => env('AUTOMATION_DEMAND_ORIGIN', 'automation_whatsapp'),
        'default_status' => env('AUTOMATION_DEMAND_DEFAULT_STATUS', 'aguardando_formalizacao_acordo'),
        'priority' => env('AUTOMATION_DEMAND_PRIORITY', 'normal'),
        'category_slug' => env('AUTOMATION_DEMAND_CATEGORY_SLUG', 'cobranca'),
        'sla_business_hours' => (int) env('AUTOMATION_DEMAND_SLA_BUSINESS_HOURS', 24),
    ],

    'messages' => [
        'menu' => "Olá! Sou a automação do Âncora. Como posso ajudar?\n1 - Atendimento\n2 - Cobrança\n3 - Dúvidas",
        'flow_unavailable' => 'Esse fluxo ainda não está disponível nesta automação. Por favor, escolha outra opção do menu.',
        'choose_condominium' => 'Digite o nome do condomínio.',
        'condominium_not_found' => 'Não encontrei nenhum condomínio com esse nome. Tente digitar novamente com outro trecho do nome.',
        'condominium_confirm' => 'Encontrei o condomínio ":name". Confirma?',
        'choose_block' => 'Informe o bloco ou torre.',
        'block_not_found' => 'Não encontrei esse bloco/torre para o condomínio informado. Tente novamente.',
        'choose_unit' => 'Informe a unidade.',
        'unit_not_found' => 'Não encontrei a unidade informada nesse condomínio. Confira o número e tente novamente.',
        'invalid_selection' => 'Não entendi sua resposta. Envie o número de uma das opções exibidas.',
        'validation_failed' => 'Não foi possível concluir a validação por aqui. Vou encerrar este atendimento e orientar o suporte humano.',
        'confirm_interlocutor' => 'Converso com :name?',
        'capture_interlocutor_name' => 'Com quem eu estou falando?',
        'no_debts' => 'No momento não localizamos débitos em aberto para essa unidade. Se precisar de ajuda adicional, fale com a equipe interna.',
        'offer_agreement' => 'Deseja seguir com uma proposta de acordo?',
        'choose_payment_mode' => "Como você prefere seguir?\n1 - À vista\n2 - Parcelado",
        'choose_installments' => 'Informe a quantidade de parcelas.',
        'choose_first_due_date' => 'Informe a data pretendida para o primeiro pagamento no formato dd/mm/aaaa.',
        'invalid_first_due_date' => 'A data informada ultrapassa o limite permitido. Escolha uma data até :limit_date.',
        'agreement_created' => 'Recebi sua solicitação. Protocolo da sessão: :session_protocol. A demanda interna :demand_protocol foi aberta para formalização do acordo.',
        'human_handover' => 'Por favor, aguarde. Nossa equipe humana dará continuidade ao atendimento.',
    ],

    'fallback_decoy_names' => [
        'Ana Beatriz',
        'Carlos Eduardo',
        'Fernanda Lima',
        'Juliana Souza',
        'Marcos Vinicius',
        'Patricia Alves',
        'Renato Nunes',
        'Vanessa Rocha',
    ],
];
