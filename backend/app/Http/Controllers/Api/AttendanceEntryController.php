<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEntry;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\MonthlyTabel;
use App\Services\TabelCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceEntryController extends Controller
{
    public function __construct(private readonly TabelCalculatorService $calculator) {}

    /**
     * Xodim + sana uchun kiritilgan yozuvlarni olish.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin() ? $request->organization_id : $user->organization_id;

        $entries = AttendanceEntry::query()
            ->with(['employee:id,first_name,last_name,middle_name,department,position', 'createdBy:id,name'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->date, fn ($q) => $q->where('work_date', $request->date))
            ->when($request->year && $request->month, fn ($q) => $q
                ->whereYear('work_date', $request->year)
                ->whereMonth('work_date', $request->month)
            )
            ->orderBy('work_date')
            ->get();

        return response()->json($entries);
    }

    /**
     * Bitta yozuv kiritish (upsert: bir xodim + bir kun + bir kod uchun).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'code' => ['required', 'string', 'in:Б,К,О,Р,ЧБ,У,А,Я,С,В,Н,П'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'note' => ['nullable', 'string', 'max:500'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'document_type' => ['nullable', 'string', 'max:50'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        // Org admin faqat o'z tashkilotiga kirita oladi
        if (! $user->isSuperAdmin() && $employee->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Ruxsat yo\'q'], 403);
        }

        $entry = DB::transaction(function () use ($data, $employee, $user) {
            return AttendanceEntry::updateOrCreate(
                [
                    'employee_id' => $data['employee_id'],
                    'work_date' => $data['work_date'],
                    'code' => $data['code'],
                ],
                array_merge($data, [
                    'organization_id' => $employee->organization_id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'source' => 'manual',
                ])
            );
        });

        return response()->json($entry->load('employee:id,first_name,last_name'), 201);
    }

    /**
     * Ko'p xodim + ko'p sana uchun ommaviy kiritish.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.employee_id' => ['required', 'exists:employees,id'],
            'entries.*.work_date' => ['required', 'date'],
            'entries.*.code' => ['required', 'string', 'in:Б,К,О,Р,ЧБ,У,А,Я,С,В,Н,П'],
            'entries.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.days' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'entries.*.note' => ['nullable', 'string', 'max:500'],
            'entries.*.document_number' => ['nullable', 'string', 'max:100'],
            'entries.*.document_date' => ['nullable', 'date'],
            'entries.*.document_type' => ['nullable', 'string', 'max:50'],
        ]);

        $employeeIds = collect($data['entries'])->pluck('employee_id')->unique();
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');

        // Ruxsat tekshiruvi
        if (! $user->isSuperAdmin()) {
            foreach ($employees as $emp) {
                if ($emp->organization_id !== $user->organization_id) {
                    return response()->json(['message' => 'Ruxsat yo\'q'], 403);
                }
            }
        }

        $saved = 0;
        DB::transaction(function () use ($data, $employees, $user, &$saved) {
            foreach ($data['entries'] as $row) {
                $emp = $employees->get($row['employee_id']);
                if (! $emp) {
                    continue;
                }

                AttendanceEntry::updateOrCreate(
                    [
                        'employee_id' => $row['employee_id'],
                        'work_date' => $row['work_date'],
                        'code' => $row['code'],
                    ],
                    array_merge($row, [
                        'organization_id' => $emp->organization_id,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                        'source' => 'manual',
                    ])
                );
                $saved++;
            }
        });

        return response()->json(['saved' => $saved]);
    }

    /**
     * Yozuvni yangilash.
     */
    public function update(Request $request, AttendanceEntry $entry): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $entry->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Ruxsat yo\'q'], 403);
        }

        if ($entry->is_approved && $user->isViewer()) {
            return response()->json(['message' => 'Tasdiqlangan yozuvni o\'zgartirish mumkin emas'], 403);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'in:Б,К,О,Р,ЧБ,У,А,Я,С,В,Н,П'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'note' => ['nullable', 'string', 'max:500'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'document_type' => ['nullable', 'string', 'max:50'],
        ]);

        $entry->update(array_merge($data, ['updated_by' => $user->id]));

        return response()->json($entry->fresh());
    }

    /**
     * Yozuvni o'chirish.
     */
    public function destroy(Request $request, AttendanceEntry $entry): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $entry->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Ruxsat yo\'q'], 403);
        }

        if ($entry->is_approved && ! $user->isSuperAdmin() && ! $user->isOrgAdmin()) {
            return response()->json(['message' => 'Tasdiqlangan yozuvni o\'chirish mumkin emas'], 403);
        }

        $entry->delete();

        return response()->json(null, 204);
    }

    /**
     * Oylik tabel — har bir xodim uchun kunlik ma'lumotlar + yig'indi.
     */
    public function monthlyTabel(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin() ? $request->organization_id : $user->organization_id;

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $department = $request->get('department');

        if (! $orgId) {
            return response()->json(['message' => 'Tashkilot tanlanmagan'], 422);
        }

        $employees = Employee::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->when($department, fn ($q) => $q->where('department', $department))
            ->orderBy('last_name')
            ->get();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $holidays = $this->calculator->getHolidayDates($year, $month);

        // Barcha attendance_entries oy uchun
        $entries = AttendanceEntry::query()
            ->where('organization_id', $orgId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('employee_id');

        // Qurilmadan kelgan daily_attendance
        $deviceData = DailyAttendance::query()
            ->where('organization_id', $orgId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->groupBy('employee_id');

        $rows = $employees->map(function (Employee $emp) use ($entries, $deviceData, $daysInMonth, $year, $month, $holidays) {
            $empEntries = $entries->get($emp->id, collect())->keyBy(fn ($e) => Carbon::parse($e->work_date)->day);
            $empDevice = $deviceData->get($emp->id, collect())->keyBy(fn ($d) => Carbon::parse($d->work_date)->day);

            $cells = [];
            $summary = ['Б' => 0, 'К' => 0, 'О' => 0, 'Р' => 0, 'ЧБ' => 0, 'У' => 0, 'А' => 0,
                'Я' => 0, 'С' => 0, 'В' => 0, 'Н' => 0, 'П' => 0, 'work_days' => 0];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = Carbon::createFromDate($year, $month, $d);
                $isWeekend = $date->isWeekend();
                $isHoliday = in_array($d, $holidays);

                $entryDay = $empEntries->get($d);
                $deviceDay = $empDevice->get($d);

                $cell = ['is_holiday' => $isHoliday, 'is_weekend' => $isWeekend, 'entry' => null, 'device' => null];

                if ($entryDay) {
                    $cell['entry'] = [
                        'id' => $entryDay->id,
                        'code' => $entryDay->code,
                        'hours' => $entryDay->hours,
                        'days' => $entryDay->days,
                        'is_approved' => $entryDay->is_approved,
                    ];

                    // Yig'indiga qo'shish
                    $code = $entryDay->code;
                    if (isset($summary[$code])) {
                        $summary[$code] += in_array($code, AttendanceEntry::ABSENT_CODES)
                            ? (float) ($entryDay->days ?? 1)
                            : (float) ($entryDay->hours ?? 8);
                    }
                    if ($code === 'Я' && ! $isWeekend && ! $isHoliday) {
                        $summary['work_days']++;
                    }
                } elseif ($deviceDay) {
                    $hours = round($deviceDay->work_minutes / 60, 2);
                    $cell['device'] = [
                        'status' => $deviceDay->status,
                        'first_entry' => $deviceDay->first_entry,
                        'last_exit' => $deviceDay->last_exit,
                        'hours' => $hours,
                    ];
                    if ($hours > 0) {
                        $summary['Я'] += $hours;
                        if (! $isWeekend && ! $isHoliday) {
                            $summary['work_days']++;
                        }
                    }
                }

                $cells[$d] = $cell;
            }

            return [
                'employee' => [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'department' => $emp->department,
                    'position' => $emp->position,
                ],
                'cells' => $cells,
                'summary' => $summary,
            ];
        });

        return response()->json([
            'rows' => $rows,
            'days_in_month' => $daysInMonth,
            'holidays' => $holidays,
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Oylik tabelni hisoblash va monthly_tabel ga saqlash.
     */
    public function calculateMonthly(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin() ? $request->organization_id : $user->organization_id;

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        if (! $orgId) {
            return response()->json(['message' => 'Tashkilot tanlanmagan'], 422);
        }

        $result = $this->calculator->calculate($orgId, $year, $month);

        return response()->json($result);
    }

    /**
     * Tabelni tasdiqlash.
     */
    public function approveTabel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->isOrgAdmin()) {
            return response()->json(['message' => 'Ruxsat yo\'q'], 403);
        }

        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if (! $user->isSuperAdmin() && $data['organization_id'] != $user->organization_id) {
            return response()->json(['message' => 'Ruxsat yo\'q'], 403);
        }

        $count = MonthlyTabel::where('organization_id', $data['organization_id'])
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->where('status', '!=', 'approved')
            ->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

        return response()->json(['approved' => $count]);
    }
}
