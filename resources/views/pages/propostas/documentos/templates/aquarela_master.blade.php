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
$contactPhoneIcon = !empty($branding['company_phone_is_whatsapp']) ? 'whatsapp' : 'phone';
$contactSocialEntries = array_values(array_filter([
    ['label' => 'Instagram', 'icon' => 'instagram', 'value' => trim((string) ($branding['company_social_instagram'] ?? ''))],
    ['label' => 'LinkedIn', 'icon' => 'linkedin', 'value' => trim((string) ($branding['company_social_linkedin'] ?? ''))],
    ['label' => 'YouTube', 'icon' => 'youtube', 'value' => trim((string) ($branding['company_social_youtube'] ?? ''))],
    ['label' => 'Facebook', 'icon' => 'facebook', 'value' => trim((string) ($branding['company_social_facebook'] ?? ''))],
    ['label' => 'Canal do WhatsApp', 'icon' => 'whatsapp', 'value' => trim((string) ($branding['company_social_whatsapp_channel'] ?? ''))],
    ['label' => 'TikTok', 'icon' => 'tiktok', 'value' => trim((string) ($branding['company_social_tiktok'] ?? ''))],
    ['label' => 'Linktree', 'icon' => 'linktree', 'value' => trim((string) ($branding['company_social_linktree'] ?? ''))],
], static fn (array $entry): bool => $entry['value'] !== ''));

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
        'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.36 8.2a1.56 1.56 0 1 1 0-3.12 1.56 1.56 0 0 1 0 3.12ZM5.05 9.55h2.63V18H5.05V9.55Zm4.27 0h2.52v1.15h.03c.35-.67 1.21-1.38 2.49-1.38 2.66 0 3.15 1.75 3.15 4.02V18h-2.63v-4.14c0-.99-.02-2.26-1.38-2.26-1.38 0-1.59 1.08-1.59 2.19V18H9.32V9.55Z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.54 7.3a2.6 2.6 0 0 0-1.83-1.84C17.1 5 12 5 12 5s-5.1 0-6.71.46A2.6 2.6 0 0 0 3.46 7.3 27.5 27.5 0 0 0 3 12a27.5 27.5 0 0 0 .46 4.7 2.6 2.6 0 0 0 1.83 1.84C6.9 19 12 19 12 19s5.1 0 6.71-.46a2.6 2.6 0 0 0 1.83-1.84A27.5 27.5 0 0 0 21 12a27.5 27.5 0 0 0-.46-4.7ZM10.4 15.06V8.94L15.6 12l-5.2 3.06Z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.35 20v-6.42h2.15l.32-2.5h-2.47V9.49c0-.72.2-1.22 1.23-1.22H16V6.03c-.23-.03-1.02-.1-1.94-.1-1.92 0-3.24 1.17-3.24 3.32v1.83H8.64v2.5h2.18V20h2.53Z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.74 3c.24 2.02 1.39 3.46 3.26 3.7v2.27a5.55 5.55 0 0 1-3.16-1.05v5.34c0 2.66-2.03 4.74-4.79 4.74A4.66 4.66 0 0 1 5.3 13.3a4.72 4.72 0 0 1 5.39-4.6v2.36a2.38 2.38 0 0 0-.65-.09 2.3 2.3 0 0 0-2.35 2.33 2.32 2.32 0 0 0 2.43 2.33 2.36 2.36 0 0 0 2.32-2.55V3h2.3Z"/></svg>',
        'linktree' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11.1 3h1.8v4.05l2.86-2.86 1.27 1.27L14.17 8.3h4.05v1.8h-4.05l2.86 2.84-1.27 1.29-2.86-2.86V15.4h-1.8v-4.03l-2.84 2.86-1.29-1.29 2.86-2.84H5.8V8.3h4.05L6.99 5.46l1.29-1.27 2.84 2.86V3Zm.9 14.8a2.2 2.2 0 1 1 0 4.4 2.2 2.2 0 0 1 0-4.4Z"/></svg>',
        'medal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.4 2h3.1l1.5 3.08L13.5 2h3.1l-2.1 4.3a6.1 6.1 0 1 1-5 0L7.4 2Zm4.6 6.2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm0 1.68 1 2.02 2.23.32-1.61 1.57.38 2.22L12 14.95l-1.99 1.06.38-2.22-1.61-1.57 2.23-.32 1-2.02Z"/></svg>',
        'dot' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="7.5"/></svg>',
        'social' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.2 6.2a3.2 3.2 0 1 1 5.3 2.43l-5.06 2.95a3.22 3.22 0 0 1 0 .8l5.06 2.95A3.2 3.2 0 1 1 19.73 17l-5.07-2.96a3.2 3.2 0 1 1 0-4.08l5.07-2.96A3.19 3.19 0 0 1 15.2 6.2Zm-8.4 4.2a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2Zm11.6-5.8a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2Zm0 11.6a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2Z"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 2.9c.32-.08.66-.02.93.16l2.26 1.5c.43.28.62.82.47 1.31l-.68 2.16a1.2 1.2 0 0 0 .28 1.18l4.91 4.91a1.2 1.2 0 0 0 1.18.28l2.16-.68c.49-.15 1.03.04 1.31.47l1.5 2.26c.18.27.24.61.16.93-.34 1.39-1.56 2.39-3 2.39-8.3 0-15.03-6.73-15.03-15.03 0-1.44 1-2.66 2.39-3Zm.4 1.55c-.7.17-1.22.79-1.22 1.56 0 7.41 6.02 13.43 13.43 13.43.77 0 1.39-.52 1.56-1.22l-1.16-1.75-1.66.52a2.8 2.8 0 0 1-2.76-.66L10.3 11.4a2.8 2.8 0 0 1-.66-2.76l.52-1.66-1.75-1.16Z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2a8.8 8.8 0 0 1 7.62 13.2L21 20.8l-4.52-1.18A8.8 8.8 0 1 1 12 3.2Zm0 1.6a7.2 7.2 0 0 0-6.17 10.92l.24.39-.83 3.01 3.08-.8.38.22A7.2 7.2 0 1 0 12 4.8Zm4.09 9.2c-.22-.11-1.32-.65-1.52-.72-.2-.08-.35-.11-.5.11-.15.22-.57.72-.7.87-.13.15-.26.17-.48.06a5.9 5.9 0 0 1-1.73-1.06 6.55 6.55 0 0 1-1.2-1.49c-.13-.22-.01-.34.1-.45.1-.1.22-.26.33-.39.11-.13.14-.22.22-.37.07-.15.04-.28-.02-.39-.06-.11-.5-1.2-.69-1.65-.18-.43-.37-.37-.5-.38h-.43c-.15 0-.39.06-.59.28-.2.22-.78.76-.78 1.85 0 1.1.8 2.16.91 2.31.11.15 1.58 2.42 3.84 3.39.54.23.96.37 1.28.47.54.17 1.02.15 1.4.09.43-.06 1.32-.54 1.51-1.06.18-.52.18-.97.13-1.06-.06-.09-.2-.15-.43-.26Z"/></svg>',
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
                        <div class="vb-contact-item__value vb-contact-item__value--email"><?= htmlspecialchars($branding['company_email']); ?></div>
                    </div>
                </div>

                <div class="vb-contact-item">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon('social'); ?></span>
                    <div>
                        <div class="vb-contact-item__label">Redes sociais</div>
                        <div class="vb-contact-item__value vb-contact-item__value--compact">
                            <?php if (!empty($contactSocialEntries)): ?>
                                <div class="vb-contact-social-list">
                                    <?php foreach ($contactSocialEntries as $socialEntry): ?>
                                        <div class="vb-contact-social-line">
                                            <span class="vb-contact-social-line__icon"><?= premium_contact_icon((string) ($socialEntry['icon'] ?? 'social')); ?></span>
                                            <span class="vb-contact-social-line__label"><?= htmlspecialchars($socialEntry['label']); ?></span>
                                            <span class="vb-contact-social-line__value"><?= htmlspecialchars($socialEntry['value']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div>—</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="vb-contact-item">
                    <span class="vb-contact-item__icon vb-contact-item__icon--svg"><?= premium_contact_icon($contactPhoneIcon); ?></span>
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
                    <strong>Vamos <span class="vb-contact-bubble__emphasis">juntos</span> transformar o futuro<br>do seu <span class="vb-contact-bubble__emphasis">condomínio?</span></strong>
                    <span>Quando podemos agendar uma conversa pessoal?</span>
                </div>

                <div class="vb-contact-social-card">
                    <div class="vb-contact-social-card__title"><?= htmlspecialchars($branding['company_name'] ?: 'Rebeca Medina Soluções Jurídicas'); ?></div>
                    <ul class="vb-contact-highlight-list">
                        <li>
                            <span class="vb-contact-highlight-list__icon vb-contact-highlight-list__icon--medal"><?= premium_contact_icon('medal'); ?></span>
                            <span>+10 anos de atuação no mercado condominial</span>
                        </li>
                        <li>
                            <span class="vb-contact-highlight-list__icon vb-contact-highlight-list__icon--dot"><?= premium_contact_icon('dot'); ?></span>
                            <span>Especialistas em Cobranças, Assembleias e Regularização de Documentos.</span>
                        </li>
                        <li>
                            <span class="vb-contact-highlight-list__icon vb-contact-highlight-list__icon--dot"><?= premium_contact_icon('dot'); ?></span>
                            <span>+ 100 Condomínios atendidos.</span>
                        </li>
                    </ul>
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