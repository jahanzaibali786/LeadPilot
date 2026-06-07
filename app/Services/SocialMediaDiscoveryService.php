<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;
use Throwable;

class SocialMediaDiscoveryService
{
    public function discover(?string $website): array
    {
        $socials = ['facebook_url' => null, 'instagram_url' => null, 'linkedin_url' => null];
        if (! $website || ! filter_var($website, FILTER_VALIDATE_URL)) return $socials;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'LeadPilot/1.0 (+public business contact discovery)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->connectTimeout(2)->timeout(4)->get($website);

            if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), 'text/html')) return $socials;

            $document = new DOMDocument();
            @$document->loadHTML($response->body(), LIBXML_NOWARNING | LIBXML_NOERROR);

            foreach ($document->getElementsByTagName('a') as $anchor) {
                $url = $this->normalizeUrl($anchor->getAttribute('href'));
                if (! $url || $this->isShareLink($url)) continue;

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if (! $socials['facebook_url'] && ($host === 'facebook.com' || str_ends_with($host, '.facebook.com'))) $socials['facebook_url'] = $url;
                if (! $socials['instagram_url'] && ($host === 'instagram.com' || str_ends_with($host, '.instagram.com'))) $socials['instagram_url'] = $url;
                if (! $socials['linkedin_url'] && ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com'))) $socials['linkedin_url'] = $url;
            }
        } catch (Throwable) {
            return $socials;
        }

        return $socials;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url));
        if (str_starts_with($url, '//')) $url = 'https:'.$url;
        if (! preg_match('~^https?://~i', $url)) return null;
        if (! filter_var($url, FILTER_VALIDATE_URL)) return null;

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) && str_contains(parse_url($url, PHP_URL_PATH) ?: '', '/authwall')) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
            $redirect = isset($query['sessionRedirect']) ? urldecode((string) $query['sessionRedirect']) : null;
            if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
                $url = $redirect;
            }
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');
        if ($host === '') return null;

        if ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) {
            return $this->canonicalSocialUrl('https://www.linkedin.com'.$path, '~^/(company|in|school|showcase)/[^/?#]+~i');
        }
        if ($host === 'facebook.com' || str_ends_with($host, '.facebook.com')) {
            return $this->canonicalSocialUrl($scheme.'://www.facebook.com'.$path, '~^/[^/?#]+~i');
        }
        if ($host === 'instagram.com' || str_ends_with($host, '.instagram.com')) {
            return $this->canonicalSocialUrl($scheme.'://www.instagram.com'.$path, '~^/[^/?#]+~i');
        }

        return $url;
    }

    private function canonicalSocialUrl(string $url, string $allowedPathPattern): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (! preg_match($allowedPathPattern, $path)) return null;
        return $url;
    }

    private function isShareLink(string $url): bool
    {
        return (bool) preg_match('~/(sharer|shareArticle|share)(/|\.php|\?)|intent/(tweet|share)~i', $url);
    }
}