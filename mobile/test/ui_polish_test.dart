import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pesantenku_mobile/core/network/api_client.dart';
import 'package:pesantenku_mobile/core/theme/app_theme.dart';
import 'package:pesantenku_mobile/core/widgets/app_widgets.dart';
import 'package:pesantenku_mobile/features/dashboard/mobile_data.dart';
import 'package:pesantenku_mobile/features/dashboard/mobile_shell.dart';

class _PolishClient extends ApiClient {
  _PolishClient({this.rows = const [], this.qrExpiresIn = const Duration(seconds: 90)})
      : super(baseUrl: 'http://localhost');

  final List<Map<String, dynamic>> rows;
  final Duration qrExpiresIn;

  @override
  Future<List<Map<String, dynamic>>> resource(String resource, {int limit = 30}) async => rows;

  @override
  Future<List<Map<String, dynamic>>> notifications() async => const [];

  @override
  Future<Map<String, dynamic>> dashboard(String role) async => {
        'profile': {'name': 'Ahmad Fauzan Setiawan Al-Hakim'},
        'summary': [
          {'label': 'Nilai rata-rata semester ini', 'value': '86,4'},
          {'label': 'Hafalan', 'value': '3 Juz'},
          {'label': 'Pelanggaran', 'value': '0'},
          {'label': 'Tagihan aktif', 'value': 'Rp 450.000'},
        ],
      };

  @override
  Future<Map<String, dynamic>> generateQr(String purpose) async => {
        'code': 'a' * 64,
        'expires_at': DateTime.now().add(qrExpiresIn).toUtc().toIso8601String(),
      };
}

class _ThrowingStorage extends FlutterSecureStorage {
  const _ThrowingStorage();

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async =>
      throw StateError('penyimpanan tidak tersedia');

  @override
  Future<void> write({
    required String key,
    required String? value,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async =>
      throw StateError('penyimpanan tidak tersedia');
}

void main() {
  group('format tanggal dan salam', () {
    test('tanggal berbahasa Indonesia', () {
      expect(indonesianDate(DateTime(2026, 10, 4)), 'Minggu, 4 Oktober 2026');
      expect(indonesianDate(DateTime(2026, 1, 1)), 'Kamis, 1 Januari 2026');
    });

    test('salam mengikuti jam', () {
      expect(greetingFor(DateTime(2026, 1, 1, 2)), 'Selamat malam');
      expect(greetingFor(DateTime(2026, 1, 1, 7)), 'Selamat pagi');
      expect(greetingFor(DateTime(2026, 1, 1, 13)), 'Selamat siang');
      expect(greetingFor(DateTime(2026, 1, 1, 16)), 'Selamat sore');
      expect(greetingFor(DateTime(2026, 1, 1, 20)), 'Selamat malam');
    });
  });

  group('tema', () {
    test('tema gelap memakai palet gelap dan kontras tinggi', () {
      final dark = AppTheme.dark();
      expect(dark.brightness, Brightness.dark);
      expect(dark.scaffoldBackgroundColor, AppColors.darkBackground);
      expect(dark.colorScheme.surface, AppColors.darkSurface);
      expect(AppTheme.light().brightness, Brightness.light);
    });

    test('ThemeController tetap bekerja walau penyimpanan gagal', () async {
      final controller = ThemeController(storage: const _ThrowingStorage());
      await controller.restore();
      expect(controller.value, ThemeMode.system);
      await controller.select(ThemeMode.dark);
      expect(controller.value, ThemeMode.dark);
    });
  });

  group('halaman daftar', () {
    testWidgets('pencarian lokal menyaring dan melaporkan hasil kosong', (tester) async {
      final client = _PolishClient(rows: const [
        {'id': 1, 'name': 'Muhammad Zaid', 'status': 'active'},
        {'id': 2, 'name': 'Salman Al-Farisi', 'status': 'active'},
      ]);
      await tester.pumpWidget(MaterialApp(
        theme: AppTheme.light(),
        home: ResourceScreen(client: client, module: const MobileModule('Santri', 'students')),
      ));
      await tester.pumpAndSettle();

      expect(find.text('2 data'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'salman');
      await tester.pumpAndSettle();
      expect(find.text('Salman Al-Farisi'), findsOneWidget);
      expect(find.text('Muhammad Zaid'), findsNothing);
      expect(find.text('1 dari 2 data'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'tidak-ada');
      await tester.pumpAndSettle();
      expect(find.text('Tidak ada hasil yang cocok.'), findsOneWidget);
    });

    testWidgets('daftar kosong menampilkan keadaan kosong', (tester) async {
      await tester.pumpWidget(MaterialApp(
        theme: AppTheme.dark(),
        home: ResourceScreen(client: _PolishClient(), module: const MobileModule('Nilai', 'grades')),
      ));
      await tester.pumpAndSettle();
      expect(find.text('Belum ada data.'), findsOneWidget);
    });
  });

  group('QR', () {
    testWidgets('menampilkan sisa masa berlaku setelah dibuat', (tester) async {
      await tester.pumpWidget(MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(body: QrGenerator(client: _PolishClient(), purpose: 'attendance')),
      ));
      await tester.tap(find.text('Buat QR'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.textContaining('Sisa 01:'), findsOneWidget);
      expect(find.text('Perbarui QR'), findsOneWidget);

      // Hentikan pewaktu periodik agar tidak bocor antar tes.
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('QR yang sudah lewat ditandai kedaluwarsa', (tester) async {
      await tester.pumpWidget(MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: QrGenerator(
            client: _PolishClient(qrExpiresIn: const Duration(seconds: -5)),
            purpose: 'identity',
          ),
        ),
      ));
      await tester.tap(find.text('Buat QR'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.text('QR kedaluwarsa'), findsOneWidget);
      expect(find.textContaining('Sisa '), findsNothing);
      await tester.pumpWidget(const SizedBox());
    });
  });

  group('beranda', () {
    for (final dark in [false, true]) {
      testWidgets('ringkasan dan modul tampil tanpa overflow di layar kecil (${dark ? 'gelap' : 'terang'})', (tester) async {
        tester.view.physicalSize = const Size(320 * 3, 640 * 3);
        tester.view.devicePixelRatio = 3;
        addTearDown(tester.view.reset);

        await tester.pumpWidget(MaterialApp(
          theme: dark ? AppTheme.dark() : AppTheme.light(),
          home: MobileShell(
            client: _PolishClient(),
            session: const SessionData(
              user: {'id': 1, 'name': 'Pengguna Uji', 'email': 'uji@pesantenku.test'},
              role: 'santri',
              home: '/dashboard/santri',
              memberships: [],
              activePesantren: {'id': 1, 'name': 'Pondok Pesantren Darul Hikmah Al-Falah'},
            ),
            onSessionChanged: (_) {},
            onLoggedOut: () {},
            themeMode: dark ? ThemeMode.dark : ThemeMode.light,
            onThemeModeChanged: (_) {},
          ),
        ));
        expect(find.byType(DashboardSkeleton), findsOneWidget);
        await tester.pumpAndSettle();

        expect(find.text('Ahmad Fauzan Setiawan Al-Hakim'), findsOneWidget);
        expect(find.text('Rp 450.000'), findsOneWidget);
        expect(find.text('Modul Utama'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  });

  group('halaman akun', () {
    testWidgets('pemilih tema memanggil callback', (tester) async {
      ThemeMode? chosen;
      await tester.pumpWidget(MaterialApp(
        theme: AppTheme.light(),
        home: AccountPage(
          client: _PolishClient(),
          session: const SessionData(
            user: {'id': 1, 'name': 'Pengguna Uji', 'email': 'uji@pesantenku.test'},
            role: 'santri',
            home: '/dashboard/santri',
            memberships: [],
          ),
          onSessionChanged: (_) {},
          onLoggedOut: () {},
          themeMode: ThemeMode.system,
          onThemeModeChanged: (mode) => chosen = mode,
        ),
      ));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Gelap'));
      await tester.pumpAndSettle();
      expect(chosen, ThemeMode.dark);
    });

    testWidgets('tanpa callback pemilih tema tidak tampil', (tester) async {
      await tester.pumpWidget(MaterialApp(
        home: AccountPage(
          client: _PolishClient(),
          session: const SessionData(
            user: {'id': 1, 'name': 'Pengguna Uji', 'email': 'uji@pesantenku.test'},
            role: 'santri',
            home: '/dashboard/santri',
            memberships: [],
          ),
          onSessionChanged: (_) {},
          onLoggedOut: () {},
        ),
      ));
      await tester.pumpAndSettle();
      expect(find.text('Tampilan'), findsNothing);
    });
  });

  group('animasi', () {
    testWidgets('FadeSlideIn langsung tampil penuh saat animasi dimatikan sistem', (tester) async {
      await tester.pumpWidget(const MediaQuery(
        data: MediaQueryData(disableAnimations: true),
        child: Directionality(
          textDirection: TextDirection.ltr,
          child: FadeSlideIn(delay: Duration(seconds: 1), child: Text('halo')),
        ),
      ));
      final opacity = tester.widget<Opacity>(find.byType(Opacity));
      expect(opacity.opacity, 1);
    });

    testWidgets('FadeSlideIn menunggu delay lalu selesai', (tester) async {
      await tester.pumpWidget(const Directionality(
        textDirection: TextDirection.ltr,
        child: FadeSlideIn(delay: Duration(milliseconds: 200), child: Text('halo')),
      ));
      expect(tester.widget<Opacity>(find.byType(Opacity)).opacity, 0);
      await tester.pumpAndSettle();
      expect(tester.widget<Opacity>(find.byType(Opacity)).opacity, 1);
    });
  });
}
