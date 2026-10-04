import React, { useCallback, useEffect, useState } from 'react';
import { icons } from '../../lib/deps.jsx';
import { toast } from './toast.js';

const toneIcon = { success: 'CircleCheck', info: 'Bell', warning: 'TriangleAlert', error: 'TriangleAlert' };
const MAX_VISIBLE = 4;

/** Wadah toast; pasang sekali di shell. Pesan penting tetap tampil inline, toast hanya pelengkap. */
export default function ToastHost() {
  const [items, setItems] = useState([]);

  const dismiss = useCallback((id) => setItems((current) => current.map((item) => (item.id === id ? { ...item, leaving: true } : item))), []);

  useEffect(() => toast.subscribe((item) => setItems((current) => [...current.slice(-(MAX_VISIBLE - 1)), item])), []);

  useEffect(() => {
    const timers = items.filter((item) => !item.leaving).map((item) => window.setTimeout(() => dismiss(item.id), item.duration));
    return () => timers.forEach((timer) => window.clearTimeout(timer));
  }, [items.length, dismiss]);

  useEffect(() => {
    const leaving = items.filter((item) => item.leaving);
    if (!leaving.length) return undefined;
    const timer = window.setTimeout(() => setItems((current) => current.filter((item) => !item.leaving)), 240);
    return () => window.clearTimeout(timer);
  }, [items]);

  return (
    <div className="pk-toasts" role="region" aria-label="Pemberitahuan">
      {items.map((item) => {
        const Icon = icons[toneIcon[item.tone]] || icons.Bell;
        return (
          <div key={item.id} className={`pk-toast pk-toast-${item.tone} ${item.leaving ? 'is-leaving' : ''}`} role={item.tone === 'error' ? 'alert' : 'status'}>
            <Icon className="pk-toast-icon h-4 w-4 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              {item.title && <div className="pk-toast-title">{item.title}</div>}
              <div className="pk-toast-message">{item.message}</div>
            </div>
            <button type="button" className="pk-toast-close" onClick={() => dismiss(item.id)} aria-label="Tutup pemberitahuan"><icons.X className="h-3.5 w-3.5" aria-hidden="true" /></button>
          </div>
        );
      })}
    </div>
  );
}
