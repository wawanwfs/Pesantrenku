import React, { useEffect, useState } from 'react';
import { EyeOff } from 'lucide-react';
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

const fieldBase = 'min-h-12 w-full rounded-lg border bg-[#fffdf8] py-3 pl-11 text-sm text-[#102c27] outline-none transition placeholder:text-[#63736d] hover:border-[#8bb4a2] focus:border-[#0d3b32] focus:ring-4 focus:ring-[#d6efdc] disabled:cursor-wait disabled:bg-[#f4f3ed] disabled:text-[#5c6d66] [&:-webkit-autofill]:[-webkit-text-fill-color:#102c27] [&:-webkit-autofill]:[box-shadow:0_0_0_1000px_#fffdf8_inset]';

export default function Login({ auth, app }) {
  const runtimeEnvironment = app?.environment || auth?.environment;
  const demoEnabled = app?.demo_enabled ?? auth?.demo_enabled ?? (runtimeEnvironment ? ['local', 'demo'].includes(runtimeEnvironment) : import.meta.env.DEV);
  const [passwordVisible, setPasswordVisible] = useState(false);
  const { data, setData, post, processing, errors } = useForm({
    login: demoEnabled ? 'superadmin@pesantenku.test' : '',
    password: demoEnabled ? 'password' : '',
  });
  const home = dashboardHref(auth?.roles?.[0]);
  const hasErrors = Object.keys(errors).length > 0;
  const selectedDemo = demoAccounts.some(([email]) => email === data.login) ? data.login : '';

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
      <main className="grid min-h-dvh place-items-center bg-[#f7f4ec] p-6 text-[#102c27]">
        <div className="flex max-w-sm items-center gap-3 border-l-2 border-[#0d3b32] bg-[#fffdf8] px-5 py-4 shadow-[0_16px_40px_rgba(13,59,50,0.08)]" aria-live="polite" aria-busy="true">
          <icons.Loader2 className="h-5 w-5 shrink-0 animate-spin text-[#127a4f] motion-reduce:animate-none" aria-hidden="true" />
          <div>
            <p className="text-sm font-semibold">Akses terverifikasi</p>
            <p className="mt-0.5 text-xs text-[#62736d]">Mengalihkan ke ruang kerja Anda...</p>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="relative min-h-dvh overflow-x-hidden bg-[#f7f4ec] text-[#102c27] selection:bg-[#d6efdc] selection:text-[#0d3b32] lg:p-6">
      <Backdrop />

      <section className="relative mx-auto grid min-h-dvh w-full max-w-[1440px] bg-[#fffdf8] lg:min-h-[calc(100dvh-3rem)] lg:grid-cols-[minmax(0,1.06fr)_minmax(440px,0.74fr)] lg:border lg:border-[#d8ded7] lg:shadow-[0_28px_70px_rgba(24,80,61,0.13)]">
        <aside className="relative hidden min-w-0 overflow-hidden bg-[#0d3b32] px-10 py-9 text-white lg:flex lg:flex-col xl:px-14 xl:py-12">
          <div className="absolute inset-0 opacity-25 [background-image:linear-gradient(rgba(223,244,124,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(223,244,124,0.09)_1px,transparent_1px)] [background-size:44px_44px] [mask-image:linear-gradient(to_bottom,black,transparent_78%)]" aria-hidden="true" />
          <div className="absolute -bottom-24 left-1/2 h-64 w-[44rem] -translate-x-1/2 border border-[#dff47c]/20 [transform:translateX(-50%)_rotateX(70deg)]" aria-hidden="true" />

          <LoginBrand inverse />

          <div className="relative my-auto max-w-xl py-12">
            <div className="flex items-center gap-3 font-mono text-[10px] uppercase text-[#c8e5d0]">
              <span className="h-px w-8 bg-[#9fcba1]" />
              Gerbang ruang kerja
            </div>
            <h1 className="mt-6 max-w-lg font-serif text-[clamp(2.5rem,4.5vw,4.6rem)] font-semibold leading-[0.98] text-[#f7f4ec]">
              Satu akses untuk ritme pesantren.
            </h1>
            <p className="mt-6 max-w-md text-sm leading-7 text-[#c7d8d0]">
              Masuk ke dashboard sesuai peran Anda untuk melanjutkan pekerjaan akademik, administrasi, pengasuhan, dan keuangan.
            </p>

            <OperationalPreview />
          </div>

          <div className="relative flex items-center justify-between gap-5 border-t border-white/15 pt-5 text-[11px] text-[#b4cdbd]">
            <span className="inline-flex items-center gap-2"><span className="h-1.5 w-1.5 rounded-full bg-[#dff47c]" /> Sistem operasional aktif</span>
            <span>Akses berbasis peran</span>
          </div>
        </aside>

        <div className="relative flex min-h-dvh min-w-0 flex-col bg-[#fffdf8] lg:min-h-0">
          <div className="h-1 w-full bg-[linear-gradient(90deg,#0d3b32_0_34%,#5b9a84_34%_67%,#dff47c_67%)] lg:hidden" aria-hidden="true" />

          <div className="mx-auto flex w-full max-w-[500px] flex-1 flex-col justify-center px-5 py-8 sm:px-9 sm:py-12 lg:px-10 xl:px-12">
            <div className="mb-10 lg:hidden">
              <LoginBrand />
            </div>

            <div className="flex items-center justify-between gap-4">
              <p className="font-mono text-[10px] font-medium uppercase text-[#4b7568]">Portal akses</p>
              <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-[#62736d]">
                <icons.Shield className="h-3.5 w-3.5 text-[#127a4f]" aria-hidden="true" />
                Terlindungi
              </span>
            </div>

            <div className="mt-5 border-l-2 border-[#5b9a84] pl-5">
              <h2 className="font-serif text-4xl font-semibold leading-tight text-[#102c27] sm:text-[2.7rem]">Selamat datang kembali.</h2>
              <p className="mt-3 max-w-md text-sm leading-6 text-[#62736d]">
                {demoEnabled ? 'Pilih akun demo atau gunakan identitas yang sudah terdaftar.' : 'Gunakan identitas akun Anda untuk melanjutkan ke dashboard.'}
              </p>
            </div>

            {hasErrors && (
              <div className="mt-7 flex gap-3 border border-red-200 bg-red-50 px-4 py-3 text-red-900" role="alert" aria-live="assertive">
                <icons.TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 text-red-600" aria-hidden="true" />
                <div>
                  <p className="text-sm font-semibold">Akses belum dapat diverifikasi</p>
                  <p className="mt-0.5 text-xs leading-5 text-red-700">Periksa kembali identitas dan password yang Anda masukkan.</p>
                </div>
              </div>
            )}

            {demoEnabled && (
              <div className="mt-7 border-y border-[#d8ded7] py-4">
                <div className="flex items-end justify-between gap-4">
                  <label htmlFor="demo-account" className="text-xs font-semibold text-[#36594f]">Akses cepat demo</label>
                  <span className="text-[10px] text-[#5c6d66]">10 peran tersedia</span>
                </div>
                <div className="relative mt-2">
                  <icons.Users className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#5b7b70]" aria-hidden="true" />
                  <select
                    id="demo-account"
                    value={selectedDemo}
                    onChange={(event) => setData('login', event.target.value)}
                    disabled={processing}
                    className="min-h-11 w-full appearance-none rounded-lg border border-[#cfd8d2] bg-[#f7f4ec] py-2.5 pl-10 pr-10 text-sm font-medium text-[#21483d] outline-none transition hover:border-[#8bb4a2] focus:border-[#0d3b32] focus:ring-4 focus:ring-[#d6efdc] disabled:cursor-wait disabled:opacity-70"
                  >
                    <option value="">Gunakan akun lain</option>
                    {demoAccounts.map(([email, role]) => <option key={email} value={email}>{role}</option>)}
                  </select>
                  <icons.ChevronRight className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 rotate-90 text-[#5b7b70]" aria-hidden="true" />
                </div>
              </div>
            )}

            <form onSubmit={submit} className="mt-7" noValidate aria-busy={processing}>
              <div>
                <label htmlFor="login" className="block text-sm font-semibold text-[#294d43]">Email, username, atau telepon</label>
                <div className="relative mt-2">
                  <icons.Users className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#6c8179]" aria-hidden="true" />
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
                    className={`${fieldBase} pr-4 ${errors.login ? 'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-red-100' : 'border-[#cfd8d2]'}`}
                    placeholder="nama@pesantren.id"
                  />
                </div>
                {errors.login && <FieldError id="login-error">{errors.login}</FieldError>}
              </div>

              <div className="mt-5">
                <div className="flex items-center justify-between gap-3">
                  <label htmlFor="password" className="block text-sm font-semibold text-[#294d43]">Password</label>
                  {demoEnabled && <span className="text-[11px] text-[#5c6d66]">Demo: <strong className="font-semibold text-[#36594f]">password</strong></span>}
                </div>
                <div className="relative mt-2">
                  <icons.Shield className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#6c8179]" aria-hidden="true" />
                  <input
                    id="password"
                    name="password"
                    type={passwordVisible ? 'text' : 'password'}
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(event) => setData('password', event.target.value)}
                    aria-invalid={Boolean(errors.password)}
                    aria-describedby={errors.password ? 'password-error' : undefined}
                    disabled={processing}
                    required
                    className={`${fieldBase} pr-14 ${errors.password ? 'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-red-100' : 'border-[#cfd8d2]'}`}
                    placeholder="Masukkan password"
                  />
                  <button
                    type="button"
                    onClick={() => setPasswordVisible((visible) => !visible)}
                    disabled={processing}
                    className="absolute right-1.5 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-md text-[#4b7568] transition hover:bg-[#e5eee3] hover:text-[#0d3b32] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0d3b32] focus-visible:ring-offset-1 disabled:cursor-wait disabled:opacity-50"
                    aria-label={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                    title={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                  >
                    {passwordVisible ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <icons.Eye className="h-4 w-4" aria-hidden="true" />}
                  </button>
                </div>
                {errors.password && <FieldError id="password-error">{errors.password}</FieldError>}
              </div>

              <button
                type="submit"
                disabled={processing}
                className="mt-7 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#0d3b32] px-4 py-3 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(13,59,50,0.18)] transition hover:bg-[#145345] hover:shadow-[0_15px_28px_rgba(13,59,50,0.22)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#b6df73] focus-visible:ring-offset-2 disabled:cursor-wait disabled:bg-[#48675e] disabled:shadow-none"
              >
                {processing ? <icons.Loader2 className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" /> : <span>Masuk ke dashboard</span>}
                {processing ? <span>Memverifikasi akses...</span> : <icons.ChevronRight className="h-4 w-4" aria-hidden="true" />}
              </button>
            </form>

            <div className="mt-8 flex items-start gap-2.5 border-t border-[#e1e5df] pt-5 text-xs leading-5 text-[#62736d]">
              <icons.CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#127a4f]" aria-hidden="true" />
              <p>Setelah masuk, akses diarahkan otomatis sesuai peran dan izin akun Anda.</p>
            </div>
          </div>

          <p className="px-5 pb-6 text-center text-[10px] text-[#5f7069] sm:px-9 lg:text-left">
            Pesantrenku - Ruang tumbuh pesantren
          </p>
        </div>
      </section>
    </main>
  );
}

function Backdrop() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <div className="absolute inset-0 opacity-35 [background-image:linear-gradient(rgba(13,59,50,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(13,59,50,0.05)_1px,transparent_1px)] [background-size:32px_32px]" />
      <div className="absolute left-0 top-0 h-1 w-1/3 bg-[#0d3b32]" />
      <div className="absolute left-1/3 top-0 h-1 w-1/3 bg-[#5b9a84]" />
      <div className="absolute right-0 top-0 h-1 w-1/3 bg-[#dff47c]" />
    </div>
  );
}

function LoginBrand({ inverse = false }) {
  return (
    <div className={`relative flex items-center gap-3 ${inverse ? 'text-white' : 'text-[#102c27]'}`}>
      <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-lg border ${inverse ? 'border-white/20 bg-white/10' : 'border-[#0d3b32]/15 bg-[#e5eee3]'}`} aria-hidden="true">
        <svg viewBox="0 0 48 48" className="h-8 w-8" fill="none">
          <path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2.2" />
          <path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" />
        </svg>
      </div>
      <div>
        <div className="text-base font-semibold leading-none">Pesantrenku</div>
        <div className={`mt-1.5 text-[9px] font-semibold uppercase ${inverse ? 'text-[#b4cdbd]' : 'text-[#62736d]'}`}>Ruang tumbuh pesantren</div>
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
    <div className="mt-10 max-w-lg rounded-lg border border-white/15 bg-white/[0.06] p-4 shadow-[18px_22px_0_rgba(0,0,0,0.08)] backdrop-blur-sm [transform:perspective(900px)_rotateY(-3deg)_rotateX(1deg)]">
      <div className="flex items-center justify-between border-b border-white/10 pb-3">
        <span className="text-xs font-semibold text-[#eff6dc]">Ringkasan ruang kerja</span>
        <span className="font-mono text-[9px] uppercase text-[#dff47c]">Siap digunakan</span>
      </div>
      <div className="divide-y divide-white/10">
        {modules.map(([title, detail, Icon]) => (
          <div key={title} className="flex items-center gap-3 py-3">
            <Icon className="h-4 w-4 shrink-0 text-[#b7d89e]" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="text-xs font-semibold text-white">{title}</p>
              <p className="mt-0.5 truncate text-[10px] text-[#a9c0b6]">{detail}</p>
            </div>
            <span className="h-1.5 w-1.5 rounded-full bg-[#dff47c]" aria-hidden="true" />
          </div>
        ))}
      </div>
    </div>
  );
}

function FieldError({ id, children }) {
  return (
    <p id={id} className="mt-2 flex items-start gap-1.5 text-xs font-medium leading-5 text-red-700" role="alert">
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
