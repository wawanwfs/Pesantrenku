import React, { useEffect, useState } from 'react';
import { EyeOff } from 'lucide-react';
import { Head, router, useForm, icons } from '../lib/deps.jsx';
import { ThemeToggleButton } from '../components/ThemeToggle.jsx';
import '../../css/login.css';

export default function Login({ auth, app }) {
  const runtimeEnvironment = app?.environment || auth?.environment;
  const demoEnabled = app?.demo_enabled ?? auth?.demo_enabled ?? (runtimeEnvironment ? ['local', 'demo'].includes(runtimeEnvironment) : import.meta.env.DEV);
  const demoAccounts = Array.isArray(app?.demo_accounts) ? app.demo_accounts : [];
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [capsLock, setCapsLock] = useState(false);
  const { data, setData, post, processing, errors } = useForm({
    login: '',
    password: '',
    mfa_code: '',
  });
  const home = dashboardHref(auth?.active_role || auth?.roles?.[0]);
  const hasErrors = Object.keys(errors).length > 0;
  const selectedDemo = demoAccounts.some((account) => account.email === data.login) ? data.login : '';

  useEffect(() => {
    if (auth?.user && home) {
      router.visit(home, { replace: true });
      return undefined;
    }

    const reloadCachedLogin = (event) => {
      if (event.persisted) window.location.reload();
    };
    window.addEventListener('pageshow', reloadCachedLogin);

    return () => window.removeEventListener('pageshow', reloadCachedLogin);
  }, [auth?.user, home]);

  const submit = (event) => {
    event.preventDefault();
    post('/login', { replace: true });
  };

  const trackCapsLock = (event) => {
    if (typeof event.getModifierState === 'function') setCapsLock(event.getModifierState('CapsLock'));
  };

  if (auth?.user) {
    return (
      <main className="login-shell grid place-items-center p-6">
        <div className="flex max-w-sm items-center gap-3 border-l-2 border-(--lg-accent) bg-(--lg-card) px-5 py-4 shadow-[0_16px_40px_rgba(13,59,50,0.08)]" aria-live="polite" aria-busy="true">
          <icons.Loader2 className="h-5 w-5 shrink-0 animate-spin text-(--lg-brand) motion-reduce:animate-none" aria-hidden="true" />
          <div>
            <p className="text-sm font-semibold">Akses terverifikasi</p>
            <p className="login-muted mt-0.5 text-xs">Mengalihkan ke ruang kerja Anda...</p>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="login-shell relative overflow-x-hidden">
      <Head title="Masuk" />
      <div className="login-backdrop" aria-hidden="true">
        <div className="login-stripe"><span /><span /><span /></div>
      </div>

      <section className="login-frame">
        <aside className="login-hero">
          <div className="login-hero-grid" aria-hidden="true" />
          <div className="login-hero-orbit" aria-hidden="true" />

          <LoginBrand inverse />

          <div className="relative my-auto max-w-xl py-12">
            <div className="login-eyebrow">Gerbang ruang kerja</div>
            <h1 className="login-hero-title">Satu akses untuk ritme pesantren.</h1>
            <p className="login-hero-copy">
              Masuk ke dashboard sesuai peran Anda untuk melanjutkan pekerjaan akademik, administrasi, pengasuhan, dan keuangan.
            </p>

            <OperationalPreview />
          </div>

          <div className="login-hero-foot">
            <span className="inline-flex items-center gap-2"><span className="login-preview-dot" /> Sistem operasional aktif</span>
            <span>Akses berbasis peran</span>
          </div>
        </aside>

        <div className="login-form-col">
          <div className="login-stripe lg:hidden" aria-hidden="true"><span /><span /><span /></div>

          <div className="mx-auto flex w-full max-w-[500px] flex-1 flex-col justify-center px-5 py-8 sm:px-9 sm:py-12 lg:px-10 xl:px-12">
            <div className="mb-10 flex items-center justify-between gap-4 lg:hidden">
              <LoginBrand />
              <ThemeToggleButton />
            </div>

            <div className="flex items-center justify-between gap-4">
              <p className="font-mono text-[10px] font-medium uppercase text-(--lg-brand)">Portal akses</p>
              <span className="flex items-center gap-3">
                <span className="login-muted inline-flex items-center gap-1.5 text-[11px] font-medium">
                  <icons.Shield className="h-3.5 w-3.5 text-(--lg-brand)" aria-hidden="true" />
                  Terlindungi
                </span>
                <span className="hidden lg:block"><ThemeToggleButton /></span>
              </span>
            </div>

            <div className="login-heading">
              <h2>Selamat datang kembali.</h2>
              <p className="login-muted mt-3 max-w-md text-sm leading-6">
                {demoEnabled ? 'Pilih akun demo atau gunakan identitas yang sudah terdaftar.' : 'Gunakan identitas akun Anda untuk melanjutkan ke dashboard.'}
              </p>
            </div>

            {hasErrors && (
              <div className="login-alert" role="alert" aria-live="assertive">
                <icons.TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-sm font-semibold">Akses belum dapat diverifikasi</p>
                  <p className="mt-0.5 text-xs leading-5 opacity-90">Periksa kembali identitas dan password yang Anda masukkan.</p>
                </div>
              </div>
            )}

            {demoEnabled && (
              <div className="login-divider mt-7 border-y py-4">
                <div className="flex items-end justify-between gap-4">
                  <label htmlFor="demo-account" className="login-label text-xs">Akses cepat demo</label>
                  <span className="login-muted text-[10px]">{demoAccounts.length} peran tersedia</span>
                </div>
                <div className="relative mt-2">
                  <icons.Users className="login-icon" aria-hidden="true" />
                  <select
                    id="demo-account"
                    value={selectedDemo}
                    onChange={(event) => setData('login', event.target.value)}
                    disabled={processing}
                    className="login-select"
                  >
                    <option value="">Gunakan akun lain</option>
                    {demoAccounts.map((account) => <option key={account.email} value={account.email}>{account.role}</option>)}
                  </select>
                  <icons.ChevronRight className="login-icon login-icon-end" aria-hidden="true" />
                </div>
              </div>
            )}

            <form onSubmit={submit} className="mt-7" noValidate aria-busy={processing}>
              <div className="login-rise">
                <label htmlFor="login" className="login-label">Email, username, atau telepon</label>
                <div className="relative mt-2">
                  <icons.Users className="login-icon" aria-hidden="true" />
                  <input
                    id="login"
                    name="login"
                    autoComplete="username"
                    autoCapitalize="none"
                    spellCheck="false"
                    value={data.login}
                    onChange={(event) => setData('login', event.target.value)}
                    aria-invalid={Boolean(errors.login)}
                    aria-describedby={errors.login ? 'login-error' : undefined}
                    disabled={processing}
                    required
                    className="login-field"
                    placeholder="nama@pesantren.id"
                  />
                </div>
                {errors.login && <FieldError id="login-error">{errors.login}</FieldError>}
              </div>

              <div className="login-rise mt-5">
                <div className="flex items-center justify-between gap-3">
                  <label htmlFor="password" className="login-label">Password</label>
                  {demoEnabled && <span className="login-muted text-[11px]">Demo: <strong className="font-semibold text-(--lg-label)">password</strong></span>}
                </div>
                <div className="relative mt-2">
                  <icons.Shield className="login-icon" aria-hidden="true" />
                  <input
                    id="password"
                    name="password"
                    type={passwordVisible ? 'text' : 'password'}
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(event) => setData('password', event.target.value)}
                    onKeyUp={trackCapsLock}
                    onKeyDown={trackCapsLock}
                    onBlur={() => setCapsLock(false)}
                    aria-invalid={Boolean(errors.password)}
                    aria-describedby={errors.password ? 'password-error' : capsLock ? 'caps-lock-hint' : undefined}
                    disabled={processing}
                    required
                    className="login-field login-field-eye"
                    placeholder="Masukkan password"
                  />
                  <button
                    type="button"
                    onClick={() => setPasswordVisible((visible) => !visible)}
                    disabled={processing}
                    className="login-eye disabled:cursor-wait disabled:opacity-50"
                    aria-label={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                    aria-pressed={passwordVisible}
                    title={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                  >
                    {passwordVisible ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <icons.Eye className="h-4 w-4" aria-hidden="true" />}
                  </button>
                </div>
                {capsLock && !errors.password && (
                  <p id="caps-lock-hint" className="login-hint" role="status">
                    <icons.TriangleAlert className="h-3.5 w-3.5" aria-hidden="true" /> Caps Lock sedang aktif
                  </p>
                )}
                {errors.password && <FieldError id="password-error">{errors.password}</FieldError>}
              </div>

              <div className="login-rise mt-5">
                <label htmlFor="mfa_code" className="login-label">Kode MFA atau recovery</label>
                <div className="relative mt-2">
                  <icons.ShieldCheck className="login-icon" aria-hidden="true" />
                  <input
                    id="mfa_code"
                    name="mfa_code"
                    autoComplete="one-time-code"
                    value={data.mfa_code}
                    onChange={(event) => setData('mfa_code', event.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ''))}
                    aria-invalid={Boolean(errors.mfa_code)}
                    aria-describedby={errors.mfa_code ? 'mfa-code-error' : 'mfa-code-help'}
                    disabled={processing}
                    className="login-field font-mono tracking-[0.12em]"
                    placeholder="123456 atau ABCDE-FGHIJ"
                  />
                </div>
                {errors.mfa_code ? <FieldError id="mfa-code-error">{errors.mfa_code}</FieldError> : <p id="mfa-code-help" className="login-muted mt-1.5 text-[11px]">Wajib untuk akun privileged; akun lain dapat mengosongkannya.</p>}
              </div>

              <div className="login-rise">
                <button type="submit" disabled={processing} className="login-submit">
                  {processing ? <icons.Loader2 className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" /> : <span>Masuk ke dashboard</span>}
                  {processing ? <span>Memverifikasi akses...</span> : <icons.ChevronRight className="h-4 w-4" aria-hidden="true" />}
                </button>
              </div>
            </form>

            <div className="login-divider login-muted mt-8 flex items-start gap-2.5 border-t pt-5 text-xs leading-5">
              <icons.CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-(--lg-brand)" aria-hidden="true" />
              <p>Setelah masuk, akses diarahkan otomatis sesuai peran dan izin akun Anda.</p>
            </div>
          </div>

          <p className="login-muted px-5 pb-6 text-center text-[10px] sm:px-9 lg:text-left">
            Pesantrenku - Ruang tumbuh pesantren
          </p>
        </div>
      </section>
    </main>
  );
}

function LoginBrand({ inverse = false }) {
  return (
    <div className={`relative flex items-center gap-3 ${inverse ? 'text-white' : ''}`}>
      <div className={`login-brand-mark ${inverse ? 'is-inverse' : ''}`} aria-hidden="true">
        <svg viewBox="0 0 48 48" className="h-8 w-8" fill="none">
          <path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2.2" />
          <path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" />
        </svg>
      </div>
      <div>
        <div className="text-base font-semibold leading-none">Pesantrenku</div>
        <div className={`mt-1.5 text-[9px] font-semibold uppercase ${inverse ? 'text-[#b4cdbd]' : 'login-muted'}`}>Ruang tumbuh pesantren</div>
      </div>
    </div>
  );
}

function OperationalPreview() {
  const modules = [
    ['Akademik', 'Jadwal dan perkembangan', icons.BookOpen],
    ['Keuangan', 'Tagihan dan laporan', icons.Wallet],
    ['Pengasuhan', 'Asrama dan kesehatan', icons.Shield],
  ];

  return (
    <div className="login-preview">
      <div className="flex items-center justify-between border-b border-white/10 pb-3">
        <span className="text-xs font-semibold text-[#eff6dc]">Ringkasan ruang kerja</span>
        <span className="font-mono text-[9px] uppercase text-[#dff47c]">Siap digunakan</span>
      </div>
      <div>
        {modules.map(([title, detail, Icon]) => (
          <div key={title} className="login-preview-row">
            <Icon className="h-4 w-4 shrink-0 text-[#b7d89e]" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="text-xs font-semibold text-white">{title}</p>
              <p className="mt-0.5 truncate text-[10px] text-[#a9c0b6]">{detail}</p>
            </div>
            <span className="login-preview-dot" aria-hidden="true" />
          </div>
        ))}
      </div>
    </div>
  );
}

function FieldError({ id, children }) {
  return (
    <p id={id} className="login-error" role="alert">
      <icons.TriangleAlert className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>{children}</span>
    </p>
  );
}

function dashboardHref(role = 'super_admin') {
  const panel = {
    owner_pesantren: 'owner',
    admin_keuangan: 'keuangan',
    admin_akademik: 'akademik',
    admin_tu: 'tu',
  }[role] || String(role).replaceAll('_', '-');

  return `/dashboard/${panel}`;
}
