<?php

namespace Tests\Feature;

use App\Imports\CsvReader;
use App\Imports\ImportFormatException;
use PHPUnit\Framework\TestCase;

class CsvReaderTest extends TestCase
{
    private const HEADER = 'id,name,category,location_area,latitude,longitude,price_per_night,vibe_category,historical_value';

    private const HEADER_V2 = 'id,name,category,kabupaten,location_area,latitude,longitude,price_min,price_max,price_source,theme,hotel_type,source_url,source_date,summary,note';

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_reads_rows_keyed_by_header(): void
    {
        $path = $this->file(self::HEADER."\n".'1,"Pura, Uji",Wisata,Ubud,-8.5,115.2,0,Pesisir,"Teks ""kutipan"""'."\n");

        $result = CsvReader::read($path);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Pura, Uji', $result['rows'][0]['name']);
        $this->assertSame('Teks "kutipan"', $result['rows'][0]['historical_value']);
    }

    public function test_strips_a_utf8_bom_and_skips_blank_lines(): void
    {
        $path = $this->file("\xEF\xBB\xBF".self::HEADER."\n\n".'1,A,Wisata,Ubud,-8.5,115.2,0,Pesisir,x'."\n\n");

        $this->assertCount(1, CsvReader::read($path)['rows']);
    }

    public function test_missing_required_columns_are_named_in_the_error(): void
    {
        $path = $this->file("id,name\n1,A\n");

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('latitude');
        CsvReader::read($path);
    }

    public function test_a_malformed_row_reports_its_line_number(): void
    {
        $path = $this->file(self::HEADER."\n".'1,A,Wisata'."\n");

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('Baris 2');
        CsvReader::read($path);
    }

    public function test_row_limit_is_enforced(): void
    {
        $line = '1,A,Wisata,Ubud,-8.5,115.2,0,Pesisir,x'."\n";
        $path = $this->file(self::HEADER."\n".$line.$line.$line);

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('batas 2 baris');
        CsvReader::read($path, 2);
    }

    public function test_non_utf8_content_is_rejected(): void
    {
        $path = $this->file(self::HEADER."\n".'1,Caf'."\xE9".',Wisata,Ubud,-8.5,115.2,0,Pesisir,x'."\n");

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('UTF-8');
        CsvReader::read($path);
    }

    public function test_semicolon_delimited_files_get_a_clear_message(): void
    {
        $path = $this->file("id;name;category\n1;A;Wisata\n");

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('koma');
        CsvReader::read($path);
    }

    public function test_header_only_and_empty_files_are_rejected(): void
    {
        $this->expectException(ImportFormatException::class);
        CsvReader::read($this->file(self::HEADER."\n"));
    }

    public function test_reads_the_v2_layout(): void
    {
        $path = $this->file(self::HEADER_V2."\n".'B1,Air Terjun,Wisata Alam,Gianyar,Gianyar,-8.5,115.2,,,,Alam,,https://x.test/a,,,catatan'."\n");

        $result = CsvReader::read($path);

        $this->assertSame('Gianyar', $result['rows'][0]['kabupaten']);
        $this->assertSame('https://x.test/a', $result['rows'][0]['source_url']);
    }

    public function test_a_file_without_any_region_column_is_rejected(): void
    {
        $path = $this->file("id,name,category,latitude,longitude\n1,A,Wisata,-8.5,115.2\n");

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessage('kabupaten atau location_area');
        CsvReader::read($path);
    }
}
