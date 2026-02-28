<?php

namespace App\Http\Middleware;

use App\Services\GeoIpCountryService;
use App\Services\LocaleFromAcceptLanguageService;
use App\Services\LocaleFromCountryService;
use App\Support\ClientIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DetectLocale
{
    protected const COOKIE_NAME = 'locale';
    protected const COOKIE_TTL_MINUTES = 180 * 24 * 60; // 180 days

    public function __construct(
        protected GeoIpCountryService $geoIp,
        protected LocaleFromCountryService $countryLocale,
        protected LocaleFromAcceptLanguageService $acceptLanguageLocale,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locale.supported', []);
        $source = 'default';
        $locale = null;
        $country = null;
        $clientIp = ClientIp::resolve($request);

        // 1. Query parameter override → set cookie
        $queryLang = $request->query('lang');
        if ($queryLang && in_array($queryLang, $supported, true)) {
            $locale = $queryLang;
            $source = 'query';
        }

        // 2. Cookie
        if (!$locale) {
            $cookieLang = $request->cookie(self::COOKIE_NAME);
            if ($cookieLang && in_array($cookieLang, $supported, true)) {
                $locale = $cookieLang;
                $source = 'cookie';
            }
        }

        // 3. IP → Country → Locale
        if (!$locale) {
            $country = $this->geoIp->lookup($clientIp);
            $geoLocale = $this->countryLocale->resolve($country);
            if ($geoLocale) {
                $locale = $geoLocale;
                $source = 'geoip';
            }
        }

        // 4. Accept-Language
        if (!$locale) {
            $alLocale = $this->acceptLanguageLocale->resolve(
                $request->header('Accept-Language')
            );
            if ($alLocale) {
                $locale = $alLocale;
                $source = 'accept-language';
            }
        }

        // 5. Fallback
        if (!$locale) {
            $locale = config('locale.default', 'en');
        }

        App::setLocale($locale);

        $response = $next($request);

        // Set/refresh the locale cookie
        if ($source === 'query' || $source === 'geoip' || $source === 'accept-language') {
            $response->headers->setCookie(cookie(
                self::COOKIE_NAME,
                $locale,
                self::COOKIE_TTL_MINUTES,
            ));
        }

        // Debug headers (local or debug mode only)
        if (config('app.debug') || config('app.env') === 'local') {
            // Resolve country lazily if not already done
            if ($country === null && $source !== 'geoip') {
                $country = $this->geoIp->lookup($clientIp);
            }

            $response->headers->set('X-Locale', $locale);
            $response->headers->set('X-Locale-Source', $source);
            $response->headers->set('X-Client-IP', $clientIp);
            $response->headers->set('X-Client-Country', $country ?? 'unknown');
        }

        return $response;
    }
}
