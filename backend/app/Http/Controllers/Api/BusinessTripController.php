<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessTrip;
use App\Models\BusinessTripDestination;
use App\Services\AutoTripService;
use App\Services\BusinessTripAttendanceService;
use App\Services\BusinessTripHikvisionService;
use App\Services\BusinessTripPdfService;
use App\Services\CertificateNumberService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BusinessTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $trips = BusinessTrip::query()
            ->with(['employee:id,first_name,last_name,position', 'organization:id,name,code', 'approvedBy:id,name', 'destinations:id,business_trip_id,organization_id,arrival_date,departure_date'])
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                // O'z tashkilotining safarlar YOKI destinatsiyada o'z tashkiloti bo'lgan safarlar
                $orgId = $user->organization_id;
                $q->where(function ($q2) use ($orgId) {
                    $q2->where('organization_id', $orgId)
                        ->orWhereHas('destinations', fn ($d) => $d->where('organization_id', $orgId));
                });
            })
            ->when($user->isSuperAdmin() && $request->input('organization_id'), fn ($q) => $q->where('organization_id', $request->input('organization_id')))
            ->when($request->input('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->input('from'), fn ($q) => $q->where('start_date', '>=', $request->input('from')))
            ->when($request->input('to'), fn ($q) => $q->where('end_date', '<=', $request->input('to')))
            ->orderByDesc('start_date')
            ->paginate($request->input('per_page', 20));

        return response()->json($trips);
    }

    public function store(Request $request, CertificateNumberService $certService): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'destination' => ['nullable', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'transport' => ['nullable', 'in:car,train,plane,bus,other'],
            'description' => ['nullable', 'string'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'order_date' => ['nullable', 'date'],
            'passport_series' => ['nullable', 'string', 'max:50'],
            'service_id_number' => ['nullable', 'string', 'max:50'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.organization_id' => ['required', 'exists:organizations,id'],
            'destinations.*.arrival_date' => ['nullable', 'date'],
            'destinations.*.departure_date' => ['nullable', 'date'],
            'destinations.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['days_count'] = (int) now()->parse($data['start_date'])->diffInDays($data['end_date']) + 1;

        // Sertifikat raqami generatsiya
        $cert = $certService->generate();
        $data['certificate_number'] = $cert['number'];
        $data['certificate_serial'] = $cert['serial'];
        $data['certificate_year'] = $cert['year'];

        $destinations = $data['destinations'] ?? [];
        unset($data['destinations']);

        $trip = BusinessTrip::create($data);

        foreach ($destinations as $idx => $dest) {
            $trip->destinations()->create([
                'organization_id' => $dest['organization_id'],
                'order_index' => $idx,
                'arrival_date' => $dest['arrival_date'] ?? null,
                'departure_date' => $dest['departure_date'] ?? null,
                'note' => $dest['note'] ?? null,
            ]);
        }

        return response()->json($trip->load(['employee', 'organization', 'destinations.organization']), 201);
    }

    public function show(BusinessTrip $businessTrip): JsonResponse
    {
        return response()->json(
            $businessTrip->load(['employee', 'organization', 'approvedBy', 'destinations.organization'])
        );
    }

    public function update(Request $request, BusinessTrip $businessTrip): JsonResponse
    {
        $data = $request->validate([
            'destination' => ['nullable', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'transport' => ['nullable', 'in:car,train,plane,bus,other'],
            'description' => ['nullable', 'string'],
            'order_number' => ['nullable', 'string'],
            'order_date' => ['nullable', 'date'],
            'passport_series' => ['nullable', 'string', 'max:50'],
            'service_id_number' => ['nullable', 'string', 'max:50'],
            'destinations' => ['nullable', 'array'],
            'destinations.*.id' => ['nullable', 'exists:business_trip_destinations,id'],
            'destinations.*.organization_id' => ['required', 'exists:organizations,id'],
            'destinations.*.arrival_date' => ['nullable', 'date'],
            'destinations.*.departure_date' => ['nullable', 'date'],
            'destinations.*.arrival_signed_by' => ['nullable', 'string', 'max:200'],
            'destinations.*.departure_signed_by' => ['nullable', 'string', 'max:200'],
            'destinations.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['start_date'], $data['end_date'])) {
            $data['days_count'] = (int) now()->parse($data['start_date'])->diffInDays($data['end_date']) + 1;
        }

        if (isset($data['daily_allowance']) && isset($data['days_count'])) {
            $data['total_amount'] = $data['daily_allowance'] * $data['days_count'];
        }

        $destinations = $data['destinations'] ?? null;
        unset($data['destinations']);

        $businessTrip->update($data);

        if ($destinations !== null) {
            // Remove destinations not in updated list
            $keepIds = collect($destinations)->pluck('id')->filter()->values()->all();
            $businessTrip->destinations()->whereNotIn('id', $keepIds)->delete();

            foreach ($destinations as $idx => $dest) {
                if (! empty($dest['id'])) {
                    BusinessTripDestination::where('id', $dest['id'])->update([
                        'organization_id' => $dest['organization_id'],
                        'order_index' => $idx,
                        'arrival_date' => $dest['arrival_date'] ?? null,
                        'departure_date' => $dest['departure_date'] ?? null,
                        'arrival_signed_by' => $dest['arrival_signed_by'] ?? null,
                        'departure_signed_by' => $dest['departure_signed_by'] ?? null,
                        'note' => $dest['note'] ?? null,
                    ]);
                } else {
                    $businessTrip->destinations()->create([
                        'organization_id' => $dest['organization_id'],
                        'order_index' => $idx,
                        'arrival_date' => $dest['arrival_date'] ?? null,
                        'departure_date' => $dest['departure_date'] ?? null,
                        'note' => $dest['note'] ?? null,
                    ]);
                }
            }
        }

        return response()->json($businessTrip->load(['employee', 'organization', 'destinations.organization']));
    }

    public function destroy(BusinessTrip $businessTrip): JsonResponse
    {
        $businessTrip->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, BusinessTrip $businessTrip, BusinessTripHikvisionService $hikvisionService, BusinessTripAttendanceService $attendanceService, AutoTripService $autoTripService): JsonResponse
    {
        $businessTrip->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        // DailyAttendance: safari kunlarini belgilash (mavjud funksiya)
        $attendanceService->markTripDays($businessTrip);

        // attendance_entries: К kodli yozuvlar yaratish
        $autoTripService->createTripEntries($businessTrip->fresh());

        // Qurilmalarga yuklash
        if ($businessTrip->destinations()->exists()) {
            $hikvisionService->pushTripToDevices($businessTrip);
        }

        return response()->json($businessTrip->load(['employee', 'approvedBy', 'destinations']));
    }

    public function reject(Request $request, BusinessTrip $businessTrip, BusinessTripAttendanceService $attendanceService, AutoTripService $autoTripService): JsonResponse
    {
        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'max:500'],
        ]);

        $businessTrip->update([
            'status' => 'rejected',
            'reject_reason' => $data['reject_reason'],
            'approved_by' => $request->user()->id,
        ]);

        // DailyAttendance: safari kunlarini olib tashlash
        $attendanceService->removeTripDays($businessTrip);

        // attendance_entries: К yozuvlarini o'chirish
        $autoTripService->removeTripEntries($businessTrip);

        return response()->json($businessTrip);
    }

    public function complete(Request $request, BusinessTrip $businessTrip, BusinessTripHikvisionService $hikvisionService, BusinessTripAttendanceService $attendanceService): JsonResponse
    {
        $user = $request->user();

        // Faqat safari tashkilotining admini yoki super_admin yakunlashi mumkin
        if (! $user->isSuperAdmin() && $businessTrip->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Ruxsat yo\'q: bu safarni faqat tashkilot admini yakunlay oladi.'], 403);
        }

        $businessTrip->update([
            'status' => 'completed',
            'returned_at' => now(),
        ]);

        // Qaytgan kundan keyingi kunlarni tabeldan olib tashlash (muddatidan oldin qaytgan holat)
        $attendanceService->handleTripCompletion($businessTrip->fresh());

        // Xodimni destinatsiya qurilmalaridan o'chirish
        if ($businessTrip->destinations()->exists()) {
            $hikvisionService->removeFromDestinationDevices($businessTrip);
        }

        return response()->json($businessTrip);
    }

    public function generatePdf(BusinessTrip $businessTrip, BusinessTripPdfService $pdfService): Response
    {
        return $pdfService->download($businessTrip);
    }

    public function extendTrip(Request $request, BusinessTrip $businessTrip, BusinessTripAttendanceService $attendanceService, AutoTripService $autoTripService): JsonResponse
    {
        $data = $request->validate([
            'extension_days' => ['required', 'integer', 'min:1'],
            'extension_order_number' => ['nullable', 'string', 'max:100'],
            'extension_order_date' => ['nullable', 'date'],
            'extension_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $oldEndDate = Carbon::parse($businessTrip->extended_end_date ?? $businessTrip->end_date)->toDateString();
        $newEndDate = Carbon::parse($businessTrip->end_date)->addDays($data['extension_days']);

        $businessTrip->update([
            'extension_days' => $data['extension_days'],
            'extended_end_date' => $newEndDate,
            'extension_order_number' => $data['extension_order_number'] ?? null,
            'extension_order_date' => $data['extension_order_date'] ?? null,
            'extension_reason' => $data['extension_reason'] ?? null,
        ]);

        // DailyAttendance: barcha safari kunlarini yangilash
        $attendanceService->markTripDays($businessTrip->fresh());

        // attendance_entries: yangi kunlarga К yozuvlar qo'shish
        $autoTripService->extendTripEntries($businessTrip->fresh(), $oldEndDate);

        return response()->json($businessTrip->load(['employee', 'organization']));
    }

    public function pushStatus(BusinessTrip $businessTrip): JsonResponse
    {
        return response()->json([
            'device_push_status' => $businessTrip->device_push_status,
            'device_pushed_at' => $businessTrip->device_pushed_at,
            'device_push_log' => $businessTrip->device_push_log,
            'destinations' => $businessTrip->destinations()->with('organization:id,name')->get([
                'id', 'organization_id', 'push_status', 'pushed_at', 'push_error', 'retry_count',
            ]),
        ]);
    }

    public function retryPush(BusinessTrip $businessTrip, BusinessTripHikvisionService $hikvisionService): JsonResponse
    {
        $hikvisionService->pushTripToDevices($businessTrip->fresh(['destinations']));

        return response()->json([
            'message' => 'Qayta yuklash boshlandi.',
            'device_push_status' => $businessTrip->fresh()->device_push_status,
        ]);
    }
}
