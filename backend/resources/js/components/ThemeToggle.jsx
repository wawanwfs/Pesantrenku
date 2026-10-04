import React from 'react';
import { icons } from '../lib/deps.jsx';
import { useTheme } from '../lib/theme.js';

const options = [
  ['light', 'Terang', 'Sun'],
  ['dark', 'Gelap', 'Moon'],
  ['system', 'Sistem', 'Monitor'],
];

/** Tombol ikon: beralih terang/gelap. `className` mengikuti tombol di sekitarnya. */
export function ThemeToggleButton({ className = 'icon-button' }) {
  const { resolved, toggle } = useTheme();
  const dark = resolved === 'dark';
  const Icon = dark ? icons.Sun : icons.Moon;
  return (
    <button type="button" onClick={toggle} className={`${className} pk-theme-button`} aria-label={dark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'} aria-pressed={dark} title={dark ? 'Mode terang' : 'Mode gelap'}>
      <Icon className="pk-theme-icon h-[1.125rem] w-[1.125rem]" aria-hidden="true" />
    </button>
  );
}

/** Pemilih tiga pilihan (terang/gelap/sistem) untuk menu akun. */
export function ThemeSegmented() {
  const { preference, setPreference } = useTheme();
  return (
    <div role="radiogroup" aria-label="Tampilan" className="pk-segmented">
      {options.map(([value, label, iconName]) => {
        const Icon = icons[iconName];
        const active = preference === value;
        return (
          <button key={value} type="button" role="radio" aria-checked={active} onClick={() => setPreference(value)} className={`pk-segmented-item ${active ? 'is-active' : ''}`}>
            <Icon className="h-3.5 w-3.5" aria-hidden="true" />
            {label}
          </button>
        );
      })}
    </div>
  );
}
