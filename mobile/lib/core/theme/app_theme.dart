import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppColors {
  static const primary50 = Color(0xFFE6F4EE);
  static const primary100 = Color(0xFFC8E9DA);
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
}

class AppTheme {
  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      colorSchemeSeed: AppColors.primary600,
    );
    return base.copyWith(
      scaffoldBackgroundColor: AppColors.neutral50,
      textTheme: GoogleFonts.poppinsTextTheme(base.textTheme),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: AppColors.neutral200),
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
            color: AppColors.primary50,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.primary100),
          ),
          child: const Icon(Icons.mosque_outlined, color: AppColors.primary700),
        ),
        if (!compact) ...[
          const SizedBox(width: 12),
          const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Pesantenku',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
              Text(
                'Aplikasi Pesantren',
                style: TextStyle(color: AppColors.neutral600, fontSize: 12),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
