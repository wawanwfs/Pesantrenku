import React, { useEffect, useState } from 'react';
import { icons } from '../../lib/deps.jsx';

/** Memberi tahu bila koneksi terputus (jaringan pesantren kerap tidak stabil) dan saat pulih. */
export default function OfflineBanner() {
  const [state, setState] = useState(() => (typeof navigator !== 'undefined' && navigator.onLine === false ? 'offline' : 'online'));

  useEffect(() => {
    let timer = 0;
    const goOffline = () => {
      window.clearTimeout(timer);
      setState('offline');
    };
    const goOnline = () => {
      setState('back');
      timer = window.setTimeout(() => setState('online'), 3200);
    };
    window.addEventListener('offline', goOffline);
    window.addEventListener('online', goOnline);
    return () => {
      window.clearTimeout(timer);
      window.removeEventListener('offline', goOffline);
      window.removeEventListener('online', goOnline);
    };
  }, []);

  if (state === 'online') return null;
  const offline = state === 'offline';
  const Icon = offline ? icons.TriangleAlert : icons.CircleCheck;
  return (
    <div className={`pk-offline ${offline ? '' : 'is-back'}`} role="status" aria-live="polite">
      <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{offline ? 'Anda sedang offline. Perubahan baru belum dapat disimpan.' : 'Koneksi pulih.'}</span>
    </div>
  );
}
