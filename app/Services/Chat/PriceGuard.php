<?php

namespace App\Services\Chat;

/**
 * Pengaman terakhir terhadap angka harga karangan (FILE 09 dan FILE 17: jangan menciptakan angka).
 * Setiap nominal rupiah pada jawaban model harus berasal dari hasil tool atau dari budget
 * yang dikirim sebagai argumen tool. Nominal lain ditandai.
 */
final class PriceGuard
{
    /**
     * @param  array<int, int>  $allowed  Nominal rupiah penuh yang sah.
     * @return array<int, int> Nominal pada jawaban yang tidak ditemukan di $allowed.
     */
    public static function unknownAmounts(string $answer, array $allowed): array
    {
        if (preg_match_all('/Rp\.?\s?(\d[\d.,]*)(?:\s?(juta|jt|ribu|rb|k)\b)?/iu', $answer, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $unknown = [];

        foreach ($matches as $m) {
            $amount = self::toRupiah($m[1], strtolower($m[2] ?? ''));

            if ($amount > 0 && ! in_array($amount, $allowed, true)) {
                $unknown[] = $amount;
            }
        }

        return array_values(array_unique($unknown));
    }

    /**
     * Semua angka yang disebut pengguna ("700rb", "1,5 juta", "Rp900.000", "2 malam"). Dipakai
     * sebagai daftar angka sah: budget yang diucapkan pengguna boleh disebut ulang oleh model.
     *
     * @return array<int, int>
     */
    public static function mentionedAmounts(string $text): array
    {
        if (preg_match_all('/(?:Rp\.?\s?)?(\d[\d.,]*)\s?(juta|jt|ribu|rb|k)?\b/iu', $text, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $amounts = [];
        foreach ($matches as $m) {
            $amounts[] = self::toRupiah($m[1], strtolower($m[2] ?? ''));
        }

        return array_values(array_unique(array_filter($amounts)));
    }

    private static function toRupiah(string $digits, string $suffix): int
    {
        $digits = rtrim($digits, '.,');

        if ($suffix !== '') {
            $multiplier = in_array($suffix, ['juta', 'jt'], true) ? 1_000_000 : 1_000;

            return (int) round((float) str_replace(',', '.', $digits) * $multiplier);
        }

        return (int) preg_replace('/\D/', '', $digits);
    }
}
