<?php

namespace Tests\Unit;

use App\Services\Chat\PriceGuard;
use PHPUnit\Framework\TestCase;

class PriceGuardTest extends TestCase
{
    public function test_amounts_in_the_allowed_list_pass(): void
    {
        $this->assertSame([], PriceGuard::unknownAmounts('Mulai Rp500.000 sampai Rp700.000 per kamar per malam.', [500000, 700000]));
    }

    public function test_invented_amounts_are_reported(): void
    {
        $this->assertSame([650000], PriceGuard::unknownAmounts('Sekitar Rp650.000 per malam. Pilihan lain Rp500.000.', [500000]));
    }

    public function test_short_forms_are_understood(): void
    {
        $this->assertSame([], PriceGuard::unknownAmounts('Budget Rp1,5 juta dan Rp700 ribu serta Rp900rb.', [1500000, 700000, 900000]));
        $this->assertSame([2000000], PriceGuard::unknownAmounts('Sekitar Rp2 juta.', [1500000]));
    }

    public function test_trailing_sentence_period_is_ignored(): void
    {
        $this->assertSame([], PriceGuard::unknownAmounts('Harganya Rp500.000.', [500000]));
    }

    public function test_answers_without_rupiah_have_nothing_to_check(): void
    {
        $this->assertSame([], PriceGuard::unknownAmounts('Ada tiga pilihan di Ubud.', []));
    }

    public function test_user_mentioned_amounts_are_collected(): void
    {
        $amounts = PriceGuard::mentionedAmounts('hotel di bawah 700rb atau maksimal 1,5 juta, Rp900.000 juga boleh');

        $this->assertContains(700000, $amounts);
        $this->assertContains(1500000, $amounts);
        $this->assertContains(900000, $amounts);
    }
}
