<?php

namespace App\Http\Controllers;

use App\Models\FoodLog;
use Illuminate\Http\Request;

class FoodLogController extends Controller
{
    private const MEALS = ['breakfast', 'lunch', 'dinner', 'snack'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'meal_type' => ['nullable', 'in:breakfast,lunch,dinner,snack'],
        ]);

        return $request->user()->foodLogs()
            ->where('logged_date', $data['date'])
            ->when($data['meal_type'] ?? null, fn ($q, $m) => $q->where('meal_type', $m))
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $log = $request->user()->foodLogs()->create($this->validateLog($request));

        return response()->json($log, 201);
    }

    public function update(Request $request, FoodLog $foodLog)
    {
        $this->authorizeOwner($request, $foodLog);
        $foodLog->update($this->validateLog($request, partial: true));

        return $foodLog->fresh();
    }

    public function destroy(Request $request, FoodLog $foodLog)
    {
        $this->authorizeOwner($request, $foodLog);
        $foodLog->delete();

        return response()->noContent();
    }

    // GET /api/food-logs/summary?date= -> DailySummary (stores/foodLog.ts)
    public function summary(Request $request)
    {
        $date = $request->validate(['date' => ['required', 'date']])['date'];
        $user = $request->user();
        $logs = $user->foodLogs()->where('logged_date', $date)->get();

        $sum = fn ($items, $col) => round($items->sum($col), 1);

        $byMeal = [];
        foreach (self::MEALS as $meal) {
            $m = $logs->where('meal_type', $meal);
            $byMeal[$meal] = [
                'calories' => (int) $m->sum('calories'),
                'protein_g' => $sum($m, 'protein_g'),
                'carbs_g' => $sum($m, 'carbs_g'),
                'fat_g' => $sum($m, 'fat_g'),
            ];
        }

        return [
            'date' => $date,
            'daily_target' => $user->daily_calorie_target,
            'totals' => [
                'calories' => (int) $logs->sum('calories'),
                'protein_g' => $sum($logs, 'protein_g'),
                'carbs_g' => $sum($logs, 'carbs_g'),
                'fat_g' => $sum($logs, 'fat_g'),
                'fiber_g' => $sum($logs, 'fiber_g'),
            ],
            'by_meal' => $byMeal,
        ];
    }

    // GET /api/food-logs/history?from=&to= -> [{ date, calories }]
    public function history(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        // ponytail: no server-side zero-fill; WeeklyChart maps by date.
        // Zero-fill here only if the chart shows gaps.
        return $request->user()->foodLogs()
            ->whereBetween('logged_date', [$data['from'], $data['to']])
            ->selectRaw('logged_date as date, sum(calories) as calories')
            ->groupBy('logged_date')
            ->orderBy('logged_date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('Y-m-d'),
                'calories' => (int) $row->calories,
            ]);
    }

    private function validateLog(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'food_name' => [$req, 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'off_product_id' => ['nullable', 'string', 'max:255'],
            'meal_type' => [$req, 'in:breakfast,lunch,dinner,snack'],
            'logged_date' => [$req, 'date'],
            'serving_qty' => ['nullable', 'numeric', 'min:0'],
            'serving_size_g' => [$req, 'numeric', 'min:0'],
            'calories' => [$req, 'integer', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'fiber_g' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function authorizeOwner(Request $request, FoodLog $foodLog): void
    {
        abort_unless($foodLog->user_id === $request->user()->id, 403);
    }
}
