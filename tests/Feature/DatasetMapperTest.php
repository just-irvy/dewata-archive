<?php

namespace Tests\Feature;

use App\Imports\DatasetMapper;
use App\Imports\ImportContext;
use PHPUnit\Framework\TestCase;

class DatasetMapperTest extends TestCase
{
    private function config(): array
    {
        return [
            'source' => 'uji',
            'hotel_categories' => ['Hotel'],
            'place_categories' => ['Wisata' => ['slug' => 'wisata', 'name' => 'Wisata'], 'Kafe' => ['slug' => 'kafe', 'name' => 'Kafe']],
            'area_regions' => ['Ubud' => 'gianyar', 'Gianyar' => 'gianyar', 'Canggu' => 'badung', 'Nusa Penida' => 'klungkung'],
            'vibe_tags' => [
                'Pesisir' => ['slug' => 'pesisir', 'name' => 'Pesisir', 'description' => 'Tepi laut.'],
                'Ritme Padat' => null,
            ],
            'promo_words' => ['mewah', 'legendaris'],
            'kabupaten_regions' => ['Gianyar' => 'gianyar', 'Badung' => 'badung', 'Klungkung' => 'klungkung', 'Denpasar' => 'denpasar'],
            'price_sources' => ['Traveloka' => 'provider', 'Bali Destinations' => 'estimate'],
            'hotel_types' => ['hotel', 'resort', 'villa', 'guesthouse', 'boutique'],
        ];
    }

    private function mapper(?ImportContext $context = null): DatasetMapper
    {
        return new DatasetMapper($this->config(), $context ?? new ImportContext(['gianyar', 'badung', 'klungkung']));
    }

    private function raw(array $overrides = []): array
    {
        return array_replace([
            'id' => '1', 'name' => 'Pura Uji', 'category' => 'Wisata', 'location_area' => 'Ubud',
            'latitude' => '-8.5069', 'longitude' => '115.2625', 'price_per_night' => '0',
            'vibe_category' => 'Pesisir', 'historical_value' => 'Pura kecil di tepi sawah.',
        ], $overrides);
    }

    private function codes($row): array
    {
        return array_column($row->issues, 'code');
    }

    public function test_a_clean_place_row_maps_without_issues(): void
    {
        $row = $this->mapper()->mapRow($this->raw(), 2);

        $this->assertSame('valid', $row->status());
        $this->assertSame('place', $row->entity);
        $this->assertSame('uji:1', $row->externalId);
        $this->assertSame('gianyar', $row->payload['region_slug']);
        $this->assertSame('Ubud', $row->payload['area_label']);
        $this->assertSame(['slug' => 'wisata', 'name' => 'Wisata'], $row->payload['category']);
        $this->assertSame('pesisir', $row->payload['tag']['slug']);
        $this->assertSame(-8.5069, $row->payload['lat']);
        $this->assertSame('pura-uji', $row->payload['slug']);
    }

    public function test_area_label_is_omitted_when_the_area_is_the_regency_itself(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['location_area' => 'Gianyar']), 2);

        $this->assertNull($row->payload['area_label']);
    }

    public function test_hotel_rows_become_hotels_with_a_price_and_no_place_fields(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['category' => 'Hotel', 'price_per_night' => '4500000']), 2);

        $this->assertSame('hotel', $row->entity);
        $this->assertSame(4500000, $row->payload['price_min']);
        $this->assertArrayNotHasKey('category', $row->payload);
        $this->assertArrayNotHasKey('tag', $row->payload);
    }

    public function test_a_zero_price_on_a_non_hotel_means_no_data_and_raises_nothing(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['price_per_night' => '0']), 2);

        $this->assertNotContains('price_on_place', $this->codes($row));
        $this->assertFalse($row->payload['price_ignored']);
    }

    public function test_a_positive_price_on_a_non_hotel_is_ignored_with_a_warning(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['price_per_night' => '50000']), 2);

        $this->assertContains('price_on_place', $this->codes($row));
        $this->assertTrue($row->payload['price_ignored']);
    }

    public function test_a_hotel_without_a_price_is_kept_as_unavailable(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['category' => 'Hotel', 'price_per_night' => '0']), 2);

        $this->assertContains('no_price', $this->codes($row));
        $this->assertNull($row->payload['price_min']);
        $this->assertSame('warning', $row->status());
    }

    public function test_unknown_area_and_unknown_category_are_errors_not_guesses(): void
    {
        $area = $this->mapper()->mapRow($this->raw(['location_area' => 'Atlantis']), 2);
        $category = $this->mapper()->mapRow($this->raw(['category' => 'Spa']), 2);

        $this->assertSame('error', $area->status());
        $this->assertContains('unknown_area', $this->codes($area));
        $this->assertSame('error', $category->status());
        $this->assertContains('unknown_category', $this->codes($category));
    }

    public function test_a_region_that_does_not_exist_yet_is_an_error(): void
    {
        $row = $this->mapper(new ImportContext(['badung']))->mapRow($this->raw(), 2);

        $this->assertContains('region_missing', $this->codes($row));
    }

    public function test_invalid_coordinates_are_errors(): void
    {
        foreach (['abc', '', '95'] as $lat) {
            $row = $this->mapper()->mapRow($this->raw(['latitude' => $lat]), 2);
            $this->assertContains('bad_coordinates', $this->codes($row), "lat={$lat}");
        }

        $zero = $this->mapper()->mapRow($this->raw(['latitude' => '0', 'longitude' => '0']), 2);
        $this->assertContains('bad_coordinates', $this->codes($zero));
    }

    public function test_coordinates_outside_bali_are_a_warning_only(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['latitude' => '-6.2', 'longitude' => '106.8']), 2);

        $this->assertContains('outside_bali', $this->codes($row));
        $this->assertFalse($row->hasError());
    }

    public function test_promotional_text_is_flagged(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['historical_value' => 'Resor mewah di tepi laut.']), 2);

        $this->assertContains('promo_language', $this->codes($row));
        $this->assertSame('warning', $row->status());
    }

    public function test_promo_words_match_whole_words_only(): void
    {
        $row = $this->mapper()->mapRow($this->raw(['historical_value' => 'Warung dengan menu legendarisme.']), 2);

        $this->assertNotContains('promo_language', $this->codes($row));
    }

    public function test_summary_limits_differ_for_places_and_hotels(): void
    {
        $long = str_repeat('a', 350);

        $place = $this->mapper()->mapRow($this->raw(['historical_value' => $long]), 2);
        $hotel = $this->mapper()->mapRow($this->raw(['category' => 'Hotel', 'price_per_night' => '1000000', 'historical_value' => $long]), 2);

        $this->assertContains('summary_too_long', $this->codes($place));
        $this->assertNotContains('summary_too_long', $this->codes($hotel));
    }

    public function test_unknown_and_ignored_vibes_warn_and_attach_no_tag(): void
    {
        $unknown = $this->mapper()->mapRow($this->raw(['vibe_category' => 'Aneh']), 2);
        $ignored = $this->mapper()->mapRow($this->raw(['vibe_category' => 'Ritme Padat']), 2);

        $this->assertContains('unknown_vibe', $this->codes($unknown));
        $this->assertContains('vibe_ignored', $this->codes($ignored));
        $this->assertNull($unknown->payload['tag']);
        $this->assertNull($ignored->payload['tag']);
    }

    public function test_shared_coordinates_warn_both_rows_and_name_each_other(): void
    {
        $result = $this->mapper()->mapAll([
            $this->raw(['id' => '1', 'name' => 'Satu']),
            $this->raw(['id' => '2', 'name' => 'Dua']),
            $this->raw(['id' => '3', 'name' => 'Tiga', 'latitude' => '-8.6', 'longitude' => '115.3']),
        ]);

        [$a, $b, $c] = $result['rows'];

        $this->assertContains('shared_coordinates', $this->codes($a));
        $this->assertContains('shared_coordinates', $this->codes($b));
        $this->assertStringContainsString('Dua', $a->issues[0]['message']);
        $this->assertNotContains('shared_coordinates', $this->codes($c));
    }

    public function test_duplicate_ids_in_one_file_are_an_error_on_the_later_row(): void
    {
        $result = $this->mapper()->mapAll([
            $this->raw(['id' => '7', 'name' => 'Satu']),
            $this->raw(['id' => '7', 'name' => 'Dua', 'latitude' => '-8.6']),
        ]);

        $this->assertNotContains('duplicate_id', $this->codes($result['rows'][0]));
        $this->assertContains('duplicate_id', $this->codes($result['rows'][1]));
    }

    public function test_rows_that_already_exist_are_skipped_not_overwritten(): void
    {
        $row = $this->mapper(new ImportContext(['gianyar'], ['uji:1']))->mapRow($this->raw(), 2);

        $this->assertSame('skip', $row->action);
        $this->assertContains('already_imported', $this->codes($row));
        $this->assertFalse($row->hasError());
    }

    public function test_slugs_are_made_unique_within_the_batch_and_against_the_database(): void
    {
        $result = $this->mapper(new ImportContext(['gianyar'], [], ['pura-uji']))->mapAll([
            $this->raw(['id' => '1', 'name' => 'Pura Uji', 'latitude' => '-8.1']),
            $this->raw(['id' => '2', 'name' => 'Pura Uji', 'latitude' => '-8.2']),
            $this->raw(['id' => '3', 'name' => 'Pura Uji', 'latitude' => '-8.3']),
        ]);

        $slugs = array_map(fn ($r) => $r->payload['slug'], $result['rows']);

        $this->assertCount(3, array_unique($slugs));
        $this->assertSame('pura-uji-ubud', $slugs[0]);
        $this->assertSame('pura-uji-2', $slugs[1]);
        $this->assertSame('pura-uji-3', $slugs[2]);
    }

    public function test_place_and_hotel_slugs_do_not_collide_with_each_other(): void
    {
        $result = $this->mapper()->mapAll([
            $this->raw(['id' => '1', 'name' => 'Sama']),
            $this->raw(['id' => '2', 'name' => 'Sama', 'category' => 'Hotel', 'price_per_night' => '900000', 'latitude' => '-8.2']),
        ]);

        $this->assertSame('sama', $result['rows'][0]->payload['slug']);
        $this->assertSame('sama', $result['rows'][1]->payload['slug']);
    }

    public function test_slug_handles_ampersands_and_accents(): void
    {
        $this->assertSame('milk-and-madu', DatasetMapper::slug('Milk & Madu'));
        $this->assertSame('pantai-suluban-blue-point', DatasetMapper::slug('Pantai Suluban (Blue Point)'));
    }

    public function test_batch_notes_state_what_the_dataset_lacks(): void
    {
        $result = $this->mapper()->mapAll([$this->raw(['category' => 'Hotel', 'price_per_night' => '900000'])]);

        $joined = implode(' ', $result['notes']);

        $this->assertStringContainsString('sumber', $joined);
        $this->assertStringContainsString('hotel', $joined);
        $this->assertStringContainsString('4 desimal', $joined);
    }

    // ---- Format v2

    private function rawV2(array $overrides = []): array
    {
        return array_replace([
            'id' => 'B1', 'name' => 'Air Terjun Uji', 'category' => 'Wisata', 'kabupaten' => 'Gianyar', 'location_area' => 'Ubud',
            'latitude' => '-8.5069000', 'longitude' => '115.2625000', 'price_min' => '', 'price_max' => '', 'price_source' => '',
            'theme' => 'Pesisir', 'hotel_type' => '', 'source_url' => '', 'source_date' => '', 'summary' => 'Air terjun di hutan.',
        ], $overrides);
    }

    public function test_v2_uses_the_kabupaten_column_for_the_region_and_keeps_the_area_as_label(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(), 2);

        $this->assertSame('gianyar', $row->payload['region_slug']);
        $this->assertSame('Ubud', $row->payload['area_label']);
        $this->assertSame('valid', $row->status());
    }

    public function test_v2_area_equal_to_the_kabupaten_is_not_stored_as_a_label(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['location_area' => 'Gianyar']), 2);

        $this->assertNull($row->payload['area_label']);
    }

    public function test_v2_unknown_kabupaten_is_an_error(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['kabupaten' => 'Atlantis']), 2);

        $this->assertContains('unknown_kabupaten', $this->codes($row));
        $this->assertSame('error', $row->status());
    }

    public function test_an_empty_summary_is_a_warning_and_is_never_invented(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['summary' => '']), 2);

        $this->assertContains('no_summary', $this->codes($row));
        $this->assertFalse($row->hasError());
        $this->assertSame('', $row->payload['summary']);
    }

    public function test_source_url_is_kept_when_valid_and_dropped_with_a_warning_when_not(): void
    {
        $ok = $this->mapper()->mapRow($this->rawV2(['source_url' => 'https://www.google.com/maps/place/X/data=!4m7']), 2);
        $bad = $this->mapper()->mapRow($this->rawV2(['source_url' => 'javascript:alert(1)']), 2);

        $this->assertSame('https://www.google.com/maps/place/X/data=!4m7', $ok->payload['source_url']);
        $this->assertNull($bad->payload['source_url']);
        $this->assertContains('bad_source_url', $this->codes($bad));
    }

    public function test_source_date_must_be_a_real_date(): void
    {
        $ok = $this->mapper()->mapRow($this->rawV2(['source_date' => '2026-07-21']), 2);
        $bad = $this->mapper()->mapRow($this->rawV2(['source_date' => '2026-13-45']), 2);

        $this->assertSame('2026-07-21', $ok->payload['source_updated_at']);
        $this->assertNull($bad->payload['source_updated_at']);
        $this->assertContains('bad_source_date', $this->codes($bad));
    }

    public function test_v2_hotel_price_uses_the_source_to_choose_the_basis(): void
    {
        $provider = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '759033', 'price_source' => 'Traveloka']), 2);
        $estimate = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '300000', 'price_source' => 'Bali Destinations']), 2);
        $unknown = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '300000', 'price_source' => 'Entah']), 2);
        $none = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '300000', 'price_source' => '']), 2);

        $this->assertSame('provider', $provider->payload['price_basis']);
        $this->assertSame('estimate', $estimate->payload['price_basis']);
        $this->assertSame('estimate', $unknown->payload['price_basis']);
        $this->assertContains('unknown_price_source', $this->codes($unknown));
        $this->assertSame('estimate', $none->payload['price_basis']);
    }

    public function test_v2_hotel_price_range_is_validated(): void
    {
        $range = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '400000', 'price_max' => '600000', 'price_source' => 'Traveloka']), 2);
        $inverted = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '600000', 'price_max' => '400000', 'price_source' => 'Traveloka']), 2);

        $this->assertSame(600000, $range->payload['price_max']);
        $this->assertNull($inverted->payload['price_max']);
        $this->assertContains('price_range_inverted', $this->codes($inverted));
    }

    public function test_hotel_type_defaults_to_hotel_and_rejects_unknown_values_loudly(): void
    {
        $villa = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '1', 'hotel_type' => 'villa']), 2);
        $none = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '1', 'hotel_type' => '']), 2);
        $bad = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '1', 'hotel_type' => 'kapal']), 2);

        $this->assertSame('villa', $villa->payload['hotel_type']);
        $this->assertSame('hotel', $none->payload['hotel_type']);
        $this->assertSame('hotel', $bad->payload['hotel_type']);
        $this->assertContains('unknown_hotel_type', $this->codes($bad));
    }

    public function test_the_theme_column_selects_the_tag(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['theme' => 'Pesisir']), 2);

        $this->assertSame('pesisir', $row->payload['tag']['slug']);
    }

    public function test_notes_report_how_many_rows_have_a_source_link_and_no_summary(): void
    {
        $result = $this->mapper()->mapAll([
            $this->rawV2(['id' => '1', 'source_url' => 'https://example.test/a', 'summary' => '']),
            $this->rawV2(['id' => '2', 'name' => 'Dua', 'latitude' => '-8.6', 'summary' => '']),
        ]);

        $joined = implode(' ', $result['notes']);

        $this->assertStringContainsString('1 dari 2 baris punya tautan sumber', $joined);
        $this->assertStringContainsString('2 baris tidak punya ringkasan', $joined);
        $this->assertStringNotContainsString('4 desimal', $joined);
    }

    // ---- Verifikasi

    public function test_a_valid_date_without_a_status_becomes_needs_review(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2026-10-27']), 2);

        $this->assertSame('2026-10-27', $row->payload['verified_at']);
        $this->assertSame('needs_review', $row->payload['verification_status']);
        $this->assertFalse($row->hasError());
    }

    public function test_a_needs_review_status_is_kept_when_there_is_a_date(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2026-10-27', 'verification_status' => 'needs_review']), 2);

        $this->assertSame('needs_review', $row->payload['verification_status']);
        $this->assertNotContains('verified_downgraded', $this->codes($row));
    }

    public function test_a_verified_claim_in_a_file_is_downgraded_because_only_an_editor_can_verify(): void
    {
        foreach (['verified', 'Verified', 'VERIFIED', ' verified '] as $value) {
            $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2026-10-27', 'verification_status' => $value]), 2);

            $this->assertSame('needs_review', $row->payload['verification_status'], $value);
            $this->assertContains('verified_downgraded', $this->codes($row), $value);
        }
    }

    public function test_status_values_are_case_insensitive(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2026-10-27', 'verification_status' => 'Needs_Review']), 2);

        $this->assertSame('needs_review', $row->payload['verification_status']);
        $this->assertNotContains('bad_verification_status', $this->codes($row));
    }

    public function test_search_result_links_are_not_accepted_as_sources(): void
    {
        foreach ([
            'https://www.google.com/search?q=Museum%20Yadnya%20Bali%20official%20website',
            'https://google.co.id/search?q=x',
            'https://www.bing.com/search?q=x',
            'https://duckduckgo.com/?q=x',
        ] as $url) {
            $row = $this->mapper()->mapRow($this->rawV2(['source_url' => $url]), 2);

            $this->assertNull($row->payload['source_url'], $url);
            $this->assertContains('search_link_not_source', $this->codes($row), $url);
        }

        $maps = $this->mapper()->mapRow($this->rawV2(['source_url' => 'https://www.google.com/maps/place/Museum+Yadnya/data=!4m7']), 2);
        $this->assertNotNull($maps->payload['source_url']);
    }

    public function test_a_sentence_repeated_across_many_rows_marks_the_summaries_as_templated(): void
    {
        $rows = [];
        foreach (range(1, 12) as $i) {
            $rows[] = $this->rawV2([
                'id' => "T{$i}", 'name' => "Tempat {$i}", 'latitude' => (string) (-8.5 - $i / 100),
                'summary' => "Tempat {$i} adalah wisata yang terletak di kawasan Ubud. Menawarkan pengalaman menginap yang nyaman dengan akses strategis ke berbagai atraksi.",
            ]);
        }
        $rows[] = $this->rawV2(['id' => 'U1', 'name' => 'Unik', 'latitude' => '-8.9', 'summary' => 'Pura kecil di tepi sawah yang berdiri sejak abad ke-16.']);

        $result = $this->mapper()->mapAll($rows);

        foreach (array_slice($result['rows'], 0, 12) as $row) {
            $this->assertSame('', $row->payload['summary']);
            $this->assertContains('boilerplate_summary', $this->codes($row));
        }
        $unique = $result['rows'][12];
        $this->assertSame('Pura kecil di tepi sawah yang berdiri sejak abad ke-16.', $unique->payload['summary']);
        $this->assertNotContains('boilerplate_summary', $this->codes($unique));
        $this->assertStringContainsString('12 ringkasan dikosongkan', implode(' ', $result['notes']));
    }

    public function test_a_few_identical_summaries_or_short_phrases_are_not_flagged(): void
    {
        $few = $this->mapper()->mapAll([
            $this->rawV2(['id' => '1', 'name' => 'Satu', 'summary' => 'Dibuka setiap hari sepanjang tahun untuk umum.']),
            $this->rawV2(['id' => '2', 'name' => 'Dua', 'latitude' => '-8.6', 'summary' => 'Dibuka setiap hari sepanjang tahun untuk umum.']),
        ]);
        $short = [];
        foreach (range(1, 12) as $i) {
            $short[] = $this->rawV2(['id' => "S{$i}", 'name' => "Nama {$i}", 'latitude' => (string) (-8.5 - $i / 100), 'summary' => "Pura nomor {$i}. Dekat pantai."]);
        }
        $shortResult = $this->mapper()->mapAll($short);

        foreach ($few['rows'] as $row) {
            $this->assertNotContains('boilerplate_summary', $this->codes($row));
        }
        foreach ($shortResult['rows'] as $row) {
            $this->assertNotContains('boilerplate_summary', $this->codes($row));
        }
    }

    public function test_a_status_without_a_valid_date_is_downgraded_to_unknown(): void
    {
        $noDate = $this->mapper()->mapRow($this->rawV2(['verification_status' => 'verified']), 2);
        $badDate = $this->mapper()->mapRow($this->rawV2(['verified_at' => '27/10/2026', 'verification_status' => 'verified']), 2);

        $this->assertSame('unknown', $noDate->payload['verification_status']);
        $this->assertContains('status_without_date', $this->codes($noDate));
        $this->assertSame('unknown', $badDate->payload['verification_status']);
        $this->assertNull($badDate->payload['verified_at']);
        $this->assertContains('bad_verified_at', $this->codes($badDate));
    }

    public function test_a_future_verification_date_is_ignored(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2999-01-01']), 2);

        $this->assertNull($row->payload['verified_at']);
        $this->assertContains('future_verified_at', $this->codes($row));
        $this->assertSame('unknown', $row->payload['verification_status']);
    }

    public function test_an_unknown_status_value_is_ignored_with_a_warning(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['verified_at' => '2026-10-27', 'verification_status' => 'pasti']), 2);

        $this->assertContains('bad_verification_status', $this->codes($row));
        $this->assertSame('needs_review', $row->payload['verification_status']);
    }

    public function test_without_verification_columns_everything_stays_unknown(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(), 2);

        $this->assertNull($row->payload['verified_at']);
        $this->assertSame('unknown', $row->payload['verification_status']);
    }

    public function test_hotels_take_the_date_but_not_a_place_status(): void
    {
        $row = $this->mapper()->mapRow($this->rawV2(['category' => 'Hotel', 'price_min' => '1', 'verified_at' => '2026-10-27']), 2);

        $this->assertSame('2026-10-27', $row->payload['verified_at']);
        $this->assertArrayNotHasKey('verification_status', $row->payload);
    }
}
