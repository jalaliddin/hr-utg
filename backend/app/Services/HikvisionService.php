<?php

namespace App\Services;

use App\Models\HikvisionDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class HikvisionService
{
    private string $baseUrl;

    public function __construct(private HikvisionDevice $device)
    {
        $this->baseUrl = "http://{$device->ip_address}:{$device->port}";
    }

    /**
     * Qurilma ma'lumotini olish (XML javob).
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function getDeviceInfo(): array
    {
        return $this->getXml('/ISAPI/System/deviceInfo');
    }

    /**
     * Qurilma vaqtini olish.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function getDeviceTime(): array
    {
        return $this->getXml('/ISAPI/System/time');
    }

    /**
     * Kirish/chiqish hodisalarini vaqt oralig'i bo'yicha olish (PULL).
     * Endpoint: POST /ISAPI/AccessControl/AcsEvent?format=json
     * major=5 — kirish-chiqish hodisalari.
     *
     * @param  string  $startTime  Format: 2024-01-01T00:00:00+05:00
     * @param  string  $endTime    Format: 2024-01-01T23:59:59+05:00
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function getACSEvents(
        string $startTime,
        string $endTime,
        int $searchResultPosition = 0,
        int $maxResults = 1000
    ): array {
        $body = [
            'AcsEventCond' => [
                'searchID' => uniqid('s'),
                'searchResultPosition' => $searchResultPosition,
                'maxResults' => $maxResults,
                'major' => 5,
                'minor' => 0,
                'startTime' => $startTime,
                'endTime' => $endTime,
            ],
        ];

        return $this->postJson('/ISAPI/AccessControl/AcsEvent?format=json', $body);
    }

    /**
     * Xodimlar ro'yxatini qurilmadan olish (JSON).
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function getPersonList(int $offset = 0, int $limit = 30): array
    {
        $body = [
            'UserInfoSearchCond' => [
                'searchID' => uniqid('u'),
                'searchResultPosition' => $offset,
                'maxResults' => $limit,
            ],
        ];

        return $this->postJson('/ISAPI/AccessControl/UserInfo/Search?format=json', $body);
    }

    /**
     * Xodimni qurilmaga yuklash yoki yangilash.
     * PUT /ISAPI/AccessControl/UserInfo/Record?format=json
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function pushEmployee(\App\Models\Employee $employee): array
    {
        if (empty($employee->hikvision_person_id)) {
            return ['success' => false, 'error' => 'hikvision_person_id not assigned'];
        }

        $hikId = (string) $employee->hikvision_person_id;
        $groupNo = $employee->organization?->hikvision_group_no;

        $fullName = trim(implode(' ', array_filter([
            $employee->last_name,
            $employee->first_name,
            $employee->middle_name,
        ])));

        $displayName = $fullName ?: $hikId;

        $baseInfo = [
            'employeeNo'     => $hikId,
            'name'           => $displayName,
            'userType'       => 'normal',
            'Valid'          => [
                'enable'    => (bool) $employee->is_active,
                'beginTime' => '2000-01-01T00:00:00',
                'endTime'   => '2037-12-31T23:59:59',
                'timeType'  => 'local',
            ],
            'localUIRight'   => false,
            'userVerifyMode' => '',
        ];

        // belongGroup faqat POST (create) da ishlaydi
        $createInfo = $groupNo
            ? [...$baseInfo, 'belongGroup' => (string) $groupNo]
            : $baseInfo;

        // Avval yaratishga urinib ko'ramiz (POST)
        $result = $this->postJson('/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => $createInfo]);

        // Agar allaqachon mavjud bo'lsa — o'chirib qayta yaratamiz
        if (! $result['success'] && str_contains($result['body'] ?? '', 'employeeNoAlreadyExist')) {
            $this->deleteEmployee($hikId);
            $result = $this->postJson('/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => $createInfo]);
        }

        return $result;
    }

    /**
     * Xodim yuz rasmini qurilmaga yuklash.
     * POST /ISAPI/Intelligent/FDLib/FaceDataRecord?format=json
     * Multipart: JSON meta + rasm binary.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function pushEmployeePhoto(\App\Models\Employee $employee): array
    {
        if (! $employee->photo_path) {
            return ['success' => false, 'error' => 'No photo'];
        }

        $storagePath = storage_path('app/public/'.$employee->photo_path);

        if (! file_exists($storagePath)) {
            return ['success' => false, 'error' => 'Photo file not found'];
        }

        $meta = json_encode([
            'faceLibType' => 'blackFD',
            'FDID'        => '1',
            'FPID'        => $employee->hikvision_person_id
                ? (string) $employee->hikvision_person_id
                : (string) $employee->employee_id,
        ]);

        try {
            $response = Http::withDigestAuth($this->device->username, decrypt($this->device->password))
                ->timeout(20)
                ->connectTimeout(5)
                ->attach('FaceDataRecord', $meta, 'face.json', ['Content-Type' => 'application/json'])
                ->attach('img', file_get_contents($storagePath), basename($storagePath), ['Content-Type' => 'image/jpeg'])
                ->post($this->baseUrl.'/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json');

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return [
                'success' => false,
                'error'   => "HTTP {$response->status()}",
                'body'    => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::warning("Hikvision pushPhoto [{$this->device->id}] employee [{$employee->id}]: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Xodimni qurilmadan o'chirish.
     * PUT /ISAPI/AccessControl/UserInfo/Delete?format=json
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function deleteEmployee(string $employeeNo): array
    {
        $body = [
            'UserInfoDelCond' => [
                'EmployeeNoList' => [
                    ['employeeNo' => $employeeNo],
                ],
            ],
        ];

        return $this->putJson('/ISAPI/AccessControl/UserInfo/Delete?format=json', $body);
    }

    /**
     * Qurilmadagi barcha xodim raqamlarini olish (tekshirish uchun).
     *
     * @return array<int, string>
     */
    /**
     * Qurilmadagi barcha employeeNo larni qaytaradi.
     * Qurilmaga ulanib bo'lmasa null qaytaradi (bo'sh qurilma [] dan farq qiladi).
     */
    public function getDeviceEmployeeIds(): ?array
    {
        $ids = [];
        $offset = 0;
        $limit = 50;
        $firstRequest = true;

        do {
            $result = $this->getPersonList($offset, $limit);
            if (! $result['success']) {
                // Birinchi so'rovda xato — qurilma offline yoki ulanib bo'lmadi
                if ($firstRequest) {
                    return null;
                }
                break;
            }

            $firstRequest = false;
            $search = $result['data']['UserInfoSearch'] ?? [];
            $users  = $search['UserInfo'] ?? [];

            if (empty($users)) {
                break;
            }

            if (isset($users['employeeNo'])) {
                $users = [$users];
            }

            foreach ($users as $user) {
                $ids[] = (string) ($user['employeeNo'] ?? '');
            }

            $hasMore = strtoupper((string) ($search['responseStatusStrg'] ?? 'OK')) === 'MORE';
            $offset += count($users);
        } while ($hasMore);

        return array_values(array_filter($ids));
    }

    /**
     * AcsEvent javobidan hodisalar ro'yxatini ajratib olish.
     *
     * @param  array<string, mixed>  $data  postJson dan kelgan data
     * @return array<int, array<string, mixed>>
     */
    public function extractEvents(array $data): array
    {
        $acsEvent = $data['AcsEvent'] ?? [];
        $infoList = $acsEvent['InfoList'] ?? [];

        if (empty($infoList)) {
            return [];
        }

        // Bir elementli bo'lsa ham array tarzida qaytarish
        if (isset($infoList['major'])) {
            return [$infoList];
        }

        return array_values($infoList);
    }

    /**
     * "responseStatusStrg" qiymatini tekshirish.
     * "MORE" = yana sahifalar bor, "OK" / "NO MATCH" = tugadi.
     */
    public function hasMoreResults(array $data): bool
    {
        $status = strtoupper((string) ($data['AcsEvent']['responseStatusStrg'] ?? 'OK'));

        return $status === 'MORE';
    }

    /**
     * Hodisadan event type aniqlash.
     * DS-K1A340FWX: attendanceStatus = checkIn/checkOut/undefined
     * minor: 75 = authentication pass
     */
    public function resolveEventType(array $event): string
    {
        $attendanceStatus = strtolower((string) ($event['attendanceStatus'] ?? ''));

        return match ($attendanceStatus) {
            'checkin', 'breakin' => 'entry',
            'checkout', 'breakout', 'overtime' => 'exit',
            default => 'unknown',
        };
    }

    /**
     * GET XML so'rov — qurilma info va time uchun.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    private function getXml(string $path): array
    {
        try {
            $response = Http::withDigestAuth($this->device->username, decrypt($this->device->password))
                ->timeout(10)
                ->connectTimeout(5)
                ->get($this->baseUrl.$path);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $this->parseXml($response->body()),
                    'raw' => $response->body(),
                ];
            }

            return ['success' => false, 'error' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            Log::warning("Hikvision GET [{$this->device->id}] {$path}: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * POST JSON so'rov — hodisalar va xodimlar uchun.
     *
     * @param  array<string, mixed>  $body
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function postJson(string $path, array $body): array
    {
        try {
            $response = Http::withDigestAuth($this->device->username, decrypt($this->device->password))
                ->acceptJson()
                ->timeout(20)
                ->connectTimeout(5)
                ->post($this->baseUrl.$path, $body);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json() ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}",
                'body' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::warning("Hikvision POST [{$this->device->id}] {$path}: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * PUT JSON so'rov — xodim yaratish/yangilash/o'chirish uchun.
     *
     * @param  array<string, mixed>  $body
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    private function putJson(string $path, array $body): array
    {
        try {
            $response = Http::withDigestAuth($this->device->username, decrypt($this->device->password))
                ->acceptJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->put($this->baseUrl.$path, $body);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json() ?? [],
                ];
            }

            return [
                'success' => false,
                'error'   => "HTTP {$response->status()}",
                'body'    => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::warning("Hikvision PUT [{$this->device->id}] {$path}: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * XML stringni massivga o'tkazish.
     *
     * @return array<string, mixed>
     */
    private function parseXml(string $xml): array
    {
        if (empty(trim($xml))) {
            return [];
        }

        try {
            $xml = preg_replace('/\sxmlns[^=]*="[^"]*"/i', '', $xml) ?? $xml;
            $obj = new SimpleXMLElement($xml);
            $json = json_encode($obj);

            return $json ? (array) json_decode($json, true) : [];
        } catch (\Exception) {
            return [];
        }
    }
}
