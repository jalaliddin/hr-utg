<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SyncController extends Controller
{
    public function syncAll(Request $request): JsonResponse
    {
        $params = ['--device' => 'all'];

        if ($request->from) {
            $params['--from'] = $request->from;
        }

        if ($request->to) {
            $params['--to'] = $request->to;
        }

        Artisan::queue('attendance:sync', $params);

        return response()->json([
            'message' => 'Barcha qurilmalar sinxronizatsiyasi navbatga qo\'yildi.',
        ]);
    }

    public function status(): JsonResponse
    {
        $logs = SyncLog::query()
            ->with(['device:id,name,ip_address', 'organization:id,name,code'])
            ->orderByDesc('sync_started_at')
            ->limit(20)
            ->get();

        $running = SyncLog::query()->where('status', 'running')->count();

        return response()->json([
            'running' => $running,
            'logs' => $logs,
        ]);
    }
}
