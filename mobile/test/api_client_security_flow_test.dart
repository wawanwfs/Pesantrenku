import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pesantenku_mobile/core/network/api_client.dart';

class _QueuedAdapter implements HttpClientAdapter {
  _QueuedAdapter(this.responses);

  final List<ResponseBody Function(RequestOptions)> responses;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (responses.isEmpty) {
      throw StateError('Tidak ada respons uji untuk ${options.method} ${options.path}.');
    }
    return responses.removeAt(0)(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(int status, Map<String, dynamic> body) => ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
  });

  test('privileged API login performs MFA retry and binds token to device', () async {
    final adapter = _QueuedAdapter([
      (_) => _json(422, {
            'message': 'Kode MFA diperlukan.',
            'mfa_required': true,
            'errors': {
              'mfa_code': ['Masukkan kode TOTP atau recovery.'],
            },
          }),
      (_) => _json(200, {
            'token': 'plain-token-value',
            'token_expires_at': '2026-07-27T12:00:00Z',
            'user': {'id': 7, 'name': 'Owner Uji'},
            'active_role': 'owner_pesantren',
            'home': '/dashboard/owner',
            'memberships': [
              {'id': 11, 'name': 'Pesantren Uji', 'role': 'owner_pesantren'},
            ],
            'active_pesantren': {'id': 11, 'name': 'Pesantren Uji'},
          }),
    ]);
    final client = ApiClient(baseUrl: 'https://api.pesantenku.test/api/v1');
    client.setHttpClientAdapter(adapter);

    await expectLater(
      client.login('owner@example.test', 'secret-password'),
      throwsA(isA<ApiException>().having((e) => e.mfaRequired, 'mfaRequired', isTrue)),
    );

    final session = await client.login(
      'owner@example.test',
      'secret-password',
      mfaCode: '123456',
    );

    expect(session.role, 'owner_pesantren');
    expect(adapter.requests, hasLength(2));
    final firstBody = Map<String, dynamic>.from(adapter.requests.first.data as Map);
    final secondBody = Map<String, dynamic>.from(adapter.requests.last.data as Map);
    expect(firstBody['device_id'], isNotEmpty);
    expect(secondBody['device_id'], firstBody['device_id']);
    expect(secondBody['mfa_code'], '123456');
    expect(adapter.requests.last.headers['X-Device-Id'], firstBody['device_id']);

    const storage = FlutterSecureStorage();
    expect(await storage.read(key: 'auth_token'), 'plain-token-value');
    expect(await storage.read(key: 'active_pesantren_id'), '11');
  });

  test('401 clears persisted session and invokes unauthorized handler', () async {
    FlutterSecureStorage.setMockInitialValues({
      'auth_token': 'expired-token',
      'active_pesantren_id': '11',
      'session_cache': '{}',
      'device_id': 'device-identifier-at-least-16',
      'qr_scanner_secret': 'scanner-secret',
      'qr_scanner_tenant': '11',
    });
    final adapter = _QueuedAdapter([
      (_) => _json(401, {'message': 'Token tidak berlaku.'}),
    ]);
    final client = ApiClient(baseUrl: 'https://api.pesantenku.test/api/v1');
    client.setHttpClientAdapter(adapter);
    var unauthorized = false;
    client.setUnauthorizedHandler(() => unauthorized = true);

    await expectLater(client.restoreSession(), throwsA(isA<ApiException>()));

    const storage = FlutterSecureStorage();
    expect(unauthorized, isTrue);
    expect(await storage.read(key: 'auth_token'), isNull);
    expect(await storage.read(key: 'active_pesantren_id'), isNull);
    expect(await storage.read(key: 'session_cache'), isNull);
    expect(await storage.read(key: 'qr_scanner_secret'), isNull);
    expect(await storage.read(key: 'device_id'), 'device-identifier-at-least-16');
  });
}
