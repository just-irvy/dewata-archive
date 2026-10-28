<?php

namespace App\Imports;

/** Membaca CSV dataset dengan pemeriksaan ketat. Tidak bergantung pada framework. */
final class CsvReader
{
    /**
     * @return array{header: array<int, string>, rows: array<int, array<string, string>>}
     *
     * @throws ImportFormatException
     */
    public static function read(string $path, int $maxRows = 2000): array
    {
        $content = @file_get_contents($path);

        if ($content === false || $content === '') {
            throw new ImportFormatException('Berkas kosong atau tidak bisa dibaca.');
        }

        // BOM UTF-8 dari Excel dibuang.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw new ImportFormatException('Berkas harus berenkode UTF-8.');
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, ',', '"', '');

        if ($header === false || $header === [null]) {
            throw new ImportFormatException('Baris header tidak ditemukan.');
        }

        $header = array_map(fn ($h) => trim((string) $h), $header);

        if (count($header) === 1 && str_contains($header[0], ';')) {
            throw new ImportFormatException('Pemisah kolom harus koma, bukan titik koma.');
        }

        $missing = array_values(array_diff(DatasetMapper::REQUIRED_COLUMNS, $header));
        if ($missing !== []) {
            throw new ImportFormatException('Kolom wajib tidak ada: '.implode(', ', $missing).'.');
        }

        if (array_intersect(DatasetMapper::REGION_COLUMNS, $header) === []) {
            throw new ImportFormatException('Berkas harus punya kolom kabupaten atau location_area.');
        }

        $rows = [];
        $line = 1;

        while (($fields = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;

            if ($fields === [null]) {
                continue;
            }
            if (count($fields) !== count($header)) {
                throw new ImportFormatException("Baris {$line} punya ".count($fields).' kolom, seharusnya '.count($header).'.');
            }
            if (count($rows) >= $maxRows) {
                throw new ImportFormatException("Berkas melebihi batas {$maxRows} baris.");
            }

            $rows[] = array_combine($header, $fields);
        }

        fclose($stream);

        if ($rows === []) {
            throw new ImportFormatException('Berkas tidak memuat baris data.');
        }

        return ['header' => $header, 'rows' => $rows];
    }
}
