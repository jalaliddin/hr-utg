<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin()
            ? $request->organization_id
            : $user->organization_id;

        $departments = Department::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->withCount('positions')
            ->withCount('employees')
            ->with('organization:id,name')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name'            => ['required', 'string', 'max:200'],
        ]);

        $department = Department::create($data);

        return response()->json($department->load('organization'), 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department->update($data);

        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            return response()->json(['message' => "Bu bo'limda xodimlar mavjud, o'chirib bo'lmaydi."], 422);
        }

        $department->delete();

        return response()->json(null, 204);
    }

    /** Bo'lim lavozimlari */
    public function positions(Department $department): JsonResponse
    {
        return response()->json(
            $department->positions()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function storePosition(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $position = $department->positions()->create($data);

        return response()->json($position, 201);
    }

    public function updatePosition(Request $request, Department $department, Position $position): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $position->update($data);

        return response()->json($position);
    }

    public function destroyPosition(Department $department, Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            return response()->json(['message' => "Bu lavozimda xodimlar mavjud, o'chirib bo'lmaydi."], 422);
        }

        $position->delete();

        return response()->json(null, 204);
    }
}
