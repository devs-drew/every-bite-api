<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SeedProducts extends Command
{
    protected $signature = 'products:seed {--max-pages=500 : Maximum pages to fetch (1000 products/page)}';
    protected $description = 'Seed the products table from OpenFoodFacts Philippines catalog';

    private const FIELDS = 'code,product_name,brands,serving_quantity,nutriments';
    private const PAGE_SIZE = 1000;

    private const UPDATE_COLS = [
        'product_name', 'brand_name', 'serving_size_g', 'calories',
        'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg', 'updated_at',
    ];

    public function handle(): int
    {
        $maxPages = (int) $this->option('max-pages');
        $imported = 0;
        $skipped = 0;

        $this->info('Fetching page 1 to determine total page count...');
        $first = $this->fetchPage(1);
        if (!$first) {
            $this->error('Failed to reach ph.openfoodfacts.org. Check your connection.');
            return Command::FAILURE;
        }

        $totalPages = min((int) ($first['page_count'] ?? 1), $maxPages);
        $this->info("Total pages: {$totalPages} (capped at {$maxPages})");

        $batch = [];
        $this->processPage($first['products'] ?? [], $batch, $imported, $skipped);

        for ($page = 2; $page <= $totalPages; $page++) {
            $data = $this->fetchPage($page);
            if (!$data) {
                $this->warn("Page {$page} failed — skipping.");
                continue;
            }

            $this->processPage($data['products'] ?? [], $batch, $imported, $skipped);

            if (count($batch) >= 500) {
                DB::table('products')->upsert($batch, ['code'], self::UPDATE_COLS);
                $batch = [];
            }

            if ($page % 10 === 0) {
                $this->info("Page {$page}/{$totalPages} — {$imported} imported, {$skipped} skipped");
            }
        }

        if ($batch) {
            DB::table('products')->upsert($batch, ['code'], self::UPDATE_COLS);
        }

        $this->info("Done. {$imported} products imported, {$skipped} skipped (missing name/code).");
        return Command::SUCCESS;
    }

    private function fetchPage(int $page): ?array
    {
        try {
            $res = Http::timeout(30)->get('https://ph.openfoodfacts.org/cgi/search.pl', [
                'action' => 'process',
                'json' => 1,
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
                'fields' => self::FIELDS,
            ]);

            return $res->successful() ? $res->json() : null;
        } catch (ConnectionException) {
            return null;
        }
    }

    private function processPage(array $products, array &$batch, int &$imported, int &$skipped): void
    {
        foreach ($products as $p) {
            $row = ProductController::offToRow($p);
            if (!$row) {
                $skipped++;
                continue;
            }
            $batch[] = $row;
            $imported++;
        }
    }
}
