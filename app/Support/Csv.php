<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads that open cleanly in Excel (UTF-8 BOM) and are safe against spreadsheet formula injection. */
class Csv
{
    /**
     * @param  list<mixed>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Swahili text correctly.
            fputcsv($out, array_map(self::cell(...), $header));
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formulas in text coming from shop data (names typed by users). */
    private static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
