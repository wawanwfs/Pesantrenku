import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pesantenku_mobile/core/network/api_client.dart';
import 'package:pesantenku_mobile/main.dart';

class FakeApiClient extends ApiClient {
  FakeApiClient({this.loginError, this.initialSession, this.requireMfa = false}) : super(baseUrl: 'http://localhost');

  final ApiException? loginError;
  final SessionData? initialSession;
  final bool requireMfa;
  String? lastMfaCode;
  int dashboardCalls = 0;
  VoidCallback? unauthorizedHandler;

  @override
  Future<SessionData?> restoreSession() async => initialSession;

  @override
  void setUnauthorizedHandler(VoidCallback? handler) {
    unauthorizedHandler = handler;
    super.setUnauthorizedHandler(handler);
  }

  void expireSession() => unauthorizedHandler?.call();

  @override
  Future<SessionData> login(String login, String password, {String? mfaCode}) async {
    lastMfaCode = mfaCode;
    if (loginError != null) throw loginError!;
    if (requireMfa && (mfaCode == null || mfaCode.isEmpty)) {
      throw const ApiException('Kode MFA diperlukan.', statusCode: 422, mfaRequired: true);
    }

    return const SessionData(
      user: {'id': 1, 'name': 'Pengguna Uji', 'email': 'uji@pesantenku.test'},
      role: 'santri',
      home: '/dashboard/santri',
      memberships: [
        {'id': 1, 'name': 'Pesantren Uji', 'role': 'santri'},
      ],
      activePesantren: {'id': 1, 'name': 'Pesantren Uji'},
    );
  }

  @override
  Future<Map<String, dynamic>> dashboard(String role) async {
    dashboardCalls += 1;
    return {
        'profile': {'name': 'Dashboard $dashboardCalls'},
        'summary': [],
      };
  }

  @override
  Future<List<Map<String, dynamic>>> resource(String resource, {int limit = 30}) async => const [];
}

SessionData testSession(int tenantId) => SessionData(
      user: const {'id': 1, 'name': 'Pengguna Uji', 'email': 'uji@pesantenku.test'},
      role: 'santri',
      home: '/dashboard/santri',
      memberships: const [
        {'id': 1, 'name': 'Pesantren Satu', 'role': 'santri'},
        {'id': 2, 'name': 'Pesantren Dua', 'role': 'santri'},
      ],
      activePesantren: {'id': tenantId, 'name': 'Pesantren $tenantId'},
    );

void main() {
  testWidgets('shows credential login screen', (tester) async {
    await tester.pumpWidget(PesantenkuMobileApp(client: FakeApiClient()));
    await tester.pumpAndSettle();

    expect(find.text('Masuk Pesantenku'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
    expect(find.text('Pilih role demo.'), findsNothing);
  });

  testWidgets('failed authentication never opens the authenticated shell', (tester) async {
    await tester.pumpWidget(PesantenkuMobileApp(
      client: FakeApiClient(
        loginError: const ApiException('Email atau password salah.', statusCode: 401),
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'salah@pesantenku.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'password-yang-salah');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();

    expect(find.text('Email atau password salah.'), findsOneWidget);
    expect(find.text('Masuk Pesantenku'), findsOneWidget);
    expect(find.text('Beranda'), findsNothing);
  });

  testWidgets('tenant change reloads dashboard and returns to home tab', (tester) async {
    final client = FakeApiClient();
    var session = testSession(1);

    Widget shell() => MaterialApp(
          home: MobileShell(
            client: client,
            session: session,
            onSessionChanged: (_) {},
            onLoggedOut: () {},
          ),
        );

    await tester.pumpWidget(shell());
    await tester.pumpAndSettle();
    expect(find.text('Dashboard 1'), findsOneWidget);

    await tester.tap(find.text('Modul'));
    await tester.pumpAndSettle();
    expect(find.text('Modul'), findsWidgets);

    session = testSession(2);
    await tester.pumpWidget(shell());
    await tester.pumpAndSettle();

    expect(client.dashboardCalls, 2);
    expect(find.text('Dashboard 2'), findsOneWidget);
    expect(find.text('Beranda'), findsOneWidget);
  });

  testWidgets('expired API session closes nested screen and returns to login', (tester) async {
    final client = FakeApiClient(initialSession: testSession(1));
    await tester.pumpWidget(PesantenkuMobileApp(client: client));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Nilai').first);
    await tester.pumpAndSettle();
    expect(find.text('Belum ada data.'), findsOneWidget);

    client.expireSession();
    await tester.pumpAndSettle();

    expect(find.text('Masuk Pesantenku'), findsOneWidget);
    expect(find.text('Sesi Anda telah berakhir. Silakan masuk kembali.'), findsOneWidget);
    expect(find.text('Belum ada data.'), findsNothing);
  });
  testWidgets('privileged login requires and submits MFA', (tester) async {
    final client = FakeApiClient(requireMfa: true);
    await tester.pumpWidget(PesantenkuMobileApp(client: client));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'owner@pesantenku.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'secret-password');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();

    expect(find.text('Kode MFA atau recovery'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).at(2), '123456');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();

    expect(client.lastMfaCode, '123456');
    expect(find.text('Beranda'), findsOneWidget);
  });

}
