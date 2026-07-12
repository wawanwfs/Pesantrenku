import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiClient {
  ApiClient({String baseUrl = 'http://10.0.2.2:8000/api/v1'})
    : _dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 2),
          receiveTimeout: const Duration(seconds: 4),
        ),
      );

  final Dio _dio;

  Future<Map<String, dynamic>?> login(String login, String password) async {
    try {
      final response = await _dio.post(
        '/auth/login',
        data: {'login': login, 'password': password},
      );
      final data = Map<String, dynamic>.from(response.data);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('token', data['token'] as String);

      final active = data['active_pesantren'];
      if (active is Map) {
        await prefs.setInt('active_pesantren_id', (active['id'] as num).toInt());
        await prefs.setString('active_pesantren_name', active['name'] as String? ?? 'Pesantren');
      }

      return data;
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>?> dashboard(String role) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('token');
      final tenantId = prefs.getInt('active_pesantren_id');
      final response = await _dio.get(
        '/dashboard/${_dashboardPath(role)}',
        options: Options(
          headers: {
            if (token != null) 'Authorization': 'Bearer $token',
            if (tenantId != null) 'X-Pesantren-Id': tenantId.toString(),
          },
        ),
      );
      return Map<String, dynamic>.from(response.data['data']);
    } catch (_) {
      return null;
    }
  }

  String _dashboardPath(String role) {
    return switch (role) {
      'admin_keuangan' => 'keuangan',
      'admin_akademik' => 'akademik',
      'admin_tu' => 'tu',
      'wali_murid' => 'wali-murid',
      _ => role.replaceAll('_', '-'),
    };
  }
}
