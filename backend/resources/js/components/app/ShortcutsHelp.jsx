import React from 'react';
import { icons } from '../../lib/deps.jsx';
import { useDialogLifecycle } from './AppDialogs.jsx';

const shortcuts = [
  [['Ctrl', 'K'], 'Buka palet perintah (cari halaman dan tindakan)'],
  [['/'], 'Buka palet perintah dari mana saja'],
  [['['], 'Ciutkan atau perluas sidebar'],
  [['T'], 'Beralih mode terang / gelap'],
  [['?'], 'Tampilkan daftar pintasan ini'],
  [['Esc'], 'Tutup dialog, menu, atau palet'],
];

export default function ShortcutsHelp({ open, onClose }) {
  const ref = useDialogLifecycle(onClose, open);
  if (!open) return null;
  return (
    <div className="pk-palette" role="presentation">
      <button type="button" className="pk-palette-backdrop" onClick={onClose} aria-label="Tutup pintasan" tabIndex={-1} />
      <div ref={ref} className="pk-palette-panel pk-shortcuts" role="dialog" aria-modal="true" aria-label="Pintasan keyboard">
        <div className="pk-shortcuts-head">
          <h2>Pintasan keyboard</h2>
          <button type="button" onClick={onClose} className="icon-button" aria-label="Tutup pintasan" data-dialog-initial-focus><icons.X className="h-4 w-4" aria-hidden="true" /></button>
        </div>
        <dl className="pk-shortcuts-list">
          {shortcuts.map(([keys, label]) => (
            <div key={label}>
              <dt>{keys.map((key, index) => <React.Fragment key={key}>{index > 0 && <span aria-hidden="true">+</span>}<kbd className="pk-kbd">{key}</kbd></React.Fragment>)}</dt>
              <dd>{label}</dd>
            </div>
          ))}
        </dl>
        <p className="pk-shortcuts-note">Pintasan satu tombol tidak aktif saat Anda sedang mengetik di kolom isian.</p>
      </div>
    </div>
  );
}
