<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class QrScannerService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function registerDevice(Request $request): array
    {
        $validated = $request->validate([
            'device_name' => ['nullable', 'string', 'max:190'],
        ]);
        [$tenantId, $userId, $deviceId, $role] = $this->scannerContext($request);
        abort_unless(in_array($role, $this->allScannerRoles(), true), 403, 'Role tenant aktif tidak dapat mendaftarkan perangkat pemindai.');
        $this->assertAllowedNetwork($request);

        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $deviceName = trim((string) ($validated['device_name'] ?? $request->header('X-Device-Name', 'Pesantenku Mobile')));
        $deviceName = $deviceName !== '' ? $deviceName : 'Pesantenku Mobile';

        $id = DB::transaction(function () use ($tenantId, $userId, $deviceId, $deviceName, $secret, $request) {
            DB::table('qr_scan_challenges')
                ->whereIn('scanner_device_id', DB::table('qr_scanner_devices')
                    ->select('id')
                    ->where('pesantren_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('device_id', $deviceId))
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            $identity = ['pesantren_id' => $tenantId, 'user_id' => $userId, 'device_id' => $deviceId];
            $attributes = [
                'device_name' => $deviceName,
                'secret_ciphertext' => Crypt::encryptString($secret),
                'status' => 'active',
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ];
            $existing = DB::table('qr_scanner_devices')->where($identity)->exists();
            if ($existing) {
                DB::table('qr_scanner_devices')->where($identity)->update($attributes);
            } else {
                DB::table('qr_scanner_devices')->insert(array_merge($identity, $attributes, ['created_at' => now()]));
            }
            $id = (int) DB::table('qr_scanner_devices')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->value('id');
            $this->audit($request, $tenantId, $userId, 'QR scanner device registered', [
                'scanner_device_id' => $id,
                'device_id_hash' => hash('sha256', $deviceId),
                'device_name' => $deviceName,
            ]);

            return $id;
        }, 3);

        return [
            'scanner_device_id' => $id,
            'scanner_secret' => $secret,
            'device_id' => $deviceId,
            'registered_at' => now()->toIso8601String(),
        ];
    }

    public function challenge(Request $request): array
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:64']]);
        [$tenantId, $userId, $deviceId, $role] = $this->scannerContext($request);
        $this->assertAllowedNetwork($request);
        $codeHash = hash('sha256', $validated['code']);
        $token = DB::table('qr_tokens')
            ->where('pesantren_id', $tenantId)
            ->where('code', $codeHash)
            ->where('status', 'active')
            ->first();
        abort_unless($token, 404, 'QR tidak ditemukan.');
        abort_if($token->expires_at && now()->greaterThan($token->expires_at), 422, 'QR sudah kedaluwarsa.');
        abort_unless(in_array($role, $this->scannerRoles((string) $token->purpose), true), 403, 'Role tenant aktif tidak dapat memindai QR untuk tujuan ini.');
        $this->assertScannerScope($token, $tenantId, $role, $userId);
        $device = $this->activeDevice($tenantId, $userId, $deviceId);

        $challengeId = (string) Str::uuid();
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = now()->addSeconds(max(10, min(90, (int) config('pesantrenku.qr.challenge_seconds', 30))));
        DB::transaction(function () use ($challengeId, $tenantId, $token, $device, $userId, $codeHash, $nonce, $expiresAt, $request) {
            DB::table('qr_scan_challenges')
                ->where('scanner_device_id', $device->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);
            DB::table('qr_scan_challenges')->insert([
                'id' => $challengeId,
                'pesantren_id' => $tenantId,
                'qr_token_id' => $token->id,
                'scanner_device_id' => $device->id,
                'scanner_user_id' => $userId,
                'code_hash' => $codeHash,
                'nonce_hash' => hash('sha256', $nonce),
                'ip_address' => (string) $request->ip(),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return [
            'challenge_id' => $challengeId,
            'nonce' => $nonce,
            'expires_at' => $expiresAt->toIso8601String(),
            'proof_algorithm' => 'HMAC-SHA256',
            'proof_message' => $this->proofMessage($challengeId, $nonce, $codeHash, $tenantId, $deviceId),
        ];
    }

    public function scan(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'challenge_id' => ['required', 'uuid'],
            'nonce' => ['required', 'string', 'min:32', 'max:128'],
            'proof' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ]);
        [$tenantId, $userId, $deviceId, $role] = $this->scannerContext($request);
        $this->assertAllowedNetwork($request);
        $codeHash = hash('sha256', $validated['code']);
        $device = $this->activeDevice($tenantId, $userId, $deviceId);
        $secret = Crypt::decryptString((string) $device->secret_ciphertext);
        $expected = hash_hmac('sha256', $this->proofMessage($validated['challenge_id'], $validated['nonce'], $codeHash, $tenantId, $deviceId), $secret);
        abort_unless(hash_equals($expected, strtolower($validated['proof'])), 403, 'Bukti pemindai tidak valid.');

        return DB::transaction(function () use ($request, $validated, $tenantId, $userId, $deviceId, $device, $role, $codeHash) {
            $challenge = DB::table('qr_scan_challenges')
                ->where('id', $validated['challenge_id'])
                ->where('pesantren_id', $tenantId)
                ->where('scanner_device_id', $device->id)
                ->where('scanner_user_id', $userId)
                ->lockForUpdate()
                ->first();
            abort_unless($challenge, 404, 'Challenge pemindai tidak ditemukan.');
            abort_unless($challenge->consumed_at === null, 409, 'Challenge pemindai sudah digunakan.');
            abort_if(now()->greaterThan($challenge->expires_at), 422, 'Challenge pemindai sudah kedaluwarsa.');
            abort_unless(hash_equals((string) $challenge->code_hash, $codeHash), 422, 'Challenge tidak terikat ke QR ini.');
            abort_unless(hash_equals((string) $challenge->nonce_hash, hash('sha256', $validated['nonce'])), 422, 'Nonce challenge tidak valid.');
            abort_unless(IpUtils::checkIp((string) $request->ip(), [(string) $challenge->ip_address]), 403, 'Jaringan pemindai berubah selama challenge.');

            $token = DB::table('qr_tokens')
                ->where('id', $challenge->qr_token_id)
                ->where('pesantren_id', $tenantId)
                ->where('code', $codeHash)
                ->lockForUpdate()
                ->first();
            abort_unless($token, 404, 'QR tidak ditemukan.');
            abort_unless($token->status === 'active', 422, 'QR sudah tidak aktif.');
            abort_if($token->expires_at && now()->greaterThan($token->expires_at), 422, 'QR sudah kedaluwarsa.');
            abort_unless(in_array($role, $this->scannerRoles((string) $token->purpose), true), 403, 'Role tenant aktif tidak dapat memindai QR untuk tujuan ini.');
            $this->assertScannerScope($token, $tenantId, $role, $userId);

            DB::table('qr_scan_challenges')->where('id', $challenge->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $updated = DB::table('qr_tokens')->where('id', $token->id)->where('status', 'active')->update([
                'status' => 'used',
                'scanned_at' => now(),
                'scanned_by' => $userId,
                'updated_at' => now(),
            ]);
            abort_unless($updated === 1, 409, 'QR telah diproses oleh pemindai lain.');

            DB::table('qr_scanner_devices')->where('id', $device->id)->update([
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);
            $sideEffect = $this->applyPurpose($token, $tenantId);
            $this->audit($request, $tenantId, $userId, 'QR scanned', [
                'code_hash' => $codeHash,
                'purpose' => $token->purpose,
                'scanner_device_id' => $device->id,
                'device_id_hash' => hash('sha256', $deviceId),
                'challenge_id' => $challenge->id,
                'side_effect' => $sideEffect,
            ]);

            return ['token_id' => $token->id, 'purpose' => $token->purpose, 'side_effect' => $sideEffect];
        }, 3);
    }

    private function scannerContext(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if($this->tenantContext->isSuperAdmin($request), 403, 'Super admin tidak memproses QR operasional tenant.');
        $deviceId = trim((string) $request->header('X-Device-Id'));
        abort_unless($request->is('api/*') && $deviceId !== '', 403, 'Pemindaian QR hanya tersedia melalui aplikasi mobile yang terikat perangkat.');

        return [
            (int) $this->tenantContext->requireActivePesantrenId($request),
            (int) $user->id,
            $deviceId,
            (string) $this->tenantContext->activeRole($request),
        ];
    }

    private function activeDevice(int $tenantId, int $userId, string $deviceId): object
    {
        $device = DB::table('qr_scanner_devices')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->first();
        abort_unless($device, 403, 'Perangkat pemindai belum terdaftar atau sudah dicabut.');

        return $device;
    }

    private function assertAllowedNetwork(Request $request): void
    {
        $ranges = collect(config('pesantrenku.qr.allowed_networks', []))->filter()->values()->all();
        abort_unless($ranges !== [], 503, 'Jaringan pemindai QR belum dikonfigurasi.');
        abort_unless(IpUtils::checkIp((string) $request->ip(), $ranges), 403, 'Pemindaian QR hanya diizinkan dari jaringan kampus yang terdaftar.');
    }

    private function proofMessage(string $challengeId, string $nonce, string $codeHash, int $tenantId, string $deviceId): string
    {
        return implode('|', [$challengeId, $nonce, $codeHash, (string) $tenantId, $deviceId]);
    }

    private function allScannerRoles(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn ($purpose) => $this->scannerRoles($purpose), ['identity', 'attendance', 'dorm-attendance']))));
    }

    private function scannerRoles(string $purpose): array
    {
        return match ($purpose) {
            'attendance' => ['guru', 'admin_akademik', 'owner_pesantren'],
            'dorm-attendance' => ['musyrif', 'owner_pesantren'],
            'identity' => ['guru', 'musyrif', 'klinik', 'admin_tu', 'admin_akademik', 'admin_keuangan', 'owner_pesantren'],
            default => [],
        };
    }

    private function applyPurpose(object $token, int $tenantId): array
    {
        if ($token->purpose === 'identity') {
            return ['type' => 'identity_verified', 'user_id' => (int) $token->user_id];
        }
        $student = DB::table('students')->where('pesantren_id', $tenantId)->where('user_id', $token->user_id)->where('status', 'active')->whereNull('deleted_at')->first();
        abort_unless($student, 422, 'QR tidak terhubung dengan data santri aktif.');
        if ($token->purpose === 'attendance') {
            DB::table('attendances')->upsert([[
                'pesantren_id' => $tenantId, 'student_id' => $student->id, 'class_id' => $student->class_id,
                'date' => now()->toDateString(), 'session' => 'qr', 'status' => 'hadir',
                'note' => 'Dicatat melalui QR dengan bukti perangkat dan jaringan', 'created_at' => now(), 'updated_at' => now(),
            ]], ['pesantren_id', 'student_id', 'date', 'session'], ['class_id', 'status', 'note', 'updated_at']);

            return ['type' => 'attendance_recorded', 'student_id' => (int) $student->id];
        }
        if ($token->purpose === 'dorm-attendance') {
            $roomId = DB::table('room_assignments')->where('pesantren_id', $tenantId)->where('student_id', $student->id)->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))->value('room_id');
            abort_unless($roomId, 422, 'Santri belum memiliki assignment kamar aktif.');
            DB::table('dorm_attendances')->upsert([[
                'pesantren_id' => $tenantId, 'student_id' => $student->id, 'room_id' => $roomId,
                'date' => now()->toDateString(), 'activity' => 'QR Check-in', 'status' => 'hadir',
                'created_at' => now(), 'updated_at' => now(),
            ]], ['pesantren_id', 'student_id', 'date', 'activity'], ['room_id', 'status', 'updated_at']);

            return ['type' => 'dorm_attendance_recorded', 'student_id' => (int) $student->id, 'room_id' => (int) $roomId];
        }
        abort(422, 'Purpose QR tidak didukung.');
    }

    private function assertScannerScope(object $token, int $tenantId, string $activeRole, int $scannerUserId): void
    {
        if (! in_array($token->purpose, ['attendance', 'dorm-attendance'], true)) {
            return;
        }
        $student = DB::table('students')->where('pesantren_id', $tenantId)->where('user_id', $token->user_id)->where('status', 'active')->whereNull('deleted_at')->first();
        abort_unless($student, 422, 'QR tidak terhubung dengan data santri aktif.');
        if ($token->purpose === 'attendance' && $activeRole === 'guru') {
            $allowed = DB::table('teacher_assignments')->join('teachers', 'teacher_assignments.teacher_id', '=', 'teachers.id')
                ->where('teacher_assignments.pesantren_id', $tenantId)->where('teachers.pesantren_id', $tenantId)
                ->where('teachers.user_id', $scannerUserId)->where('teacher_assignments.class_id', $student->class_id)
                ->where('teacher_assignments.status', 'active')->whereNull('teachers.deleted_at')
                ->where(fn ($query) => $query->whereNull('teacher_assignments.assigned_at')->orWhere('teacher_assignments.assigned_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('teacher_assignments.ended_at')->orWhere('teacher_assignments.ended_at', '>=', today()))->exists();
            abort_unless($allowed, 403, 'Guru hanya dapat memindai QR santri dari kelas yang ditugaskan.');
        }
        if ($token->purpose === 'dorm-attendance' && $activeRole === 'musyrif') {
            $allowed = DB::table('room_assignments')->join('musyrif_room_assignments', function ($join) use ($tenantId, $scannerUserId) {
                $join->on('musyrif_room_assignments.room_id', '=', 'room_assignments.room_id')
                    ->where('musyrif_room_assignments.pesantren_id', '=', $tenantId)
                    ->where('musyrif_room_assignments.user_id', '=', $scannerUserId)
                    ->where('musyrif_room_assignments.status', '=', 'active');
            })->where('room_assignments.pesantren_id', $tenantId)->where('room_assignments.student_id', $student->id)->where('room_assignments.status', 'active')
                ->where(fn ($query) => $query->whereNull('room_assignments.assigned_at')->orWhere('room_assignments.assigned_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('room_assignments.ended_at')->orWhere('room_assignments.ended_at', '>=', today()))
                ->where(fn ($query) => $query->whereNull('musyrif_room_assignments.assigned_at')->orWhere('musyrif_room_assignments.assigned_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('musyrif_room_assignments.ended_at')->orWhere('musyrif_room_assignments.ended_at', '>=', today()))->exists();
            abort_unless($allowed, 403, 'Musyrif hanya dapat memindai QR santri dari kamar binaannya.');
        }
    }

    private function audit(Request $request, int $tenantId, int $userId, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'module' => 'QR',
            'ip_address' => $request->ip(),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
