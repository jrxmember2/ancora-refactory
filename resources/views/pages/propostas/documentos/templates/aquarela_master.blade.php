<?php
$templateContent = config('proposal_template_content');

$branding = $renderData['branding'];
$assets = $renderData['assets'];
$meta = $renderData['meta'];
$proposta = $renderData['proposta'];
$document = $renderData['document'];
$options = $renderData['options'];

$logoUrl = $branding['logo_premium'] ?? ($branding['logo_light'] ?? asset('imgs/logomarca.svg'));
$coverImageUrl = $assets['cover_image_url'] ?? '';
$rebecaImageUrl = $assets['rebeca_image_url'] ?? asset('assets/imgs/templates/rebeca-premium.png');
$updatedAtBr = $meta['updated_at_br'] ?? date('d/m/Y');

$whoWeAre = $templateContent['who_we_are'] ?? [];
$services = $templateContent['services'] ?? [];
$contactsCta = $templateContent['contacts_cta'] ?? [];
$contactPhone = $branding['company_phone'] ?? '';
$contactSocialLines = array_values(array_filter([
    trim((string) ($branding['company_social_primary'] ?? '')),
    trim((string) ($branding['company_social_secondary'] ?? '')),
]));

$investmentPageLogoUrl = $branding['logo_light'] ?? asset('imgs/logomarca.svg');
$whoPageLogoUrl = $branding['logo_dark'] ?? asset('imgs/logomarca.svg');
$contactPageLogoUrl = $branding['logo_dark'] ?? asset('imgs/logomarca.svg');

function premium_lines(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $lines = preg_split('/\R+/', $text) ?: [];
    $result = [];

    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^[\-\•\▪\◼\*]+\s*/u', '', $line);
        $line = rtrim($line, " \t\n\r\0\x0B;");
        if ($line !== '') {
            $result[] = $line;
        }
    }

    return $result;
}

function premium_multiline(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $lines = preg_split('/\R+/', $text) ?: [];
    return array_values(array_filter(array_map(static fn ($line) => trim((string) $line), $lines)));
}

function premium_clamp_text(string $text, int $limit = 360): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function premium_prepare_investment_options(array $options): array
{
    $featuredIndex = null;

    foreach ($options as $index => $option) {
        if (!empty($option['is_recommended'])) {
            $featuredIndex = $index;
            break;
        }
    }

    $ordered = [];

    if ($featuredIndex !== null && isset($options[$featuredIndex])) {
        $featured = $options[$featuredIndex];
        $featured['_is_featured'] = true;
        $ordered[] = $featured;
    }

    foreach ($options as $index => $option) {
        if ($index === $featuredIndex) {
            continue;
        }

        $option['_is_featured'] = false;
        $ordered[] = $option;
    }

    foreach ($ordered as $index => &$option) {
        $planNumber = $index + 1;
        $scopeText = trim((string) ($option['scope_html'] ?? ''));

        $option['_plan_number'] = $planNumber;
        $option['_plan_label'] = sprintf('Plano %02d', $planNumber);
        $option['_is_plan_one'] = $planNumber === 1;
        $option['_has_scope'] = $scopeText !== '';
        $option['_safe_scope_text'] = premium_clamp_text($scopeText, 360);
    }
    unset($option);

    return $ordered;
}

function premium_render_topbar(string $website, string $theme = 'dark'): void
{
    $themeClass = $theme === 'light' ? 'vb-topbar--light' : 'vb-topbar--dark';
    ?>
    <div class="vb-topbar <?= $themeClass; ?>">
        <span class="vb-topbar__arrow" aria-hidden="true">→</span>
        <span class="vb-topbar__pill"><?= htmlspecialchars($website); ?></span>
        <span class="vb-topbar__arrow" aria-hidden="true">→</span>
    </div>
    <?php
}

function premium_render_page_number(int $number, string $theme = 'red'): void
{
    $themeClass = $theme === 'light' ? 'vb-page-number--light' : 'vb-page-number--red';
    ?>
    <div class="vb-page-number <?= $themeClass; ?>">
        <span><?= (int) $number; ?></span>
    </div>
    <?php
}

function premium_contact_icon(string $type): string
{
    $icons = [
        'email' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.75A1.75 1.75 0 0 1 4.75 5h14.5A1.75 1.75 0 0 1 21 6.75v10.5A1.75 1.75 0 0 1 19.25 19H4.75A1.75 1.75 0 0 1 3 17.25V6.75Zm1.6.2 7.18 5.52a.4.4 0 0 0 .44 0l7.18-5.52a.35.35 0 0 0-.15-.06H4.75a.35.35 0 0 0-.15.06Zm14.8 1.67-6.27 4.82a1.9 1.9 0 0 1-2.32 0L4.6 8.62v8.63c0 .08.07.15.15.15h14.5c.08 0 .15-.07.15-.15V8.62Z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.25 3h9.5A4.25 4.25 0 0 1 21 7.25v9.5A4.25 4.25 0 0 1 16.75 21h-9.5A4.25 4.25 0 0 1 3 16.75v-9.5A4.25 4.25 0 0 1 7.25 3Zm0 1.6A2.65 2.65 0 0 0 4.6 7.25v9.5a2.65 2.65 0 0 0 2.65 2.65h9.5a2.65 2.65 0 0 0 2.65-2.65v-9.5a2.65 2.65 0 0 0-2.65-2.65h-9.5Zm10.15 1.2a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1ZM12 7.3A4.7 4.7 0 1 1 7.3 12 4.7 4.7 0 0 1 12 7.3Zm0 1.6A3.1 3.1 0 1 0 15.1 12 3.1 3.1 0 0 0 12 8.9Z"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 2.9c.32-.08.66-.02.93.16l2.26 1.5c.43.28.62.82.47 1.31l-.68 2.16a1.2 1.2 0 0 0 .28 1.18l4.91 4.91a1.2 1.2 0 0 0 1.18.28l2.16-.68c.49-.15 1.03.04 1.31.47l1.5 2.26c.18.27.24.61.16.93-.34 1.39-1.56 2.39-3 2.39-8.3 0-15.03-6.73-15.03-15.03 0-1.44 1-2.66 2.39-3Zm.4 1.55c-.7.17-1.22.79-1.22 1.56 0 7.41 6.02 13.43 13.43 13.43.77 0 1.39-.52 1.56-1.22l-1.16-1.75-1.66.52a2.8 2.8 0 0 1-2.76-.66L10.3 11.4a2.8 2.8 0 0 1-.66-2.76l.52-1.66-1.75-1.16Z"/></svg>',
        'site' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 4h14.5A1.75 1.75 0 0 1 21 5.75v12.5A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V5.75A1.75 1.75 0 0 1 4.75 4Zm0 1.6a.15.15 0 0 0-.15.15v1.5h14.8v-1.5a.15.15 0 0 0-.15-.15H4.75Zm-.15 3.25v9.4c0 .08.07.15.15.15h14.5c.08 0 .15-.07.15-.15v-9.4H4.6Zm2.3 1.5h4.9v1.4H6.9v-1.4Zm0 2.7h7.7v1.4H6.9v-1.4Z"/></svg>',
    ];

    return $icons[$type] ?? '';
}

function premium_scope_line_weight(string $line): int
{
    $length = mb_strlen(trim($line));

    if ($length <= 70) {
        return 1;
    }

    if ($length <= 130) {
        return 2;
    }

    if ($length <= 190) {
        return 3;
    }

    return 4;
}

function premium_paginate_scope(array $lines, int $budget, int $minTailWeight = 4): array
{
    if (empty($lines)) {
        return [];
    }

    $pages = [];
    $currentPage = [];
    $currentWeight = 0;

    foreach ($lines as $line) {
        $weight = premium_scope_line_weight((string) $line);

        if (!empty($currentPage) && ($currentWeight + $weight) > $budget) {
            $pages[] = $currentPage;
            $currentPage = [];
            $currentWeight = 0;
        }

        $currentPage[] = $line;
        $currentWeight += $weight;
    }

    if (!empty($currentPage)) {
        $pages[] = $currentPage;
    }

    if (count($pages) > 1) {
        $lastIndex = count($pages) - 1;
        $lastPage = $pages[$lastIndex];

        $lastWeight = 0;
        foreach ($lastPage as $line) {
            $lastWeight += premium_scope_line_weight((string) $line);
        }

        if ($lastWeight <= $minTailWeight) {
            $pages[$lastIndex - 1] = array_merge($pages[$lastIndex - 1], $lastPage);
            array_pop($pages);
        }
    }

    return $pages;
}

$scopeLines = premium_lines((string) ($document['scope_intro'] ?? ''));
$scopeClosingLines = premium_multiline((string) ($document['closing_message'] ?? ''));
$preparedInvestmentOptions = premium_prepare_investment_options($options);

$investmentScopedCount = count(array_filter(
    $preparedInvestmentOptions,
    static fn (array $option): bool => !empty($option['_has_scope'])
));

$investmentPerPage = $investmentScopedCount >= 2 ? 2 : 3;
$investmentPages = array_chunk($preparedInvestmentOptions, $investmentPerPage);

$scopePages = premium_paginate_scope($scopeLines, 17, 4);
$scopeFirstPageLines = $scopePages[0] ?? [];
$scopeExtraPages = array_slice($scopePages, 1);

$pageCounter = 1;
?>

<div class="proposal-page vb-cover-page">
    <div class="vb-cover-bg"></div>

    <div class="vb-cover-brand">
        <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
    </div>

    <?php premium_render_topbar((string) $branding['company_website'], 'dark'); ?>

    <div class="vb-cover-visual">
        <?php if ($coverImageUrl !== ''): ?>
            <div class="vb-cover-building" style="background-image: url('<?= htmlspecialchars($coverImageUrl); ?>');"></div>
        <?php else: ?>
            <div class="vb-cover-building vb-cover-building--placeholder"></div>
        <?php endif; ?>
    </div>

    <div class="vb-cover-copy">
        <?php if (!empty($document['proposal_kind'])): ?>
            <span class="vb-cover-kicker"><?= htmlspecialchars($document['proposal_kind']); ?></span>
        <?php endif; ?>

        <h1><?= nl2br(htmlspecialchars($document['document_title'])); ?></h1>

        <?php if (!empty($document['cover_subtitle'])): ?>
            <p class="vb-cover-subtitle"><?= htmlspecialchars($document['cover_subtitle']); ?></p>
        <?php endif; ?>
    </div>

    <div class="vb-cover-client-card">
        <div class="vb-cover-client-card__title"><?= htmlspecialchars($document['client_display_name']); ?></div>

        <?php if (!empty($document['attention_to'])): ?>
            <div class="vb-cover-client-card__line">
                <span class="vb-cover-client-card__arrow" aria-hidden="true">→</span>
                <span>
                    A/C: <?= htmlspecialchars($document['attention_to']); ?>
                    <?php if (!empty($document['attention_role'])): ?>
                        <small>• <?= htmlspecialchars($document['attention_role']); ?></small>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="vb-cover-date"><?= htmlspecialchars($updatedAtBr); ?></div>
</div>

<?php if (!empty($document['show_institutional'])): ?>
    <div class="proposal-page vb-who-page">
        <div class="vb-page-line vb-page-line--light"></div>
        <?php premium_render_topbar((string) $branding['company_website'], 'light'); ?>
        <?php premium_render_page_number($pageCounter++, 'light'); ?>

        <div class="vb-page-title vb-page-title--light"><?= htmlspecialchars($whoWeAre['title'] ?? 'Quem somos'); ?></div>

        <div class="vb-who-brand">
            <img src="<?= htmlspecialchars($whoPageLogoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>

        <div class="vb-who-shell">
            <div class="vb-who-copy-card">
                <?php foreach (($whoWeAre['paragraphs'] ?? []) as $paragraph): ?>
                    <p><?= htmlspecialchars($paragraph); ?></p>
                <?php endforeach; ?>
            </div>

            <div class="vb-who-photo-stage">
                <div class="vb-who-photo-panel"></div>
                <?php if ($rebecaImageUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($rebecaImageUrl); ?>" alt="Dra. Rebeca Medina">
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($document['show_services'])): ?>
    <div class="proposal-page vb-services-page">
        <?php premium_render_topbar((string) $branding['company_website'], 'dark'); ?>
        <?php premium_render_page_number($pageCounter++, 'red'); ?>

        <div class="vb-page-title vb-page-title--red"><?= htmlspecialchars($services['title'] ?? 'Nossos serviços'); ?></div>

        <div class="vb-services-grid <?= count($services['groups'] ?? []) === 4 ? 'vb-services-grid--four' : ''; ?>">
            <?php foreach (($services['groups'] ?? []) as $group): ?>
                <article class="vb-service-card">
                    <header class="vb-service-card__head">
                        <h3><?= htmlspecialchars($group['title'] ?? ''); ?></h3>
                    </header>

                    <?php if (!empty($group['subtitle'])): ?>
                        <p class="vb-service-card__subtitle"><?= htmlspecialchars($group['subtitle']); ?></p>
                    <?php endif; ?>

                    <ul class="vb-service-card__list">
                        <?php foreach (($group['items'] ?? []) as $item): ?>
                            <li><?= htmlspecialchars($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="vb-footer-logo">
            <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>
    </div>
<?php endif; ?>

<?php $hasScopePage = !empty($scopeFirstPageLines) || !empty($scopeClosingLines); ?>
<?php if ($hasScopePage): ?>
    <div class="proposal-page vb-scope-page">
        <div class="vb-page-line vb-page-line--red"></div>
        <?php premium_render_topbar((string) $branding['company_website'], 'dark'); ?>
        <?php premium_render_page_number($pageCounter++, 'red'); ?>

        <div class="vb-scope-shell">
            <div class="vb-scope-title-column">
                <div class="vb-scope-title-block">
                    <span>Escopo do<br>serviço</span>
                </div>

                <?php if (!empty($scopeClosingLines)): ?>
                    <div class="vb-scope-title-note">
                        <?php foreach ($scopeClosingLines as $line): ?>
                            <p><?= htmlspecialchars($line); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vb-scope-content-box">
                <?php if (!empty($scopeFirstPageLines)): ?>
                    <ul class="vb-scope-bullets">
                        <?php foreach ($scopeFirstPageLines as $line): ?>
                            <li><?= htmlspecialchars($line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="vb-footer-logo">
            <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>
    </div>
<?php endif; ?>

<?php foreach ($scopeExtraPages as $pageLines): ?>
    <div class="proposal-page vb-scope-page vb-scope-page--continuation">
        <div class="vb-page-line vb-page-line--red"></div>
        <?php premium_render_topbar((string) $branding['company_website'], 'dark'); ?>
        <?php premium_render_page_number($pageCounter++, 'red'); ?>

        <div class="vb-scope-shell vb-scope-shell--continuation">
            <div class="vb-scope-title-column">
                <div class="vb-scope-title-block vb-scope-title-block--continuation">
                    <span>Continuação...</span>
                </div>
            </div>

            <div class="vb-scope-content-box vb-scope-content-box--continuation">
                <ul class="vb-scope-bullets">
                    <?php foreach ($pageLines as $line): ?>
                        <li><?= htmlspecialchars($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="vb-footer-logo">
            <img src="<?= htmlspecialchars($logoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($investmentPages as $pageIndex => $pageOptions): ?>
    <div class="proposal-page vb-investment-page">
        <?php premium_render_topbar((string) $branding['company_website'], 'dark'); ?>
        <?php premium_render_page_number($pageCounter++, 'red'); ?>

        <div class="vb-investment-top-logo">
            <img src="<?= htmlspecialchars($investmentPageLogoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>

        <div class="vb-page-title vb-page-title--red vb-page-title--sm">Investimento</div>

        <div class="vb-investment-grid">
            <?php foreach ($pageOptions as $option): ?>
                <article class="vb-investment-card<?= !empty($option['_is_featured']) ? ' is-featured' : ''; ?><?= !empty($option['_is_plan_one']) ? ' is-plan-one' : ''; ?>">
                    <div class="vb-investment-card__main">
                        <?php if (!empty($option['_is_featured'])): ?>
                            <div class="vb-investment-badge">
                                <span class="vb-investment-badge__icon" aria-hidden="true">☞</span>
                                <span>Opção recomendada</span>
                            </div>
                        <?php endif; ?>

                        <div class="vb-investment-label"><?= htmlspecialchars($option['_plan_label']); ?></div>

                        <?php if (!empty($option['title'])): ?>
                            <div class="vb-investment-original-title"><?= htmlspecialchars($option['title']); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($option['fee_label'])): ?>
                            <div class="vb-investment-fee-label"><?= htmlspecialchars($option['fee_label']); ?></div>
                        <?php endif; ?>

                        <?php if ($option['amount_value'] !== null && $option['amount_value'] !== ''): ?>
                            <div class="vb-investment-price">R$ <?= htmlspecialchars(number_format((float) $option['amount_value'], 2, ',', '.')); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($option['amount_text'])): ?>
                            <p class="vb-investment-extenso"><?= htmlspecialchars($option['amount_text']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="vb-investment-card__side">
                        <?php if (!empty($option['scope_title'])): ?>
                            <h3 class="vb-investment-side__title"><?= htmlspecialchars($option['scope_title']); ?></h3>
                        <?php endif; ?>

                        <?php if (!empty($option['_safe_scope_text'])): ?>
                            <div class="vb-investment-side__box"><?= nl2br(htmlspecialchars($option['_safe_scope_text'])); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($option['payment_terms'])): ?>
                            <div class="vb-investment-payment-card">
                                <span class="vb-investment-payment-card__label">Forma de pagamento</span>
                                <div><?= nl2br(htmlspecialchars($option['payment_terms'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!empty($document['show_contacts_page'])): ?>
    <div class="proposal-page vb-contact-page">
        <div class="vb-page-line vb-page-line--light"></div>
        <?php premium_render_topbar((string) $branding['company_website'], 'light'); ?>
        <?php premium_render_page_number($pageCounter++, 'light'); ?>

        <div class="vb-page-title vb-page-title--light">Contatos</div>

        <div class="vb-contact-shell">
            <div class="vb-contact-card">
                <div class="vb-contact-item">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon('email'); ?></span>
                    <div>
                        <div class="vb-contact-item__label">Email</div>
                        <div class="vb-contact-item__value"><?= htmlspecialchars($branding['company_email']); ?></div>
                    </div>
                </div>

                <div class="vb-contact-item">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon('instagram'); ?></span>
                    <div>
                        <div class="vb-contact-item__label">Redes sociais</div>
                        <div class="vb-contact-item__value">
                            <?php if (!empty($contactSocialLines)): ?>
                                <?php foreach ($contactSocialLines as $socialLine): ?>
                                    <div><?= htmlspecialchars($socialLine); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div>—</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="vb-contact-item">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon('phone'); ?></span>
                    <div>
                        <div class="vb-contact-item__label">Telefone</div>
                        <div class="vb-contact-item__value"><?= htmlspecialchars($contactPhone); ?></div>
                    </div>
                </div>

                <div class="vb-contact-item vb-contact-item--last">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon('site'); ?></span>
                    <div>
                        <div class="vb-contact-item__label">Site</div>
                        <div class="vb-contact-item__value"><?= htmlspecialchars($branding['company_website']); ?></div>
                    </div>
                </div>
            </div>

            <div class="vb-contact-side">
                <div class="vb-contact-bubble">
                    <strong><?= htmlspecialchars($contactsCta['title'] ?? 'Vamos juntos transformar o futuro do seu condomínio.'); ?></strong>
                    <span><?= htmlspecialchars($contactsCta['message'] ?? 'Quando podemos agendar uma conversa?'); ?></span>
                </div>

                <div class="vb-contact-social-card">
                    <div class="vb-contact-social-card__title"><?= htmlspecialchars(str_replace('@', '', $branding['company_social_primary'] ?: $branding['company_name'])); ?></div>
                    <p>Especialistas em cobranças, assembleias, regularização documental e estratégia jurídica condominial.</p>
                </div>

                <div class="vb-contact-portrait-wrap">
                    <?php if ($rebecaImageUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($rebecaImageUrl); ?>" alt="Dra. Rebeca Medina">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vb-contact-page-logo">
            <img src="<?= htmlspecialchars($contactPageLogoUrl); ?>" alt="<?= htmlspecialchars($branding['company_name']); ?>">
        </div>
    </div>
<?php endif; ?>