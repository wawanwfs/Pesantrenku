import React, { useEffect, useState } from 'react';
import { router, useForm, icons } from '../lib/deps.jsx';

const demoAccounts = [
  ['superadmin@pesantenku.test', 'Super Admin'],
  ['owner@pesantenku.test', 'Owner Pesantren'],
  ['keuangan@pesantenku.test', 'Keuangan'],
  ['akademik@pesantenku.test', 'Akademik'],
  ['tu@pesantenku.test', 'Tata Usaha'],
  ['santri@pesantenku.test', 'Santri'],
  ['wali@pesantenku.test', 'Wali Santri'],
  ['guru@pesantenku.test', 'Guru'],
  ['musyrif@pesantenku.test', 'Musyrif'],
  ['klinik@pesantenku.test', 'Klinik'],
];

export default function Login({ auth, app }) {
  const runtimeEnvironment = app?.environment || auth?.environment;
  const demoEnabled = app?.demo_enabled ?? auth?.demo_enabled ?? (runtimeEnvironment ? ['local', 'demo'].includes(runtimeEnvironment) : import.meta.env.DEV);
  const [passwordVisible, setPasswordVisible] = useState(false);
  const { data, setData, post, processing, errors } = useForm({
    login: demoEnabled ? 'superadmin@pesantenku.test' : '',
    password: demoEnabled ? 'password' : '',
  });
  const home = dashboardHref(auth?.roles?.[0]);

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

  if (auth?.user) {
    return (
      <main className="grid min-h-screen place-items-center bg-[#f4f8f4] p-6 text-neutral-900">
        <div className="flex items-center gap-3 rounded-2xl border border-primary-100 bg-white px-6 py-5 text-sm font-medium shadow-xl shadow-primary-900/5" aria-live="polite">
          <icons.Loader2 className="h-5 w-5 animate-spin text-primary-600" aria-hidden="true" />
          Mengalihkan ke dashboard...
        </div>
      </main>
    );
  }

  return (
    <main className="relative isolate min-h-screen overflow-hidden bg-[#f4f8f4] px-4 py-5 text-neutral-900 sm:px-6 sm:py-8 lg:grid lg:place-items-center lg:p-10">
      <Backdrop />

      <section className="relative mx-auto grid min-w-0 w-full max-w-6xl overflow-hidden rounded-[2rem] border border-white/80 bg-white/90 shadow-[0_32px_90px_-28px_rgba(6,64,42,0.34)] backdrop-blur-xl lg:grid-cols-[1.06fr_0.94fr]">
        <aside className="relative min-w-0 overflow-hidden bg-[#06402A] p-6 text-white sm:p-9 lg:p-11">
          <LoginBrand />
          <HeroArtwork />
          <div className="relative mt-8 max-w-md lg:mt-12">
            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium tracking-wide text-primary-50">
              <span className="h-1.5 w-1.5 rounded-full bg-[#9FE3BA] shadow-[0_0_0_4px_rgba(159,227,186,0.16)]" />
              TERHUBUNG & TERLINDUNGI
            </span>
            <h1 className="mt-5 max-w-sm text-3xl font-semibold leading-[1.12] tracking-tight sm:text-4xl">
              Satu ruang kerja untuk seluruh kehidupan pesantren.
            </h1>
            <p className="mt-4 max-w-sm text-sm leading-6 text-primary-50/85">
              Kelola pembelajaran, asrama, keuangan, dan komunikasi keluarga dalam alur yang rapi dan manusiawi.
            </p>
          </div>

          <div className="relative mt-8 grid max-w-md grid-cols-3 gap-2.5 text-center sm:gap-3 lg:mt-10">
            <TrustPoint label="Akademik" icon={icons.BookOpen} />
            <TrustPoint label="Keuangan" icon={icons.Wallet} />
            <TrustPoint label="Asrama" icon={icons.Shield} />
          </div>
        </aside>

        <div className="flex min-h-full min-w-0 flex-col p-6 sm:p-9 lg:p-11">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-sm font-semibold text-primary-700">Portal Pesantenku</p>
              <h2 className="mt-2 text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">Selamat datang kembali</h2>
              <p className="mt-2 max-w-sm text-sm leading-6 text-neutral-600">
                {demoEnabled ? 'Pilih peran demo atau gunakan akun yang sudah terdaftar.' : 'Masuk untuk melanjutkan aktivitas Anda di Pesantenku.'}
              </p>
            </div>
            <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-700" aria-hidden="true">
              <icons.Shield className="h-5 w-5" />
            </div>
          </div>

          {demoEnabled && (
            <div className="mt-7 min-w-0">
              <div className="flex items-center justify-between gap-3">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-600">Akses cepat demo</p>
                <span className="text-xs text-neutral-400">10 peran tersedia</span>
              </div>
              <div className="mt-3 flex max-w-full gap-2 overflow-x-auto pb-2 [scrollbar-width:thin]" aria-label="Pilih akun demo">
                {demoAccounts.map(([email, role]) => {
                  const selected = data.login === email;
                  return (
                    <button
                      key={email}
                      type="button"
                      onClick={() => setData('login', email)}
                      aria-pressed={selected}
                      className={`shrink-0 rounded-xl border px-3 py-2 text-left text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 ${selected ? 'border-primary-600 bg-primary-50 text-primary-700 shadow-sm' : 'border-neutral-200 bg-white text-neutral-600 hover:border-primary-200 hover:bg-primary-50/50'}`}
                    >
                      {role}
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          <form onSubmit={submit} className="mt-7" noValidate>
            <div>
              <label htmlFor="login" className="block text-sm font-semibold text-neutral-800">Email, username, atau telepon</label>
              <div className="relative mt-2">
                <icons.Users className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true" />
                <input
                  id="login"
                  name="login"
                  autoComplete="username"
                  value={data.login}
                  onChange={(event) => setData('login', event.target.value)}
                  aria-invalid={Boolean(errors.login)}
                  aria-describedby={errors.login ? 'login-error' : undefined}
                  required
                  className="w-full rounded-xl border border-neutral-200 bg-white py-3 pl-11 pr-4 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 hover:border-primary-200 focus:border-primary-600 focus:ring-4 focus:ring-primary-100"
                  placeholder="nama@pesantren.id"
                />
              </div>
              {errors.login && <p id="login-error" className="mt-2 text-sm font-medium text-red-600" role="alert">{errors.login}</p>}
            </div>

            <div className="mt-5">
              <div className="flex items-center justify-between gap-3">
                <label htmlFor="password" className="block text-sm font-semibold text-neutral-800">Password</label>
                {demoEnabled && <span className="text-xs text-neutral-500">Demo: <strong className="font-semibold text-neutral-700">password</strong></span>}
              </div>
              <div className="relative mt-2">
                <icons.Shield className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true" />
                <input
                  id="password"
                  name="password"
                  type={passwordVisible ? 'text' : 'password'}
                  autoComplete="current-password"
                  value={data.password}
                  onChange={(event) => setData('password', event.target.value)}
                  aria-invalid={Boolean(errors.password)}
                  aria-describedby={errors.password ? 'password-error' : undefined}
                  required
                  className="w-full rounded-xl border border-neutral-200 bg-white py-3 pl-11 pr-20 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 hover:border-primary-200 focus:border-primary-600 focus:ring-4 focus:ring-primary-100"
                  placeholder="Masukkan password"
                />
                <button
                  type="button"
                  onClick={() => setPasswordVisible((visible) => !visible)}
                  className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                  aria-label={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                >
                  {passwordVisible ? 'Sembunyikan' : 'Tampilkan'}
                </button>
              </div>
              {errors.password && <p id="password-error" className="mt-2 text-sm font-medium text-red-600" role="alert">{errors.password}</p>}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="mt-7 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/20 transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-xl hover:shadow-primary-600/25 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 disabled:translate-y-0 disabled:cursor-wait disabled:opacity-65"
            >
              {processing ? <icons.Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <icons.Shield className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Memverifikasi akses...' : 'Masuk'}
            </button>
          </form>

          <div className="mt-auto flex items-start gap-2.5 border-t border-neutral-100 pt-6 text-xs leading-5 text-neutral-500">
            <icons.CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
            <p>Dengan melanjutkan, Anda menggunakan ruang kerja yang aman sesuai peran dan izin akun Anda.</p>
          </div>
        </div>
      </section>
    </main>
  );
}

function Backdrop() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <div className="absolute -left-40 -top-44 h-[34rem] w-[34rem] rounded-full bg-[radial-gradient(circle,rgba(23,191,92,0.16),transparent_67%)] blur-2xl" />
      <div className="absolute -bottom-64 -right-40 h-[42rem] w-[42rem] rounded-full bg-[radial-gradient(circle,rgba(14,98,63,0.14),transparent_65%)] blur-2xl" />
      <div className="absolute inset-0 opacity-[0.16] [background-image:linear-gradient(rgba(6,64,42,0.11)_1px,transparent_1px),linear-gradient(90deg,rgba(6,64,42,0.11)_1px,transparent_1px)] [background-size:42px_42px]" />
    </div>
  );
}

function LoginBrand() {
  return (
    <div className="relative flex items-center gap-3">
      <div className="grid h-12 w-12 place-items-center rounded-2xl border border-white/30 bg-white/10 shadow-lg shadow-black/10 backdrop-blur" aria-hidden="true">
        <svg viewBox="0 0 48 48" className="h-8 w-8" fill="none">
          <path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2.5" />
          <path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
        </svg>
      </div>
      <div>
        <div className="text-base font-semibold tracking-tight">Pesantenku</div>
        <div className="text-xs text-primary-50/70">Ruang tumbuh pesantren</div>
      </div>
    </div>
  );
}

function HeroArtwork() {
  return (
    <div className="pointer-events-none absolute right-[-4.5rem] top-[5.5rem] hidden h-64 w-72 sm:block lg:top-[7rem]" aria-hidden="true">
      <div className="absolute left-14 top-4 h-40 w-44 rotate-[14deg] rounded-[2.25rem] border border-white/20 bg-gradient-to-br from-white/25 via-white/10 to-transparent shadow-2xl shadow-black/20 backdrop-blur-sm [transform:rotateX(55deg)_rotateZ(-24deg)]" />
      <div className="absolute left-7 top-9 grid h-40 w-44 place-items-center rounded-[2.25rem] border border-white/20 bg-[linear-gradient(145deg,rgba(159,227,186,0.82),rgba(23,191,92,0.26)_48%,rgba(255,255,255,0.12))] shadow-[0_28px_44px_-16px_rgba(0,0,0,0.58)] [transform:rotateX(52deg)_rotateZ(-24deg)] animate-pulse">
        <div className="grid h-20 w-20 place-items-center rounded-3xl border border-white/40 bg-[#06402A]/55 shadow-xl backdrop-blur">
          <svg viewBox="0 0 48 48" className="h-12 w-12 text-white" fill="none">
            <path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2.3" />
            <path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2.3" strokeLinecap="round" />
          </svg>
        </div>
      </div>
      <div className="absolute left-2 top-40 h-12 w-12 rounded-2xl border border-white/20 bg-white/10 shadow-lg backdrop-blur-sm [transform:rotateX(52deg)_rotateZ(-24deg)] animate-pulse" />
      <div className="absolute left-44 top-40 h-9 w-9 rounded-xl border border-white/25 bg-[#9FE3BA]/55 shadow-lg [transform:rotateX(52deg)_rotateZ(-24deg)] animate-pulse" />
    </div>
  );
}

function TrustPoint({ label, icon: Icon }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.07] px-2 py-3 backdrop-blur-sm">
      <Icon className="mx-auto h-4 w-4 text-[#9FE3BA]" aria-hidden="true" />
      <p className="mt-1.5 text-[11px] font-medium text-white/85">{label}</p>
    </div>
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
