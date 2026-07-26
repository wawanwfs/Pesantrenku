import 'package:flutter_test/flutter_test.dart';
import 'package:pesantenku_mobile/core/network/api_client.dart';

void main() {
  test('SessionData rejects incomplete server session', () {
    expect(
      () => SessionData.fromJson({'user': {'id': 1}}),
      throwsA(isA<ApiException>()),
    );
  });

  test('QR scanner proof is deterministic HMAC-SHA256', () {
    expect(
      ApiClient.scannerProof('scanner-secret', 'challenge|nonce|code'),
      '015fc1c9992d3ad12ab40fc0ccd67f90d6575eda1f66a3cb1a26cbed4d644e2f',
    );
    expect(
      ApiClient.scannerProof('scanner-secret-2', 'challenge|nonce|code'),
      isNot(ApiClient.scannerProof('scanner-secret', 'challenge|nonce|code')),
    );
  });
}
