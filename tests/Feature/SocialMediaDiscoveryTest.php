<?php

namespace Tests\Feature;

use App\Services\SocialMediaDiscoveryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMediaDiscoveryTest extends TestCase
{
    public function test_it_extracts_company_social_links_from_public_website(): void
    {
        Http::fake([
            'https://example-business.test' => Http::response(<<<'HTML'
<html><body>
<a href="https://www.facebook.com/examplebusiness">Facebook</a>
<a href="https://instagram.com/examplebusiness/">Instagram</a>
<a href="https://www.linkedin.com/company/example-business">LinkedIn</a>
<a href="https://www.facebook.com/sharer/sharer.php?u=https://example-business.test">Share</a>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $links = app(SocialMediaDiscoveryService::class)->discover('https://example-business.test');

        $this->assertSame('https://www.facebook.com/examplebusiness', $links['facebook_url']);
        $this->assertSame('https://www.instagram.com/examplebusiness', $links['instagram_url']);
        $this->assertSame('https://www.linkedin.com/company/example-business', $links['linkedin_url']);
    }

    public function test_it_canonicalizes_linkedin_authwall_redirects(): void
    {
        $redirect = rawurlencode('https://www.linkedin.com/company/travelwithtaymoorpvt/');
        Http::fake([
            'https://travel.test' => Http::response("<a href=\"https://www.linkedin.com/authwall?trk=bf&sessionRedirect={$redirect}\">LinkedIn</a>", 200, ['Content-Type' => 'text/html']),
        ]);

        $links = app(SocialMediaDiscoveryService::class)->discover('https://travel.test');

        $this->assertSame('https://www.linkedin.com/company/travelwithtaymoorpvt', $links['linkedin_url']);
        $this->assertLessThan(255, strlen($links['linkedin_url']));
    }
    public function test_it_returns_empty_links_when_website_is_unavailable(): void
    {
        Http::fake(['*' => Http::response('Unavailable', 503)]);

        $this->assertSame([
            'facebook_url' => null,
            'instagram_url' => null,
            'linkedin_url' => null,
        ], app(SocialMediaDiscoveryService::class)->discover('https://unavailable.test'));
    }
}