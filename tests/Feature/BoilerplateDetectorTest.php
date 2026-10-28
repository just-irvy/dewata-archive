<?php

namespace Tests\Feature;

use App\Imports\BoilerplateDetector;
use PHPUnit\Framework\TestCase;

class BoilerplateDetectorTest extends TestCase
{
    public function test_the_place_name_is_removed_before_comparing_sentences(): void
    {
        $a = BoilerplateDetector::sentences('Museum Yadnya adalah tempat yang terletak di kawasan Mengwi.', 'Museum Yadnya');
        $b = BoilerplateDetector::sentences('Pura Taman adalah tempat yang terletak di kawasan Mengwi.', 'Pura Taman');

        $this->assertSame($a, $b);
    }

    public function test_only_sentences_with_at_least_six_words_are_considered(): void
    {
        $this->assertSame([], BoilerplateDetector::sentences('Dekat pantai. Buka setiap hari.', ''));
        $this->assertCount(1, BoilerplateDetector::sentences('Menawarkan pengalaman menginap yang nyaman bagi tamu.', ''));
    }

    public function test_a_sentence_is_flagged_only_at_or_above_the_threshold(): void
    {
        $records = [];
        foreach (range(1, 9) as $i) {
            $records[] = ['name' => "T{$i}", 'summary' => "Isi unik nomor {$i} yang berbeda total dari lainnya. Kalimat templat yang sama muncul berulang kali."];
        }

        $this->assertArrayHasKey('kalimat templat yang sama muncul berulang kali', BoilerplateDetector::flagged($records, 5));
        $this->assertSame([], BoilerplateDetector::flagged($records, 10));
    }

    public function test_the_default_threshold_scales_with_the_batch_but_never_drops_below_five(): void
    {
        $few = array_map(fn ($i) => ['name' => "T{$i}", 'summary' => 'Kalimat yang sama dan cukup panjang untuk diperiksa.'], range(1, 4));

        $this->assertSame([], BoilerplateDetector::flagged($few));
    }

    public function test_search_links_are_recognised_but_map_pages_are_not(): void
    {
        $this->assertTrue(BoilerplateDetector::isSearchLink('https://www.google.com/search?q=hotel+bali'));
        $this->assertTrue(BoilerplateDetector::isSearchLink('https://www.bing.com/search?q=hotel'));
        $this->assertTrue(BoilerplateDetector::isSearchLink('https://duckduckgo.com/?q=hotel'));
        $this->assertFalse(BoilerplateDetector::isSearchLink('https://www.google.com/maps/place/Pura/data=!4m7'));
        $this->assertFalse(BoilerplateDetector::isSearchLink('https://www.indonesia.travel/id-id/travel-ideas/adventure/desa-penglipuran/'));
    }
}
