import React from 'react';
import { Link, icons } from '../../lib/deps.jsx';

export function SideItem({ label, icon, href, active, light, collapsed, onClick }) {
  const Icon = icons[icon] || icons.CircleCheck;
  return <Link href={href} onClick={onClick} aria-label={collapsed ? label : undefined} title={collapsed ? label : undefined} aria-current={active ? 'page' : undefined} className={`side-item group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium leading-5 transition focus:outline-none focus:ring-2 focus:ring-primary-500/50 ${active ? light ? 'bg-primary-50 text-primary-800' : 'bg-white/15 text-white' : light ? 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' : 'text-white/75 hover:bg-white/10 hover:text-white'}`}><span className={`absolute inset-y-2 left-0 w-0.5 transition ${active ? light ? 'bg-primary-600' : 'bg-primary-400' : 'bg-transparent'}`} /><Icon className="h-4 w-4 shrink-0" />{!collapsed && <span className="min-w-0 break-words">{label}</span>}</Link>;
}

export function Logo() {
  return <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-current/15 bg-white/10 shadow-sm"><svg viewBox="0 0 48 48" className="h-8 w-8" fill="none"><path d="M24 5 43 41H5L24 5Z" stroke="currentColor" strokeWidth="2" /><path d="M24 15v18M18 32c4-2 8-2 12 0M16 36h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg></div>;
}
