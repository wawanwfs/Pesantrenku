import 'package:flutter_test/flutter_test.dart';
import 'package:pesantenku_mobile/main.dart';

void main() {
  testWidgets('shows role login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const PesantenkuMobileApp());

    expect(find.text('Masuk Pesantenku'), findsOneWidget);
    expect(find.text('Santri'), findsOneWidget);
  });
}
