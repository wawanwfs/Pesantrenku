import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.validationErrors, this.mfaRequired = false});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? validationErrors;
  final bool mfaRequired;

  @override
  String toString() => message;
}

class SessionData {
  const SessionData({
    required this.user,
    required this.role,
    required this.home,
    required this.memberships,
    this.activePesantren,
  });

  final Map<String, dynamic> user;
  final String role;
  final String home;
  final List<Map<String, dynamic>> memberships;
  final Map<String, dynamic>? activePesantren;

  factory SessionData.fromJson(Map<String, dynamic> json) {
    final rawUser = json['user'];
    final rawRole = json['active_role']?.toString().trim();
    if (rawUser is! Map || rawRole == null || rawRole.isEmpty) {
      throw const ApiException('Respons sesi server tidak lengkap.');
    }
    final memberships = (json['memberships'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    return SessionData(
      user: Map<String, dynamic>.from(rawUser),
      role: rawRole,
      home: (json['home'] ?? '/dashboard/${rawRole.replaceAll('_', '-')}').toString(),
      memberships: memberships,
      activePesantren: json['active_pesantren'] is Map
          ? Map<String, dynamic>.from(json['active_pesantren'] as Map)
          : null,
    );
  }
}

class ApiClient {
  ApiClient({String? baseUrl, FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage(),
        _dio = Dio(
          BaseOptions(
            baseUrl: _resolveBaseUrl(baseUrl),
            connectTimeout: const Duration(seconds: 10),
            sendTimeout: const Duration(seconds: 15),
            receiveTimeout: const Duration(seconds: 20),
            headers: const {'Accept': 'application/json'},
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final skipAuth = options.extra['skipAuth'] == true;
          final skipTenant = options.extra['skipTenant'] == true;
          final deviceId = await _deviceId();
          options.headers['X-Device-Id'] = deviceId;
          if (!skipAuth) {
            final token = await _storage.read(key: _tokenKey);
            if (token != null && token.isNotEmpty) {
              options.headers['Authorization'] = 'Bearer $token';
            }
          }
          if (!skipTenant) {
            final tenantId = await _storage.read(key: _tenantKey);
            if (tenantId != null && tenantId.isNotEmpty) {
              options.headers['X-Pesantren-Id'] = tenantId;
            }
          }
          if ({'POST', 'PUT', 'PATCH', 'DELETE'}.contains(options.method.toUpperCase()) &&
              !options.headers.containsKey('Idempotency-Key')) {
            options.headers['Idempotency-Key'] = _idempotencyKey();
          }
          handler.next(options);
        },
      ),
    );
  }

  static String _resolveBaseUrl(String? override) {
    final value = (override ?? const String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://127.0.0.1:8000/api/v1',
    )).trim();
    final uri = Uri.tryParse(value);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw StateError('API_BASE_URL tidak valid.');
    }
    if (kReleaseMode && uri.scheme.toLowerCase() != 'https') {
      throw StateError('Build release wajib menggunakan API_BASE_URL HTTPS.');
    }
    return value.endsWith('/') ? value.substring(0, value.length - 1) : value;
  }

  static const _tokenKey = 'auth_token';
  static const _tenantKey = 'active_pesantren_id';
  static const _sessionKey = 'session_cache';
  static const _deviceKey = 'device_id';
  static const _scannerSecretKey = 'qr_scanner_secret';
  static const _scannerTenantKey = 'qr_scanner_tenant';

  final Dio _dio;
  final FlutterSecureStorage _storage;
  VoidCallback? _onUnauthorized;

  void setUnauthorizedHandler(VoidCallback? handler) {
    _onUnauthorized = handler;
  }

  Future<SessionData?> restoreSession() async {
    final token = await _storage.read(key: _tokenKey);
    if (token == null || token.isEmpty) return null;

    final data = await _requestMap(() => _dio.get('/auth/me'));
    final session = SessionData.fromJson(data);
    await _persistSession(session);
    return session;
  }

  Future<SessionData> login(String login, String password, {String? mfaCode}) async {
    final deviceId = await _deviceId();
    final data = await _requestMap(
      () => _dio.post(
        '/auth/login',
        data: {
          'login': login,
          'password': password,
          'device_id': deviceId,
          'device_name': 'Pesantenku Mobile',
          if (mfaCode != null && mfaCode.trim().isNotEmpty) 'mfa_code': mfaCode.trim(),
        },
        options: Options(extra: const {'skipAuth': true, 'skipTenant': true}),
      ),
    );
    final token = data['token']?.toString();
    if (token == null || token.isEmpty) {
      throw const ApiException('Server tidak mengembalikan token autentikasi.');
    }
    final session = SessionData.fromJson(data);
    await clearSession();
    await _storage.write(key: _tokenKey, value: token);
    await _persistSession(session);
    return session;
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } finally {
      await clearSession();
    }
  }

  Future<SessionData> switchTenant(int pesantrenId) async {
    final previousTenantId = await _storage.read(key: _tenantKey);
    final data = await _requestMap(
      () => _dio.post('/tenant/switch', data: {'pesantren_id': pesantrenId}),
    );
    await _storage.write(key: _tenantKey, value: pesantrenId.toString());
    try {
      final refreshed = await _requestMap(() => _dio.get('/auth/me'));
      final session = SessionData.fromJson({...refreshed, ...data});
      await _persistSession(session);
      return session;
    } catch (_) {
      if (previousTenantId == null || previousTenantId.isEmpty) {
        await _storage.delete(key: _tenantKey);
      } else {
        await _storage.write(key: _tenantKey, value: previousTenantId);
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> dashboard(String role) => _requestMap(
        () => _dio.get('/dashboard/${_dashboardPath(role)}'),
        dataSelector: (data) => data['data'],
      );

  Future<List<Map<String, dynamic>>> resource(String resource, {int limit = 30}) async {
    final data = await _requestMap(
      () => _dio.get('/$resource', queryParameters: {'per_page': limit}),
    );
    final raw = data['data'];
    final list = raw is Map ? raw['data'] : raw;
    return (list as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<List<Map<String, dynamic>>> notifications() async {
    final data = await _requestMap(() => _dio.get('/notifications'));
    final raw = data['data'] ?? data['notifications'];
    return (raw as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> markNotificationsRead({List<int> ids = const []}) async {
    await _requestMap(() => _dio.post('/notifications/read', data: {'ids': ids}));
  }

  Future<Map<String, dynamic>> generateQr(String purpose) => _requestMap(
        () => _dio.post('/qr/generate', data: {'purpose': purpose}),
      );

  Future<Map<String, dynamic>> registerQrScannerDevice() async {
    final data = await _requestMap(
      () => _dio.post('/qr/scanner/register', data: {'device_name': 'Pesantenku Mobile'}),
    );
    final secret = data['scanner_secret']?.toString();
    final tenantId = await _storage.read(key: _tenantKey);
    if (secret == null || secret.isEmpty || tenantId == null || tenantId.isEmpty) {
      throw const ApiException('Registrasi perangkat pemindai tidak lengkap.');
    }
    await _storage.write(key: _scannerSecretKey, value: secret);
    await _storage.write(key: _scannerTenantKey, value: tenantId);
    return data;
  }

  static String scannerProof(String secret, String proofMessage) {
    final digest = Hmac(sha256, utf8.encode(secret)).convert(utf8.encode(proofMessage));
    return digest.toString();
  }

  @visibleForTesting
  void setHttpClientAdapter(HttpClientAdapter adapter) {
    _dio.httpClientAdapter = adapter;
  }

  Future<Map<String, dynamic>> scanQr(String code) async {
    final tenantId = await _storage.read(key: _tenantKey);
    if (tenantId == null || tenantId.isEmpty) {
      throw const ApiException('Pilih tenant sebelum memindai QR.');
    }
    var secret = await _storage.read(key: _scannerSecretKey);
    final secretTenant = await _storage.read(key: _scannerTenantKey);
    if (secret == null || secret.isEmpty || secretTenant != tenantId) {
      await registerQrScannerDevice();
      secret = await _storage.read(key: _scannerSecretKey);
    }
    if (secret == null || secret.isEmpty) {
      throw const ApiException('Secret perangkat pemindai tidak tersedia.');
    }

    final challenge = await _requestMap(
      () => _dio.post('/qr/scan/challenge', data: {'code': code}),
    );
    final challengeId = challenge['challenge_id']?.toString();
    final nonce = challenge['nonce']?.toString();
    final proofMessage = challenge['proof_message']?.toString();
    if (challengeId == null || nonce == null || proofMessage == null) {
      throw const ApiException('Challenge pemindai tidak lengkap.');
    }
    final proof = scannerProof(secret, proofMessage);

    return _requestMap(
      () => _dio.post(
        '/qr/scan',
        data: {
          'code': code,
          'challenge_id': challengeId,
          'nonce': nonce,
          'proof': proof,
        },
      ),
    );
  }

  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _tenantKey);
    await _storage.delete(key: _sessionKey);
    await _storage.delete(key: _scannerSecretKey);
    await _storage.delete(key: _scannerTenantKey);
  }

  Future<void> _persistSession(SessionData session) async {
    final tenantId = session.activePesantren?['id'];
    if (tenantId != null) {
      await _storage.write(key: _tenantKey, value: tenantId.toString());
    } else {
      await _storage.delete(key: _tenantKey);
    }
    await _storage.write(
      key: _sessionKey,
      value: jsonEncode({
        'user': session.user,
        'active_role': session.role,
        'home': session.home,
        'memberships': session.memberships,
        'active_pesantren': session.activePesantren,
      }),
    );
  }

  Future<Map<String, dynamic>> _requestMap(
    Future<Response<dynamic>> Function() request, {
    dynamic Function(Map<String, dynamic>)? dataSelector,
  }) async {
    try {
      final response = await request();
      final map = response.data is Map
          ? Map<String, dynamic>.from(response.data as Map)
          : <String, dynamic>{};
      final selected = dataSelector?.call(map) ?? map;
      if (selected is! Map) throw const ApiException('Format respons server tidak valid.');
      return Map<String, dynamic>.from(selected);
    } on DioException catch (error) {
      final response = error.response;
      if (response?.statusCode == 401 &&
          error.requestOptions.extra['skipAuth'] != true) {
        try {
          await clearSession();
        } catch (_) {
          // Sesi UI tetap harus dihentikan walau secure storage gagal dibersihkan.
        }
        _onUnauthorized?.call();
      }
      final body = response?.data is Map
          ? Map<String, dynamic>.from(response!.data as Map)
          : <String, dynamic>{};
      final message = body['message']?.toString() ?? switch (error.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => 'Koneksi ke server melewati batas waktu.',
        DioExceptionType.connectionError => 'Server tidak dapat dihubungi. Periksa koneksi dan konfigurasi API.',
        _ => 'Permintaan ke server gagal.',
      };
      throw ApiException(
        message,
        statusCode: response?.statusCode,
        validationErrors: body['errors'] is Map
            ? Map<String, dynamic>.from(body['errors'] as Map)
            : null,
        mfaRequired: body['mfa_required'] == true,
      );
    }
  }


  Future<String> _deviceId() async {
    final existing = await _storage.read(key: _deviceKey);
    if (existing != null && existing.length >= 16) return existing;
    final random = Random.secure();
    final bytes = List<int>.generate(24, (_) => random.nextInt(256));
    final generated = base64UrlEncode(bytes).replaceAll('=', '');
    await _storage.write(key: _deviceKey, value: generated);
    return generated;
  }

  String _idempotencyKey() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    final token = base64UrlEncode(bytes).replaceAll('=', '');
    return 'mobile-${DateTime.now().microsecondsSinceEpoch}-$token';
  }

  String _dashboardPath(String role) => switch (role) {
        'super_admin' => 'super-admin',
        'owner_pesantren' => 'owner',
        'admin_keuangan' => 'keuangan',
        'admin_akademik' => 'akademik',
        'admin_tu' => 'tu',
        'wali_murid' => 'wali-murid',
        _ => role.replaceAll('_', '-'),
      };
}
