<?php

namespace App\Exports;

use App\Models\DailyAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private int $year,
        private int $month,
        private ?int $organizationId = null
    ) {}

    public function collection()
    {
        $daysInMonth = Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId))
            ->with('organization:id,name')
            ->orderBy('last_name')
            ->get();

        $attendance = DailyAttendance::query()
            ->whereYear('work_date', $this->year)
            ->whereMonth('work_date', $this->month)
            ->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId))
            ->get()
            ->groupBy('employee_id');

        $statusMap = [
            'present' => 'K',
            'absent' => 'B',
            'late' => 'KK',
            'half_day' => 'YK',
            'business_trip' => 'X',
            'leave' => 'T',
            'holiday' => 'D',
        ];

        return $employees->map(function (Employee $employee) use ($attendance, $daysInMonth, $statusMap) {
            $records = $attendance->get($employee->id, collect());
            $byDay = $records->keyBy(fn ($r) => Carbon::parse($r->work_date)->day);

            $row = [
                $employee->full_name,
                $employee->position,
                $employee->organization->name ?? '',
            ];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $rec = $byDay->get($d);
                $row[] = $rec ? ($statusMap[$rec->status] ?? '?') : '';
            }

            $row[] = $records->whereIn('status', ['present', 'late'])->count();
            $row[] = round($records->sum('work_minutes') / 60, 1);

            return $row;
        });
    }

    public function headings(): array
    {
        $daysInMonth = Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;

        $headings = ['F.I.O.', 'Lavozim', 'Tashkilot'];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $headings[] = $d;
        }

        $headings[] = 'Ish kuni';
        $headings[] = 'Soat';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
