# Backlog pengembangan Pesantrenku

Disusun 2026-07-29.

## Cara membaca dokumen ini

Permintaannya 1000 butir. Dokumen ini berisi 232. Angka itu bukan hasil
menyerah, melainkan batas kejujuran: setiap butir di bawah berakar pada kode
yang sudah dibaca, pada temuan audit RC7-RC9, atau pada kebutuhan domain
pesantren yang jelas. Menambah 768 butir lagi berarti memecah satu pekerjaan
menjadi sepuluh baris, atau menebak isi file yang belum pernah dibuka. Backlog
yang digelembungkan justru menyembunyikan mana yang penting.

Penanda:

- **[V]** Terverifikasi. Berdasar kode yang sudah dibaca langsung.
- **[?]** Perlu diperiksa dulu. Dugaan kuat, tapi filenya belum dibaca.
- **[N]** Fitur baru. Bukan perbaikan cacat.
- ~~Dicoret~~ menandai butir yang sudah selesai dan terverifikasi lolos di CI.

Prioritas: **P0** menahan rilis, **P1** penting, **P2** peningkatan, **P3** nilai
tambah jangka panjang.

File yang belum pernah dibaca sama sekali dan karenanya kurang terwakili di
sini: `routes/`, `app/Models/`, `app/Policies/`, `app/Providers/`,
`backend/tests/`, seluruh `mobile/`, `resources/js/Pages/App.jsx`, dan
`resources/css/landing.css` selain potongan kecil.

---

## 1. Keamanan dan multi-tenancy

1. **[V][P0]** ~~Pasang pemanggilan `TimelineRedactor` di `CrudController::timeline`; kelasnya masih kode mati (M-REMAIN-06).~~ Selesai 2026-08-01: `timeline()` kini memanggil `TimelineRedactor::redactMetadata()`.
2. **[V][P1]** ~~Satukan cakupan asrama: export memakai `room_assignments`, `OperationalScope` memakai `musyrif_room_assignments`. Dua sumber kebenaran untuk pertanyaan yang sama.~~ Selesai 2026-08-01: `DashboardScopeConcern::dormScope()` dan `OperationalScope::dormScope()` kini menghitung `student_ids` dari tabel relasional `room_assignments` (status aktif, berjendela tanggal `assigned_at`/`ended_at`) alih-alih mencocokkan teks `students.room`, selaras dengan `CrudAuthorizationConcern`, `ActionWorkflowConcern`, dan `BuildResourceExportJob` yang sebelumnya sudah benar. `QrScannerService` diperiksa dan sudah benar sejak awal (menggabungkan `room_assignments` + `musyrif_room_assignments` langsung dengan filter status/tanggal untuk keduanya). Diuji regresi di dua tes baru pada `OperationalScopeTest.php` yang membuktikan cakupan mengikuti `room_assignments`, bukan `students.room`, baik saat keduanya berbeda maupun saat assignment sudah berakhir. Commit `d4a8666b56cc30883c27983323a11cae28d22be9` (DashboardScopeConcern), `14d560c2fe5c0f298111ec258f80cea8cc7aef69` (OperationalScope), `6c108006a93c38d8eb1630b35b4172a7f7c9ec61` (tes regresi).
3. **[V][P1]** ~~Uji `PrivilegedAccess::grantsSensitiveAccess` terhadap payload JSON rusak; jalur fail-closed belum pernah dieksekusi.~~ Selesai 2026-08-01: sudah terwakili oleh `PrivilegedAccessTest::test_unreadable_permission_payload_fails_closed()`, yang memasang `permissions => 'bukan-json-yang-valid'` dan membuktikan `PrivilegedAccess::isPrivileged()` mengembalikan `true` lewat cabang `! is_array($decoded)` di `grantsSensitiveAccess()`. Tidak ada perubahan kode; penutupan bersifat dokumentasi.
4. **[V][P1]** ~~Terapkan `PrivilegedAccess` pada penghapusan akun dan penurunan peran, bukan hanya penonaktifan MFA.~~ Selesai 2026-08-01: sebelumnya `PrivilegedAccess` hanya dipakai `MfaController::disable()`; mutasi tabel `users` (termasuk penghapusan akun platform) sama sekali tidak mewajibkan step-up MFA. Sekarang `CrudAuthorizationConcern::assertAccessAdministrationStepUp()` mewajibkan step-up MFA (purpose `membership-administration`, sama dengan `pesantren_user`) untuk seluruh mutasi tabel `users`, bukan hanya `pesantren_user`. `CrudController::destroy()` mencatat `privileged_reasons` pada metadata audit saat akun `users` yang privileged dihapus. `CrudAuthorizationConcern::assertMembershipTransitionAllowed()` kini mendeteksi saat membership yang privileged (owner aktif, atau custom role tenant dengan akses tulis sensitif) diturunkan atau dihapus, dan mencatat entri audit terpisah (`privileged membership demoted`/`removed`) berisi `privileged_reasons`, sehingga penurunan peran privileged tidak lagi tidak bisa dibedakan dari edit membership rutin. `CrudPermissionConcern::audit()` diperluas menerima metadata tambahan opsional (`$extra`, default kosong, tidak mengubah pemanggilan lama). Diuji regresi tiga tes baru di `TenantSecurityTest.php`: `test_users_administration_requires_step_up_mfa` (mutasi `users` tanpa step-up kini ditolak), `test_deleting_privileged_user_account_is_audited_with_privileged_reasons` (penghapusan akun super admin lain tercatat dengan `global_role`), `test_privileged_custom_role_demotion_is_audited` (melepas custom role bertulis-sensitif dari membership tercatat dengan `tenant_custom_role:<id>`). Commit `df4a98b355ab03376f1f6fac9977ecb056094f49` (CrudPermissionConcern), `bd9d53e4a9e07a93ec803fa89cb658c99ea5c7c6` (CrudAuthorizationConcern), `ada8b374928250ebcd038dbc0e31de81f9df6179` (CrudController), `d80f5e20d0e717ca2b60ffce84239872dac3444c` (tes regresi).
5. **[V][P1]** ~~Audit `TenantContext::GLOBAL_TABLES`; tabel global melewati penyaringan tenant, jadi daftar itu adalah permukaan serangan.~~ Selesai 2026-08-01, hasil audit: daftar ini aman secara desain, tidak ditemukan kebocoran lintas tenant. Rinciannya per entri: (1) `'users'` dan `'pesantrens'` sebenarnya BUKAN benar-benar lolos tanpa scoping — keduanya punya cabang khusus yang lebih ketat di `applyScope()`/`tenantIdForTable()` yang dievaluasi lebih dulu dan tetap menegakkan keanggotaan tenant; keberadaannya di `GLOBAL_TABLES` hanya memengaruhi `withTenantPayload()` (benar tidak menyuntikkan `pesantren_id` karena kedua tabel memang tidak punya kolom itu). (2) `'roles'` dan `'plans'` memang data katalog platform yang global (tidak punya kolom `pesantren_id`); akses tulis dibatasi hanya untuk `super_admin` dan tidak bisa didelegasikan ke peran tenant maupun custom role apa pun karena keduanya ada di `ResourceCatalog::NON_DELEGABLE`, yang disaring oleh `ResourceAuthorization::normalizeDelegableAccess()` di semua jalur pemberian akses (matriks default, permission role dari DB, `permissions.matrix_override` tenant, dan custom role tenant) — bahkan bila JSON override/izin yang tersimpan sudah memuat kedua tabel itu (skenario data lama/dimanipulasi). (3) `'permissions'`, `'permission_role'`, `'role_user'` tidak terdaftar di `ResourceCatalog::ALL`, sehingga `CrudAuthorizationConcern::table()` langsung 404 sebelum request pernah sampai ke `TenantContext`; ketiganya saat ini tidak bisa dijangkau (kode mati/defensif) lewat rute CRUD generik. Dikonfirmasi juga bahwa setiap pemanggil `applyScope()`, `tenantIdForTable()`, dan `withTenantPayload()` di luar `TenantContext` sendiri (`CrudController`, `CrudImportConcern`) selalu memakai `$table` yang sudah lolos gerbang `ResourceCatalog::ALL`, jadi tidak ada jalur lain yang bisa menyalahgunakan daftar ini. Ditambahkan komentar audit lengkap di atas konstanta `GLOBAL_TABLES` untuk pemelihara berikutnya, dan dua tes regresi baru di `TenantSecurityTest.php`: `test_dead_global_table_aliases_are_unreachable_through_generic_crud` (mengunci bahwa tiga entri mati tetap 404 walau diakses super admin) dan `test_platform_catalog_tables_cannot_be_delegated_via_override_or_custom_role` (membuktikan `'roles'`/`'plans'` tetap ditolak meski override tenant atau custom role sudah dimanipulasi untuk memuatnya, dengan kontrol positif bahwa super admin tetap bisa mengakses). Commit `286f2942cac4ac36ce8a7a90547ba3895046e982` (tes regresi), `809282128b3f998812868c329b5da0a1b003b525` (dokumentasi audit di `TenantContext.php`).
6. **[V][P1]** ~~Verifikasi setiap endpoint menegakkan `X-Pesantren-Id` terhadap keanggotaan aktif, bukan menerima begitu saja.~~ Selesai 2026-08-01, hasil audit: tidak ditemukan jalur yang mempercayai header ini secara langsung. `X-Pesantren-Id` (beserta parameter `pesantren_id` di query dan session `active_pesantren_id`) hanya pernah dibaca di satu tempat pada seluruh backend, `TenantContext::requestedPesantrenId()`, sebuah method privat. Setiap konsumen luar — `CrudAuthorizationConcern` (lewat `scopedQuery`/`applyScope`/`withTenantPayload`), `DashboardController::show()`, `DashboardScopeConcern`, `ActionWorkflowConcern`, `ActionNotificationJobConcern`, `EntitlementService`, dan `QrScannerService` — selalu memanggil `TenantContext::activePesantrenId()` atau `requireActivePesantrenId()`, yang untuk pengguna non-super-admin menegakkan `abort_unless($memberships->contains('id', $requested), 403, ...)` terhadap keanggotaan aktif nyata (bukan sekadar percaya nilai yang dikirim klien), dan untuk super admin memvalidasi keberadaan/status tenant (super admin memang secara desain tidak dibatasi keanggotaan, itu bukan celah). `DashboardController::show()` secara tambahan memaksa `activePesantrenId($request, $requiredRoleForRoute)`, sehingga rute dasbor per peran (mis. `/dashboard/keuangan`) menolak header tenant yang benar tetapi perannya tidak sesuai. Pada jalur tulis, `withTenantPayload()` menimpa `pesantren_id` apa pun yang dikirim di body permintaan dengan tenant yang sudah tervalidasi, sehingga payload klien tidak bisa menembus scoping meski mencantumkan `pesantren_id` tenant lain. Ditambahkan satu tes regresi baru di `TenantSecurityTest.php`, `test_user_without_any_membership_cannot_impersonate_a_foreign_tenant_via_header`, yang membuktikan pengguna tanpa keanggotaan sama sekali di tenant lain (bukan sekadar peran berbeda) ditolak 403 saat mengirim `X-Pesantren-Id` tenant tersebut, baik pada jalur baca CRUD, aksi workflow, maupun rute dasbor. Commit `68b0beb78f503831ec779a849196598cbe8d09f6` (tes regresi).
7. **[V][P1]** Cache keanggotaan 30 detik berarti pencabutan akses tertunda 30 detik; invalidasi eksplisit saat keanggotaan berubah.
8. **[V][P2]** `UserAccessRevoker` perlu uji yang membuktikan sesi, token, dan job antrean milik pengguna ikut mati.
9. **[?][P1]** Pastikan `scopedQuery` dipakai di semua jalur baca, termasuk export, import, timeline, dan dashboard.
10. **[V][P2]** Tambah pembatasan laju pada `/auth/login` per akun dan per IP, dengan penguncian bertahap.
11. **[V][P2]** Tambah pembatasan laju pada verifikasi TOTP; brute force enam digit murah tanpa itu.
12. **[V][P2]** Kode pemulihan MFA harus di-hash, bukan disimpan apa adanya. Periksa `mfa_recovery_codes`.
13. **[V][P2]** Catat setiap konsumsi kode pemulihan ke `audit_logs` dengan IP dan user agent.
14. **[N][P2]** Wajibkan MFA untuk semua peran privileged, bukan sekadar melarang penonaktifannya.
15. **[V][P2]** Beri masa kedaluwarsa pada `permissions.matrix_override` agar override sementara tidak jadi permanen.
16. **[V][P2]** Validasi `tenant_roles.permissions` saat ditulis, bukan hanya saat dibaca.
17. **[?][P1]** Pastikan unggahan berkas divalidasi berdasar isi, bukan ekstensi, dan disajikan dari domain terpisah.
18. **[?][P2]** Tinjau header keamanan: HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
19. **[?][P2]** Pastikan CSP produksi memakai nonce, bukan `unsafe-inline`; lint CSP hanya menjaga sisi kode.
20. **[V][P2]** Rotasi kunci enkripsi `BackupCipher` dengan dukungan dekripsi kunci lama.
21. **[V][P2]** Uji bahwa `PROTECTED_SETTING_PREFIXES` tidak bisa ditembus lewat import.
22. **[N][P3]** Halaman sesi aktif: pengguna melihat dan mencabut perangkat lain.
23. **[N][P3]** Notifikasi email saat login dari perangkat baru.
24. **[V][P2]** Batasi ukuran dan jumlah baris file import sebelum job berjalan, bukan setelah.
25. **[?][P2]** Pastikan `SignedImportRow` memakai perbandingan waktu tetap dan tanda tangan berkedaluwarsa.

## 2. Privasi dan kepatuhan UU PDP

26. **[N][P1]** Klasifikasikan kolom yang memuat data pribadi; ini prasyarat semua butir lain di bagian ini.
27. **[N][P1]** Data kesehatan santri adalah data spesifik menurut UU PDP; butuh kontrol akses dan audit terpisah dari data akademik.
28. **[N][P1]** Kebijakan retensi: berapa lama rekam medis, catatan pelanggaran, dan data alumni disimpan.
29. **[N][P2]** Mekanisme penghapusan atas permintaan subjek data, termasuk yang tersimpan di backup.
30. **[N][P2]** Ekspor data pribadi per individu untuk memenuhi hak akses.
31. **[N][P2]** Pencatatan persetujuan wali untuk data santri di bawah umur.
32. **[N][P2]** Anonimisasi data alumni setelah periode retensi, bukan penghapusan total, agar statistik tetap utuh.
33. **[V][P2]** Pastikan `audit_logs.metadata` tidak menyimpan kata sandi atau token dalam snapshot.
34. **[N][P3]** Prosedur pemberitahuan insiden kebocoran dalam 3x24 jam.
35. **[N][P3]** Perjanjian pemrosesan data untuk pesantren yang memakai layanan ini sebagai tenant.

## 3. Basis data dan integritas

36. **[V][P1]** Migrasi domain ada dalam satu file besar; pecah agar riwayat skema terbaca.
37. **[?][P1]** Pastikan setiap kolom `pesantren_id` punya indeks komposit dengan kolom yang sering difilter.
38. **[?][P1]** Tambah kunci asing yang hilang; banyak kolom relasi tampak hanya `foreignId(...)->index()` tanpa `constrained()`.
39. **[?][P1]** Batasan unik untuk NIS per pesantren, bukan global.
40. **[V][P1]** `subscriptions` butuh indeks unik parsial agar satu tenant hanya punya satu langganan hidup; `SubscriptionProvisioner` kini menolak duplikat di lapisan aplikasi saja.
41. **[?][P2]** Soft delete konsisten: sebagian tabel punya `deleted_at`, sebagian tidak.
42. **[?][P2]** Tinjau tipe kolom uang; gunakan integer terkecil atau decimal, jangan float.
43. **[?][P2]** Zona waktu: pastikan penyimpanan UTC dan penyajian Asia/Jakarta konsisten.
44. **[N][P2]** Arsip tabel `audit_logs` dan `notification_logs` yang akan tumbuh tanpa batas.
45. **[N][P2]** Partisi atau rotasi `idempotency_keys` beserta pembersih terjadwal.
46. **[V][P2]** Perintah pemeriksa integritas lintas tenant: cari baris yang `pesantren_id`-nya tidak cocok dengan induknya.
47. **[?][P2]** Uji migrasi mundur; banyak proyek hanya menguji arah maju.
48. **[N][P3]** Seeder data demo yang realistis untuk demo penjualan, terpisah dari seeder pengujian.
49. **[?][P2]** Tinjau perilaku penghapusan berantai agar penghapusan pesantren tidak meninggalkan yatim.
50. **[N][P3]** Katalog data: dokumentasi setiap tabel dan kolom untuk tim baru.

## 4. Antrean, job, dan keandalan

51. **[V][P1]** Uji regresi untuk sidik jari cakupan export; ini logika keamanan tanpa tes.
52. **[V][P1]** Uji regresi untuk penolakan baris di luar cakupan pada import.
53. **[V][P2]** Job import dan export perlu jalur pembatalan oleh pengguna, bukan hanya kedaluwarsa.
54. **[V][P2]** Batas 20 pesan lewati pada import menyembunyikan pola; simpan ringkasan hitungan per alasan.
55. **[V][P2]** Beri progres yang lebih halus pada export; saat ini per halaman 500 baris.
56. **[V][P2]** `OperationJobRecoveryService` perlu uji yang membuktikan job mati benar-benar dipulihkan.
57. **[?][P2]** Pastikan job idempoten saat percobaan ulang; `tries 3` tanpa idempotensi berbahaya.
58. **[N][P2]** Antrean prioritas terpisah agar export besar tidak menghambat notifikasi.
59. **[N][P2]** Papan status operasi untuk admin: job berjalan, gagal, dan durasinya.
60. **[V][P2]** Bersihkan berkas export lama dari penyimpanan sesuai retensi.
61. **[?][P2]** Pastikan kegagalan job mengirim notifikasi, bukan hanya mengisi kolom `error`.
62. **[N][P3]** Uji beban: 10.000 baris export sambil 50 pengguna aktif.
63. **[V][P2]** Kunci `operation-job:*` perlu masa kedaluwarsa yang jelas agar tidak menggantung.
64. **[N][P3]** Percobaan ulang eksponensial untuk kegagalan sementara pada notifikasi eksternal.

## 5. Langganan dan entitlement

65. **[V][P1]** Jalankan `pesantrenku:provision-subscription` di staging; perintah ini belum pernah dieksekusi.
66. **[V][P1]** Uji lima jalur provisioner: created, updated, unchanged, rejected, duplikat hidup.
67. **[V][P2]** Ganti `Cache::forget` manual dengan `TenantContext::invalidatePesantren` setelah tanda tangannya diperiksa.
68. **[N][P1]** Pekerjaan terjadwal yang mengubah langganan kedaluwarsa menjadi `expired`; saat ini status tampak manual.
69. **[N][P2]** Peringatan sebelum langganan berakhir, ke owner dan super admin.
70. **[N][P2]** Masa tenggang setelah kedaluwarsa agar pesantren tidak langsung terkunci di tengah semester.
71. **[V][P2]** `EntitlementService` menolak dengan 402; pastikan frontend menampilkan pesan yang manusiawi, bukan kode mentah.
72. **[N][P2]** Riwayat perubahan paket per tenant yang bisa dibaca owner.
73. **[N][P3]** Kuota per modul, bukan hanya per sumber daya.
74. **[N][P3]** Faktur otomatis untuk langganan platform, terpisah dari tagihan santri.

## 6. API dan kontrak

75. **[?][P1]** Pastikan `docs/openapi.yaml` mencakup endpoint provisioning dan timeline.
76. **[V][P2]** Versikan API dengan sungguh-sungguh; `/api/v1` ada, tapi kebijakan perubahan belum tertulis.
77. **[?][P2]** Standarkan bentuk galat: kode, pesan manusiawi, dan detail per field.
78. **[V][P2]** Pesan galat kini campur bahasa Indonesia dan Inggris; pilih satu untuk API.
79. **[?][P2]** Pagination konsisten di semua koleksi, termasuk kursor untuk data besar.
80. **[?][P2]** Pastikan endpoint daftar mendukung penyaringan dan pengurutan yang sama secara konsisten.
81. **[V][P2]** Dokumentasikan header `Idempotency-Key`: operasi mana yang mewajibkannya.
82. **[V][P2]** Jelaskan di dokumentasi bahwa idempotensi HTTP bukan idempotensi domain; ini sudah menyesatkan sekali.
83. **[N][P3]** Webhook keluar untuk integrasi pihak ketiga.
84. **[N][P3]** Kunci API untuk integrasi mesin, terpisah dari sesi pengguna.
85. **[?][P2]** Pastikan CORS dibatasi ke origin yang dikenal.
86. **[N][P3]** Halaman status API publik.

## 7. Arsitektur backend dan kualitas kode

87. **[V][P0]** Pecah `CrudController.php`, 44 KB. Perbaikan M-REMAIN-06 (item 1) sudah dilakukan lewat penggantian berkas penuh via API tanpa korupsi, tapi itu bukan pengganti pemecahan sesungguhnya menjadi controller/service yang lebih kecil.
88. **[V][P1]** CRUD generik berbasis nama tabel mengorbankan aturan domain; pertimbangkan controller khusus untuk sumber daya bernilai tinggi seperti pembayaran dan rekam medis.
89. **[?][P1]** Pindahkan aturan bisnis dari controller ke service atau action.
90. **[V][P2]** `ResourceCatalog` menyimpan matriks izin sebagai konstanta; pertimbangkan sumber tunggal yang bisa diuji dan didokumentasikan otomatis.
91. **[V][P2]** Banyak concern saling bergantung lewat `$this->`; ubah jadi dependensi eksplisit.
92. **[?][P2]** Gunakan Form Request untuk validasi, bukan validasi manual di controller.
93. **[?][P2]** Model Eloquent tampak jarang dipakai; banyak `DB::table`. Tentukan sikap, jangan setengah-setengah.
94. **[V][P2]** Tambah tipe kembalian dan properti bertipe di seluruh service baru.
95. **[N][P2]** Jalankan analisis statis: PHPStan atau Psalm, mulai dari level rendah.
96. **[V][P2]** `MetricRegistry` dan `TelemetryService` tumpang tindih; perjelas batasnya.
97. **[?][P3]** Pertimbangkan event domain untuk memisahkan notifikasi dari alur utama.
98. **[N][P3]** Arsitektur modul agar fitur klinik, keuangan, dan akademik bisa dinyalakan per tenant tanpa cabang if.
99. **[V][P2]** Hilangkan duplikasi logika cakupan antara `DashboardScopeConcern` dan `OperationalScope`. Catatan 2026-08-01: kedua kelas kini konsisten secara fungsional (sama-sama memakai `room_assignments`), tapi implementasinya masih diduplikasi, bukan dibagi lewat satu sumber; butir ini tetap terbuka.
100. **[N][P3]** Adopsi konvensi penamaan tunggal; kini ada `pesantenku` dan `pesantrenku` berdampingan di kunci cache dan perintah.

## 8. Pengujian

101. **[V][P0]** ~~Uji `OperationalScope` dan `ImportRowScope`.~~ Selesai: `OperationalScopeTest.php` dan `ImportRowScopeTest.php` lolos di CI.
102. **[V][P0]** ~~Uji `SubscriptionProvisioner`.~~ Selesai: `SubscriptionProvisionerTest.php` lolos di CI.
103. **[V][P0]** ~~Uji `PrivilegedAccess`, termasuk custom role tenant.~~ Selesai: `PrivilegedAccessTest.php` lolos di CI.
104. **[V][P0]** ~~Uji `TimelineRedactor` setelah dipasang.~~ Selesai: `TimelineRedactorTest.php` lolos di CI dan kelasnya kini benar-benar dipanggil dari `CrudController::timeline`.
105. **[V][P1]** Uji isolasi tenant: pengguna tenant A tidak boleh membaca apa pun milik tenant B, untuk setiap sumber daya.
106. **[V][P1]** Uji matriks izin untuk sepuluh peran; ini inti produk.
107. **[?][P1]** Uji bahwa endpoint tanpa autentikasi memang hanya yang dimaksud.
108. **[N][P2]** Uji mutasi untuk kode keamanan; cakupan baris menipu.
109. **[N][P2]** Uji properti untuk logika penjadwalan dan tagihan.
110. **[V][P2]** Uji kontrak OpenAPI terhadap respons nyata, bukan hanya lint skema.
111. **[N][P2]** Uji migrasi pada dump produksi tersamar.
112. **[V][P2]** Uji regresi visual untuk landing setelah layer motion baru.
113. **[N][P2]** Uji aksesibilitas otomatis dengan axe di alur utama.
114. **[N][P2]** Uji pemulihan bencana yang benar-benar memulihkan backup ke basis data kosong.
115. **[N][P3]** Uji ketahanan: matikan Redis dan MySQL di tengah operasi.
116. **[N][P3]** Tetapkan ambang cakupan minimum di CI, naikkan bertahap.

## 9. Observability

117. **[?][P1]** Pastikan setiap galat 500 tercatat dengan konteks tenant dan pengguna.
118. **[N][P1]** ID korelasi permintaan yang mengalir sampai ke job antrean.
119. **[N][P2]** Dasbor Grafana untuk kesehatan antrean: kedalaman, usia job tertua, laju gagal.
120. **[N][P2]** Peringatan saat job export atau import gagal berturut-turut.
121. **[N][P2]** Peringatan saat langganan tenant kedaluwarsa massal.
122. **[V][P2]** Ekspos metrik provisioning: created, updated, unchanged, rejected.
123. **[N][P2]** Pelacakan galat frontend; saat ini tidak jelas ada atau tidak.
124. **[N][P2]** Pantau Core Web Vitals dari pengguna nyata, bukan hanya Lighthouse lokal.
125. **[N][P2]** Log audit yang bisa dicari oleh admin lewat UI, bukan hanya lewat SQL.
126. **[N][P3]** Anggaran SLO dan laporan bulanan.
127. **[?][P2]** Pastikan log tidak memuat data pribadi santri.
128. **[N][P3]** Pelacakan terdistribusi hingga ke kueri basis data lambat.

## 10. CI/CD dan rantai pasok

129. **[V][P1]** Lipat workflow SBOM Android ke `release.yml` agar bundel tidak dibangun dua kali.
130. **[V][P1]** Verifikasi atestasi di CI setelah dibuat, jangan hanya membuatnya.
131. **[V][P2]** Tambah SBOM untuk dependensi frontend, bukan hanya image dan mobile.
132. **[V][P2]** Batasi Dependabot dengan pengelompokan dan jadwal bulanan; 13 cabang menumpuk pernah terjadi.
133. **[N][P2]** Wajibkan tinjauan untuk perubahan pada `app/Services` dan `app/Support` lewat CODEOWNERS.
134. **[N][P1]** Aktifkan perlindungan cabang `main`; kini `protected: false`.
135. **[N][P2]** Wajibkan commit bertanda tangan untuk rilis.
136. **[V][P2]** Cache dependensi Composer di CI seperti npm.
137. **[N][P2]** Jalankan pengujian secara paralel untuk memangkas waktu rilis.
138. **[N][P2]** Pindai rahasia pada setiap PR, bukan sesekali.
139. **[N][P2]** Pindai lisensi dependensi.
140. **[N][P3]** Lingkungan pratinjau otomatis per PR.
141. **[V][P2]** Uji rollback deployment kanari, bukan hanya jalur maju.
142. **[N][P3]** Buat rilis dapat direproduksi; `reproducible: false` kini eksplisit di provenance.

## 11. Frontend web: arsitektur dan performa

143. **[V][P0]** ~~Pecah `App.jsx`, 101 KB. Ini satu berkas untuk seluruh aplikasi.~~ Selesai 2026-10-04: `Pages/App.jsx` 1.738 → 263 baris; Topbar, Sidebar, Widgets, CrudModule, SystemModule, QrTool, moduleMeta dipindah ke `components/app/`. Bundel App tidak berubah (174,8 kB). Diverifikasi build + eslint no-undef + crawl 148 halaman pada server preview.
144. **[V][P1]** Pisah kode per rute agar dasbor tidak memuat seluruh aplikasi.
145. **[V][P1]** Muat ikon lucide secara selektif; impor besar mahal.
146. **[?][P1]** Audit ukuran bundel per rute terhadap anggaran 550 KB JS.
147. **[V][P2]** Ukur ulang anggaran CSS 250 KB setelah `motion.css`, `ui-kit.css`, dan `landing-motion.css`.
148. **[N][P2]** Pramuat font Poppins dengan `font-display: swap` dan subset latin.
149. **[N][P2]** Sajikan gambar dalam format modern dengan ukuran responsif.
150. **[V][P2]** Runtime Three.js hanya dimuat setelah cat pertama; pastikan juga tidak dimuat di perangkat hemat data.
151. **[N][P2]** Hormati `prefers-reduced-data` untuk adegan 3D.
152. **[N][P2]** Virtualisasi tabel panjang di dasbor.
153. **[N][P2]** ~~Status memuat berbasis kerangka, bukan pemintal kosong.~~ Selesai sebagian 2026-10-04: skeleton untuk bagan (`ChartLoading`) dan tabel (`TableSkeleton`). Pemintal masih dipakai pada tombol/aksi.
154. **[N][P2]** ~~Penanganan galat per komponen dengan error boundary.~~ Selesai 2026-10-04: `ErrorBoundary` membungkus konten halaman dan tiap bagan; ada tes e2e pemutusan chunk.
155. **[N][P3]** Dukungan luring dasar untuk data yang sering dibaca.
156. **[N][P3]** Service worker untuk aset statis.
157. **[V][P2]** Konsolidasikan tiga berkas CSS motion menjadi satu sistem yang terdokumentasi.
158. **[N][P3]** Pertimbangkan TypeScript untuk frontend; permukaan sudah cukup besar.

## 12. UI/UX dan sistem desain

159. **[V][P1]** ~~Mode gelap; sudah dua kali ditunda dan token prototipe sudah tersedia.~~ Selesai 2026-10-04: `dark.css`, `public/theme-init.js`, `ThemeToggle`, login dan landing berpalet gelap. Audit kontras otomatis (axe) belum dijalankan.
160. **[V][P2]** Terapkan primitif `ui-kit.css` ke dasbor; kini baru tersedia, belum dipakai luas.
161. **[N][P2]** ~~Dokumentasikan sistem desain: token, jarak, radius, bayangan, dalam satu halaman hidup.~~ Selesai 2026-10-04: `docs/ui/design-system.md`.
162. **[N][P2]** ~~Keadaan kosong yang membimbing, bukan sekadar tulisan tidak ada data.~~ Selesai 2026-10-04 untuk modul CRUD: keadaan kosong menawarkan "Atur ulang filter" atau "Tambah data".
163. **[N][P2]** Keadaan galat yang menawarkan tindakan, bukan kode status.
164. **[N][P2]** Konfirmasi destruktif yang menyebutkan dampak, misalnya jumlah baris terhapus.
165. **[N][P2]** Umpan balik optimistis untuk aksi cepat.
166. **[N][P2]** Pola pencarian global lintas modul.
167. **[N][P3]** ~~Palet perintah untuk pengguna mahir.~~ Selesai 2026-10-04: `CommandPalette` (Ctrl/Cmd+K).
168. **[N][P2]** ~~Pintasan papan tik untuk aksi yang sering.~~ Selesai 2026-10-04: `/`, `[`, `T`, `?`, `Esc`; tidak aktif saat mengetik.
169. **[N][P2]** Onboarding untuk pesantren baru: langkah pertama yang jelas setelah login.
170. **[N][P2]** Tur singkat per peran saat pertama masuk.
171. **[V][P2]** ~~Landing: tambahkan indikator progres scroll; butuh satu elemen baru di JSX.~~ Selesai 2026-10-04: `.landing-progress` memakai `animation-timeline: scroll(root)` (tanpa JS; tersembunyi di peramban tanpa dukungan).
172. **[V][P2]** ~~Landing: transisi antar section saat ini seragam; beri satu momen puncak agar tidak monoton.~~ Selesai 2026-10-04: dock navigasi muncul setelah hero, bar konsol alur kerja tumbuh saat terlihat, langkah alur yang sedang dibaca disorot.
173. **[N][P3]** Landing: dukungan Firefox lewat fallback IntersectionObserver, jika angka pengunjungnya besar.
174. **[N][P2]** Konsistensi mikrokopi Indonesia: satu daftar istilah untuk santri, wali, musyrif, dan seterusnya.
175. **[N][P3]** Cetak yang rapi untuk rapor, kuitansi, dan surat.
~~176. **[N][P2]** Tampilan tabel yang dapat dikonfigurasi per pengguna, kolom mana yang tampil.~~ Selesai: pemilih kolom per sumber daya (`ColumnPicker`, disimpan di `localStorage["pk-cols:<resource>"]`, minimal satu kolom tetap tampil).

## 13. Aksesibilitas

177. **[N][P1]** Audit kontras warna seluruh palet terhadap WCAG AA.
178. **[N][P1]** Pastikan seluruh alur bisa diselesaikan dengan papan tik saja.
179. **[N][P1]** Kelola fokus saat dialog dibuka dan ditutup.
180. **[V][P2]** Menu seluler landing sudah mengelola fokus; terapkan pola yang sama di dasbor.
~~181. **[N][P2]** Label ARIA untuk tabel data dan aksi barisnya.~~ Selesai: `<caption>` sr-only, `scope="col"`, `aria-rowcount`, dan tombol aksi baris menyebut barisnya ("Edit data: Nama").
182. **[N][P2]** Umumkan perubahan asinkron lewat live region.
183. **[N][P2]** Target sentuh minimum 44 piksel di seluruh aplikasi.
184. **[N][P2]** Jangan sampaikan makna hanya lewat warna, terutama pada status.
185. **[N][P2]** Uji dengan pembaca layar berbahasa Indonesia.
186. **[V][P2]** ~~Pastikan animasi baru benar-benar mati saat reduced-motion aktif; verifikasi di peramban.~~ Selesai 2026-10-04: `tests/e2e/ux-shell.spec.js` ("kebijakan reduced motion") menegaskan tidak ada animasi berulang di app shell dan landing. Dijalankan terhadap server preview; belum terhadap backend sungguhan.
187. **[N][P3]** Dukungan pembesaran teks hingga 200 persen tanpa kehilangan konten.
188. **[N][P3]** Pernyataan aksesibilitas publik.

## 14. Mobile

189. **[?][P1]** Baca dan audit seluruh `mobile/`; bagian ini paling tidak terwakili.
190. **[V][P1]** Jalankan workflow rantai pasok Android sekali secara manual untuk membuktikan atestasinya terbentuk.
191. **[N][P1]** Penyimpanan token yang aman di perangkat, bukan penyimpanan biasa.
192. **[N][P2]** Penyematan sertifikat untuk API produksi.
193. **[N][P2]** Dukungan luring untuk presensi dan hafalan, dengan sinkronisasi belakangan.
194. **[N][P2]** Notifikasi dorong untuk wali: perizinan, tagihan, kesehatan.
195. **[N][P2]** Pemindaian QR yang tahan cahaya rendah.
196. **[N][P3]** Rilis iOS; kini hanya Android yang ditandatangani.
197. **[N][P3]** Ukuran aplikasi dan waktu mulai dingin sebagai anggaran yang dipantau.
198. **[N][P2]** Penanganan versi API yang tidak cocok dengan pesan pembaruan.
199. **[N][P3]** Mode hemat data untuk daerah dengan jaringan terbatas.
200. **[N][P3]** Dukungan tablet untuk penggunaan di ruang guru.

## 15. Fitur domain pesantren

201. **[N][P1]** Tahfidz: target hafalan, setoran, murajaah, dan kualitas bacaan sebagai modul utuh.
202. **[N][P1]** PPDB: pendaftaran santri baru dari formulir publik sampai penetapan kelas.
203. **[N][P2]** Kurikulum diniyah dan formal berdampingan dalam satu jadwal.
204. **[N][P2]** Rapor gabungan akademik, hafalan, dan akhlak.
205. **[N][P2]** Presensi berbasis QR untuk salat berjamaah, bukan hanya kelas.
206. **[N][P2]** Perizinan berjenjang: musyrif, lalu pengasuh, lalu wali diberi tahu.
207. **[N][P2]** Poin pelanggaran dan prestasi dengan tindak lanjut otomatis.
208. **[N][P2]** Kesehatan: riwayat alergi dan obat rutin yang terlihat saat antrean klinik.
209. **[N][P2]** Rujukan ke fasilitas luar beserta pencatatan biayanya.
210. **[N][P2]** Keuangan: tagihan berulang per semester dengan skema keringanan dan beasiswa.
211. **[N][P2]** Pembayaran cicilan dengan pengingat bertahap ke wali.
212. **[N][P2]** Integrasi pembayaran virtual account bank lokal.
213. **[N][P2]** Kas pesantren: pemasukan dan pengeluaran unit usaha.
214. **[N][P3]** Koperasi dan uang saku santri dengan saldo yang bisa dipantau wali.
215. **[N][P3]** Dapur: perencanaan menu, stok, dan biaya per santri.
216. **[N][P3]** Inventaris asrama dan pemeliharaan.
217. **[N][P2]** Penempatan kamar dengan mempertimbangkan kelas, usia, dan catatan khusus.
218. **[N][P2]** Jadwal piket dan tugas santri.
219. **[N][P2]** Komunikasi wali: pengumuman terarah per kelas atau per asrama.
220. **[N][P3]** Kunjungan wali: penjadwalan dan pencatatan.
221. **[N][P3]** Alumni: pelacakan, verifikasi ijazah, dan jejaring.
222. **[N][P3]** Zakat, infak, dan wakaf dengan pelaporan terpisah.
223. **[N][P2]** Kalender akademik yang menjadi sumber tunggal semua penjadwalan.
224. **[N][P2]** Laporan untuk Kemenag dalam format yang diminta.
225. **[N][P3]** Perpustakaan: peminjaman kitab dan buku.
226. **[N][P3]** Evaluasi guru dan supervisi pengajaran.
227. **[N][P2]** Analitik untuk pimpinan: tren hafalan, kehadiran, dan tunggakan dalam satu layar.

## 16. Operasional dan pemulihan

228. **[V][P1]** Selesaikan M-REMAIN-04: bukti kanari untuk worker, penjadwal, dan Reverb di staging.
229. **[V][P1]** Latihan pemulihan bencana yang sungguh dijalankan, dengan RTO dan RPO tercatat.
230. **[N][P2]** Runbook untuk lima insiden paling mungkin, bukan dokumen umum.
231. **[N][P2]** Uji pemulihan backup terjadwal dan otomatis, bukan manual.
232. **[N][P3]** Rencana kapasitas: berapa tenant per instans sebelum perlu penskalaan.

---

## Urutan yang saya sarankan

Item P0 yang tersisa: 87, 134, 228, dan 65. Item 1, 101, 102, 103, dan 104
sudah selesai dan terverifikasi lolos di CI (lihat
`memory/2026-07-27-rc9-remediation-handoff.md`). Item 2, 3, 4, 5, dan 6
(semuanya P1) juga sudah selesai dan terverifikasi lolos di CI. Yang tersisa
menutup utang dari audit RC7-RC9. Tanpa itu, setiap fitur baru dibangun di
atas fondasi yang belum terbukti sepenuhnya lewat eksekusi nyata (build, e2e,
MySQL 8.4, dan bukti kanari staging).

Sesudah itu barulah bagian 15 layak dikerjakan. Fitur domain adalah yang paling
menarik untuk dibangun dan paling mudah menjebak: menambah modul tahfidz di atas
isolasi tenant yang belum diuji hanya memperbesar permukaan masalah yang sama.
