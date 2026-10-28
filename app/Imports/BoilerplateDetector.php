<?php

namespace App\Imports;

/**
 * Mendeteksi ringkasan templat: kalimat yang sama persis (setelah nama tempat dibuang) muncul di banyak
 * record. Ringkasan hasil riset per tempat tidak mungkin berbagi kalimat panjang yang sama di puluhan tempat.
 * Kalimat pendek diabaikan agar frasa umum tidak terdeteksi keliru.
 */
final class BoilerplateDetector
{
    public const MIN_WORDS = 6;

    /** @return array<int, string> kunci kalimat ternormalisasi */
    public static function sentences(string $summary, string $name): array
    {
        $text = $name !== '' ? str_ireplace($name, '{n}', $summary) : $summary;
        $keys = [];

        foreach (preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [] as $part) {
            $key = mb_strtolower(trim($part));
            $key = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N}{} ]+/u', ' ', $key)));

            if ($key !== '' && count(explode(' ', $key)) >= self::MIN_WORDS) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<int|string, array{name: string, summary: string}>  $records
     * @return array<string, true> kalimat yang muncul sebanyak ambang atau lebih
     */
    public static function flagged(array $records, ?int $threshold = null): array
    {
        $threshold ??= max(5, (int) ceil(count($records) * 0.05));
        $counts = [];

        foreach ($records as $record) {
            foreach (self::sentences((string) $record['summary'], (string) $record['name']) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return array_fill_keys(array_keys(array_filter($counts, fn (int $c) => $c >= $threshold)), true);
    }

    /** @param  array<string, true>  $flagged */
    public static function isBoilerplate(string $summary, string $name, array $flagged): bool
    {
        foreach (self::sentences($summary, $name) as $key) {
            if (isset($flagged[$key])) {
                return true;
            }
        }

        return false;
    }

    /** Tautan hasil pencarian (Google, Bing, dan sebagainya) bukan sumber informasi. */
    public static function isSearchLink(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        $searchHost = (bool) preg_match('/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|search\.yahoo\.com)$/', $host);

        return $searchHost && (str_starts_with($path, '/search') || preg_match('/(^|&)q=/', $query));
    }
}
