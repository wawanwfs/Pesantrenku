# Disaster Recovery Pesantrenku

## Target Operasional

- RPO database: maksimal 15 menit melalui MySQL binary log dan backup harian terenkripsi.
- RTO layanan: maksimal 60 menit untuk restore database, object storage, worker, dan Reverb.
- Backup tenant aplikasi melengkapi backup fisik database; backup tenant bukan pengganti PITR MySQL.

## Konfigurasi MySQL PITR

Aktifkan binary log dengan format row dan retensi sesuai kebijakan organisasi:

```ini
[mysqld]
server-id=1
log_bin=mysql-bin
binlog_format=ROW
binlog_expire_logs_seconds=604800
sync_binlog=1
innodb_flush_log_at_trx_commit=1
```

Ambil full backup konsisten setiap hari menggunakan snapshot managed database atau `mysqldump --single-transaction --master-data=2`. Salin full backup dan binary log ke bucket berbeda dengan versioning, object lock, dan encryption aktif.

## Drill Berkala

1. Buat environment recovery terisolasi tanpa akses outbound ke payment gateway atau layanan notifikasi.
2. Pulihkan full backup terakhir ke MySQL recovery.
3. Terapkan binary log sampai timestamp sebelum insiden:

```bash
mysqlbinlog --start-datetime="2026-07-11 00:00:00" --stop-datetime="2026-07-11 10:30:00" mysql-bin.000123 | mysql -h recovery-db -u restore -p pesantenku
```

4. Gunakan credential production read-only untuk membandingkan jumlah tenant, user, pembayaran, invoice, dan audit log.
5. Jalankan `php artisan pesantenku:dr-drill --backup-id=<id>` untuk memverifikasi snapshot tenant dan kesiapan PITR.
6. Jalankan backend test, deployment smoke test, dan satu workflow pembayaran pada recovery environment.
7. Simpan report drill dari prefix `dr-drills/` ke arsip compliance.

Workflow recovery terjadwal mengunggah hanya `build/pitr/report.json`. Dump SQL, binary log, dan replay SQL sengaja tidak dimasukkan ke artifact CI karena dapat memuat data sensitif. Jika file antara perlu dipertahankan untuk investigasi, simpan melalui kanal backup terenkripsi dengan akses terbatas, bukan artifact CI umum.

Jangan menjalankan replay binary log langsung ke database production tanpa change approval, backup terkini, dan verifikasi target waktu.
