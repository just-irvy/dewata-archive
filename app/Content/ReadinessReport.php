<?php

namespace App\Content;

/** Hasil pemeriksaan kesiapan terbit. Blocker menghentikan publikasi, warning tidak. */
final class ReadinessReport
{
    /**
     * @param  array<int, string>  $blockers
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $blockers = [],
        public readonly array $warnings = [],
    ) {}

    public function passes(): bool
    {
        return $this->blockers === [];
    }
}
