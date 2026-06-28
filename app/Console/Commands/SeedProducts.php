<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedProducts extends Command
{
    protected $signature = 'products:seed {--country=en:philippines : Country tag to filter}';
    protected $description = 'Seed the products table from the Open Food Facts CSV bulk export';

    private const CSV_URL = 'https://static.openfoodfacts.org/data/en.openfoodfacts.org.products.csv.gz';

    private const UPDATE_COLS = [
        'product_name', 'brand_name', 'serving_size_g', 'calories',
        'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg', 'updated_at',
    ];

    public function handle(): int
    {
        $country = $this->option('country');

        $this->info('Opening CSV stream (this may take a moment)...');
        $fh = @fopen('compress.zlib://' . self::CSV_URL, 'r');
        if (!$fh) {
            $this->error('Failed to open CSV stream. Check your internet connection.');
            return Command::FAILURE;
        }

        stream_set_timeout($fh, 60);

        $headers = fgetcsv($fh, 0, "\t");
        if (!$headers) {
            fclose($fh);
            $this->error('Failed to read CSV headers.');
            return Command::FAILURE;
        }
        $col = array_flip($headers);

        if (!isset($col['code'], $col['product_name'], $col['countries_tags'])) {
            fclose($fh);
            $this->error('Unexpected CSV format — required columns missing.');
            return Command::FAILURE;
        }

        $batch = [];
        $imported = 0;
        $skipped = 0;
        $processed = 0;

        while (($row = fgetcsv($fh, 0, "\t")) !== false) {
            $processed++;

            $countries = $row[$col['countries_tags']] ?? '';
            if (!str_contains($countries, $country)) {
                continue;
            }

            $record = $this->csvRowToRecord($row, $col);
            if (!$record) {
                $skipped++;
                continue;
            }

            $batch[] = $record;
            $imported++;

            if (count($batch) >= 500) {
                DB::table('products')->upsert($batch, ['code'], self::UPDATE_COLS);
                $batch = [];
            }

            if ($imported % 500 === 0) {
                $this->info("{$imported} imported, {$skipped} skipped ({$processed} rows scanned)");
            }
        }

        fclose($fh);

        if ($batch) {
            DB::table('products')->upsert($batch, ['code'], self::UPDATE_COLS);
        }

        $this->info("Done. {$imported} imported, {$skipped} skipped from {$processed} rows scanned.");
        return Command::SUCCESS;
    }

    private function csvRowToRecord(array $row, array $col): ?array
    {
        $code = trim($row[$col['code']] ?? '');
        $name = trim($row[$col['product_name']] ?? '');
        if (!$code || !$name) return null;

        $get = fn(string $field): string => $row[$col[$field] ?? -1] ?? '';

        $kcal = $get('energy-kcal_100g');
        if ($kcal === '' && ($kj = $get('energy-kj_100g')) !== '') {
            $kcal = (string) ((float) $kj / 4.184);
        }

        // serving_size in CSV is a string like "100 g" or "1 serving (28 g)"
        preg_match('/(\d+(?:\.\d+)?)\s*g\b/i', $get('serving_size'), $m);
        $servingG = isset($m[1]) ? (float) $m[1] : null;

        $brand = trim(explode(',', $get('brands'))[0]) ?: null;
        $num = fn(string $field): ?float => ($v = $get($field)) !== '' ? (float) $v : null;

        return [
            'code'           => $code,
            'product_name'   => $name,
            'brand_name'     => $brand,
            'serving_size_g' => $servingG,
            'calories'       => $kcal !== '' ? (int) round((float) $kcal) : null,
            'protein_g'      => ($v = $num('proteins_100g'))      !== null ? round($v, 1)        : null,
            'carbs_g'        => ($v = $num('carbohydrates_100g')) !== null ? round($v, 1)        : null,
            'fat_g'          => ($v = $num('fat_100g'))           !== null ? round($v, 1)        : null,
            'fiber_g'        => ($v = $num('fiber_100g'))         !== null ? round($v, 1)        : null,
            'sugar_g'        => ($v = $num('sugars_100g'))        !== null ? round($v, 1)        : null,
            'sodium_mg'      => ($v = $num('sodium_100g'))        !== null ? (int) round($v * 1000) : null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
    }
}
