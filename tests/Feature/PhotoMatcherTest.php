<?php

namespace Tests\Feature;

use App\Photos\PhotoMatcher;
use PHPUnit\Framework\TestCase;

class PhotoMatcherTest extends TestCase
{
    private array $index = [
        'museum-yadnya' => [1],
        'pura-2' => [2],
        'pura' => [3],
        'tumpang' => [4, 5],
        'pantai-blue-point' => [6],
    ];

    public function test_matches_an_exact_slug_as_the_first_photo(): void
    {
        $this->assertSame(['status' => 'ok', 'id' => 1, 'order' => 1], PhotoMatcher::match('museum-yadnya.jpg', $this->index));
    }

    public function test_a_numeric_suffix_means_the_next_photo(): void
    {
        $this->assertSame(['status' => 'ok', 'id' => 1, 'order' => 2], PhotoMatcher::match('museum-yadnya-2.jpg', $this->index));
        $this->assertSame(['status' => 'ok', 'id' => 1, 'order' => 1], PhotoMatcher::match('museum-yadnya-1.jpg', $this->index));
    }

    public function test_spaces_capitals_and_punctuation_in_the_file_name_are_tolerated(): void
    {
        $this->assertSame(1, PhotoMatcher::match('Museum Yadnya.JPG', $this->index)['id']);
        $this->assertSame(6, PhotoMatcher::match('Pantai Blue Point.webp', $this->index)['id']);
    }

    public function test_a_full_name_wins_over_a_suffix_reading(): void
    {
        $this->assertSame(['status' => 'ok', 'id' => 2, 'order' => 1], PhotoMatcher::match('pura-2.jpg', $this->index));
        $this->assertSame(['status' => 'ok', 'id' => 3, 'order' => 3], PhotoMatcher::match('pura-3.png', $this->index));
    }

    public function test_names_shared_by_two_records_are_ambiguous_not_guessed(): void
    {
        $this->assertSame('ambiguous', PhotoMatcher::match('tumpang.jpg', $this->index)['status']);
    }

    public function test_unknown_names_do_not_match(): void
    {
        $this->assertSame('none', PhotoMatcher::match('tidak-ada.jpg', $this->index)['status']);
    }
}
