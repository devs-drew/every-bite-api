<?php

namespace App\Http\Controllers;

use App\Models\CustomFood;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $q = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $customFoods = $request->user()->customFoods()
            ->where('food_name', 'like', '%' . $q . '%')
            ->orderBy('food_name')
            ->get()
            ->map(fn(CustomFood $f) => $this->formatCustomFood($f));

        $products = Product::where('product_name', 'like', '%' . $q . '%')
            ->orWhere('brand_name', 'like', '%' . $q . '%')
            ->orderBy('product_name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn(Product $p) => $this->formatProduct($p));

        return response()->json([
            'products' => array_merge($customFoods->all(), $products->all()),
            'failed' => false,
        ]);
    }

    public function barcode(Request $request, string $code)
    {
        $product = Product::where('code', $code)->first();
        if ($product) {
            return response()->json($this->formatProduct($product));
        }

        $customFood = $request->user()->customFoods()
            ->where('barcode', $code)->first();
        if ($customFood) {
            return response()->json($this->formatCustomFood($customFood));
        }

        // OFF fallback — cache result for future lookups
        $offRes = Http::timeout(10)
            ->withHeaders(['User-Agent' => 'EveryBite/1.0 (andrewferrer80@gmail.com)'])
            ->get(
                "https://world.openfoodfacts.org/api/v3/product/{$code}.json",
                ['fields' => 'code,product_name,brands,serving_quantity,nutriments']
            );

        if (!$offRes->successful() || $offRes->json('result.id') !== 'product_found') {
            return response()->json(null, 404);
        }

        $row = self::offToRow($offRes->json('product') ?? []);
        if (!$row) {
            return response()->json(null, 404);
        }

        $updateCols = [
            'product_name', 'brand_name', 'serving_size_g', 'calories',
            'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg', 'updated_at',
        ];
        Product::upsert([$row], ['code'], $updateCols);

        return response()->json($this->formatProduct(
            Product::where('code', $code)->firstOrFail()
        ));
    }

    private function formatProduct(Product $p): array
    {
        return [
            'off_product_id' => $p->code,
            'food_name' => $p->product_name,
            'brand_name' => $p->brand_name ?: null,
            'barcode' => $p->code,
            'serving_size_g' => $p->serving_size_g ?? 100,
            'nutrients_per_100g' => [
                'calories' => $p->calories ?? 0,
                'protein_g' => $p->protein_g,
                'carbs_g' => $p->carbs_g,
                'fat_g' => $p->fat_g,
                'fiber_g' => $p->fiber_g,
                'sugar_g' => $p->sugar_g,
                'sodium_mg' => $p->sodium_mg,
            ],
        ];
    }

    private function formatCustomFood(CustomFood $f): array
    {
        return [
            'off_product_id' => 'custom-' . $f->id,
            'food_name' => $f->food_name,
            'brand_name' => $f->brand_name ?: null,
            'barcode' => $f->barcode ?: null,
            'serving_size_g' => $f->serving_size_g ?? 100,
            'nutrients_per_100g' => [
                'calories' => $f->calories ?? 0,
                'protein_g' => $f->protein_g,
                'carbs_g' => $f->carbs_g,
                'fat_g' => $f->fat_g,
                'fiber_g' => $f->fiber_g,
                'sugar_g' => $f->sugar_g,
                'sodium_mg' => $f->sodium_mg,
            ],
        ];
    }

    // Static so SeedProducts command can call it without instantiating the controller.
    public static function offToRow(array $p): ?array
    {
        $name = trim($p['product_name'] ?? '');
        if (!$name || empty($p['code'])) return null;

        $n = $p['nutriments'] ?? [];
        $kcal = $n['energy-kcal_100g']
            ?? (isset($n['energy_100g']) ? $n['energy_100g'] / 4.184 : null)
            ?? (isset($n['energy-kj_100g']) ? $n['energy-kj_100g'] / 4.184 : null);

        $brands = $p['brands'] ?? null;
        $brand = is_array($brands) ? ($brands[0] ?? null) : explode(',', $brands ?? '')[0];
        $brand = trim($brand ?? '') ?: null;

        return [
            'code' => (string) $p['code'],
            'product_name' => $name,
            'brand_name' => $brand,
            'serving_size_g' => is_numeric($p['serving_quantity'] ?? null) ? (float) $p['serving_quantity'] : null,
            'calories' => $kcal !== null ? (int) round((float) $kcal) : null,
            'protein_g' => isset($n['proteins_100g']) ? round((float) $n['proteins_100g'], 1) : null,
            'carbs_g' => isset($n['carbohydrates_100g']) ? round((float) $n['carbohydrates_100g'], 1) : null,
            'fat_g' => isset($n['fat_100g']) ? round((float) $n['fat_100g'], 1) : null,
            'fiber_g' => isset($n['fiber_100g']) ? round((float) $n['fiber_100g'], 1) : null,
            'sugar_g' => isset($n['sugars_100g']) ? round((float) $n['sugars_100g'], 1) : null,
            'sodium_mg' => isset($n['sodium_100g']) ? (int) round((float) $n['sodium_100g'] * 1000) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
