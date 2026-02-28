<?php

namespace App\Services;

class LocaleFromCountryService
{
    public function resolve(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }

        $locale = config('locale.country_map.' . strtoupper($countryCode));

        return $this->isSupported($locale) ? $locale : null;
    }

    protected function isSupported(?string $locale): bool
    {
        if (!$locale) {
            return false;
        }

        return in_array($locale, config('locale.supported', []), true);
    }
}
