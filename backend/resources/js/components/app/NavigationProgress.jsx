import React, { useEffect, useRef, useState } from 'react';
import { router } from '../../lib/deps.jsx';

/** Bilah progres tipis di atas layar selama Inertia memuat halaman berikutnya. */
export default function NavigationProgress() {
  const [phase, setPhase] = useState('idle');
  const timer = useRef(0);

  useEffect(() => {
    const offStart = router.on('start', () => {
      window.clearTimeout(timer.current);
      // Tunda sedikit agar navigasi instan tidak berkedip.
      timer.current = window.setTimeout(() => setPhase('active'), 120);
    });
    const offFinish = router.on('finish', () => {
      window.clearTimeout(timer.current);
      setPhase((current) => (current === 'active' ? 'done' : 'idle'));
      timer.current = window.setTimeout(() => setPhase('idle'), 360);
    });
    return () => {
      window.clearTimeout(timer.current);
      offStart?.();
      offFinish?.();
    };
  }, []);

  return <div className={`pk-navprogress is-${phase}`} aria-hidden="true"><span /></div>;
}
