# Pesantrenku Mobile

Aplikasi Flutter menggunakan API Laravel yang sama dengan web. Tidak ada akun demo atau fallback data lokal.

Target rilis yang didukung saat ini adalah Android. Folder platform iOS/macOS tetap berupa scaffold Flutter dan belum menjadi target distribusi atau gate rilis.

## Menjalankan

```bash
flutter pub get --enforce-lockfile
flutter run --dart-define=API_BASE_URL=https://example.org/api/v1
```

Untuk Android emulator lokal gunakan `http://10.0.2.2:8000/api/v1` hanya melalui `--dart-define`. Build release wajib memakai HTTPS.

Token disimpan dengan `flutter_secure_storage`. Kamera diperlukan untuk pemindaian QR. Login, dashboard, resource, notifikasi, perpindahan tenant, pembuatan QR, dan pemindaian QR semuanya memanggil API nyata.

## Tampilan dan aksesibilitas

- Tema terang/gelap/sistem (`AppTheme.light/dark`, `ThemeController`); pilihan tersimpan di penyimpanan aman dan bisa diubah dari tab Akun.
- Status memuat memakai kerangka (`Skeleton`, `DashboardSkeleton`, `ListSkeleton`); kemunculan konten memakai `FadeSlideIn`. Semua animasi dimatikan bila sistem meminta animasi dikurangi (`MediaQuery.disableAnimations`).
- Daftar modul memiliki pencarian lokal; QR menampilkan hitung mundur dan keadaan kedaluwarsa; haptic ringan pada aksi penting.

Verifikasi yang dijalankan sesi 2026-10-04 (Flutter 3.44.7 seperti CI): `flutter analyze` bersih dan `flutter test` 23 lulus. Build APK/AAB tidak dijalankan.

## Build release Android

1. Salin `android/key.properties.example` menjadi `android/key.properties`.
2. Isi lokasi upload keystore dan secret melalui secret manager CI.
3. Jalankan `flutter pub get --enforce-lockfile`, `git diff --exit-code -- pubspec.lock`, `flutter analyze`, dan `flutter test`.
4. Build App Bundle dengan `flutter build appbundle --release --dart-define=API_BASE_URL=https://example.org/api/v1`.

Build release tidak menggunakan debug signing, mengaktifkan minification/resource shrinking, dan menolak base URL non-HTTPS. Jangan commit `key.properties` atau file keystore.


Workflow release membutuhkan repository variable `MOBILE_API_BASE_URL` dan secrets `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, serta `ANDROID_KEY_PASSWORD`. Pipeline mem-build AAB, memverifikasi signature JAR, menerbitkan checksum SHA-256, lalu mengunggah ketiganya sebagai artifact release; secret dan keystore tidak disimpan di repository.
