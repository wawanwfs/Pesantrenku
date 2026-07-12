import React, { useEffect } from 'react';
import { router, useForm } from '../lib/deps.jsx';

export default function Login({ auth }) {
  const { data, setData, post, processing, errors } = useForm({ login: 'superadmin@pesantenku.test', password: 'password' });
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
      <main className="min-h-screen bg-neutral-50 flex items-center justify-center p-6">
        <div className="rounded-2xl border border-neutral-200 bg-white px-6 py-5 text-sm shadow-sm">Mengalihkan ke dashboard...</div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-neutral-50 flex items-center justify-center p-6">
      <section className="w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-neutral-200 grid md:grid-cols-[1fr_1.1fr]">
        <div className="bg-gradient-to-br from-primary-900 via-primary-700 to-primary-600 p-10 text-white">
          <Logo large />
          <h1 className="mt-10 text-4xl font-semibold leading-tight">Pesantenku</h1>
          <p className="mt-4 text-sm leading-6 text-primary-50">Sistem manajemen pesantren modern untuk akademik, keuangan, tata usaha, asrama, guru, wali, santri, dan klinik.</p>
          <div className="mt-10 max-h-56 space-y-3 overflow-y-auto pr-1 text-sm">
            {['superadmin@pesantenku.test', 'owner@pesantenku.test', 'keuangan@pesantenku.test', 'akademik@pesantenku.test', 'tu@pesantenku.test', 'santri@pesantenku.test', 'wali@pesantenku.test', 'guru@pesantenku.test', 'musyrif@pesantenku.test', 'klinik@pesantenku.test'].map((email) => (
              <button key={email} type="button" onClick={() => setData('login', email)} className="block rounded-lg bg-white/10 px-3 py-2 text-left hover:bg-white/20">{email}</button>
            ))}
          </div>
        </div>
        <form onSubmit={submit} className="p-10">
          <p className="text-sm font-medium text-primary-700">Masuk Dashboard</p>
          <h2 className="mt-2 text-2xl font-semibold">Gunakan akun demo</h2>
          <label className="mt-8 block text-sm font-medium">Email, username, atau telepon</label>
          <input autoComplete="username" value={data.login} onChange={(e) => setData('login', e.target.value)} className="mt-2 w-full rounded-xl border border-neutral-200 px-4 py-3 outline-none focus:border-primary-600" />
          {errors.login && <p className="mt-2 text-sm text-red-600">{errors.login}</p>}
          <label className="mt-5 block text-sm font-medium">Password</label>
          <input type="password" autoComplete="current-password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="mt-2 w-full rounded-xl border border-neutral-200 px-4 py-3 outline-none focus:border-primary-600" />
          <button type="submit" disabled={processing} className="mt-8 w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">Masuk</button>
          <p className="mt-5 text-xs text-neutral-500">Password semua akun demo: <span className="font-semibold">password</span></p>
        </form>
      </section>
    </main>
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

function Logo({ large = false }) {
  return (
    <div className="flex items-center gap-3">
      <div className={`${large ? 'h-16 w-16' : 'h-10 w-10'} grid place-items-center rounded-2xl border border-white/40 bg-white/10`}>
        <svg viewBox="0 0 48 48" className="h-10 w-10" fill="none">
          <path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2" />
          <path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
      </div>
      <div>
        <div className="font-semibold">Pesantenku</div>
        <div className="text-xs opacity-80">Platform Pesantren</div>
      </div>
    </div>
  );
}
