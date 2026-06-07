<?php

namespace App\Services;

class WebsiteClassifier
{
    private const SOCIAL_DOMAINS = [
        'facebook.com' => 'facebook_url',
        'fb.com' => 'facebook_url',
        'instagram.com' => 'instagram_url',
        'linkedin.com' => 'linkedin_url',
        'twitter.com' => 'social_url',
        'x.com' => 'social_url',
        'tiktok.com' => 'social_url',
        'youtube.com' => 'social_url',
        'youtu.be' => 'social_url',
        'wa.me' => 'social_url',
        'whatsapp.com' => 'social_url',
    ];

    public function classify(?string $url): array
    {
        $url = $this->normalize($url);
        if (! $url) {
            return [
                'website' => null,
                'has_website' => false,
                'website_status' => 'No Website',
                'social' => [],
            ];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        foreach (self::SOCIAL_DOMAINS as $domain => $field) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [
                    'website' => null,
                    'has_website' => false,
                    'website_status' => 'Social Profile Only',
                    'social' => $field === 'social_url' ? [] : [$field => $url],
                ];
            }
        }

        return [
            'website' => $url,
            'has_website' => true,
            'website_status' => 'Has Website',
            'social' => [],
        ];
    }

    private function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }
}
