<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $holidays = PublicHoliday::query()
            ->where('year', $year)
            ->orderBy('holiday_date')
            ->get();

        return response()->json($holidays);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:public_holidays,holiday_date'],
            'name' => ['required', 'string', 'max:200'],
            'is_recurring' => ['boolean'],
        ]);

        $data['year'] = (int) date('Y', strtotime($data['holiday_date']));

        $holiday = PublicHoliday::create($data);

        return response()->json($holiday, 201);
    }

    public function update(Request $request, PublicHoliday $holiday): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        $holiday->update($data);

        return response()->json($holiday);
    }

    public function destroy(PublicHoliday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(null, 204);
    }
}
