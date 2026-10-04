import React, { useEffect, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { router, icons } from '../../lib/deps.jsx';
import { apiJson, rowsOf } from './appSupport.jsx';
import { Alert, PanelCard } from './AppDialogs.jsx';
import { Table, formatCell } from './Widgets.jsx';

function useCountdown(expiresAt) {
  const target = expiresAt ? new Date(expiresAt).getTime() : NaN;
  const totalRef = useRef(0);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!Number.isFinite(target)) return undefined;
    totalRef.current = Math.max(1000, target - Date.now());
    setNow(Date.now());
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, [target]);

  if (!Number.isFinite(target)) return null;
  const remaining = Math.max(0, target - now);
  return { remaining, total: totalRef.current || remaining || 1, expired: remaining <= 0 };
}

function formatRemaining(milliseconds) {
  const seconds = Math.ceil(milliseconds / 1000);
  return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

export function QrTool({ panel, rows, shared }) {
  const initialQr = rowsOf(rows)[0] || {};
  const initialCode = initialQr.code || initialQr.kode || '';
  const [code, setCode] = useState(typeof initialCode === 'string' && initialCode.length === 64 ? initialCode : '');
  const [expiresAt, setExpiresAt] = useState(initialQr.expires_at || '');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const QrIcon = icons.QrCode || icons.Box;
  const purpose = panel === 'santri' ? 'attendance' : 'identity';
  const countdown = useCountdown(code ? expiresAt : '');
  const canScan = ['guru', 'musyrif', 'klinik', 'tu', 'akademik', 'keuangan', 'owner'].includes(panel);

  const generate = async () => {
    setLoading(true);
    setMessage('');
    setError('');
    try {
      const json = await apiJson('/web-actions/qr/generate', { method: 'POST', body: JSON.stringify({ purpose }) });
      if (typeof json.code !== 'string' || json.code.length !== 64) {
        throw new Error('Server mengembalikan kode QR yang tidak valid.');
      }
      setCode(json.code);
      setExpiresAt(json.expires_at || '');
      setMessage('QR berhasil dibuat dan dicatat di audit log.');
      router.reload({ only: ['shared'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };



  return (
    <section className={`grid gap-5 ${canScan ? 'xl:grid-cols-[0.8fr_1.2fr]' : 'max-w-xl'}`}>
      <PanelCard title={purpose === 'attendance' ? 'QR Presensi Aktif' : 'QR Identitas Aktif'}>
        {(message || error) && <div className="mb-4"><Alert tone={error ? 'error' : 'success'}>{error || message}</Alert></div>}
        <p className="mb-4 text-sm leading-6 text-neutral-600">
          QR berlaku singkat dan sekali pakai. Jangan membagikannya melalui tangkapan layar.
        </p>
        <div className={`pk-qr-frame ${code ? 'has-code' : ''} ${countdown?.expired ? 'is-expired' : ''}`}>
          {code ? (
            <>
              <QRCodeSVG value={code} size={240} level="M" marginSize={2} title={purpose === 'attendance' ? 'QR presensi Pesantrenku' : 'QR identitas Pesantrenku'} />
              {countdown?.expired && <div className="pk-qr-expired" role="status"><icons.TriangleAlert className="h-5 w-5" aria-hidden="true" /><strong>QR kedaluwarsa</strong><span>Buat QR baru untuk melanjutkan.</span></div>}
            </>
          ) : (
            <div className="pk-qr-placeholder">
              <QrIcon className="h-10 w-10" aria-hidden="true" />
              <div className="text-center text-sm text-neutral-500">Generate kode untuk menampilkan QR yang dapat dipindai.</div>
            </div>
          )}
        </div>
        {countdown && (
          <div className="pk-qr-timer mt-3 max-w-xs">
            <div className="mb-1 flex items-center justify-between text-xs font-medium text-neutral-600">
              <span>{countdown.expired ? 'Sudah kedaluwarsa' : 'Berlaku untuk'}</span>
              <span className="font-mono tabular-nums" aria-hidden="true">{formatRemaining(countdown.remaining)}</span>
            </div>
            <progress className={`app-progress ${countdown.remaining < 30000 ? 'app-progress-tone-4' : ''}`} max={countdown.total} value={countdown.remaining} aria-label="Sisa masa berlaku QR" />
          </div>
        )}
        <button type="button" onClick={generate} disabled={loading} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
          {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <QrIcon className="h-4 w-4" />}
          {code ? 'Perbarui QR' : 'Buat QR'}
        </button>
      </PanelCard>
      {canScan && <PanelCard title="Pemindai Terverifikasi">
        <div className="rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm leading-6 text-primary-900">
          Pemindaian presensi hanya tersedia melalui aplikasi mobile pada perangkat terdaftar dan jaringan kampus. Kode yang ditempel atau dikirim dari browser tidak dapat mencatat kehadiran.
        </div>
        <div className="mt-5">
          <Table rows={rows} shared={shared} compact />
        </div>
      </PanelCard>}
    </section>
  );
}
