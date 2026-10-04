import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
    this.themeMode,
    this.onThemeModeChanged,
  });

  final ApiClient client;
  final SessionData session;
  final ValueChanged<SessionData> onSessionChanged;
  final VoidCallback onLoggedOut;

  /// Opsional: bila keduanya diberikan, halaman Akun menampilkan pemilih tema.
  final ThemeMode? themeMode;
  final ValueChanged<ThemeMode>? onThemeModeChanged;

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
        themeMode: widget.themeMode,
        onThemeModeChanged: widget.onThemeModeChanged,
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
          return const DashboardSkeleton();
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
                        children: summary.indexed.map((entry) {
                          final item = entry.$2;
                          return FadeSlideIn(
                            delay: Duration(milliseconds: 60 * entry.$1),
                            child: SizedBox(
                              width: (MediaQuery.sizeOf(context).width - 42) / 2,
                              child: Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(item['label']?.toString() ?? 'Ringkasan', style: TextStyle(fontSize: 12, color: context.mutedText)),
                                      const SizedBox(height: 6),
                                      Text(item['value']?.toString() ?? '-', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                                    ],
                                  ),
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
                      itemBuilder: (_, i) => FadeSlideIn(
                        delay: Duration(milliseconds: 160 + 45 * i),
                        child: MenuTile(
                          icon: _moduleIcon(modules[i].resource),
                          label: modules[i].label,
                          onTap: () => onOpenModule(modules[i]),
                        ),
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
                  leading: Icon(_moduleIcon(modules[i].resource), color: context.accent),
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
  final searchController = TextEditingController();
  String query = '';

  @override
  void initState() {
    super.initState();
    rows = widget.client.resource(widget.module.resource);
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  void retry() => setState(() => rows = widget.client.resource(widget.module.resource));

  bool _matches(Map<String, dynamic> item) {
    if (query.isEmpty) return true;
    final needle = query.toLowerCase();
    return item.values.any((value) => value != null && value.toString().toLowerCase().contains(needle));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.module.label)),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: rows,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const ListSkeleton();
          }
          if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), onRetry: retry);
          final items = snapshot.data ?? const [];
          if (items.isEmpty) return const _EmptyView(message: 'Belum ada data.');
          final visible = items.where(_matches).toList();
          return RefreshIndicator(
            onRefresh: () async => retry(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: visible.length + 1,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, index) {
                if (index == 0) {
                  return _SearchHeader(
                    controller: searchController,
                    total: items.length,
                    shown: visible.length,
                    onChanged: (value) => setState(() => query = value.trim()),
                  );
                }
                final i = index - 1;
                final item = visible[i];
                final title = _firstValue(item, const ['name', 'title', 'invoice_number', 'number', 'nis', 'complaint', 'queue_number']) ?? 'Data #${item['id'] ?? i + 1}';
                final status = item['status']?.toString();
                final subtitle = item.entries
                    .where((entry) => !['id', 'name', 'title', 'status', 'created_at', 'updated_at', 'deleted_at', 'metadata'].contains(entry.key))
                    .take(3)
                    .map((entry) => '${_fieldLabel(entry.key)}: ${entry.value ?? '-'}')
                    .join(' • ');
                return FadeSlideIn(
                  delay: Duration(milliseconds: i < 10 ? 35 * i : 0),
                  child: Card(
                    child: ListTile(
                      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: subtitle.isEmpty ? null : Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis),
                      trailing: status == null ? null : StatusBadge(status),
                    ),
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

/// Kolom cari lokal di atas daftar: menyaring data yang sudah dimuat, tanpa permintaan jaringan baru.
class _SearchHeader extends StatelessWidget {
  const _SearchHeader({
    required this.controller,
    required this.total,
    required this.shown,
    required this.onChanged,
  });
  final TextEditingController controller;
  final int total;
  final int shown;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          controller: controller,
          onChanged: onChanged,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            hintText: 'Cari di daftar ini',
            prefixIcon: const Icon(Icons.search),
            suffixIcon: controller.text.isEmpty
                ? null
                : IconButton(
                    tooltip: 'Hapus pencarian',
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      controller.clear();
                      onChanged('');
                    },
                  ),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
            isDense: true,
          ),
        ),
        const SizedBox(height: 8),
        Semantics(
          liveRegion: true,
          child: Text(
            shown == total ? '$total data' : '$shown dari $total data',
            style: TextStyle(fontSize: 12, color: context.mutedText),
          ),
        ),
        if (shown == 0)
          const Padding(
            padding: EdgeInsets.only(top: 24),
            child: _EmptyView(message: 'Tidak ada hasil yang cocok.', icon: Icons.search_off),
          ),
      ],
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
  Timer? _ticker;
  DateTime _now = DateTime.now();
  DateTime? _issuedAt;

  DateTime? get _expiresAt => DateTime.tryParse(data?['expires_at']?.toString() ?? '')?.toLocal();

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  void _startCountdown() {
    _ticker?.cancel();
    _issuedAt = DateTime.now();
    _now = _issuedAt!;
    if (_expiresAt == null) return;
    // Pewaktu hanya hidup selama QR berlaku, lalu berhenti sendiri.
    _ticker = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      setState(() => _now = DateTime.now());
      final expiresAt = _expiresAt;
      if (expiresAt == null || !_now.isBefore(expiresAt)) timer.cancel();
    });
  }

  Future<void> generate() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.client.generateQr(widget.purpose);
      final code = result['code'];
      if (code is! String || code.length != 64) {
        throw const ApiException('Server mengembalikan kode QR yang tidak valid.');
      }
      if (mounted) {
        HapticFeedback.mediumImpact();
        setState(() => data = result);
        _startCountdown();
      }
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _remainingLabel(Duration remaining) {
    final seconds = remaining.inSeconds.clamp(0, 86400 * 365);
    return '${(seconds ~/ 60).toString().padLeft(2, '0')}:${(seconds % 60).toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final code = data?['code']?.toString();
    final expiresAt = _expiresAt;
    final remaining = expiresAt?.difference(_now);
    final expired = remaining != null && remaining.isNegative;
    final total = expiresAt != null && _issuedAt != null ? expiresAt.difference(_issuedAt!) : null;
    final progress = (remaining != null && total != null && total.inMilliseconds > 0 && !expired)
        ? (remaining.inMilliseconds / total.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const Text('QR ini hanya berlaku singkat dan sekali pakai. Jangan membagikannya melalui tangkapan layar.', textAlign: TextAlign.center),
        const SizedBox(height: 24),
        if (code != null)
          Center(
            child: Card(
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    AnimatedOpacity(
                      duration: const Duration(milliseconds: 240),
                      opacity: expired ? 0.1 : 1,
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
                    if (expired)
                      const Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.timer_off_outlined, size: 40, color: AppColors.warning),
                          SizedBox(height: 8),
                          Text('QR kedaluwarsa', style: TextStyle(fontWeight: FontWeight.w800, color: Colors.black87)),
                          Text('Buat QR baru untuk melanjutkan.', style: TextStyle(color: Colors.black54, fontSize: 12)),
                        ],
                      ),
                  ],
                ),
              ),
            ),
          )
        else
          SizedBox(height: 280, child: Center(child: Icon(Icons.qr_code_2, size: 160, color: context.borderColor))),
        if (data?['expires_at'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text('Berlaku sampai ${data!['expires_at']}', textAlign: TextAlign.center),
          ),
        if (remaining != null && !expired)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Semantics(
              label: 'Sisa masa berlaku QR ${_remainingLabel(remaining)}',
              child: Column(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 6,
                      color: remaining.inSeconds < 30 ? AppColors.error : context.accent,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text('Sisa ${_remainingLabel(remaining)}', style: TextStyle(fontSize: 12, color: context.mutedText)),
                ],
              ),
            ),
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
      if (mounted) {
        HapticFeedback.mediumImpact();
        setState(() => message = result['message']?.toString() ?? 'QR berhasil diproses.');
      }
    } on ApiException catch (exception) {
      if (mounted) {
        HapticFeedback.heavyImpact();
        setState(() => error = exception.message);
      }
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
          if (snapshot.connectionState != ConnectionState.done) return const ListSkeleton(count: 5);
          if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), onRetry: retry);
          final rows = snapshot.data ?? const [];
          if (rows.isEmpty) return const _EmptyView(message: 'Tidak ada notifikasi.', icon: Icons.notifications_off_outlined);
          return RefreshIndicator(
            onRefresh: () async => retry(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: rows.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final unread = rows[i]['read_at'] == null;
                return FadeSlideIn(
                  delay: Duration(milliseconds: i < 8 ? 40 * i : 0),
                  child: Card(
                    child: ListTile(
                      leading: Icon(unread ? Icons.notifications_active_outlined : Icons.notifications_outlined, color: context.accent),
                      title: Text(
                        _firstValue(rows[i], const ['title', 'subject', 'message']) ?? 'Notifikasi',
                        style: TextStyle(fontWeight: unread ? FontWeight.w800 : FontWeight.w500),
                      ),
                      subtitle: Text(_firstValue(rows[i], const ['body', 'message', 'created_at']) ?? ''),
                    ),
                  ),
                );
              },
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
    this.themeMode,
    this.onThemeModeChanged,
  });
  final ApiClient client;
  final SessionData session;
  final ValueChanged<SessionData> onSessionChanged;
  final VoidCallback onLoggedOut;
  final ThemeMode? themeMode;
  final ValueChanged<ThemeMode>? onThemeModeChanged;

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
          if (widget.themeMode != null && widget.onThemeModeChanged != null) ...[
            const Text('Tampilan', style: TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            SegmentedButton<ThemeMode>(
              segments: const [
                ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined), label: Text('Terang')),
                ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_outlined), label: Text('Gelap')),
                ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto_outlined), label: Text('Sistem')),
              ],
              selected: {widget.themeMode!},
              showSelectedIcon: false,
              onSelectionChanged: (selection) {
                HapticFeedback.selectionClick();
                widget.onThemeModeChanged!(selection.first);
              },
            ),
          ],
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

class _EmptyView extends StatelessWidget {
  const _EmptyView({required this.message, this.icon = Icons.inbox_outlined});
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: context.borderColor),
            const SizedBox(height: 10),
            Text(message, style: TextStyle(color: context.mutedText)),
          ],
        ),
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
