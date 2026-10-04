import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, icons } from '../../lib/deps.jsx';
import { ThemeSegmented, ThemeToggleButton } from '../ThemeToggle.jsx';
import { apiJson, panels, roleLabel, rowsOf, titleFromSlug } from './appSupport.jsx';

export function Topbar({ panel, auth, shared, app, onMenuOpen }) {
  const memberships = Array.isArray(auth?.memberships) ? auth.memberships : Object.values(auth?.memberships || {});
  const activeTenantId = auth?.active_pesantren?.id || memberships[0]?.id || '';
  const [selectedTenantId, setSelectedTenantId] = useState(String(activeTenantId));
  const [tenantSwitching, setTenantSwitching] = useState(false);
  const [tenantError, setTenantError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notificationState, setNotificationState] = useState({ data: rowsOf(shared?.notifications), unread: rowsOf(shared?.notifications).length });
  const [notificationAction, setNotificationAction] = useState({ loading: false, error: '' });
  const [accountOpen, setAccountOpen] = useState(false);
  const academicYear = app?.academic_year || 'Tahun ajaran belum diatur';
  const topbarRef = useRef(null);
  const menuItems = useMemo(() => (panels[panel]?.menu || []).map(([label, icon, href]) => ({ label, icon, href: label === 'Dashboard' ? `/dashboard/${panel}` : href })), [panel]);
  const searchResults = useMemo(() => {
    const term = searchTerm.trim().toLowerCase();
    if (!term) return [];
    return menuItems.filter((item) => item.label.toLowerCase().includes(term)).slice(0, 6);
  }, [menuItems, searchTerm]);
  const notifications = rowsOf(notificationState.data).slice(0, 5);
  const unreadCount = notificationState.unread ?? notifications.filter((item) => !item.read_at).length;
  const userName = auth?.user?.name || roleLabel(panel);
  const userEmail = auth?.user?.email || '';
  const initials = userName.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  const roles = rowsOf(auth?.roles).map((role) => titleFromSlug(String(role))).join(', ') || roleLabel(panel);
  const settingsHref = menuItems.find((item) => item.label.toLowerCase().includes('pengaturan'))?.href || `/dashboard/${panel}`;
  const notificationConfig = app?.notifications || {};

  useEffect(() => {
    const closeFloatingMenus = (event) => {
      if (event.key === 'Escape') {
        setSearchOpen(false);
        setNotificationsOpen(false);
        setAccountOpen(false);
      }
      if (event.type === 'mousedown' && topbarRef.current && !topbarRef.current.contains(event.target)) {
        setSearchOpen(false);
        setNotificationsOpen(false);
        setAccountOpen(false);
      }
    };

    document.addEventListener('mousedown', closeFloatingMenus);
    document.addEventListener('keydown', closeFloatingMenus);

    return () => {
      document.removeEventListener('mousedown', closeFloatingMenus);
      document.removeEventListener('keydown', closeFloatingMenus);
    };
  }, []);

  useEffect(() => {
    setNotificationState({ data: rowsOf(shared?.notifications), unread: rowsOf(shared?.notifications).length });
  }, [shared?.notifications]);

  useEffect(() => {
    setSelectedTenantId(String(activeTenantId));
    setTenantSwitching(false);
    setTenantError('');
  }, [activeTenantId]);

  useEffect(() => {
    let mounted = true;
    let source = null;
    let unsubscribeRealtime = () => {};
    const poll = async () => {
      try {
        const json = await apiJson('/web-actions/notifications', { method: 'GET' });
        if (mounted) setNotificationState({ data: rowsOf(json.data), unread: json.unread ?? 0 });
      } catch {
        // Header polling is best-effort; page actions still surface request errors inline.
      }
    };
    poll();
    if (notificationConfig.sse_enabled && 'EventSource' in window) {
      source = new EventSource('/web-actions/notifications/stream');
      source.addEventListener('notifications', (event) => {
        try {
          const json = JSON.parse(event.data || '{}');
          if (mounted) setNotificationState({ data: rowsOf(json.data), unread: json.unread ?? 0 });
        } catch {
          // Bad stream payload should not disable the polling fallback.
        }
      });
    }
    if (notificationConfig.websocket_enabled) {
      import('../../lib/realtime.js')
        .then(({ subscribeToNotifications }) => subscribeToNotifications({
          userId: auth?.user?.id,
          tenantId: activeTenantId,
          onChange: poll,
          connection: notificationConfig.websocket,
        }))
        .then((unsubscribe) => { unsubscribeRealtime = unsubscribe; })
        .catch(() => {});
    }
    const pollingSeconds = Math.max(5, Number(notificationConfig.polling_seconds) || 20);
    const timer = window.setInterval(poll, pollingSeconds * 1000);
    return () => {
      mounted = false;
      source?.close();
      unsubscribeRealtime();
      window.clearInterval(timer);
    };
  }, [activeTenantId, auth?.user?.id, notificationConfig.polling_seconds, notificationConfig.sse_enabled, notificationConfig.websocket, notificationConfig.websocket_enabled]);

  const openMenu = (href) => {
    if (!href) return;
    setSearchOpen(false);
    setSearchTerm('');
    router.visit(href);
  };

  const submitSearch = (event) => {
    event.preventDefault();
    openMenu(searchResults[0]?.href);
  };

  const logout = () => {
    setAccountOpen(false);
    router.post('/logout');
  };

  const switchTenant = (value) => {
    if (!value || value === String(activeTenantId) || tenantSwitching) return;
    setSelectedTenantId(value);
    setTenantError('');
    router.post('/tenant/switch', { pesantren_id: Number(value) }, {
      preserveScroll: true,
      onStart: () => setTenantSwitching(true),
      onError: () => {
        setSelectedTenantId(String(activeTenantId));
        setTenantError('Pesantren aktif gagal diganti. Silakan coba lagi.');
      },
      onFinish: () => setTenantSwitching(false),
    });
  };

  const markNotificationsRead = async () => {
    if (!notifications.length || notificationAction.loading) return;
    setNotificationAction({ loading: true, error: '' });
    try {
      await apiJson('/web-actions/notifications/read', {
        method: 'POST',
        body: JSON.stringify({ ids: notifications.map((item) => item.id).filter(Boolean) }),
      });
      const fresh = await apiJson('/web-actions/notifications', { method: 'GET' });
      setNotificationState({ data: rowsOf(fresh.data), unread: fresh.unread ?? 0 });
      setNotificationsOpen(false);
    } catch (error) {
      setNotificationAction({ loading: false, error: error.message || 'Notifikasi gagal diperbarui.' });
      return;
    }
    setNotificationAction({ loading: false, error: '' });
  };

  return (
    <header ref={topbarRef} className="app-topbar sticky top-0 z-10 border-b border-neutral-200 bg-white/95 px-3.5 py-2.5 backdrop-blur-lg sm:px-5 lg:px-6">
      <div className="flex items-center gap-2.5 sm:gap-4">
        <button type="button" onClick={onMenuOpen} className="icon-button lg:hidden" aria-label="Buka menu"><icons.Menu className="h-5 w-5" /></button>
        <form onSubmit={submitSearch} className="relative flex-1">
          <icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" />
          <input
            aria-label="Cari menu, modul, dan laporan"
            value={searchTerm}
            onFocus={() => setSearchOpen(true)}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setSearchOpen(true);
            }}
            className="topbar-search w-full max-w-xl rounded-lg border border-neutral-200 bg-neutral-50 py-2.5 pl-10 pr-10 text-sm outline-none transition placeholder:text-neutral-400 focus:border-primary-500 focus:bg-white focus:ring-3 focus:ring-primary-100/70"
            placeholder="Cari menu, modul, laporan..."
          />
          {searchTerm && <button type="button" onClick={() => setSearchTerm('')} className="absolute right-3 top-2.5 rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40" aria-label="Bersihkan pencarian"><icons.X className="h-4 w-4" /></button>}
          {searchOpen && searchTerm && (
            <div className="floating-menu absolute left-0 top-12 z-30 w-full max-w-xl overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              {searchResults.length > 0 ? searchResults.map((item) => {
                const Icon = icons[item.icon] || icons.Search;
                return (
                  <button key={item.href} type="button" onClick={() => openMenu(item.href)} className="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700"><Icon className="h-4 w-4" /></span>
                    <span className="font-medium">{item.label}</span>
                  </button>
                );
              }) : <div className="px-4 py-3 text-sm text-neutral-500">Menu tidak ditemukan.</div>}
            </div>
          )}
        </form>
        {memberships.length > 0 && (
          <div className="relative hidden xl:block">
            <select
              aria-label="Pilih pesantren aktif"
              aria-invalid={Boolean(tenantError)}
              value={selectedTenantId}
              disabled={tenantSwitching}
              onChange={(event) => switchTenant(event.target.value)}
              className="max-w-64 rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm text-neutral-700 outline-none transition focus:border-primary-500 focus:ring-3 focus:ring-primary-100/70 disabled:cursor-wait disabled:bg-neutral-100"
            >
              {memberships.map((item) => <option key={`${item.id}-${item.role || item.role_label}`} value={item.id}>{item.name || item.pesantren} {item.role_label ? `- ${item.role_label}` : ''}</option>)}
            </select>
            {tenantSwitching && <icons.Loader2 className="pointer-events-none absolute right-8 top-3 h-4 w-4 animate-spin text-primary-700 motion-reduce:animate-none" aria-hidden="true" />}
          </div>
        )}
        <div className="hidden max-w-56 rounded-md border border-primary-100 bg-primary-50 px-3 py-2 text-xs font-medium text-primary-800 lg:block">{academicYear}</div>
        <ThemeToggleButton />
        <div className="relative">
          <button type="button" onClick={() => { setNotificationsOpen((open) => !open); setAccountOpen(false); }} className="icon-button relative" aria-label="Buka notifikasi" aria-expanded={notificationsOpen}>
            <icons.Bell className="h-5 w-5" />
            {unreadCount > 0 && <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">{unreadCount}</span>}
          </button>
          {notificationsOpen && (
            <div className="floating-menu absolute right-0 top-12 z-30 w-[min(22rem,calc(100vw-1rem))] overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              <div className="flex items-center justify-between border-b border-neutral-100 px-3 py-3 text-sm">
                <span className="font-semibold">Notifikasi</span>
                {notifications.length > 0 && <button type="button" disabled={notificationAction.loading} onClick={markNotificationsRead} className="rounded-lg px-2 py-1 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-wait disabled:opacity-50">{notificationAction.loading ? 'Memperbarui...' : 'Tandai terbaca'}</button>}
              </div>
              {notificationAction.error && <div role="alert" className="mx-2 mt-2 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{notificationAction.error}</div>}
              <div className="max-h-80 overflow-y-auto">
                {notifications.length > 0 ? notifications.map((item) => (
                  <div key={item.id || item.title} className="mx-1 border-b border-neutral-100 px-3 py-3 text-sm last:border-0">
                    <div className="font-medium">{item.title}</div>
                    <div className="mt-1 text-xs text-neutral-500">{item.body || item.description || 'Notifikasi sistem'}</div>
                  </div>
                )) : <div className="px-4 py-6 text-center text-sm text-neutral-500">Belum ada notifikasi.</div>}
              </div>
            </div>
          )}
        </div>
        <div className="relative">
          <button type="button" onClick={() => { setAccountOpen((open) => !open); setNotificationsOpen(false); }} className="flex items-center gap-3 rounded-lg px-1.5 py-1 text-left transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500/40" aria-label="Buka menu akun" aria-expanded={accountOpen}>
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-sky-50 text-sm font-semibold text-sky-700 ring-1 ring-sky-100">{initials || 'U'}</div>
            <div className="hidden text-sm md:block"><div className="font-semibold">{userName}</div><div className="text-xs text-neutral-500">{roleLabel(panel)}</div></div>
          </button>
          {accountOpen && (
            <div className="floating-menu absolute right-0 top-12 z-30 w-[min(22rem,calc(100vw-1rem))] overflow-hidden border border-neutral-200 bg-white p-1.5 shadow-xl">
              <div className="border-b border-neutral-100 px-3 py-3">
                <div className="font-semibold">{userName}</div>
                {userEmail && <div className="mt-1 text-xs text-neutral-500">{userEmail}</div>}
                <div className="mt-2 text-xs text-neutral-500">{roles}</div>
              </div>
              {auth?.active_pesantren && (
                <div className="mx-1 border-b border-neutral-100 px-3 py-3 text-sm">
                  <div className="text-xs text-neutral-500">Pesantren Aktif</div>
                  <div className="mt-1 font-medium">{auth.active_pesantren.name}</div>
                </div>
              )}
              {memberships.length > 1 && (
                <div className="mx-1 border-b border-neutral-100 px-3 py-3 xl:hidden">
                  <label htmlFor="account-tenant-switch" className="mb-1.5 block text-xs font-medium text-neutral-500">Ganti pesantren aktif</label>
                  <select
                    id="account-tenant-switch"
                    value={selectedTenantId}
                    disabled={tenantSwitching}
                    aria-invalid={Boolean(tenantError)}
                    onChange={(event) => switchTenant(event.target.value)}
                    className="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 disabled:cursor-wait disabled:bg-neutral-100"
                  >
                    {memberships.map((item) => <option key={`${item.id}-${item.role || item.role_label}`} value={item.id}>{item.name || item.pesantren} {item.role_label ? `- ${item.role_label}` : ''}</option>)}
                  </select>
                </div>
              )}
              {tenantError && <div className="mx-2 my-2 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{tenantError}</div>}
              <div className="mx-1 border-b border-neutral-100 px-3 py-3">
                <div className="mb-1.5 text-xs font-medium text-neutral-500">Tampilan</div>
                <ThemeSegmented />
              </div>
              <Link href={settingsHref} className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition hover:bg-neutral-50" onClick={() => setAccountOpen(false)}><icons.Settings className="h-4 w-4" /> Pengaturan</Link>
              <button type="button" onClick={logout} className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200"><icons.LogOut className="h-4 w-4" /> Logout</button>
            </div>
          )}
        </div>
      </div>
      {tenantError && (
        <div role="alert" className="fixed right-4 top-16 z-50 flex max-w-sm items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 shadow-lg">
          <span>{tenantError}</span>
          <button type="button" onClick={() => setTenantError('')} className="-mr-1 rounded p-1 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300" aria-label="Tutup pesan gagal ganti pesantren"><icons.X className="h-4 w-4" /></button>
        </div>
      )}
    </header>
  );
}
