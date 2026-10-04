import 'package:flutter/material.dart';
import 'core/network/api_client.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/app_widgets.dart';
import 'features/dashboard/mobile_shell.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(PesantenkuMobileApp(client: ApiClient()));
}

class PesantenkuMobileApp extends StatefulWidget {
  const PesantenkuMobileApp({super.key, required this.client});
  final ApiClient client;

  @override
  State<PesantenkuMobileApp> createState() => _PesantenkuMobileAppState();
}

class _PesantenkuMobileAppState extends State<PesantenkuMobileApp> {
  final navigatorKey = GlobalKey<NavigatorState>();
  final themeController = ThemeController();
  SessionData? session;
  bool loading = true;
  String? bootstrapError;

  @override
  void initState() {
    super.initState();
    widget.client.setUnauthorizedHandler(_handleUnauthorized);
    themeController.restore();
    _restore();
  }

  @override
  void didUpdateWidget(covariant PesantenkuMobileApp oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.client != widget.client) {
      oldWidget.client.setUnauthorizedHandler(null);
      widget.client.setUnauthorizedHandler(_handleUnauthorized);
      session = null;
      loading = true;
      bootstrapError = null;
      _restore();
    }
  }

  @override
  void dispose() {
    widget.client.setUnauthorizedHandler(null);
    themeController.dispose();
    super.dispose();
  }

  void _handleUnauthorized() {
    if (!mounted) return;
    navigatorKey.currentState?.popUntil((route) => route.isFirst);
    setState(() {
      session = null;
      loading = false;
      bootstrapError = 'Sesi Anda telah berakhir. Silakan masuk kembali.';
    });
  }

  Future<void> _restore() async {
    try {
      final restored = await widget.client.restoreSession();
      if (mounted) setState(() => session = restored);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => bootstrapError = error.statusCode == 401
            ? 'Sesi Anda telah berakhir. Silakan masuk kembali.'
            : error.message);
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: themeController,
      builder: (context, themeMode, _) => MaterialApp(
        navigatorKey: navigatorKey,
        title: 'Pesantenku',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: themeMode,
        home: loading
            ? const _SplashScreen()
            : session == null
                ? LoginScreen(
                    client: widget.client,
                    initialError: bootstrapError,
                    onAuthenticated: (value) => setState(() {
                      session = value;
                      bootstrapError = null;
                    }),
                  )
                : MobileShell(
                    client: widget.client,
                    session: session!,
                    onSessionChanged: (value) => setState(() => session = value),
                    onLoggedOut: () => setState(() => session = null),
                    themeMode: themeMode,
                    onThemeModeChanged: themeController.select,
                  ),
      ),
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.client,
    required this.onAuthenticated,
    this.initialError,
  });

  final ApiClient client;
  final ValueChanged<SessionData> onAuthenticated;
  final String? initialError;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final formKey = GlobalKey<FormState>();
  final loginController = TextEditingController();
  final passwordController = TextEditingController();
  final mfaController = TextEditingController();
  bool mfaRequired = false;
  bool loading = false;
  bool obscure = true;
  String? error;

  @override
  void initState() {
    super.initState();
    error = widget.initialError;
  }

  @override
  void dispose() {
    loginController.dispose();
    passwordController.dispose();
    mfaController.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final session = await widget.client.login(
        loginController.text.trim(),
        passwordController.text,
        mfaCode: mfaRequired ? mfaController.text.trim() : null,
      );
      if (mounted) widget.onAuthenticated(session);
    } on ApiException catch (exception) {
      if (mounted) {
        setState(() {
          mfaRequired = mfaRequired || exception.mfaRequired;
          error = exception.message;
        });
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Form(
                key: formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const FadeSlideIn(child: PesantenkuLogo()),
                    const SizedBox(height: 36),
                    FadeSlideIn(
                      delay: const Duration(milliseconds: 80),
                      child: Text('Masuk Pesantenku', style: Theme.of(context).textTheme.headlineMedium),
                    ),
                    const SizedBox(height: 8),
                    const FadeSlideIn(
                      delay: Duration(milliseconds: 140),
                      child: Text('Gunakan akun yang terdaftar pada pesantren Anda.'),
                    ),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: loginController,
                      enabled: !loading,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username, AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'Email atau username',
                        prefixIcon: Icon(Icons.person_outline),
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) => value == null || value.trim().isEmpty
                          ? 'Email atau username wajib diisi.'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: passwordController,
                      enabled: !loading,
                      obscureText: obscure,
                      autofillHints: const [AutofillHints.password],
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        border: const OutlineInputBorder(),
                        suffixIcon: IconButton(
                          tooltip: obscure ? 'Tampilkan password' : 'Sembunyikan password',
                          onPressed: () => setState(() => obscure = !obscure),
                          icon: Icon(obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                        ),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Password wajib diisi.'
                          : null,
                      onFieldSubmitted: (_) => submit(),
                    ),
                    if (mfaRequired) ...[
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: mfaController,
                        enabled: !loading,
                        autofillHints: const [AutofillHints.oneTimeCode],
                        textCapitalization: TextCapitalization.characters,
                        decoration: const InputDecoration(
                          labelText: 'Kode MFA atau recovery',
                          prefixIcon: Icon(Icons.verified_user_outlined),
                          border: OutlineInputBorder(),
                          helperText: 'Masukkan TOTP 6 digit atau recovery code.',
                        ),
                        validator: (value) => mfaRequired && (value == null || value.trim().isEmpty)
                            ? 'Kode MFA atau recovery wajib diisi.'
                            : null,
                        onFieldSubmitted: (_) => submit(),
                      ),
                    ],
                    if (error != null) ...[
                      const SizedBox(height: 16),
                      Material(
                        color: AppColors.error.withAlpha(18),
                        borderRadius: BorderRadius.circular(12),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Semantics(
                            liveRegion: true,
                            child: Text(error!, style: const TextStyle(color: AppColors.error)),
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: loading ? null : submit,
                      child: loading
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text('Masuk'),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Alamat API harus diatur saat build dengan --dart-define=API_BASE_URL=https://domain/api/v1.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 12, color: context.mutedText),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            PesantenkuLogo(),
            SizedBox(height: 24),
            CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
