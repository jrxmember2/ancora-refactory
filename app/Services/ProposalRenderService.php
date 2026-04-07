<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalDocument;
use App\Models\ProposalTemplate;
use App\Support\AncoraSettings;

class ProposalRenderService
{
    private static function publicUrl(?string $path, string $fallback = ''): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $fallback;
        }
        if (preg_match('~^(https?:)?//~', $path)) {
            return $path;
        }
        return asset(ltrim($path, '/'));
    }

    public static function buildByPropostaId(int $propostaId): ?array
    {
        $proposta = Proposal::query()->find($propostaId);
        if (!$proposta) {
            return null;
        }

        $document = ProposalDocument::query()->where('proposta_id', $propostaId)->with('options')->first();
        if (!$document) {
            return null;
        }

        $template = ProposalTemplate::query()->find((int) $document->template_id);
        $options = $document->options()->orderBy('sort_order')->get()->toArray();

        $brandingLogoDark = AncoraSettings::get('branding_logo_dark_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $brandingLogoLight = AncoraSettings::get('branding_logo_light_path', '/imgs/logomarca.svg') ?: '/imgs/logomarca.svg';
        $premiumLogoVariant = AncoraSettings::get('branding_premium_logo_variant', 'light') ?: 'light';
        $premiumLogoVariant = $premiumLogoVariant === 'dark' ? 'dark' : 'light';

        $coverImageUrl = self::publicUrl($document->cover_image_path ?? '', '');
        $rebecaImageUrl = asset('assets/imgs/templates/rebeca-premium.png');
        $updatedAt = $document->updated_at ?? $document->created_at ?? $proposta->updated_at ?? $proposta->created_at ?? now();

        return [
            'branding' => [
                'company_name' => AncoraSettings::get('app_company', 'Âncora') ?: 'Âncora',
                'company_address' => AncoraSettings::get('company_address', '') ?: '',
                'company_phone' => AncoraSettings::get('company_phone', '') ?: '',
                'company_email' => AncoraSettings::get('company_email', '') ?: '',
                'company_website' => AncoraSettings::get('company_website', config('app.url')) ?: (config('app.url') ?: ''),
                'company_social_primary' => AncoraSettings::get('company_social_primary', '') ?: '',
                'company_social_secondary' => AncoraSettings::get('company_social_secondary', '') ?: '',
                'logo_light' => self::publicUrl($brandingLogoLight, asset('imgs/logomarca.svg')),
                'logo_dark' => self::publicUrl($brandingLogoDark, asset('imgs/logomarca.svg')),
                'premium_logo_variant' => $premiumLogoVariant,
                'logo_premium' => self::publicUrl($premiumLogoVariant === 'dark' ? $brandingLogoDark : $brandingLogoLight, asset('imgs/logomarca.svg')),
            ],
            'assets' => [
                'cover_image_url' => $coverImageUrl,
                'rebeca_image_url' => $rebecaImageUrl,
            ],
            'meta' => [
                'updated_at' => $updatedAt,
                'updated_at_br' => $updatedAt ? date('d/m/Y', strtotime((string) $updatedAt)) : now()->format('d/m/Y'),
            ],
            'proposta' => $proposta->toArray(),
            'document' => $document->toArray(),
            'template' => $template?->toArray(),
            'options' => $options,
        ];
    }
}
