<?php

namespace Tests\Feature;

use App\Services\GeoIpCountryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DetectLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function mockGeoIp(?string $country): void
    {
        $mock = Mockery::mock(GeoIpCountryService::class);
        $mock->shouldReceive('lookup')->andReturn($country);
        $this->app->instance(GeoIpCountryService::class, $mock);
    }

    public function test_query_lang_override_sets_locale(): void
    {
        $this->mockGeoIp(null);

        $response = $this->get('/login?lang=zh-TW');

        $response->assertStatus(200);
        $this->assertEquals('zh-TW', app()->getLocale());
        $response->assertCookie('locale', 'zh-TW');
    }

    public function test_invalid_query_lang_is_ignored(): void
    {
        $this->mockGeoIp(null);

        $response = $this->get('/login?lang=fr');

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_cookie_persistence(): void
    {
        $this->mockGeoIp(null);

        $response = $this->withCookie('locale', 'zh-TW')
            ->get('/login');

        $response->assertStatus(200);
        $this->assertEquals('zh-TW', app()->getLocale());
    }

    public function test_geoip_detection_sets_locale(): void
    {
        $this->mockGeoIp('TW');

        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertEquals('zh-TW', app()->getLocale());
        $response->assertCookie('locale', 'zh-TW');
    }

    public function test_accept_language_fallback(): void
    {
        $this->mockGeoIp(null);

        $response = $this->withHeaders([
            'Accept-Language' => 'zh-TW,zh;q=0.9,en;q=0.8',
        ])->get('/login');

        $response->assertStatus(200);
        $this->assertEquals('zh-TW', app()->getLocale());
        $response->assertCookie('locale', 'zh-TW');
    }

    public function test_default_locale_when_no_signals(): void
    {
        $this->mockGeoIp(null);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_debug_headers_present_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $this->mockGeoIp(null);

        $response = $this->get('/login');

        $response->assertHeader('X-Locale');
        $response->assertHeader('X-Locale-Source');
        $response->assertHeader('X-Client-IP');
        $response->assertHeader('X-Client-Country');
    }

    public function test_query_override_takes_priority_over_cookie(): void
    {
        $this->mockGeoIp(null);

        $response = $this->withCookie('locale', 'en')
            ->get('/login?lang=zh-TW');

        $response->assertStatus(200);
        $this->assertEquals('zh-TW', app()->getLocale());
    }

    public function test_cookie_takes_priority_over_geoip(): void
    {
        $this->mockGeoIp('TW');

        $response = $this->withCookie('locale', 'en')
            ->get('/login');

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }
}
