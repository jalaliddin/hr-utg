<?php

namespace App\Services;

use App\Models\BusinessTrip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BusinessTripPdfService
{
    /**
     * Xizmat safari guvohnomasini PDF sifatida generatsiya qiladi va saqlaydi.
     *
     * @return string Storage disk ichidagi fayl yo'li
     */
    public function generate(BusinessTrip $trip): string
    {
        $trip->loadMissing(['employee', 'organization', 'destinations.organization']);

        $director = $trip->organization?->activeDirector;

        $pdf = Pdf::loadView('pdf.business_trip_certificate', [
            'trip' => $trip,
            'employee' => $trip->employee,
            'organization' => $trip->organization,
            'director' => $director,
        ])->setPaper('A4', 'portrait');

        $filename = 'business-trips/'.date('Y').'/'.$trip->id.'_'.time().'.pdf';

        Storage::disk('public')->put($filename, $pdf->output());

        $trip->update([
            'pdf_path' => $filename,
            'pdf_generated_at' => now(),
        ]);

        return $filename;
    }

    /**
     * PDF ni brauzerga download yoki inline ko'rsatish uchun response qaytaradi.
     */
    public function download(BusinessTrip $trip): Response
    {
        $trip->loadMissing(['employee', 'organization', 'destinations.organization']);

        $director = $trip->organization?->activeDirector;

        $pdf = Pdf::loadView('pdf.business_trip_certificate', [
            'trip' => $trip,
            'employee' => $trip->employee,
            'organization' => $trip->organization,
            'director' => $director,
        ])->setPaper('A4', 'portrait');

        $name = 'safari_'.$trip->certificate_serial.'_'.$trip->employee?->last_name.'.pdf';
        $name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $name);

        return $pdf->download($name);
    }
}
