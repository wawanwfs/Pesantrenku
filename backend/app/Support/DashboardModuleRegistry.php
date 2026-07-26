<?php

namespace App\Support;

final class DashboardModuleRegistry
{
    /**
     * Explicit panel-to-module registry. A module URL is an authorization
     * boundary, not a decorative slug supplied by the browser.
     */
    private const MODULES = [
        'super-admin' => [
            'pesantrens', 'owner', 'subscriptions', 'plans', 'users', 'modules',
            'integrasi-api', 'laporan-penggunaan', 'laporan-pesantren', 'laporan-keuangan',
            'backups', 'system-update', 'cache-management', 'settings',
            'aktivitas-sistem', 'audit-logs', 'login-monitoring', 'error-logs',
        ],
        'owner' => [
            'profil-pesantren', 'user-dan-role', 'modul-aktif', 'paket-dan-subscription',
            'backup-dan-restore', 'pengaturan', 'audit-tenant',
        ],
        'keuangan' => [
            'santri', 'wali-santri', 'program-dan-unit', 'komponen-biaya', 'rekening-bank',
            'tahun-ajaran', 'tagihan', 'pembayaran', 'riwayat-pembayaran', 'tunggakan',
            'diskon-dan-keringanan', 'pembayaran-massal', 'laporan-pemasukan',
            'laporan-pengeluaran', 'laporan-arus-kas', 'laporan-tunggakan',
            'laporan-rekap-bulanan', 'laporan-per-santri', 'pengaturan-umum', 'pengguna-dan-akses',
        ],
        'akademik' => [
            'data-master', 'kurikulum', 'kelas', 'mata-pelajaran', 'guru', 'jadwal-pelajaran',
            'presensi', 'penilaian', 'nilai-siswa', 'rapor', 'kalender-akademik', 'kelulusan', 'pengumuman',
            'laporan-akademik', 'laporan-nilai', 'pengaturan',
        ],
        'tu' => [
            'santri', 'guru-dan-staf', 'kelas', 'kamar', 'data-master', 'surat-menyurat',
            'mutasi-santri', 'alumni', 'pengumuman', 'pengaturan',
        ],
        'santri' => ['profil', 'akademik', 'raport', 'tahfidz', 'presensi', 'perizinan', 'pelanggaran', 'kesehatan', 'billing', 'pengumuman', 'qr-scanner', 'jadwal', 'notifikasi', 'pengaturan'],
        'wali-murid' => ['anak-saya', 'akademik', 'raport', 'presensi', 'perizinan', 'pembayaran', 'tagihan', 'riwayat-pembayaran', 'pelanggaran', 'kesehatan', 'jadwal-anak', 'notifikasi', 'profil'],
        'guru' => ['jadwal-mengajar', 'kelas-saya', 'presensi-kelas', 'nilai', 'tugas', 'materi', 'raport', 'catatan-siswa', 'pengumuman', 'profil', 'pengaturan'],
        'musyrif' => ['santri-binaan', 'kamar', 'absensi-asrama', 'perizinan', 'pelanggaran', 'catatan-harian', 'mutasi-kamar', 'jadwal-piket', 'pengumuman', 'laporan', 'pengaturan'],
        'klinik' => ['antrian', 'rekam-medis', 'pemeriksaan', 'obat', 'riwayat-berobat', 'surat-sakit', 'rujukan', 'jadwal-dokter', 'stok-obat', 'pengumuman', 'laporan-kesehatan', 'profil', 'pengaturan'],
    ];

    private const PLATFORM_DATASETS = ['aktivitas-sistem', 'audit-logs', 'login-monitoring', 'error-logs'];

    public static function allows(string $panel, string $module): bool
    {
        return in_array($module, self::MODULES[$panel] ?? [], true);
    }

    public static function isPlatformDataset(string $module): bool
    {
        return in_array($module, self::PLATFORM_DATASETS, true);
    }

    public static function modulesFor(string $panel): array
    {
        return self::MODULES[$panel] ?? [];
    }
}
