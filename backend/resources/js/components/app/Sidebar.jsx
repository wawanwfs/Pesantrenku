import React from 'react';
import { Link, icons } from '../../lib/deps.jsx';
import { slug } from './appSupport.jsx';

export function SideItem({ label, icon, href, active, light, collapsed, onClick }) {
  const Icon = icons[icon] || icons.CircleCheck;
  const tone = active
    ? light ? 'bg-primary-50 text-primary-800' : 'bg-white/15 text-white'
    : light ? 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' : 'text-white/75 hover:bg-white/10 hover:text-white';
  const bar = active ? (light ? 'bg-primary-600' : 'bg-primary-400') : 'bg-transparent';
  return (
    <Link
      href={href}
      onClick={onClick}
      aria-label={collapsed ? label : undefined}
      title={collapsed ? label : undefined}
      aria-current={active ? 'page' : undefined}
      className={`side-item group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium leading-5 transition focus:outline-none focus:ring-2 focus:ring-primary-500/50 ${tone}`}
    >
      <span className={`side-item-bar absolute inset-y-2 left-0 w-0.5 transition ${bar}`} />
      <Icon className="h-4 w-4 shrink-0" />
      {!collapsed && <span className="min-w-0 break-words">{label}</span>}
    </Link>
  );
}

export function Logo() {
  return <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-current/15 bg-white/10 shadow-sm"><svg viewBox="0 0 48 48" className="h-8 w-8" fill="none"><path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2" /><path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg></div>;
}

export function isMenuActive({ label, href, screen, module }) {
  return (screen === 'dashboard' && label === 'Dashboard') || Boolean(href?.endsWith(`/module/${module}`)) || module === slug(label);
}

function Brand({ config, collapsed }) {
  return (
    <div className="flex items-center gap-3 px-2 py-2">
      <Logo />
      {!collapsed && (
        <div className="min-w-0">
          <div className="font-semibold">Pesantrenku</div>
          <div className={`mt-0.5 text-[11px] font-medium uppercase ${config.light ? 'text-neutral-500' : 'text-white/60'}`}>{config.role}</div>
        </div>
      )}
    </div>
  );
}

function NavList({ panel, config, screen, module, collapsed, onNavigate }) {
  return (
    <nav aria-label="Navigasi utama" className="sidebar-nav mt-3 flex-1 space-y-1 overflow-y-auto pr-1">
      {config.menu.map(([label, icon, href]) => {
        const target = label === 'Dashboard' ? `/dashboard/${panel}` : href;
        return <SideItem key={label} label={label} icon={icon} href={target} active={isMenuActive({ label, href, screen, module })} light={config.light} collapsed={collapsed} onClick={onNavigate} />;
      })}
    </nav>
  );
}

/** Kartu pesantren aktif di dasar sidebar: konteks tenant selalu terlihat. */
function TenantCard({ auth, config, collapsed }) {
  const tenant = auth?.active_pesantren;
  if (!tenant?.name) return null;
  const initials = tenant.name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
  return (
    <div className={`sidebar-tenant mt-3 flex items-center gap-2.5 rounded-lg px-2.5 py-2 ${config.light ? 'bg-neutral-100' : 'bg-white/10'}`} title={collapsed ? tenant.name : undefined}>
      <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-md text-[11px] font-bold ${config.light ? 'bg-primary-100 text-primary-800' : 'bg-white/15 text-white'}`} aria-hidden="true">{initials}</span>
      {!collapsed && (
        <div className="min-w-0">
          <div className={`text-[10px] font-medium uppercase ${config.light ? 'text-neutral-500' : 'text-white/55'}`}>Pesantren aktif</div>
          <div className="truncate text-xs font-semibold">{tenant.name}</div>
        </div>
      )}
    </div>
  );
}

export function DesktopSidebar({ panel, config, screen, module, auth, collapsed, onToggle }) {
  const light = config.light;
  return (
    <aside className={`app-sidebar ${collapsed ? 'is-collapsed w-20' : 'w-72'} fixed inset-y-0 left-0 z-30 hidden border-r transition-[width] duration-300 lg:block ${light ? 'app-sidebar-light border-neutral-200 bg-white text-neutral-900' : 'app-sidebar-dark border-white/10 text-white'}`}>
      <div className="relative flex h-full flex-col p-3.5">
        <Brand config={config} collapsed={collapsed} />
        <div className={`mt-7 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] ${collapsed ? 'sr-only' : light ? 'text-neutral-400' : 'text-white/45'}`}>Navigasi</div>
        <NavList panel={panel} config={config} screen={screen} module={module} collapsed={collapsed} />
        <TenantCard auth={auth} config={config} collapsed={collapsed} />
        <button
          type="button"
          onClick={onToggle}
          aria-label={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
          aria-expanded={!collapsed}
          title={collapsed ? 'Perluas sidebar ([)' : 'Ciutkan sidebar ([)'}
          className={`mt-3 inline-flex min-h-10 items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500/50 ${light ? 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' : 'bg-white/10 text-white/90 hover:bg-white/15'}`}
        >
          {collapsed ? <icons.ChevronRight className="h-4 w-4" /> : <span className="inline-flex items-center gap-2"><icons.ChevronLeft className="h-4 w-4" /> Ciutkan</span>}
        </button>
      </div>
    </aside>
  );
}

export function MobileSidebar({ dialogRef, panel, config, screen, module, auth, onClose }) {
  const light = config.light;
  return (
    <div ref={dialogRef} className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigasi utama">
      <button type="button" className="mobile-nav-backdrop absolute inset-0 bg-neutral-950/45 backdrop-blur-[2px]" onClick={onClose} aria-label="Tutup menu" tabIndex={-1} />
      <aside className={`app-sidebar mobile-nav-panel relative flex h-full w-80 max-w-[88vw] flex-col border-r p-4 shadow-2xl ${light ? 'app-sidebar-light border-neutral-200 bg-white text-neutral-900' : 'app-sidebar-dark border-white/10 text-white'}`}>
        <div className="flex items-center justify-between">
          <Brand config={config} collapsed={false} />
          <button type="button" onClick={onClose} className={`rounded-lg p-2 ${light ? 'hover:bg-neutral-100' : 'hover:bg-white/10'}`} aria-label="Tutup menu" data-dialog-initial-focus><icons.X className="h-5 w-5" /></button>
        </div>
        <div className={`mt-7 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] ${light ? 'text-neutral-400' : 'text-white/45'}`}>Navigasi</div>
        <NavList panel={panel} config={config} screen={screen} module={module} collapsed={false} onNavigate={onClose} />
        <TenantCard auth={auth} config={config} collapsed={false} />
      </aside>
    </div>
  );
}
