<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $roles = [
            'super_admin' => 'Super Admin',
            'owner_pesantren' => 'Owner Pesantren',
            'admin_keuangan' => 'Admin Keuangan',
            'admin_akademik' => 'Admin Akademik',
            'admin_tu' => 'Tata Usaha',
            'santri' => 'Santri',
            'wali_murid' => 'Wali Murid',
            'guru' => 'Guru',
            'musyrif' => 'Musyrif',
            'klinik' => 'Klinik',
        ];

        foreach ($roles as $name => $label) {
            DB::table('roles')->insert(['name' => $name, 'label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionResources = [
            'users', 'roles', 'pesantrens', 'plans', 'subscriptions', 'tenant-modules', 'memberships',
            'academic-years', 'classes', 'programs', 'subjects', 'teacher-assignments', 'students', 'guardians',
            'teachers', 'staff', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'attendances', 'dorm-attendances', 'grades',
            'report-cards', 'memorization-records', 'schedules', 'assignments', 'materials', 'announcements',
            'violations', 'permits', 'daily-notes', 'invoices', 'invoice-items', 'payments', 'payment-methods',
            'bank-accounts', 'expense-categories', 'expenses', 'cashflow-entries', 'discounts', 'letters',
            'room-mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine-stocks', 'medical-queues',
            'medical-records', 'medical-examinations', 'prescriptions', 'sick-letters', 'referrals',
            'notifications', 'audit-logs', 'system-settings', 'backups',
        ];
        foreach ($permissionResources as $resource) {
            foreach (['read' => 'Baca', 'write' => 'Kelola'] as $ability => $label) {
                DB::table('permissions')->insert([
                    'name' => "{$resource}.{$ability}",
                    'label' => "{$label} ".str($resource)->replace('-', ' ')->headline(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $starterPlanId = DB::table('plans')->insertGetId([
            'name' => 'Starter Pesantren',
            'code' => 'starter',
            'monthly_price' => 750000,
            'max_students' => 750,
            'max_users' => 1500,
            'features' => json_encode(['akademik', 'keuangan', 'tu']),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $growthPlanId = DB::table('plans')->insertGetId([
            'name' => 'Growth Pesantren',
            'code' => 'growth',
            'monthly_price' => 1500000,
            'max_students' => 2500,
            'max_users' => 6000,
            'features' => json_encode(['akademik', 'keuangan', 'tu', 'asrama', 'klinik', 'api']),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $darulId = DB::table('pesantrens')->insertGetId([
            'name' => 'Pondok Pesantren Darul Hikmah',
            'city' => 'Surabaya',
            'address' => 'Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',
            'phone' => '031-1234567',
            'email' => 'info@darulhikmah.sch.id',
            'status' => 'active',
            'metadata' => json_encode(['total_santri' => 1248, 'server_status' => 100]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $falahId = DB::table('pesantrens')->insertGetId([
            'name' => 'Pesantren Al-Falah',
            'city' => 'Bandung',
            'address' => 'Jl. Cibiru Indah No. 45, Bandung, Jawa Barat',
            'phone' => '022-778899',
            'email' => 'admin@alfalah.sch.id',
            'status' => 'active',
            'metadata' => json_encode(['total_santri' => 642, 'server_status' => 99]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('subscriptions')->insert([
            [
                'pesantren_id' => $darulId,
                'plan_id' => $growthPlanId,
                'status' => 'active',
                'starts_at' => '2025-01-01',
                'ends_at' => '2025-12-31',
                'amount' => 1500000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'pesantren_id' => $falahId,
                'plan_id' => $starterPlanId,
                'status' => 'trial',
                'starts_at' => '2025-05-01',
                'ends_at' => '2025-06-30',
                'amount' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        foreach ([$darulId, $falahId] as $pesantrenId) {
            foreach (['akademik', 'keuangan', 'tata_usaha', 'asrama', 'klinik', 'monitoring'] as $module) {
                DB::table('tenant_modules')->insert([
                    'pesantren_id' => $pesantrenId,
                    'module' => $module,
                    'is_enabled' => $module !== 'monitoring',
                    'settings' => json_encode(['enabled_by' => 'seed']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $userCounter = 0;
        $createUser = function (string $name, string $email, string $username, string $role, ?int $pesantrenId = null) use ($now, $roleIds, &$userCounter): int {
            $userCounter++;

            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'phone' => '0857-1001-'.str_pad((string) $userCounter, 3, '0', STR_PAD_LEFT),
                'status' => 'active',
                'address' => 'Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',
                'password' => Hash::make('password'),
                'mfa_secret' => in_array($role, ['super_admin', 'owner_pesantren'], true)
                    ? Crypt::encryptString($role === 'super_admin' ? 'KRUGS4ZANFZSAYJA' : 'JBSWY3DPEHPK3PXP')
                    : null,
                'mfa_enabled' => in_array($role, ['super_admin', 'owner_pesantren'], true),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $roleIds[$role],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($pesantrenId) {
                DB::table('pesantren_user')->insert([
                    'pesantren_id' => $pesantrenId,
                    'user_id' => $userId,
                    'role_id' => $roleIds[$role],
                    'status' => 'active',
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $userId;
        };

        $superAdminId = $createUser('Super Admin', 'superadmin@pesantenku.test', 'superadmin', 'super_admin');
        $ownerId = $createUser('Owner Darul Hikmah', 'owner@pesantenku.test', 'owner', 'owner_pesantren', $darulId);

        DB::table('pesantren_user')->insert([
            'pesantren_id' => $falahId,
            'user_id' => $ownerId,
            'role_id' => $roleIds['owner_pesantren'],
            'status' => 'active',
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $keuanganId = $createUser('Keuangan Admin', 'keuangan@pesantenku.test', 'keuangan', 'admin_keuangan', $darulId);
        $akademikId = $createUser('Akademik Admin', 'akademik@pesantenku.test', 'akademik', 'admin_akademik', $darulId);
        $tuId = $createUser('TU Admin', 'tu@pesantenku.test', 'tu', 'admin_tu', $darulId);
        $santriUserId = $createUser('Najwa Karimah', 'santri@pesantenku.test', 'santri', 'santri', $darulId);
        $waliUserId = $createUser('Nur Azizah Ghafur', 'wali@pesantenku.test', 'wali', 'wali_murid', $darulId);
        $guruUserId = $createUser('Ustadzah Siti Aisyah', 'guru@pesantenku.test', 'guru', 'guru', $darulId);
        $musyrifUserId = $createUser('Ustadzah Siti Aisyah Musyrif', 'musyrif@pesantenku.test', 'musyrif', 'musyrif', $darulId);
        $klinikUserId = $createUser('Klinik Admin', 'klinik@pesantenku.test', 'klinik', 'klinik', $darulId);
        $falahFinanceId = $createUser('Keuangan Al-Falah', 'keuangan.alfalah@pesantenku.test', 'keuangan_alfalah', 'admin_keuangan', $falahId);

        $seedTenant = function (int $pesantrenId, string $codePrefix, array $options = []) use ($now, $santriUserId, $waliUserId, $guruUserId, $musyrifUserId): void {
            $academicYearId = DB::table('academic_years')->insertGetId([
                'pesantren_id' => $pesantrenId,
                'name' => '2024/2025',
                'start_date' => '2024-07-01',
                'end_date' => '2025-06-30',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $programIds = [];
            foreach ([['Tsanawiyah', 'MTS', 'Menengah'], ['Aliyah', 'MA', 'Atas'], ['Tahfidz', 'THF', 'Khusus']] as $program) {
                $programIds[$program[1]] = DB::table('programs')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'name' => $program[0],
                    'code' => $program[1],
                    'level' => $program[2],
                    'description' => 'Program '.$program[0],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $classIds = [];
            $classes = $options['classes'] ?? ['7A', '7B', '8A', '8B', '9A', '9B', '10A', '10B', '11A', '11B'];
            $homerooms = ['Ust. Ahmad Fauzi', 'Ust. Nur Halimah', 'Ust. Muhammad Iqbal', 'Usth. Siti Aisyah', 'Bapak Nur Hidayat'];
            foreach ($classes as $index => $class) {
                $classIds[$class] = DB::table('classes')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'program_id' => $index < 6 ? $programIds['MTS'] : $programIds['MA'],
                    'academic_year_id' => $academicYearId,
                    'name' => $class,
                    'level' => $index < 6 ? 'Tsanawiyah' : 'Aliyah',
                    'grade' => preg_replace('/[^0-9]/', '', $class),
                    'homeroom_teacher' => $homerooms[$index % count($homerooms)],
                    'student_count' => [32, 31, 30, 31, 32, 31, 29, 28, 27, 26][$index] ?? 24,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $subjectIds = [];
            $subjects = [
                ['Al-Qur\'an', 'QUR', 'Ust. Ahmad Fauzi'],
                ['Fiqih', 'FIQ', 'Ust. Nur Halimah'],
                ['Akidah Akhlak', 'AKH', 'Usth. Siti Aisyah'],
                ['Bahasa Arab', 'ARB', 'Ust. Muhammad Iqbal'],
                ['Bahasa Indonesia', 'BIN', 'Ust. Riky Maulana'],
                ['Matematika', 'MTK', 'Bapak Nur Hidayat'],
                ['IPA', 'IPA', 'Usth. Devi Rahma'],
            ];
            foreach ($subjects as [$name, $code, $teacher]) {
                $subjectIds[$name] = DB::table('subjects')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'name' => $name,
                    'code' => $code,
                    'level' => 'Tsanawiyah, Aliyah',
                    'kkm' => 75,
                    'teacher_name' => $teacher,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $teacherIds = [];
            foreach ([
                ['Ust. Ahmad Fauzi', 'GURU-001', 'Al-Qur\'an Hadits'],
                ['Usth. Siti Aisyah', 'GURU-002', 'Bahasa Arab'],
                ['Ust. Muhammad Iqbal', 'GURU-003', 'Fiqih'],
            ] as $index => [$name, $nip, $subject]) {
                $teacherIds[$name] = DB::table('teachers')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'user_id' => $index === 1 && $codePrefix === 'DH' ? $guruUserId : null,
                    'name' => $name,
                    'nip' => "{$codePrefix}-{$nip}",
                    'subject' => $subject,
                    'phone' => '0812-3456-78'.str_pad((string) ($index + 10), 2, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ([['Bapak Nur Hidayat', 'TU-001', 'Tata Usaha'], ['Ibu Rina Agustina', 'TU-002', 'Administrasi']] as [$name, $nip, $role]) {
                DB::table('staff')->insert([
                    'pesantren_id' => $pesantrenId,
                    'name' => $name,
                    'nip' => "{$codePrefix}-{$nip}",
                    'role' => $role,
                    'phone' => '0812-3456-7890',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roomIds = [];
            foreach ([['C-07', 'Putri Blok C', 6, 4], ['C-08', 'Putri Blok C', 6, 5], ['C-09', 'Putri Blok C', 6, 6], ['C-10', 'Putri Blok C', 6, 3], ['A-01', 'Putra Blok A', 10, 9], ['A-02', 'Putra Blok A', 10, 10]] as [$name, $block, $capacity, $occupied]) {
                $roomIds[$name] = DB::table('rooms')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'name' => $name,
                    'block' => $block,
                    'capacity' => $capacity,
                    'occupied' => $occupied,
                    'type' => 'Reguler',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $sitiClassId = $classIds['9A'] ?? array_values($classIds)[0];
            foreach (array_filter([$subjectIds['Bahasa Arab'] ?? null, $subjectIds['Akidah Akhlak'] ?? null]) as $subjectId) {
                DB::table('teacher_assignments')->insert([
                    'pesantren_id' => $pesantrenId,
                    'teacher_id' => $teacherIds['Usth. Siti Aisyah'] ?? null,
                    'class_id' => $sitiClassId,
                    'subject_id' => $subjectId,
                    'assigned_at' => '2024-07-01',
                    'status' => 'active',
                    'note' => 'Penugasan mengajar aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($codePrefix === 'DH') {
                foreach (['C-07', 'C-08', 'C-09', 'C-10'] as $roomName) {
                    DB::table('musyrif_room_assignments')->insert([
                        'pesantren_id' => $pesantrenId,
                        'user_id' => $musyrifUserId,
                        'room_id' => $roomIds[$roomName] ?? null,
                        'assigned_at' => '2024-07-01',
                        'status' => 'active',
                        'note' => 'Binaan asrama putri',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $guardianId = DB::table('guardians')->insertGetId([
                'pesantren_id' => $pesantrenId,
                'user_id' => $codePrefix === 'DH' ? $waliUserId : null,
                'name' => $options['guardian'] ?? 'Nur Azizah Ghafur',
                'phone' => '0857-1001-1270',
                'email' => strtolower($codePrefix).'.wali@example.test',
                'address' => 'Jl. Raya Pegantenan KM 09, Pamekasan, Jawa Timur',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $students = $options['students'] ?? [
                ['20230001', 'Najwa Karimah', 'Perempuan', '9A', 'C-07'],
                ['20230002', 'Ahmad Zaky', 'Laki-laki', '8B', 'A-01'],
                ['20230003', 'Zahra Azzahra', 'Perempuan', '9A', 'C-08'],
                ['20230004', 'Rafi Nur Fadli', 'Laki-laki', '9B', 'A-02'],
                ['20230005', 'Muhammad Faris', 'Laki-laki', '8A', 'A-01'],
                ['20230006', 'Salma Az Zahra', 'Perempuan', '8A', 'C-09'],
                ['20230007', 'Aisyah Putri', 'Perempuan', '7B', 'C-08'],
                ['20230008', 'Hana Nabila', 'Perempuan', '10A', 'C-10'],
                ['20230009', 'Zahra Nur', 'Perempuan', '11A', 'C-10'],
            ];

            $studentIds = [];
            foreach ($students as $index => [$nis, $name, $gender, $class, $room]) {
                $studentIds[$nis] = DB::table('students')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'user_id' => $index === 0 && $codePrefix === 'DH' ? $santriUserId : null,
                    'guardian_id' => $guardianId,
                    'class_id' => $classIds[$class] ?? array_values($classIds)[0],
                    'nis' => $nis,
                    'name' => $name,
                    'gender' => $gender,
                    'birth_place' => $gender === 'Perempuan' ? 'Surabaya' : 'Gresik',
                    'birth_date' => now()->subYears(15 + ($index % 3))->subMonths($index)->toDateString(),
                    'room' => $room,
                    'status' => 'active',
                    'metadata' => json_encode(['average' => 85.6 - $index, 'hafalan' => max(5, 12 - $index), 'blood_type' => $index % 2 ? 'A' : 'B']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('room_assignments')->insert([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $studentIds[$nis],
                    'room_id' => $roomIds[$room] ?? null,
                    'assigned_at' => '2024-07-01',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($studentIds as $index => $studentId) {
                foreach (array_slice(array_values($subjectIds), 0, 5) as $subjectIndex => $subjectId) {
                    $avg = 90 - ((int) $index % 7) - ($subjectIndex % 3);
                    DB::table('grades')->insert([
                        'pesantren_id' => $pesantrenId,
                        'student_id' => $studentId,
                        'class_id' => DB::table('students')->where('id', $studentId)->value('class_id'),
                        'subject_id' => $subjectId,
                        'semester' => 'Genap 2024/2025',
                        'assignment_score' => $avg - 5,
                        'midterm_score' => $avg,
                        'final_score' => $avg + 2,
                        'attitude_score' => 88,
                        'average_score' => $avg,
                        'grade' => $avg >= 88 ? 'A' : ($avg >= 82 ? 'A-' : 'B+'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('report_cards')->insert([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $studentId,
                    'semester' => 'Semester 1 - 2024/2025',
                    'average_score' => 85.6,
                    'status' => 'published',
                    'items' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('memorization_records')->insert([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $studentId,
                    'juz' => 'Juz '.(1 + ((int) $index % 12)),
                    'surah' => 'Al-Baqarah',
                    'ayat_range' => '1-20',
                    'progress_percent' => 75,
                    'recorded_at' => '2025-05-20',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (['Transfer Bank', 'Bayar di Kantor', 'E-Wallet', 'Virtual Account'] as $method) {
                DB::table('payment_methods')->insert([
                    'pesantren_id' => $pesantrenId,
                    'name' => $method,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $paymentMethodId = DB::table('payment_methods')->where('pesantren_id', $pesantrenId)->where('name', 'Transfer Bank')->value('id');

            foreach ([['Bank Syariah Indonesia (BSI)', '7182 0100 1234 5678', 2125450000], ['Bank Muamalat', '30110 0012 3456 7890', 1250300000], ['Bank BCA', '1234 5678 9012 3456', 1070470000]] as $i => [$bank, $number, $balance]) {
                DB::table('bank_accounts')->insert([
                    'pesantren_id' => $pesantrenId,
                    'bank_name' => $bank,
                    'account_number' => $number,
                    'account_holder' => $codePrefix === 'DH' ? 'Pondok Pesantren Darul Hikmah' : 'Pesantren Al-Falah',
                    'balance' => $balance,
                    'is_primary' => $i === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (array_slice(array_values($studentIds), 0, 6) as $i => $studentId) {
                $invoiceId = DB::table('invoices')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $studentId,
                    'invoice_number' => 'INV/'.$codePrefix.'/2025/05/'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'period' => 'Mei 2025',
                    'amount' => $i % 2 ? 900000 : 1350000,
                    'due_date' => '2025-05-10',
                    'status' => $i < 2 ? 'paid' : 'unpaid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (['SPP Mei 2025' => 800000, 'Uang Makan Mei 2025' => 500000, 'Uang Kegiatan' => 150000, 'Seragam Olahraga' => 50000] as $name => $amount) {
                    DB::table('invoice_items')->insert([
                        'pesantren_id' => $pesantrenId,
                        'invoice_id' => $invoiceId,
                        'name' => $name,
                        'amount' => $amount,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($i < 2) {
                    DB::table('payments')->insert([
                        'pesantren_id' => $pesantrenId,
                        'invoice_id' => $invoiceId,
                        'student_id' => $studentId,
                        'payment_method_id' => $paymentMethodId,
                        'amount' => $i % 2 ? 900000 : 1350000,
                        'paid_at' => $now,
                        'status' => 'success',
                        'reference' => 'PAY-'.$codePrefix.'-'.date('Ymd').'-'.$i,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ($i === 2) {
                    DB::table('payments')->insert([
                        'pesantren_id' => $pesantrenId,
                        'invoice_id' => $invoiceId,
                        'student_id' => $studentId,
                        'payment_method_id' => $paymentMethodId,
                        'amount' => 500000,
                        'paid_at' => $now,
                        'status' => 'pending',
                        'reference' => 'WAIT-'.$codePrefix.'-'.date('Ymd').'-'.$i,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach (array_slice(array_values($studentIds), 0, 2) as $studentId) {
                DB::table('discounts')->insert([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $studentId,
                    'name' => 'Keringanan Syahriyah',
                    'amount' => 150000,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ([['Pemasukan', 'SPP', 1850450000], ['Pemasukan', 'Uang Makan', 780120000], ['Pengeluaran', 'Gaji Guru & Staf', 650000000], ['Pengeluaran', 'Belanja Operasional', 280450000]] as [$type, $title, $amount]) {
                DB::table('cashflow_entries')->insert([
                    'pesantren_id' => $pesantrenId,
                    'type' => strtolower($type),
                    'title' => $title,
                    'amount' => $amount,
                    'date' => '2025-05-20',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $expenseCategoryId = DB::table('expense_categories')->insertGetId([
                'pesantren_id' => $pesantrenId,
                'name' => 'Operasional',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('expenses')->insert([
                'pesantren_id' => $pesantrenId,
                'expense_category_id' => $expenseCategoryId,
                'title' => 'Belanja dapur pesantren',
                'amount' => 280450000,
                'date' => '2025-05-20',
                'note' => 'Kebutuhan operasional bulanan',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([
                ['Ujian Semester Genap', '1 - 10 Juni 2025', 'Akademik'],
                ['Libur Awal Ramadhan', 'Kegiatan belajar mengajar diliburkan mulai 10 - 14 Maret 2025', 'Umum'],
                ['Pembagian Rapor', 'Akan dilaksanakan pada 20 Juni 2025', 'Akademik'],
                ['Jaga Kebersihan Lingkungan', 'Buang sampah pada tempatnya.', 'Kesehatan'],
            ] as [$title, $body, $category]) {
                DB::table('announcements')->insert([
                    'pesantren_id' => $pesantrenId,
                    'title' => $title,
                    'body' => $body,
                    'category' => $category,
                    'audience' => 'all',
                    'published_at' => '2025-05-20',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $firstStudentId = array_values($studentIds)[0];
            foreach ([['Terlambat masuk kelas', 2], ['Tidak ikut jamaah', 5], ['Rambut tidak rapi', 2]] as [$title, $points]) {
                DB::table('violations')->insert([
                    'pesantren_id' => $pesantrenId,
                    'student_id' => $firstStudentId,
                    'title' => $title,
                    'points' => $points,
                    'category' => $points > 2 ? 'Sedang' : 'Ringan',
                    'date' => '2025-05-20',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('permits')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'type' => 'Izin Keluar',
                'start_at' => '2025-05-22 16:00:00',
                'end_at' => '2025-05-22 20:00:00',
                'reason' => 'Pulang ke rumah',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('daily_notes')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'noted_at' => '2025-05-20 20:00:00',
                'note' => 'Mengikuti halaqah malam dengan baik.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('attendances')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'class_id' => DB::table('students')->where('id', $firstStudentId)->value('class_id'),
                'date' => '2025-05-21',
                'session' => 'Bahasa Arab',
                'status' => 'hadir',
                'note' => 'Hadir tepat waktu',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('dorm_attendances')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'room_id' => $roomIds['C-07'] ?? null,
                'date' => '2025-05-21',
                'activity' => 'Halaqah malam',
                'status' => 'hadir',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $defaultClassId = array_values($classIds)[0];
            $sitiSubjectId = $subjectIds['Bahasa Arab'] ?? array_values($subjectIds)[0];

            foreach ([
                ['Senin', '07:00 - 07:45', 'Al-Qur\'an', 'Ust. Ahmad Fauzi'],
                ['Senin', '07:45 - 08:30', 'Fiqih', 'Ust. Nur Halimah'],
                ['Selasa', '08:30 - 09:15', 'Bahasa Arab', 'Usth. Siti Aisyah'],
                ['Selasa', '10:15 - 11:00', 'IPA', 'Usth. Devi Rahma'],
            ] as [$day, $time, $subject, $teacher]) {
                DB::table('schedules')->insert([
                    'pesantren_id' => $pesantrenId,
                    'class_id' => $teacher === 'Usth. Siti Aisyah' ? $sitiClassId : $defaultClassId,
                    'subject_id' => $subjectIds[$subject] ?? null,
                    'day' => $day,
                    'time' => $time,
                    'teacher_name' => $teacher,
                    'room_name' => 'R-01',
                    'type' => 'academic',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('assignments')->insert([
                'pesantren_id' => $pesantrenId,
                'class_id' => $sitiClassId,
                'subject_id' => $sitiSubjectId,
                'title' => 'Hafalan surat pendek',
                'due_date' => '2025-05-25',
                'status' => 'given',
                'metadata' => json_encode(['point' => 100]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('materials')->insert([
                'pesantren_id' => $pesantrenId,
                'class_id' => $sitiClassId,
                'subject_id' => $sitiSubjectId,
                'title' => 'Materi Fiqih Ibadah',
                'file_type' => 'pdf',
                'file_size' => '2.4 MB',
                'url' => '/storage/materials/fiqih-ibadah.pdf',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([['SK/052/TU/2025', 'Surat Keterangan Aktif', 'outgoing', 'Wali Santri'], ['SM/05/TU/2025', 'Surat Izin Pulang', 'incoming', 'Orang Tua'], ['SK/050/TU/2025', 'Surat Keterangan', 'outgoing', 'Bank Syariah']] as [$number, $subject, $type, $destination]) {
                DB::table('letters')->insert([
                    'pesantren_id' => $pesantrenId,
                    'number' => $codePrefix.'/'.$number,
                    'subject' => $subject,
                    'type' => $type,
                    'destination' => $destination,
                    'date' => '2025-05-20',
                    'status' => 'done',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('room_mutations')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'from_room' => 'C-08',
                'to_room' => 'C-07',
                'date' => '2025-05-18',
                'status' => 'approved',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([['Rizky Maulana', '2024', 'Aliyah', 'Mahasiswa'], ['Siti Nurhaliza', '2024', 'Aliyah', 'Mahasiswi'], ['Muhammad Fadli', '2023', 'Aliyah', 'Karyawan']] as [$name, $year, $program, $occupation]) {
                DB::table('alumni')->insert([
                    'pesantren_id' => $pesantrenId,
                    'name' => $name,
                    'graduation_year' => $year,
                    'program' => $program,
                    'occupation' => $occupation,
                    'phone' => '0812-3456-7890',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('clinics')->insert([
                'pesantren_id' => $pesantrenId,
                'name' => 'Klinik Pesantren',
                'phone' => '031-7654321',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([['dr. Ahmad Fauzi', 'Dokter Umum', 'Senin - Jumat 08:00 - 12:00'], ['dr. Nur Halimah', 'Dokter Umum', 'Senin - Kamis 13:00 - 16:00']] as [$name, $spec, $schedule]) {
                DB::table('doctors')->insert([
                    'pesantren_id' => $pesantrenId,
                    'name' => $name,
                    'specialization' => $spec,
                    'schedule' => $schedule,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $medicineIds = [];
            foreach (['Paracetamol 500 mg' => 120, 'Ibuprofen 400 mg' => 85, 'CTM 4 mg' => 30, 'Vitamin C 500 mg' => 60, 'Minyak Kayu Putih 60 ml' => 15] as $medicine => $stock) {
                $medicineIds[$medicine] = DB::table('medicines')->insertGetId([
                    'pesantren_id' => $pesantrenId,
                    'name' => $medicine,
                    'unit' => str_contains($medicine, 'Minyak') ? 'botol' : 'tablet',
                    'stock' => $stock,
                    'minimum_stock' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('medicine_stocks')->insert([
                'pesantren_id' => $pesantrenId,
                'medicine_id' => $medicineIds['Paracetamol 500 mg'],
                'quantity' => 40,
                'type' => 'in',
                'date' => '2025-05-18',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('medical_queues')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'queue_number' => 'A-012',
                'status' => 'waiting',
                'estimated_time' => '09:30',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $medicalRecordId = DB::table('medical_records')->insertGetId([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'complaint' => 'Demam dan sakit kepala',
                'diagnosis' => 'Demam',
                'date' => '2025-05-12',
                'doctor_name' => 'dr. Ahmad Fauzi',
                'metadata' => json_encode(['temperature' => 37.8, 'blood_pressure' => '110/70 mmHg']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('medical_examinations')->insert([
                'pesantren_id' => $pesantrenId,
                'medical_record_id' => $medicalRecordId,
                'temperature' => 37.8,
                'blood_pressure' => '110/70',
                'pulse' => 82,
                'doctor_note' => 'Istirahat dan cukup minum.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('prescriptions')->insert([
                'pesantren_id' => $pesantrenId,
                'medical_record_id' => $medicalRecordId,
                'medicine_id' => $medicineIds['Paracetamol 500 mg'],
                'dosage' => '3x1 setelah makan',
                'quantity' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('sick_letters')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'number' => $codePrefix.'/SS/05/OS/2025/012',
                'date' => '2025-05-12',
                'duration_days' => 1,
                'description' => 'Istirahat selama 1 hari karena sakit.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('referrals')->insert([
                'pesantren_id' => $pesantrenId,
                'student_id' => $firstStudentId,
                'hospital_name' => 'RS Islam Surabaya',
                'phone' => '031-998877',
                'address' => 'Surabaya',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([['Pembayaran Berhasil', 'Syahriyah Mei 2025 telah lunas', 'success'], ['Perizinan Disetujui', 'Izin keluarga 10-12 Mei disetujui', 'info'], ['Tagihan Baru', 'Tagihan bulan Juni 2025 telah dibuat', 'warning']] as [$title, $body, $type]) {
                DB::table('notification_logs')->insert([
                    'pesantren_id' => $pesantrenId,
                    'user_id' => null,
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ([
                ['Tagihan SPP massal dibuat', 'Keuangan', '103.147.12.46'],
                ['Nilai siswa diimpor', 'Akademik', '103.147.12.47'],
                ['Surat keluar dibuat', 'Tata Usaha', '103.147.12.48'],
            ] as [$action, $module, $ip]) {
                DB::table('audit_logs')->insert([
                    'pesantren_id' => $pesantrenId,
                    'user_id' => null,
                    'action' => $action,
                    'module' => $module,
                    'ip_address' => $ip,
                    'metadata' => json_encode(['status' => 'success']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_settings')->insert([
                [
                    'pesantren_id' => $pesantrenId,
                    'key' => 'institution',
                    'value' => json_encode(['academic_year' => '2024/2025']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'pesantren_id' => $pesantrenId,
                    'key' => 'pdf.receipt_template',
                    'value' => json_encode([
                        'title' => 'Kwitansi Pembayaran Pesantenku',
                        'header' => 'Kwitansi Pembayaran Resmi Pesantren',
                        'footer' => 'Terima kasih. Simpan kwitansi ini sebagai bukti pembayaran.',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'pesantren_id' => $pesantrenId,
                    'key' => 'pdf.letter_template',
                    'value' => json_encode([
                        'title' => 'Surat Pesantren',
                        'header' => 'Surat Resmi Pesantren',
                        'footer' => 'Dokumen ini diterbitkan oleh tata usaha pesantren.',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'pesantren_id' => $pesantrenId,
                    'key' => 'pdf.report_template',
                    'value' => json_encode([
                        'title' => 'Laporan Pesantenku',
                        'header' => 'Laporan Operasional Pesantren',
                        'footer' => 'Data mengikuti filter dan scope role aktif.',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

        };

        $seedTenant($darulId, 'DH');
        $seedTenant($falahId, 'AF', [
            'guardian' => 'Wali Santri Al-Falah',
            'classes' => ['7A', '8A', '9A', '10A'],
            'students' => [
                ['20230001', 'Nabila Azizah', 'Perempuan', '7A', 'C-07'],
                ['20230002', 'Fathan Mubarak', 'Laki-laki', '8A', 'A-01'],
                ['20230003', 'Alya Humaira', 'Perempuan', '9A', 'C-08'],
            ],
        ]);

        DB::table('audit_logs')->insert([
            [
                'pesantren_id' => null,
                'user_id' => $superAdminId,
                'action' => 'Login dashboard super admin',
                'module' => 'Autentikasi',
                'ip_address' => '103.147.12.45',
                'metadata' => json_encode(['status' => 'success']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'pesantren_id' => null,
                'user_id' => $superAdminId,
                'action' => 'Pesantren baru didaftarkan',
                'module' => 'Manajemen Sistem',
                'ip_address' => '103.147.12.45',
                'metadata' => json_encode(['status' => 'success']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('system_settings')->insert([
            [
                'pesantren_id' => null,
                'key' => 'platform',
                'value' => json_encode(['name' => 'Pesantenku', 'academic_year' => '2024/2025']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'pesantren_id' => null,
                'key' => 'pdf.report_template',
                'value' => json_encode([
                    'title' => 'Laporan Platform Pesantenku',
                    'header' => 'Laporan SaaS Pesantenku',
                    'footer' => 'Dokumen platform dibuat otomatis oleh Pesantenku.',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

    }
}
