import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AppColors {
  static const primary50 = Color(0xFFE6F4EE);
  static const primary100 = Color(0xFFC8E9DA);
  static const primary400 = Color(0xFF35A872);
  static const primary500 = Color(0xFF17BF5C);
  static const primary600 = Color(0xFF127A4F);
  static const primary700 = Color(0xFF0E623F);
  static const primary900 = Color(0xFF06402A);
  static const neutral50 = Color(0xFFF8FAFC);
  static const neutral100 = Color(0xFFF1F5F9);
  static const neutral200 = Color(0xFFE2E8F0);
  static const neutral600 = Color(0xFF475569);
  static const neutral900 = Color(0xFF0F172A);
  static const warning = Color(0xFFF59E0B);
  static const error = Color(0xFFEF4444);
  static const info = Color(0xFF3B82F6);
  static const purple = Color(0xFF8B5CF6);

  // Palet mode gelap: selaras dengan token web (docs/ui/design-system.md).
  static const darkBackground = Color(0xFF0A0F16);
  static const darkSurface = Color(0xFF111A26);
  static const darkSurfaceAlt = Color(0xFF182434);
  static const darkBorder = Color(0xFF26344A);
  static const darkMuted = Color(0xFF94A6BC);
  static const darkInk = Color(0xFFE6EDF5);
}

/// Warna yang menyesuaikan terang/gelap tanpa mengubah tampilan mode terang yang sudah ada.
extension AppThemeContext on BuildContext {
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
  Color get mutedText => isDark ? AppColors.darkMuted : AppColors.neutral600;
  Color get borderColor => isDark ? AppColors.darkBorder : AppColors.neutral200;
  Color get surfaceAlt => isDark ? AppColors.darkSurfaceAlt : AppColors.neutral50;
  Color get accent => isDark ? AppColors.primary400 : AppColors.primary600;
}

class AppTheme {
  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final dark = brightness == Brightness.dark;
    var scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary600,
      brightness: brightness,
    );
    if (dark) {
      scheme = scheme.copyWith(
        surface: AppColors.darkSurface,
        onSurface: AppColors.darkInk,
        onSurfaceVariant: AppColors.darkMuted,
        outlineVariant: AppColors.darkBorder,
        primary: AppColors.primary400,
        onPrimary: const Color(0xFF05261B),
      );
    }
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      brightness: brightness,
    );
    final background = dark ? AppColors.darkBackground : AppColors.neutral50;
    final surface = dark ? AppColors.darkSurface : Colors.white;
    return base.copyWith(
      scaffoldBackgroundColor: background,
      textTheme: base.textTheme.apply(fontFamily: 'sans-serif'),
      appBarTheme: AppBarTheme(
        backgroundColor: background,
        surfaceTintColor: Colors.transparent,
        scrolledUnderElevation: 0,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: dark
            ? AppColors.primary600.withAlpha(90)
            : AppColors.primary100,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(
            color: dark ? AppColors.darkBorder : AppColors.neutral200,
          ),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary600,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}

/// Preferensi tema (sistem/terang/gelap) yang dipulihkan dari penyimpanan aman.
/// Kegagalan membaca/menulis tidak pernah menghalangi aplikasi: tema tetap berlaku untuk sesi ini.
class ThemeController extends ValueNotifier<ThemeMode> {
  ThemeController({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage(),
      super(ThemeMode.system);

  static const _key = 'pk_theme_mode';
  final FlutterSecureStorage _storage;

  Future<void> restore() async {
    try {
      final raw = await _storage.read(key: _key);
      value = switch (raw) {
        'light' => ThemeMode.light,
        'dark' => ThemeMode.dark,
        _ => ThemeMode.system,
      };
    } catch (_) {
      // Penyimpanan tidak tersedia (mis. pada tes): pertahankan nilai bawaan.
    }
  }

  Future<void> select(ThemeMode mode) async {
    value = mode;
    try {
      await _storage.write(key: _key, value: mode.name);
    } catch (_) {
      // Preferensi tetap berlaku untuk sesi berjalan.
    }
  }
}

class PesantenkuLogo extends StatelessWidget {
  const PesantenkuLogo({super.key, this.compact = false});
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          height: compact ? 42 : 56,
          width: compact ? 42 : 56,
          decoration: BoxDecoration(
            color: context.isDark ? AppColors.darkSurfaceAlt : AppColors.primary50,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: context.isDark ? AppColors.darkBorder : AppColors.primary100,
            ),
          ),
          child: Icon(
            Icons.mosque_outlined,
            color: context.isDark ? AppColors.primary400 : AppColors.primary700,
          ),
        ),
        if (!compact) ...[
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Pesantenku',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
              Text(
                'Aplikasi Pesantren',
                style: TextStyle(color: context.mutedText, fontSize: 12),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
