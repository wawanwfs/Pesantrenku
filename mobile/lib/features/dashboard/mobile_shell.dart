import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/app_widgets.dart';
import 'mobile_data.dart';

class MobileShell extends StatefulWidget {
  const MobileShell({
    super.key,
    required this.client,
    required this.session,
    required this.onSessionChanged,
    required this.onLoggedOut,
  });

  final ApiClient client;
  final SessionData session;
  final ValueChanged<SessionData> onSessionChanged;
  final VoidCallback onLoggedOut;

  @override
  State<MobileShell> createState() => _MobileShellState();
}

class _MobileShellState extends State<MobileShell> {
  int index = 0;
  late Future<Map<String, dynamic>> dashboard;

  @override
  void initState() {
    super.initState();
    dashboard = widget.client.dashboard(widget.session.role);
  }

  @override
  void didUpdateWidget(covariant MobileShell oldWidget) {
    super.didUpdateWidget(oldWidget);
    final oldTenantId = oldWidget.session.activePesantren?['id']?.toString();
    final tenantId = widget.session.activePesantren?['id']?.toString();
    if (oldWidget.client != widget.client ||
        oldWidget.session.role != widget.session.role ||
        oldTenantId != tenantId) {
      index = 0;
      dashboard = widget.client.dashboard(widget.session.role);
    }
  }

  void refresh() => setState(() => dashboard = widget.client.dashboard(widget.session.role));

  @override
  Widget build(BuildContext context) {
    final pages = [
      _DashboardPage(
        session: widget.session,
        dashboard: dashboard,
        onRefresh: refresh,
        onOpenModule: (module) => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ResourceScreen(client: widget.client, module: module),
          ),
        ),
      ),
      _ModulesPage(
        role: widget.session.role,
        onOpen: (module) => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ResourceScreen(client: widget.client, module: module)),
        ),
      ),
      QrPage(client: widget.client, role: widget.session.role),
      NotificationsPage(client: widget.client),
      AccountPage(
        client: widget.client,
        session: widget.session,
        onSessionChanged: widget.onSessionChanged,
        onLoggedOut: widget.onLoggedOut,
      ),
    ];

    return Scaffold(
      body: SafeArea(child: IndexedStack(index: index, children: pages)),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) => setState(() => index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Beranda'),
          NavigationDestination(icon: Icon(Icons.grid_view_outlined), selectedIcon: Icon(Icons.grid_view), label: 'Modul'),
          NavigationDestination(icon: Icon(Icons.qr_code_scanner), label: 'QR'),
          NavigationDestination(icon: Icon(Icons.notifications_none), selectedIcon: Icon(Icons.notifications), label: 'Notifikasi'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Akun'),
        ],
      ),
    );
  }
}

class _DashboardPage extends StatelessWidget {
  const _DashboardPage({
    required this.session,
    required this.dashboard,
    required this.onRefresh,
    required this.onOpenModule,
  });

  final SessionData session;
  final Future<Map<String, dynamic>> dashboard;
  final VoidCallback onRefresh;
  final ValueChanged<MobileModule> onOpenModule;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: dashboard,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorView(message: snapshot.error.toString(), onRetry: onRefresh);
        }
        final data = snapshot.data ?? const <String, dynamic>{};
        final profile = data['profile'] is Map
            ? Map<String, dynamic>.from(data['profile'] as Map)
            : const <String, dynamic>{};
        final summary = (data['summary'] as List? ?? const []).whereType<Map>().take(4).toList();
        final modules = modulesFor(session.role);
        final name = profile['name']?.toString() ?? session.user['name']?.toString() ?? 'Pengguna';
        final tenant = session.activePesantren?['name']?.toString() ?? 'Pesantenku';

        return RefreshIndicator(
          onRefresh: () async => onRefresh(),
          child: ListView(
            children: [
              GradientHeader(name: name, subtitle: '$tenant • ${_roleLabel(session.role)}'),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (summary.isNotEmpty) ...[
                      const Text('Ringkasan', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: summary.map((item) {
                          return SizedBox(
                            width: (MediaQuery.sizeOf(context).width - 42) / 2,
                            child: Card(
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(item['label']?.toString() ?? 'Ringkasan', style: const TextStyle(fontSize: 12, color: AppColors.neutral600)),
                                    const SizedBox(height: 6),
                                    Text(item['value']?.toString() ?? '-', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                                  ],
                                ),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                      const SizedBox(height: 20),
                    ],
                    const Text('Modul Utama', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(height: 10),
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: modules.length,
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3,
                        mainAxisSpacing: 10,
                        crossAxisSpacing: 10,
                        childAspectRatio: .95,
                      ),
                      itemBuilder: (_, i) => MenuTile(
                        icon: _moduleIcon(modules[i].resource),
                        label: modules[i].label,
                        onTap: () => onOpenModule(modules[i]),
                      ),
                    ),
                    if (modules.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 28),
                        child: Text('Belum ada modul mobile untuk role ini.'),
                      ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _ModulesPage extends StatelessWidget {
  const _ModulesPage({required this.role, required this.onOpen});
  final String role;
  final ValueChanged<MobileModule> onOpen;

  @override
  Widget build(BuildContext context) {
    final modules = modulesFor(role);
    return Scaffold(
      appBar: AppBar(title: const Text('Modul')),
      body: modules.isEmpty
          ? const Center(child: Text('Belum ada modul untuk role ini.'))
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: modules.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) => Card(
                child: ListTile(
                  leading: Icon(_moduleIcon(modules[i].resource), color: AppColors.primary600),
                  title: Text(modules[i].label, style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text(modules[i].resource),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => onOpen(modules[i]),
                ),
              ),
            ),
    );
  }
}

class ResourceScreen extends StatefulWidget {
  const ResourceScreen({super.key, required this.client, required this.module});
  final ApiClient client;
  final MobileModule module;

  @override
  State<ResourceScreen> createState() => _ResourceScreenState();
}

class _ResourceScreenState extends State<ResourceScreen> {
  late Future<List<Map<String, dynamic>>> rows;

  @override
  void initState() {
    super.initState();
    rows = widget.client.resource(widget.module.resource);
  }

  void retry() => setState(() => rows = widget.client.resource(widget.module.resource));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.module.label)),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: rows,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), onRetry: retry);
          final items = snapshot.data ?? const [];
          if (items.isEmpty) return const Center(child: Text('Belum ada data.'));
          return RefreshIndicator(
            onRefresh: () async => retry(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final item = items[i];
                final title = _firstValue(item, const ['name', 'title', 'invoice_number', 'number', 'nis', 'complaint', 'queue_number']) ?? 'Data #${item['id'] ?? i + 1}';
                final status = item['status']?.toString();
                final subtitle = item.entries
                    .where((entry) => !['id', 'name', 'title', 'status', 'created_at', 'updated_at', 'deleted_at', 'metadata'].contains(entry.key))
                    .take(3)
                    .map((entry) => '${_fieldLabel(entry.key)}: ${entry.value ?? '-'}')
                    .join(' • ');
                return Card(
                  child: ListTile(
                    title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: subtitle.isEmpty ? null : Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis),
                    trailing: status == null ? null : StatusBadge(status),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class QrPage extends StatelessWidget {
  const QrPage({super.key, required this.client, required this.role});
  final ApiClient client;
  final String role;

  @override
  Widget build(BuildContext context) {
    final canScan = const ['guru', 'musyrif', 'klinik', 'admin_tu', 'admin_akademik', 'admin_keuangan', 'owner_pesantren'].contains(role);
    final purpose = role == 'santri' ? 'attendance' : 'identity';
    return Scaffold(
      appBar: AppBar(title: const Text('QR Pesantenku')),
      body: role == 'super_admin'
          ? const Center(child: Padding(
              padding: EdgeInsets.all(24),
              child: Text('QR operasional hanya tersedia di dalam membership tenant.', textAlign: TextAlign.center),
            ))
          : canScan
              ? QrScanner(client: client)
              : QrGenerator(client: client, purpose: purpose),
    );
  }
}

class QrGenerator extends StatefulWidget {
  const QrGenerator({super.key, required this.client, required this.purpose});
  final ApiClient client;
  final String purpose;

  @override
  State<QrGenerator> createState() => _QrGeneratorState();
}

class _QrGeneratorState extends State<QrGenerator> {
  Map<String, dynamic>? data;
  bool loading = false;
  String? error;

  Future<void> generate() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.client.generateQr(widget.purpose);
      final code = result['code'];
      if (code is! String || code.length != 64) {
        throw const ApiException('Server mengembalikan kode QR yang tidak valid.');
      }
      if (mounted) setState(() => data = result);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final code = data?['code']?.toString();
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const Text('QR ini hanya berlaku singkat dan sekali pakai. Jangan membagikannya melalui tangkapan layar.', textAlign: TextAlign.center),
        const SizedBox(height: 24),
        if (code != null)
          Center(
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Semantics(
                  image: true,
                  label: widget.purpose == 'attendance'
                      ? 'QR presensi Pesantenku'
                      : 'QR identitas Pesantenku',
                  child: ExcludeSemantics(
                    child: QrImageView(data: code, size: 240),
                  ),
                ),
              ),
            ),
          )
        else
          const SizedBox(height: 280, child: Center(child: Icon(Icons.qr_code_2, size: 160, color: AppColors.neutral200))),
        if (data?['expires_at'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text('Berlaku sampai ${data!['expires_at']}', textAlign: TextAlign.center),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Semantics(
              liveRegion: true,
              child: Text(error!, style: const TextStyle(color: AppColors.error), textAlign: TextAlign.center),
            ),
          ),
        const SizedBox(height: 24),
        FilledButton(onPressed: loading ? null : generate, child: Text(code == null ? 'Buat QR' : 'Perbarui QR')),
      ],
    );
  }
}

class QrScanner extends StatefulWidget {
  const QrScanner({super.key, required this.client});
  final ApiClient client;

  @override
  State<QrScanner> createState() => _QrScannerState();
}

class _QrScannerState extends State<QrScanner> {
  final controller = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool processing = false;
  String? message;
  String? error;

  Future<void> detect(BarcodeCapture capture) async {
    if (processing) return;
    final code = capture.barcodes.isEmpty ? null : capture.barcodes.first.rawValue;
    if (code == null || code.length != 64) return;
    setState(() { processing = true; message = null; error = null; });
    await controller.stop();
    try {
      final result = await widget.client.scanQr(code);
      if (mounted) setState(() => message = result['message']?.toString() ?? 'QR berhasil diproses.');
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => processing = false);
    }
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: Stack(
            fit: StackFit.expand,
            children: [
              MobileScanner(controller: controller, onDetect: detect),
              Center(
                child: Container(
                  width: 250,
                  height: 250,
                  decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(20)),
                ),
              ),
              if (processing) const Center(child: CircularProgressIndicator()),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              if (message != null) Semantics(
                liveRegion: true,
                child: Text(message!, style: const TextStyle(color: AppColors.primary700, fontWeight: FontWeight.w700)),
              ),
              if (error != null) Semantics(
                liveRegion: true,
                child: Text(error!, style: const TextStyle(color: AppColors.error)),
              ),
              const SizedBox(height: 10),
              FilledButton.tonal(
                onPressed: processing ? null : () { setState(() { message = null; error = null; }); controller.start(); },
                child: const Text('Pindai Lagi'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key, required this.client});
  final ApiClient client;

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  late Future<List<Map<String, dynamic>>> notifications;
  bool markingRead = false;
  String? error;

  @override
  void initState() {
    super.initState();
    notifications = widget.client.notifications();
  }

  void retry() => setState(() => notifications = widget.client.notifications());

  Future<void> markAllRead() async {
    setState(() { markingRead = true; error = null; });
    try {
      await widget.client.markNotificationsRead();
      retry();
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => markingRead = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi'),
        actions: [
          IconButton(
            tooltip: 'Tandai semua dibaca',
            onPressed: markingRead ? null : markAllRead,
            icon: markingRead
                ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.done_all),
          ),
        ],
      ),
      body: Column(
        children: [
          if (error != null) Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Semantics(
              liveRegion: true,
              child: Text(error!, style: const TextStyle(color: AppColors.error)),
            ),
          ),
          Expanded(child: FutureBuilder<List<Map<String, dynamic>>>(
        future: notifications,
        builder: (_, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), onRetry: retry);
          final rows = snapshot.data ?? const [];
          if (rows.isEmpty) return const Center(child: Text('Tidak ada notifikasi.'));
          return RefreshIndicator(
            onRefresh: () async => retry(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: rows.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) => Card(
                child: ListTile(
                  leading: const Icon(Icons.notifications_outlined, color: AppColors.primary600),
                  title: Text(_firstValue(rows[i], const ['title', 'subject', 'message']) ?? 'Notifikasi'),
                  subtitle: Text(_firstValue(rows[i], const ['body', 'message', 'created_at']) ?? ''),
                ),
              ),
            ),
          );
        },
      )),
        ],
      ),
    );
  }
}

class AccountPage extends StatefulWidget {
  const AccountPage({
    super.key,
    required this.client,
    required this.session,
    required this.onSessionChanged,
    required this.onLoggedOut,
  });
  final ApiClient client;
  final SessionData session;
  final ValueChanged<SessionData> onSessionChanged;
  final VoidCallback onLoggedOut;

  @override
  State<AccountPage> createState() => _AccountPageState();
}

class _AccountPageState extends State<AccountPage> {
  bool loading = false;
  String? error;

  Future<void> switchTenant(int id) async {
    setState(() { loading = true; error = null; });
    try {
      final session = await widget.client.switchTenant(id);
      if (mounted) widget.onSessionChanged(session);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> logout() async {
    setState(() => loading = true);
    try {
      await widget.client.logout();
    } finally {
      if (mounted) widget.onLoggedOut();
    }
  }

  @override
  Widget build(BuildContext context) {
    final activeId = int.tryParse(widget.session.activePesantren?['id']?.toString() ?? '');
    return Scaffold(
      appBar: AppBar(title: const Text('Akun')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const CircleAvatar(child: Icon(Icons.person)),
              title: Text(widget.session.user['name']?.toString() ?? 'Pengguna'),
              subtitle: Text('${widget.session.user['email'] ?? ''}\n${_roleLabel(widget.session.role)}'),
              isThreeLine: true,
            ),
          ),
          const SizedBox(height: 16),
          if (widget.session.memberships.length > 1) ...[
            const Text('Pesantren Aktif', style: TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            DropdownButtonFormField<int>(
              key: ValueKey(activeId),
              initialValue: activeId,
              decoration: const InputDecoration(border: OutlineInputBorder()),
              items: widget.session.memberships.map((membership) {
                final id = (membership['id'] as num).toInt();
                return DropdownMenuItem(value: id, child: Text(membership['name']?.toString() ?? 'Pesantren #$id'));
              }).toList(),
              onChanged: loading ? null : (id) { if (id != null && id != activeId) switchTenant(id); },
            ),
            const SizedBox(height: 16),
          ],
          if (error != null) Semantics(
            liveRegion: true,
            child: Text(error!, style: const TextStyle(color: AppColors.error)),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: loading ? null : logout,
            icon: const Icon(Icons.logout),
            label: const Text('Keluar'),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 56, color: AppColors.error),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.tonal(onPressed: onRetry, child: const Text('Coba Lagi')),
          ],
        ),
      ),
    );
  }
}

String? _firstValue(Map<String, dynamic> row, List<String> keys) {
  for (final key in keys) {
    final value = row[key];
    if (value != null && value.toString().trim().isNotEmpty) return value.toString();
  }
  return null;
}

String _fieldLabel(String value) => value.split('_').map((part) => part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}').join(' ');

String _roleLabel(String role) => _fieldLabel(role == 'owner_pesantren' ? 'owner pesantren' : role);

IconData _moduleIcon(String resource) {
  if (resource.contains('payment') || resource.contains('invoice') || resource.contains('cashflow') || resource.contains('expense')) return Icons.account_balance_wallet_outlined;
  if (resource.contains('medical') || resource.contains('medicine')) return Icons.health_and_safety_outlined;
  if (resource.contains('attendance')) return Icons.fact_check_outlined;
  if (resource.contains('grade') || resource.contains('report') || resource.contains('subject')) return Icons.school_outlined;
  if (resource.contains('permit')) return Icons.assignment_turned_in_outlined;
  return Icons.folder_outlined;
}
