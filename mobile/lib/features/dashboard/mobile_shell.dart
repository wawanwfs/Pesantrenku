import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/app_widgets.dart';
import 'mobile_data.dart';

class MobileShell extends StatefulWidget {
  const MobileShell({super.key, required this.role});
  final String role;

  @override
  State<MobileShell> createState() => _MobileShellState();
}

class _MobileShellState extends State<MobileShell> {
  int index = 0;
  Map<String, dynamic>? remote;

  @override
  void initState() {
    super.initState();
    ApiClient().dashboard(widget.role).then((value) {
      if (mounted) setState(() => remote = value);
    });
  }

  @override
  Widget build(BuildContext context) {
    final local = Map<String, dynamic>.from(
      mobileData[widget.role] ?? mobileData['santri']!,
    );
    final title = index == 0
        ? 'Dashboard'
        : List<String>.from(local['menus'])[index - 1];
    return Scaffold(
      body: SafeArea(
        child: IndexedStack(
          index: index == 0 ? 0 : 1,
          children: [
            DashboardScreen(
              role: widget.role,
              local: local,
              remote: remote,
              onOpen: (screen) => setState(() => index = screen),
            ),
            DetailScreen(title: title, role: widget.role),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index > 4 ? 4 : index,
        onDestinationSelected: (value) => setState(() => index = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Beranda',
          ),
          NavigationDestination(
            icon: Icon(Icons.groups_outlined),
            selectedIcon: Icon(Icons.groups),
            label: 'Data',
          ),
          NavigationDestination(
            icon: Icon(Icons.account_balance_wallet_outlined),
            selectedIcon: Icon(Icons.account_balance_wallet),
            label: 'Laporan',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_none),
            selectedIcon: Icon(Icons.notifications),
            label: 'Notifikasi',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Akun',
          ),
        ],
      ),
    );
  }
}

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    super.key,
    required this.role,
    required this.local,
    required this.remote,
    required this.onOpen,
  });
  final String role;
  final Map<String, dynamic> local;
  final Map<String, dynamic>? remote;
  final ValueChanged<int> onOpen;

  @override
  Widget build(BuildContext context) {
    final profile = remote?['profile'] is Map
        ? Map<String, dynamic>.from(remote!['profile'] as Map)
        : <String, dynamic>{};
    final remoteSummary = remote?['summary'];
    final summary = remoteSummary is List
        ? {
            for (final item in remoteSummary)
              if (item is Map)
                item['label'].toString(): item['value'].toString(),
          }
        : Map<String, String>.from(local['summary']);
    final menus = List<String>.from(local['menus']);
    final name = profile['name'] as String? ?? local['name'] as String;
    final subtitle =
        '${profile['subtitle'] as String? ?? local['subtitle'] as String} • ${local['tenant'] as String}';
    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: GradientHeader(
            name: name,
            subtitle: subtitle,
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.all(16),
          sliver: SliverList.list(
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Ringkasan Hari Ini',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                          if (remote != null)
                            const StatusBadge('API aktif')
                          else
                            const StatusBadge('Offline cache'),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: summary.entries
                            .take(4)
                            .map(
                              (e) => StatTile(
                                label: e.key,
                                value: e.value,
                                color: colorFor(e.key),
                              ),
                            )
                            .toList(),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Menu Utama',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 14),
                      GridView.builder(
                        itemCount: menus.length,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        gridDelegate:
                            const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 4,
                              crossAxisSpacing: 10,
                              mainAxisSpacing: 10,
                              childAspectRatio: .88,
                            ),
                        itemBuilder: (context, i) => MenuTile(
                          icon: iconFor(menus[i]),
                          label: menus[i],
                          onTap: () => onOpen(i + 1),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              if (role == 'santri') const QrPreview(),
              if (role == 'klinik') const ClinicStatus(),
              const SizedBox(height: 16),
              const Text(
                'Pengumuman Terbaru',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...sampleRows.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: InfoRow(item: item),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class DetailScreen extends StatelessWidget {
  const DetailScreen({super.key, required this.title, required this.role});
  final String title;
  final String role;

  @override
  Widget build(BuildContext context) {
    final isQr = title.toLowerCase().contains('qr');
    return Scaffold(
      backgroundColor: isQr ? Colors.black : AppColors.neutral50,
      appBar: AppBar(
        title: Text(title),
        backgroundColor: isQr ? Colors.black : AppColors.neutral50,
        foregroundColor: isQr ? Colors.white : AppColors.neutral900,
        elevation: 0,
      ),
      body: isQr
          ? const QrScannerMock()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (title.toLowerCase().contains('pengaturan') ||
                    title.toLowerCase().contains('profil'))
                  const ProfileCard(),
                if (title.toLowerCase().contains('pembayaran') ||
                    title.toLowerCase().contains('tagihan'))
                  const FinanceCard(),
                if (title.toLowerCase().contains('kesehatan') ||
                    title.toLowerCase().contains('obat') ||
                    title.toLowerCase().contains('rekam'))
                  const ClinicStatus(),
                ...sampleRows.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: InfoRow(item: item),
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(onPressed: () {}, child: Text(actionFor(title))),
              ],
            ),
    );
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow({super.key, required this.item});
  final Map<String, String> item;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const CircleAvatar(
          backgroundColor: AppColors.primary50,
          child: Icon(Icons.description_outlined, color: AppColors.primary600),
        ),
        title: Text(
          item['title']!,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: Text(item['subtitle']!),
        trailing: StatusBadge(item['status']!),
      ),
    );
  }
}

class QrPreview extends StatelessWidget {
  const QrPreview({super.key});
  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const Icon(Icons.qr_code_scanner, color: AppColors.primary600),
        title: const Text('QR Scanner'),
        subtitle: const Text('Presensi, izin keluar, dan kunjungan'),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {},
      ),
    );
  }
}

class QrScannerMock extends StatelessWidget {
  const QrScannerMock({super.key});
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            height: 240,
            width: 240,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.white, width: 4),
              borderRadius: BorderRadius.circular(24),
            ),
            child: const Center(
              child: Icon(Icons.qr_code_2, size: 160, color: Colors.white),
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            'Arahkan kamera ke QR Code',
            style: TextStyle(color: Colors.white70),
          ),
          const SizedBox(height: 32),
          const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircleAvatar(
                backgroundColor: Colors.white12,
                child: Icon(Icons.camera_alt, color: Colors.white),
              ),
              SizedBox(width: 24),
              CircleAvatar(
                backgroundColor: Colors.white12,
                child: Icon(Icons.logout, color: Colors.white),
              ),
              SizedBox(width: 24),
              CircleAvatar(
                backgroundColor: Colors.white12,
                child: Icon(Icons.groups, color: Colors.white),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class ClinicStatus extends StatelessWidget {
  const ClinicStatus({super.key});
  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            Text(
              'Kondisi Hari Ini',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(
                backgroundColor: AppColors.primary50,
                child: Icon(Icons.favorite, color: AppColors.primary600),
              ),
              title: Text(
                'Sehat',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                'Alhamdulillah, kondisi tubuhmu dalam keadaan baik.',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ProfileCard extends StatelessWidget {
  const ProfileCard({super.key});
  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 34,
              backgroundColor: AppColors.primary50,
              child: Icon(Icons.person, color: AppColors.primary600, size: 34),
            ),
            SizedBox(height: 12),
            Text(
              'Profil Saya',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            SizedBox(height: 8),
            Text(
              'Informasi akun, kontak, password, notifikasi, dan preferensi aplikasi.',
            ),
          ],
        ),
      ),
    );
  }
}

class FinanceCard extends StatelessWidget {
  const FinanceCard({super.key});
  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Status Pembayaran',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 12),
            Row(
              children: [
                StatTile(label: 'Tagihan', value: 'Rp1.500.000'),
                SizedBox(width: 10),
                StatTile(label: 'Status', value: 'Lunas'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

Color colorFor(String label) {
  if (label.toLowerCase().contains('pelanggaran') ||
      label.toLowerCase().contains('pengeluaran')) {
    return AppColors.error;
  }
  if (label.toLowerCase().contains('izin') ||
      label.toLowerCase().contains('tunggakan')) {
    return AppColors.warning;
  }
  if (label.toLowerCase().contains('sakit')) {
    return AppColors.info;
  }
  return AppColors.primary600;
}

IconData iconFor(String label) {
  final lower = label.toLowerCase();
  if (lower.contains('akademik') ||
      lower.contains('nilai') ||
      lower.contains('raport')) {
    return Icons.school_outlined;
  }
  if (lower.contains('presensi') || lower.contains('jadwal')) {
    return Icons.calendar_month_outlined;
  }
  if (lower.contains('pembayaran') ||
      lower.contains('billing') ||
      lower.contains('tagihan')) {
    return Icons.account_balance_wallet_outlined;
  }
  if (lower.contains('kesehatan') ||
      lower.contains('klinik') ||
      lower.contains('obat')) {
    return Icons.health_and_safety_outlined;
  }
  if (lower.contains('qr')) {
    return Icons.qr_code_scanner;
  }
  if (lower.contains('pengaturan') || lower.contains('profil')) {
    return Icons.settings_outlined;
  }
  return Icons.widgets_outlined;
}

String actionFor(String title) {
  final lower = title.toLowerCase();
  if (lower.contains('presensi')) return 'Simpan Presensi';
  if (lower.contains('pembayaran')) return 'Lanjutkan Pembayaran';
  if (lower.contains('laporan')) return 'Unduh Semua Laporan';
  if (lower.contains('pengumuman')) return 'Buat Pengumuman';
  return 'Tambah Data';
}
