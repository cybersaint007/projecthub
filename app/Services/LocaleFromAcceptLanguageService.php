<?php

namespace App\Services;

class LocaleFromAcceptLanguageService
{
    public function resolve(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $languages = $this->parse($header);
        $map = config('locale.accept_language_map', []);

        foreach ($languages as $tag) {
            $normalized = strtolower($tag);

            if (isset($map[$normalized])) {
                return $map[$normalized];
            }

            // Try the primary subtag (e.g., "en" from "en-au")
            $primary = explode('-', $normalized)[0];
            if (isset($map[$primary])) {
                return $map[$primary];
            }
        }

        return null;
    }

    /**
     * Parse Accept-Language header and return tags sorted by q-value descending.
     *
     * @return string[]
     */
    protected function parse(string $header): array
    {
        $entries = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', $part, $m)) {
                $tag = $m[1];
                $q = isset($m[2]) ? (float) $m[2] : 1.0;
                $entries[] = ['tag' => $tag, 'q' => $q];
            }
        }

        usort($entries, fn($a, $b) => $b['q'] <=> $a['q']);

        return array_column($entries, 'tag');
    }
}
