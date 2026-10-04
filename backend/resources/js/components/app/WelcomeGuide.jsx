import React from 'react';
import { icons } from '../../lib/deps.jsx';
import { panels, roleLabel } from './appSupport.jsx';
import { useDialogLifecycle } from './AppDialogs.jsx';

const SEEN_KEY = 'pk-guide-seen';

export function guideAlreadySeen(panel) {
  try {
    return (JSON.parse(window.localStorage.getItem(SEEN_KEY) || '[]') || []).includes(panel);
  } catch {
    return false;
  }
}

export function markGuideSeen(panel) {
  try {
    const seen = new Set(JSON.parse(window.localStorage.getItem(SEEN_KEY) || '[]') || []);
    seen.add(panel);
    window.localStorage.setItem(SEEN_KEY, JSON.stringify([...seen]));
  } catch {
    // Tanpa penyimpanan, panduan hanya muncul kembali di kunjungan berikutnya.
  }
}

/**
 * Panduan singkat per peran. Otomatis muncul sekali per peran per peramban (tidak pada peramban
 * otomatis kecuali ?guide=1), dan dapat dibuka lagi dari palet perintah.
 */
export default function WelcomeGuide({ open, onClose, panel, auth }) {
  const ref = useDialogLifecycle(onClose, open);
  if (!open) return null;
  const menu = (panels[panel]?.menu || []).filter(([label]) => label !== 'Dashboard');
  const highlights = menu.slice(0, 3).map(([label]) => label).join(', ');
  const memberships = Array.isArray(auth?.memberships) ? auth.memberships : Object.values(auth?.memberships || {});
  const steps = [
    ['Search', 'Lompat ke mana saja', 'Tekan Ctrl K (atau /) untuk membuka palet perintah dan mencari halaman atau tindakan.'],
    ['Menu', `Menu ${roleLabel(panel)}`, highlights ? `Menu di kiri hanya memuat yang boleh Anda akses. Mulai dari ${highlights}.` : 'Menu di kiri hanya memuat yang boleh Anda akses.'],
    ['School', 'Pesantren aktif', memberships.length > 1 ? 'Anda terdaftar di beberapa pesantren. Ganti pesantren aktif dari bilah atas atau palet perintah.' : 'Seluruh data yang tampil mengikuti pesantren aktif Anda.'],
    ['Settings', 'Tampilan nyaman', 'Mode gelap, kurangi animasi, dan kepadatan tabel ada di menu akun dan di atas tabel. Tekan ? untuk melihat semua pintasan.'],
  ];
  const finish = () => {
    markGuideSeen(panel);
    onClose();
  };
  return (
    <div className="pk-palette" role="presentation">
      <button type="button" className="pk-palette-backdrop" onClick={finish} aria-label="Tutup panduan" tabIndex={-1} />
      <div ref={ref} className="pk-palette-panel pk-guide" role="dialog" aria-modal="true" aria-label="Panduan singkat">
        <div className="pk-shortcuts-head">
          <h2>Selamat datang di Pesantrenku</h2>
          <button type="button" onClick={finish} className="icon-button" aria-label="Tutup panduan"><icons.X className="h-4 w-4" aria-hidden="true" /></button>
        </div>
        <div className="pk-guide-body">
          {steps.map(([icon, title, text]) => {
            const Icon = icons[icon] || icons.CircleCheck;
            return (
              <div key={title} className="pk-guide-item">
                <span className="pk-palette-icon"><Icon className="h-4 w-4" aria-hidden="true" /></span>
                <div>
                  <h3>{title}</h3>
                  <p>{text}</p>
                </div>
              </div>
            );
          })}
        </div>
        <div className="pk-guide-foot">
          <button type="button" onClick={finish} className="ui-button bg-primary-600 text-white hover:bg-primary-700" data-dialog-initial-focus>Mulai bekerja</button>
        </div>
      </div>
    </div>
  );
}
