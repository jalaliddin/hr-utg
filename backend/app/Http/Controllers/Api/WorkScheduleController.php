<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin()
            ? $request->organization_id
            : $user->organization_id;

        $schedules = WorkSchedule::query()
            ->with('organization:id,name')
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('organization_id')
            ->orderByDesc('is_default')
            ->get();

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id'        => ['required', 'exists:organizations,id'],
            'name'                   => ['required', 'string', 'max:100'],
            'work_start'             => ['required', 'date_format:H:i'],
            'work_end'               => ['required', 'date_format:H:i'],
            'lunch_minutes'          => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days'              => ['nullable', 'array'],
            'work_days.*'            => ['integer', 'min:1', 'max:7'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_default'             => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            WorkSchedule::where('organization_id', $data['organization_id'])->update(['is_default' => false]);
        }

        $schedule = WorkSchedule::create($data);

        return response()->json($schedule->load('organization:id,name'), 201);
    }

    public function update(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        $data = $request->validate([
            'name'                   => ['sometimes', 'string', 'max:100'],
            'work_start'             => ['sometimes', 'date_format:H:i'],
            'work_end'               => ['sometimes', 'date_format:H:i'],
            'lunch_minutes'          => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days'              => ['nullable', 'array'],
            'work_days.*'            => ['integer', 'min:1', 'max:7'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_default'             => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            WorkSchedule::where('organization_id', $workSchedule->organization_id)
                ->where('id', '!=', $workSchedule->id)
                ->update(['is_default' => false]);
        }

        $workSchedule->update($data);

        return response()->json($workSchedule->load('organization:id,name'));
    }

    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        $workSchedule->delete();

        return response()->json(null, 204);
    }
}
