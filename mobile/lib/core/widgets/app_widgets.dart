import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';

const _dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const _monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

/// Tanggal berbahasa Indonesia tanpa dependensi intl, mis. "Minggu, 4 Oktober 2026".
String indonesianDate(DateTime date) =>
    '${_dayNames[date.weekday - 1]}, ${date.day} ${_monthNames[date.month - 1]} ${date.year}';

/// Salam menurut jam setempat.
String greetingFor(DateTime time) {
  final hour = time.hour;
  if (hour < 4) return 'Selamat malam';
  if (hour < 11) return 'Selamat pagi';
  if (hour < 15) return 'Selamat siang';
  if (hour < 18) return 'Selamat sore';
  return 'Selamat malam';
}

class GradientHeader extends StatelessWidget {
  const GradientHeader({
    super.key,
    required this.name,
    required this.subtitle,
    this.trailing,
  });
  final String name;
  final String subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.primary700, AppColors.primary600],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Assalamu\'alaikum,',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  '${greetingFor(DateTime.now())} · ${indonesianDate(DateTime.now())}',
                  style: const TextStyle(color: Colors.white60, fontSize: 11),
                ),
              ],
            ),
          ),
          trailing ??
              const CircleAvatar(
                radius: 30,
                backgroundColor: Colors.white,
                child: Icon(Icons.person, color: AppColors.primary700),
              ),
        ],
      ),
    );
  }
}

class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.label,
    required this.value,
    this.color = AppColors.primary600,
  });
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: color.withAlpha(18),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class MenuTile extends StatelessWidget {
  const MenuTile({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
  });
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      child: ExcludeSemantics(
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            HapticFeedback.selectionClick();
            onTap();
          },
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: context.surfaceAlt,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: context.borderColor),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: context.accent),
                const SizedBox(height: 8),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.label, {super.key});
  final String label;

  @override
  Widget build(BuildContext context) {
    final lower = label.toLowerCase();
    final color =
        lower.contains('lunas') ||
            lower.contains('aktif') ||
            lower.contains('hadir') ||
            lower.contains('berhasil')
        ? AppColors.primary600
        : lower.contains('sakit') || lower.contains('info')
        ? AppColors.info
        : lower.contains('tolak') || lower.contains('alpa')
        ? AppColors.error
        : AppColors.warning;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withAlpha(18),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

/// Muncul dengan memudar + bergeser sedikit; berurutan lewat [delay]. Dilewati bila pengguna
/// meminta animasi dikurangi (MediaQuery.disableAnimations).
class FadeSlideIn extends StatefulWidget {
  const FadeSlideIn({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.duration = const Duration(milliseconds: 420),
    this.offset = 14,
  });
  final Widget child;
  final Duration delay;
  final Duration duration;
  final double offset;

  @override
  State<FadeSlideIn> createState() => _FadeSlideInState();
}

class _FadeSlideInState extends State<FadeSlideIn> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _curve;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    final total = widget.delay + widget.duration;
    _controller = AnimationController(vsync: this, duration: total);
    final begin = total.inMilliseconds == 0
        ? 0.0
        : widget.delay.inMilliseconds / total.inMilliseconds;
    _curve = CurvedAnimation(
      parent: _controller,
      curve: Interval(begin, 1, curve: Curves.easeOutCubic),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _controller.value = 1;
    } else {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      child: widget.child,
      builder: (context, child) => Opacity(
        opacity: _curve.value,
        child: Transform.translate(
          offset: Offset(0, (1 - _curve.value) * widget.offset),
          child: child,
        ),
      ),
    );
  }
}

/// Blok kerangka dengan kilau yang bergerak. Tanpa animasi bila pengguna meminta dikurangi.
class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.width, this.height = 14, this.radius = 8});
  final double? width;
  final double height;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300),
  );
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (!(MediaQuery.maybeDisableAnimationsOf(context) ?? false)) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = context.isDark ? AppColors.darkSurfaceAlt : AppColors.neutral100;
    final shine = context.isDark ? const Color(0xFF223248) : Colors.white;
    return ExcludeSemantics(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = _controller.value;
          return Container(
            width: widget.width,
            height: widget.height,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(widget.radius),
              gradient: LinearGradient(
                begin: Alignment(-1.6 + t * 3.2, 0),
                end: Alignment(-0.6 + t * 3.2, 0),
                colors: [base, shine, base],
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Kerangka daftar kartu untuk status memuat.
class ListSkeleton extends StatelessWidget {
  const ListSkeleton({super.key, this.count = 6});
  final int count;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Memuat data',
      liveRegion: true,
      child: ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: count,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, _) => const Card(
          child: Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Skeleton(width: 160, height: 14),
                SizedBox(height: 10),
                Skeleton(height: 10),
                SizedBox(height: 6),
                Skeleton(width: 220, height: 10),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Kerangka beranda: header + kartu ringkasan + ubin modul.
class DashboardSkeleton extends StatelessWidget {
  const DashboardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Memuat beranda',
      liveRegion: true,
      child: ListView(
        physics: const NeverScrollableScrollPhysics(),
        children: [
          Container(
            height: 128,
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [AppColors.primary700, AppColors.primary600]),
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Skeleton(width: 90, height: 16),
                const SizedBox(height: 12),
                Row(
                  children: const [
                    Expanded(child: Skeleton(height: 74, radius: 16)),
                    SizedBox(width: 10),
                    Expanded(child: Skeleton(height: 74, radius: 16)),
                  ],
                ),
                const SizedBox(height: 22),
                const Skeleton(width: 110, height: 16),
                const SizedBox(height: 12),
                GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 3,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: .95,
                  children: List.generate(6, (_) => const Skeleton(radius: 16, height: 80)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
