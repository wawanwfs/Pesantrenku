import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/network/api_client.dart';
import 'core/theme/app_theme.dart';
import 'features/dashboard/mobile_shell.dart';

void main() {
  runApp(const ProviderScope(child: PesantenkuMobileApp()));
}

class PesantenkuMobileApp extends StatelessWidget {
  const PesantenkuMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Pesantenku',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: const RoleLoginScreen(),
    );
  }
}

class RoleLoginScreen extends ConsumerWidget {
  const RoleLoginScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final roles = [
      ['santri', 'Santri', 'Najwa Karimah', 'santri@pesantenku.test'],
      ['wali_murid', 'Wali Murid', 'Nur Azizah Ghafur', 'wali@pesantenku.test'],
      ['musyrif', 'Musyrif', 'Ustadzah Siti Aisyah', 'musyrif@pesantenku.test'],
      ['guru', 'Guru', 'Ustadzah Siti Aisyah', 'guru@pesantenku.test'],
      ['admin_keuangan', 'Keuangan', 'Bendahara Pesantren', 'keuangan@pesantenku.test'],
      ['klinik', 'Klinik', 'Klinik Pesantenku', 'klinik@pesantenku.test'],
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const PesantenkuLogo(),
              const SizedBox(height: 32),
              Text(
                'Masuk Pesantenku',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              const Text(
                'Pilih role demo. Aplikasi akan memakai API Laravel jika server aktif, dan fallback data lokal jika offline.',
              ),
              const SizedBox(height: 24),
              Expanded(
                child: ListView.separated(
                  itemCount: roles.length,
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final role = roles[index];
                    return Card(
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 10,
                        ),
                        leading: CircleAvatar(
                          backgroundColor: AppColors.primary50,
                          child: Text(
                            role[1][0],
                            style: const TextStyle(
                              color: AppColors.primary700,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        title: Text(
                          role[1],
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(role[2]),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () async {
                          final result = await ApiClient().login(role[3], 'password');
                          if (!context.mounted) return;
                          if (result == null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Server API tidak aktif, memakai data offline demo.'),
                              ),
                            );
                          }
                          Navigator.of(context).pushReplacement(
                            MaterialPageRoute(
                              builder: (_) => MobileShell(role: role[0]),
                            ),
                          );
                        },
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
