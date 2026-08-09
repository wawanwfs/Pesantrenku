# Peta Business Logic Pesantrenku

Dokumen ini adalah sumber tunggal untuk memahami model bisnis, aktor, alur uang,
dan workflow domain Pesantrenku. Diagram memakai Mermaid agar dirender langsung
di GitLab dan mudah diperbarui bersama kode.

Rujukan implementasi: `app/Services/EntitlementService.php`,
`app/Http/Controllers/Concerns/ActionWorkflowConcern.php`,
`app/Services/DashboardService.php`, `docs/architecture.md`, dan backlog di
`docs/backlog/improvement-backlog.md`.

## 1. Dua lapisan bisnis

Ada dua lapisan uang dan dua lapisan aktor yang tidak boleh tercampur:

| Lapisan | Aktor | Sumber uang | Objek utama |
|---|---|---|---|
| Platform (B2B SaaS) | Super Admin | Langganan pesantren | `plans`, `subscriptions`, tenant modules |
| Tenant (operasional) | Owner, Keuangan, Akademik, TU, Musyrif, Klinik, Wali, Santri | Tagihan santri | `invoices`, `payments`, resource domain |

Role efektif selalu berasal dari membership `pesantren_user` pada tenant aktif
(`TenantContext`), bukan role global. Entitlement modul dievaluasi
`EntitlementService` dan menolak dengan 402 bila modul tidak termasuk paket.

```mermaid
flowchart TD
    subgraph PLATFORM["Lapisan Platform (Super Admin)"]
        SA[Super Admin] --> PLAN[Plans dan Modul]
        SA --> TEN[Provisioning Tenant]
        TEN --> SUB[Subscription]
        SUB -->|aktif / grace / expired| ENT[EntitlementService<br/>gate 402 per modul]
    end

    subgraph TENANT["Lapisan Tenant (1 Pesantren)"]
        OWN[Owner] --> RBAC[Membership pesantren_user<br/>+ custom role delegable]
        RBAC --> AK[Akademik]
        RBAC --> KEU[Keuangan]
        RBAC --> TU[Tata Usaha]
        RBAC --> MUS[Musyrif / Asrama]
        RBAC --> KLI[Klinik]
    end

    subgraph LIFECYCLE["Siklus Hidup Santri"]
        PPDB[PPDB: formulir publik] --> VER[Verifikasi berkas dan tes]
        VER --> PEN[Penetapan kelas dan kamar]
        PEN --> AKTIF[Santri Aktif]
        AKTIF --> KBM[KBM + Tahfidz + Presensi QR]
        AKTIF --> ASR[Asrama: pelanggaran, izin, piket]
        AKTIF --> KES[Klinik: antrean, rekam medis]
        AKTIF --> KEU2[Tagihan, cicilan, pembayaran, kuitansi]
        KBM --> RAPOR[Rapor gabungan:<br/>akademik + hafalan + akhlak]
        AKTIF --> LULUS[Lulus / Keluar] --> ALUMNI[Alumni + arsip ijazah]
    end

    ENT -.gate modul.-> TENANT
    TENANT --> LIFECYCLE
```

## 2. Siklus hidup langganan tenant

Status langganan yang seharusnya, termasuk masa tenggang agar tenant tidak
terkunci mendadak di tengah semester. Transisi `active -> grace -> expired`
harus dijalankan job terjadwal, bukan manual (backlog #68-70).

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Trial: provisioning
    Trial --> Active: pembayaran pertama
    Active --> Active: perpanjangan
    Active --> Grace: jatuh tempo lewat<br/>(job terjadwal)
    Grace --> Active: pembayaran diterima
    Grace --> Expired: masa tenggang habis<br/>(job terjadwal)
    Expired --> Active: reaktivasi oleh Super Admin
    note right of Grace
        Peringatan dikirim ke Owner dan Super Admin
        pada H-14, H-7, H-1 sebelum jatuh tempo.
        Selama Grace: baca tetap boleh, tulis dibatasi.
    end note
```

## 3. Workflow domain (state machine)

Semua workflow di bawah memakai state transition khusus, row lock, audit, dan
idempotency (`ActionWorkflowConcern`). Diagram menunjukkan alur yang seharusnya,
termasuk tahap yang belum diimplementasikan (perizinan berjenjang, backlog #206).

### 3.1 Perizinan santri (berjenjang)

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Diajukan
    Diajukan --> DisetujuiMusyrif: musyrif
    DisetujuiMusyrif --> DisetujuiPengasuh: pengasuh
    DisetujuiPengasuh --> WaliDiberitahu: notifikasi otomatis
    WaliDiberitahu --> Keluar: QR scan gerbang
    Keluar --> Kembali: QR scan masuk
    Kembali --> [*]
    Diajukan --> Ditolak
    DisetujuiMusyrif --> Ditolak: pengasuh menolak
    Keluar --> Terlambat: lewat batas waktu
    Terlambat --> Kembali: dicatat + poin pelanggaran
```

### 3.2 Tagihan dan pembayaran santri

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Diterbitkan: per semester / berulang
    Diterbitkan --> Keringanan: beasiswa / diskon disetujui Keuangan
    Keringanan --> Diterbitkan: nominal disesuaikan
    Diterbitkan --> SebagianDibayar: cicilan
    SebagianDibayar --> Lunas
    Diterbitkan --> Lunas: bayar penuh
    Diterbitkan --> Menunggak: jatuh tempo
    Menunggak --> SebagianDibayar: pengingat bertahap ke wali
    Lunas --> [*]: kuitansi + jurnal kas
```

Aturan uang: nominal disimpan sebagai integer satuan terkecil (rupiah penuh),
bukan float. Setiap mutasi pembayaran wajib `Idempotency-Key`.

### 3.3 Antrean klinik

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Terdaftar
    Terdaftar --> Dipanggil
    Dipanggil --> Diperiksa: tampilkan alergi dan obat rutin
    Diperiksa --> Diresepkan: potong stok obat
    Diperiksa --> Dirujuk: fasilitas luar + pencatatan biaya
    Diresepkan --> Selesai: notifikasi wali
    Dirujuk --> Selesai
    Selesai --> [*]
```

Catatan UU PDP: data kesehatan santri adalah data pribadi spesifik. Kontrol
akses dan audit untuk resource medis harus terpisah dari data akademik
(backlog #27).

### 3.4 PPDB (pendaftaran santri baru) - modul baru

```mermaid
stateDiagram-v2
    direction LR
    [*] --> FormulirMasuk: formulir publik tanpa login
    FormulirMasuk --> BerkasDiverifikasi: TU
    BerkasDiverifikasi --> TesSeleksi
    TesSeleksi --> Diterima
    TesSeleksi --> Ditolak
    Diterima --> DaftarUlang: pembayaran awal
    DaftarUlang --> SantriAktif: penetapan kelas dan kamar
    SantriAktif --> [*]
    Ditolak --> [*]
```

### 3.5 Tahfidz - modul baru

```mermaid
flowchart LR
    TGT[Target hafalan<br/>per santri per periode] --> SETOR[Setoran ke musyrif/ustadz]
    SETOR --> NILAI[Penilaian kualitas bacaan<br/>tajwid, kelancaran]
    NILAI --> MURAJAAH[Jadwal murajaah]
    MURAJAAH --> SETOR
    NILAI --> PROGRES[Progres juz/halaman<br/>terlihat wali di mobile]
    PROGRES --> RAPOR[Komponen rapor gabungan]
```

## 4. Alur sistem teknis

```mermaid
flowchart TD
    subgraph CLIENT["Klien"]
        WEB[Web Panel<br/>Inertia React]
        MOB[Mobile Flutter<br/>secure storage + QR]
    end

    subgraph API["Laravel 13"]
        AUTH[Sanctum<br/>session web + token mobile]
        TCTX[TenantContext<br/>X-Pesantren-Id tervalidasi membership]
        RBACL[ResourceAuthorization<br/>allow-list ResourceCatalog]
        CRUD[CrudController<br/>tenant-scoped]
        WF[ActionWorkflowConcern<br/>state machine + row lock]
        DASH[DashboardService per role]
        IDEM[IdempotencyKey middleware]
    end

    subgraph ASYNC["Antrean berversi"]
        Q1[imports-v3<br/>chunk atomik + HMAC]
        Q2[exports-v3<br/>hard row cap]
        Q3[backups-v1<br/>envelope encryption]
        SCHED[Scheduler<br/>heartbeat recovery]
    end

    subgraph DATA["Data"]
        MYSQL[(MySQL)]
        REDIS[(Redis)]
        OBJ[(Object Storage)]
    end

    REVERB[Reverb websocket] --> WEB
    WEB --> AUTH
    MOB --> AUTH
    AUTH --> TCTX --> RBACL
    RBACL --> CRUD --> MYSQL
    RBACL --> WF --> MYSQL
    RBACL --> DASH --> MYSQL
    CRUD --> IDEM --> REDIS
    CRUD --> Q1 & Q2 & Q3
    Q1 & Q2 & Q3 --> MYSQL
    Q3 --> OBJ
    SCHED --> Q1 & Q2 & Q3
    WF --> REVERB
```

## 5. Kesenjangan logika bisnis (per 2026-08-09)

Dipetakan terhadap backlog `docs/backlog/improvement-backlog.md`:

1. **Subscription lifecycle belum otomatis** - tidak ada job `expired`, masa
   tenggang, atau peringatan pra-kedaluwarsa (#68-70). Risiko: tenant terkunci
   mendadak. Prioritas tertinggi.
2. **Keuangan santri belum siklus utuh** - belum ada tagihan berulang,
   keringanan, cicilan, virtual account (#210-212); `payments` masih generik (#88).
3. **Tahfidz dan PPDB belum ada** (#201-202) - pembeda utama produk pesantren.
4. **Perizinan belum berjenjang** dan presensi QR belum mencakup salat
   berjamaah (#205-206).
5. **Kalender akademik belum jadi sumber tunggal penjadwalan** (#223).
6. **Pencabutan akses tertunda 30 detik** karena cache membership (#7).

## 6. Urutan eksekusi yang disepakati

1. Fondasi: sisa P0 (#87 pecah CrudController, #134 proteksi branch main,
   #143 pecah App.jsx, #65/#228 bukti staging).
2. Otomasi subscription lifecycle (bagian 2 dokumen ini).
3. Modul domain: Tahfidz, PPDB, keuangan siklus penuh, perizinan berjenjang.
4. Lapisan UI/UX: lihat `docs/ui/redesign-spec.md`.
