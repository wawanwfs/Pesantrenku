import React, { useEffect, useRef, useState } from 'react';
import { Link, icons } from '../../lib/deps.jsx';
import { apiJson, apiUpload, clampPercent, downloadAuthenticatedFile, optionsFor, parseJsonValue, relationSelectTypes, requestStepUpMfa, rowsOf, rupiah, slug, statusOptions, titleFromSlug } from './appSupport.jsx';

let openDialogCount = 0;

function PanelCard({ title, action, actionHref, onAction, children }) {
  return (
    <section className="surface-panel min-w-0 p-4 sm:p-5">
      {title && (
        <div className="mb-4 flex min-w-0 items-start justify-between gap-3 border-b border-neutral-100 pb-3">
          <h2 className="min-w-0 break-words text-sm font-semibold leading-5 text-neutral-800 sm:text-base">{title}</h2>
          {actionHref ? <Link href={actionHref} className="inline-flex min-h-9 shrink-0 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40">{action}</Link> : action ? <button type="button" onClick={onAction} className="inline-flex min-h-9 shrink-0 items-center rounded-md px-2.5 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40">{action}</button> : null}
        </div>
      )}
      {children}
    </section>
  );
}
function StatePanel({ icon = 'Inbox', title, description, loading = false, compact = false }) {
  const Icon = icons[icon] || icons.Inbox;
  return (
    <div className={`state-panel grid place-items-center border border-dashed border-neutral-200 bg-neutral-50/70 text-center ${compact ? 'min-h-28 p-4' : 'min-h-44 p-7'}`} role={loading ? 'status' : undefined} aria-live={loading ? 'polite' : undefined}>
      <div className="max-w-sm">
        <div className="mx-auto grid h-9 w-9 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-500">
          <Icon className={`h-4 w-4 ${loading ? 'animate-spin text-primary-600' : ''}`} />
        </div>
        <div className="mt-2.5 text-sm font-semibold text-neutral-700">{title}</div>
        {description && <div className="mt-1 text-xs leading-5 text-neutral-500">{description}</div>}
      </div>
    </div>
  );
}
const hiddenTableColumns = new Set(['id', 'created_at', 'updated_at', 'deleted_at', 'metadata', 'profile', 'password', 'remember_token', 'mfa_secret']);



const relationColumnLabels = {
  academic_year_id: 'Tahun Ajaran',
  class_id: 'Kelas',
  clinic_id: 'Klinik',
  doctor_id: 'Dokter',
  expense_category_id: 'Kategori Pengeluaran',
  guardian_id: 'Wali',
  invoice_id: 'Tagihan',
  medical_record_id: 'Rekam Medis',
  medicine_id: 'Obat',
  payment_method_id: 'Metode Pembayaran',
  pesantren_id: 'Pesantren',
  plan_id: 'Paket',
  program_id: 'Program',
  role_id: 'Role',
  room_id: 'Kamar',
  student_id: 'Santri',
  subject_id: 'Mata Pelajaran',
  teacher_id: 'Guru',
  user_id: 'Pengguna',
};

const fieldLabels = {
  name: 'Nama', city: 'Kota', phone: 'Telepon', email: 'Email', address: 'Alamat', status: 'Status',
  code: 'Kode', title: 'Judul', description: 'Deskripsi', date: 'Tanggal', starts_at: 'Tanggal Mulai',
  ends_at: 'Tanggal Selesai', amount: 'Nominal', monthly_price: 'Harga Bulanan', max_students: 'Batas Santri',
  max_users: 'Batas Pengguna', is_enabled: 'Aktif', is_active: 'Aktif', role: 'Peran', module: 'Modul',
  invoice_number: 'Nomor Tagihan', due_date: 'Jatuh Tempo', paid_at: 'Waktu Bayar', created_at: 'Dibuat',
  pengguna: 'Pengguna', pesantren_name: 'Pesantren', plan_name: 'Paket', nama_modul: 'Modul', role_akses: 'Akses',
};

function isVisibleTableColumn(key) {
  return !hiddenTableColumns.has(key);
}

function columnTitle(key) {
  if (relationColumnLabels[key]) return relationColumnLabels[key];
  if (fieldLabels[key]) return fieldLabels[key];
  return titleFromSlug(key.endsWith('_id') ? key.slice(0, -3) : key);
}

function renderCell(key, value, row = {}, shared = {}, field) {
  const displayValue = displayCellValue(key, value, row, shared, field);
  if (key === 'status' || key === 'level') return <Badge label={String(displayValue || 'Aktif')} />;
  if (typeof displayValue === 'boolean') return displayValue ? 'Ya' : 'Tidak';
  if (typeof displayValue === 'number' && (key.includes('amount') || key.includes('balance') || key.includes('price') || key.includes('nominal'))) return rupiah(displayValue);
  return String(displayValue ?? '-');
}

function displayCellValue(key, value, row, shared, field) {
  if (value === null || value === undefined || value === '') return value;
  const resolved = relationLabelFor(key, value, row, shared, field);
  return resolved ?? value;
}

function relationLabelFor(key, value, row, shared, field) {
  const relationField = field?.type?.startsWith('select') ? field : inferredFieldForColumn(key);
  if (!relationField) return null;

  const option = optionsFor(relationField, shared).find((item) => String(item.value) === String(value));
  if (option) return option.label;

  return relationFallbackLabel(key, value, row);
}

function inferredFieldForColumn(key) {
  const type = relationSelectTypes[key];
  return type ? { name: key, type } : null;
}

function relationFallbackLabel(key, value, row) {
  const candidates = [
    key.replace(/_id$/, '_name'),
    key.replace(/_id$/, '_title'),
    key.replace(/_id$/, '_number'),
    key.replace(/_id$/, ''),
  ];
  const label = candidates.map((candidate) => row[candidate]).find((candidateValue) => candidateValue !== undefined && candidateValue !== null && candidateValue !== '');
  return label ?? `#${value}`;
}

function Badge({ label }) {
  const lower = String(label).toLowerCase();
  const cls = lower.includes('inactive') || lower.includes('unpaid') || lower.includes('error') || lower.includes('rejected') || lower.includes('cancelled') || lower.includes('ditolak') || lower.includes('diblokir') ? 'bg-red-50 text-red-700' : lower.includes('pending') || lower.includes('waiting') || lower.includes('in_progress') || lower.includes('warning') || lower.includes('peringatan') || lower.includes('dipantau') ? 'bg-amber-50 text-amber-700' : lower.includes('paid') || lower.includes('active') || lower.includes('approved') || lower.includes('aktif') || lower.includes('success') || lower.includes('berhasil') || lower.includes('selesai') || lower.includes('done') || lower.includes('info') ? 'bg-green-50 text-green-700' : 'bg-neutral-100 text-neutral-600';
  return <span className={`inline-flex max-w-full rounded-md px-2 py-0.5 text-[11px] font-semibold leading-5 ${cls}`}>{statusLabel(label)}</span>;
}

function statusLabel(value) {
  const key = String(value ?? '').toLowerCase();
  return {
    active: 'Aktif', inactive: 'Tidak Aktif', pending: 'Menunggu', paid: 'Lunas', unpaid: 'Belum Lunas',
    success: 'Berhasil', waiting: 'Menunggu', draft: 'Draf', published: 'Terbit', done: 'Selesai',
    approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan', in_progress: 'Diproses',
  }[key] || titleFromSlug(String(value ?? '-'));
}
function Alert({ tone: alertTone, children }) {
  const cls = alertTone === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700';
  const Icon = alertTone === 'error' ? icons.TriangleAlert : icons.CircleCheck;
  return <div role={alertTone === 'error' ? 'alert' : 'status'} aria-live={alertTone === 'error' ? 'assertive' : 'polite'} className={`flex items-start gap-2.5 rounded-md border px-4 py-3 text-sm font-medium leading-6 ${cls}`}><Icon className="mt-1 h-4 w-4 shrink-0" /><div className="min-w-0 break-words">{children}</div></div>;
}

function PaginationControls({ pagination, page, setPage, perPage, setPerPage }) {
  const total = pagination.total ?? 0;
  const lastPage = Math.max(1, pagination.last_page || 1);
  const currentPage = Math.min(page, lastPage);

  return (
    <div className="pagination-bar mt-4 flex flex-col gap-3 border-t border-neutral-200 pt-4 text-sm text-neutral-600 md:flex-row md:items-center md:justify-between">
      <div><span className="font-semibold text-neutral-700">{total}</span> data <span className="text-neutral-300">/</span> halaman {currentPage} dari {lastPage}</div>
      <div className="flex flex-wrap items-center gap-2">
        <select value={perPage} onChange={(event) => setPerPage(Number(event.target.value))} className="rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
          {[10, 20, 50, 100].map((value) => <option key={value} value={value}>{value} / halaman</option>)}
        </select>
        <button type="button" disabled={currentPage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-primary-200"><icons.ChevronLeft className="h-4 w-4" /> Sebelumnya</button>
        <button type="button" disabled={currentPage >= lastPage} onClick={() => setPage((value) => Math.min(lastPage, value + 1))} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-primary-200">Berikutnya <icons.ChevronRight className="h-4 w-4" /></button>
      </div>
    </div>
  );
}

function ImportModal({ title, resource, loading, onSubmit, onClose }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [mapping, setMapping] = useState({});
  const [queue, setQueue] = useState(false);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [error, setError] = useState('');
  const UploadIcon = icons.Upload || icons.Download;
  const dialogRef = useDialogLifecycle(onClose);

  const previewFile = async () => {
    if (!file || !resource) return;
    setPreviewLoading(true);
    setError('');
    try {
      const json = await apiUpload(`/web-api/${resource}/import/preview`, file);
      setPreview(json);
      setMapping(json.mapping || {});
    } catch (err) {
      setError(err.message);
    } finally {
      setPreviewLoading(false);
    }
  };

  const submit = (event) => {
    event.preventDefault();
    onSubmit({ file, mapping, queue });
  };

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label={`Impor ${title}`}>
      <form onSubmit={submit} className="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Import {title}</h2>
            <p className="text-xs text-neutral-500">Preview header, mapping kolom, dan validasi sebelum data disimpan.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          {error && <Alert tone="error">{error}</Alert>}
          <div className="grid gap-3 md:grid-cols-[1fr_auto]">
            <label className="block text-sm">
              <span className="mb-1 block font-medium">File Import</span>
              <input type="file" accept=".csv,.txt,.xls,.xlsx" onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setMapping({}); }} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" required />
            </label>
            <button type="button" onClick={previewFile} disabled={!file || previewLoading} className="self-end rounded-xl border border-neutral-200 px-4 py-2.5 text-sm font-semibold disabled:opacity-60">
              {previewLoading ? 'Memuat...' : 'Preview'}
            </button>
          </div>
          {preview && (
            <div className="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
              <div className="rounded-xl border border-neutral-200">
                <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Mapping Kolom</div>
                <div className="max-h-80 space-y-3 overflow-y-auto p-4">
                  {rowsOf(preview.headers).map((header) => (
                    <label key={header} className="grid gap-1 text-sm">
                      <span className="font-medium">{header}</span>
                      <select value={mapping[header] || ''} onChange={(event) => setMapping((current) => ({ ...current, [header]: event.target.value }))} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm">
                        <option value="">Abaikan</option>
                        {rowsOf(preview.columns).map((column) => <option key={column} value={column}>{columnTitle(column)}</option>)}
                      </select>
                    </label>
                  ))}
                </div>
              </div>
              <div className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-3">
                  <StatPill label="Valid" value={preview.summary?.valid ?? 0} />
                  <StatPill label="Invalid" value={preview.summary?.invalid ?? 0} />
                  <StatPill label="Kosong" value={preview.summary?.blank ?? 0} />
                </div>
                <div className="rounded-xl border border-neutral-200">
                  <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Contoh Data</div>
                  <div className="max-h-52 overflow-auto p-4 text-xs">
                    <pre className="whitespace-pre-wrap">{JSON.stringify(rowsOf(preview.preview), null, 2)}</pre>
                  </div>
                </div>
                {rowsOf(preview.errors).length > 0 && (
                  <div className="rounded-xl border border-red-100 bg-red-50 p-4 text-xs text-red-700">
                    {rowsOf(preview.errors).slice(0, 3).map((item) => <div key={item.row}>Baris {item.row}: {rowsOf(item.messages).join(', ')}</div>)}
                  </div>
                )}
              </div>
            </div>
          )}
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={queue} onChange={(event) => setQueue(event.target.checked)} className="h-4 w-4 accent-primary-600" />
            Proses lewat queue untuk file besar
          </label>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={loading || !file || !preview} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <UploadIcon className="h-4 w-4" />}
            Commit Import
          </button>
        </div>
      </form>
    </div>
  );
}

function StatPill({ label, value }) {
  return <div className="rounded-xl border border-neutral-200 p-3 text-sm"><div className="text-xs text-neutral-500">{label}</div><div className="mt-1 text-lg font-semibold">{value}</div></div>;
}

function JobStatusStrip({ jobs, onRefresh }) {
  return (
    <PanelCard title="Job Import/Export" action="Refresh" onAction={onRefresh}>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {rowsOf(jobs).slice(0, 6).map((job) => (
          <div key={job.uuid} className="rounded-xl border border-neutral-200 p-4 text-sm">
            <div className="flex items-center justify-between gap-3">
              <div className="font-semibold">{job.type} {job.resource}</div>
              <Badge label={job.status} />
            </div>
            <div className="mt-2 font-mono text-xs text-neutral-500">{String(job.uuid).slice(0, 8)}...</div>
            <progress className="app-progress app-progress-primary mt-3" max="100" value={clampPercent(job.progress)} aria-label={`Progres ${job.type} ${job.resource}: ${clampPercent(job.progress)}%`}>{clampPercent(job.progress)}%</progress>
            <div className="mt-1 text-xs text-neutral-500">{clampPercent(job.progress)}% {job.total_rows ? `- ${job.processed_rows || 0}/${job.total_rows} baris` : ''}</div>
            {job.result && <div className="mt-2 text-xs text-neutral-600">{Object.entries(job.result).map(([key, value]) => `${key}: ${value}`).join(' | ')}</div>}
            {job.error && <div className="mt-2 text-xs text-red-600">{job.error}</div>}
            {job.download_url && <button type="button" onClick={() => downloadAuthenticatedFile(job.download_url, job.file_name || `${job.resource}.dat`)} className="mt-3 inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs"><icons.Download className="h-3.5 w-3.5" /> Download</button>}
          </div>
        ))}
      </div>
    </PanelCard>
  );
}

function DownloadManagerModal({ jobs, onRefresh, onClose }) {
  const [status, setStatus] = useState('all');
  const dialogRef = useDialogLifecycle(onClose);
  const filtered = rowsOf(jobs).filter((job) => status === 'all' || job.status === status);
  const remove = async (job) => {
    if (!window.confirm(`Hapus file job ${String(job.uuid).slice(0, 8)}?`)) return;
    await apiJson(`/web-actions/jobs/${job.uuid}`, { method: 'DELETE' });
    await onRefresh();
  };
  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Hasil unduhan">
      <div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div><h2 className="font-semibold">Download Manager</h2><p className="text-xs text-neutral-500">Hasil export dan laporan import yang tersimpan sementara.</p></div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          <div className="mb-4 flex gap-2"><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm"><option value="all">Semua Status</option>{['queued', 'processing', 'completed', 'failed'].map((item) => <option key={item} value={item}>{item}</option>)}</select><button type="button" onClick={onRefresh} className="rounded-xl border border-neutral-200 px-3 py-2 text-sm">Refresh</button></div>
          <div className="space-y-3">{filtered.map((job) => <div key={job.uuid} className="grid gap-3 rounded-xl border border-neutral-200 p-4 md:grid-cols-[1fr_auto] md:items-center"><div><div className="flex flex-wrap items-center gap-2"><span className="font-semibold">{job.type} {job.resource}</span><Badge label={job.status} /></div><div className="mt-1 text-xs text-neutral-500">{job.file_name || String(job.uuid)} - {job.progress || 0}%</div>{job.expires_at && <div className="mt-1 text-xs text-neutral-400">Kedaluwarsa: {job.expires_at}</div>}</div><div className="flex gap-2">{job.download_url && <button type="button" onClick={() => downloadAuthenticatedFile(job.download_url, job.file_name)} className="rounded-lg border border-neutral-200 p-2" title="Download"><icons.Download className="h-4 w-4" /></button>}<button type="button" onClick={() => remove(job)} className="rounded-lg border border-red-200 p-2 text-red-600" title="Hapus"><icons.Trash2 className="h-4 w-4" /></button></div></div>)}{filtered.length === 0 && <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada file job.</div>}</div>
        </div>
      </div>
    </div>
  );
}

function QueueMonitorModal({ onClose }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const load = async () => { try { setData(await apiJson('/web-actions/queue/monitor')); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => { load(); }, []);
  const retry = async (uuid) => { await apiJson(`/web-actions/queue/failed/${uuid}/retry`, { method: 'POST', body: '{}' }); await load(); };
  const forget = async (uuid) => { await apiJson(`/web-actions/queue/failed/${uuid}`, { method: 'DELETE' }); await load(); };
  const dialogRef = useDialogLifecycle(onClose);
  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Monitor antrean">
      <div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Queue Monitor</h2><p className="text-xs text-neutral-500">Pantau worker, operation job, dan failed jobs.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div>
        <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">{error && <Alert tone="error">{error}</Alert>}<div className="grid gap-3 sm:grid-cols-3">{Object.entries(data?.workers || {}).map(([key, value]) => <StatPill key={key} label={`Worker ${key}`} value={value} />)}</div><div className="grid gap-3 sm:grid-cols-4">{Object.entries(data?.operations || {}).map(([key, value]) => <StatPill key={key} label={`Operation ${key}`} value={value} />)}</div><PanelCard title="Failed Jobs" action="Refresh" onAction={load}><div className="space-y-3">{rowsOf(data?.failed_jobs).map((job) => <div key={job.uuid} className="rounded-xl border border-red-100 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><div className="font-semibold">{job.job}</div><div className="text-xs text-neutral-500">{job.queue} - {job.failed_at}</div></div><div className="flex gap-2"><button type="button" onClick={() => retry(job.uuid)} className="rounded-lg border border-neutral-200 px-3 py-2 text-xs">Retry</button><button type="button" onClick={() => forget(job.uuid)} className="rounded-lg border border-red-200 px-3 py-2 text-xs text-red-600">Hapus</button></div></div><div className="mt-2 text-xs text-red-600">{job.exception}</div></div>)}{rowsOf(data?.failed_jobs).length === 0 && <div className="text-sm text-neutral-500">Tidak ada failed job.</div>}</div></PanelCard></div>
      </div>
    </div>
  );
}

function RestoreBackupModal({ draft, setDraft, loading, onSubmit, onClose }) {
  const dialogRef = useDialogLifecycle(onClose);
  return <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Ajukan restore backup"><form onSubmit={onSubmit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Ajukan Restore Backup</h2><p className="text-xs text-neutral-500">{draft.name || `Backup #${draft.id}`}</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="space-y-3 p-5"><Alert tone="error">Restore memerlukan MFA dan persetujuan owner serta super admin. Ketik RESTORE untuk membuat permintaan.</Alert><textarea aria-label="Alasan restore dan referensi insiden" required minLength={10} value={draft.reason || ''} onChange={(event) => setDraft((current) => ({ ...current, reason: event.target.value }))} className="h-24 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Alasan restore dan referensi insiden" /><input aria-label="Kode MFA restore" required inputMode="numeric" pattern="[0-9]{6}" maxLength={6} value={draft.mfa_code || ''} onChange={(event) => setDraft((current) => ({ ...current, mfa_code: event.target.value.replace(/\D/g, '') }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Kode MFA 6 digit" /><input aria-label="Konfirmasi restore" value={draft.confirmation || ''} onChange={(event) => setDraft((current) => ({ ...current, confirmation: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="RESTORE" /></div><div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4"><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm">Batal</button><button type="submit" disabled={loading || draft.confirmation !== 'RESTORE' || String(draft.mfa_code || '').length !== 6 || String(draft.reason || '').length < 10} className="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Ajukan Pemulihan</button></div></form></div>;
}

function RestoreApprovalModal({ onClose }) {
  const [rows, setRows] = useState([]);
  const [mfaCode, setMfaCode] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const load = async () => { try { const json = await apiJson('/web-actions/backup/restore-requests'); setRows(rowsOf(json.data)); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => {
    load();
    const timer = window.setInterval(load, 5000);
    return () => window.clearInterval(timer);
  }, []);
  const action = async (row, type) => { setError(''); setMessage(''); try { const json = await apiJson(`/web-actions/backup/restore-requests/${row.id}/${type}`, { method: 'POST', body: JSON.stringify({ mfa_code: mfaCode }) }); setMessage(json.message); await load(); } catch (err) { setError(err.message); } };
  const dialogRef = useDialogLifecycle(onClose);
  return <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Persetujuan restore"><div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Persetujuan Restore</h2><p className="text-xs text-neutral-500">Persetujuan berlapis dan eksekusi khusus super admin.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">{message && <Alert tone="success">{message}</Alert>}{error && <Alert tone="error">{error}</Alert>}<input aria-label="Kode MFA super admin" inputMode="numeric" maxLength={6} value={mfaCode} onChange={(event) => setMfaCode(event.target.value.replace(/\D/g, ''))} className="w-full max-w-xs rounded-xl border border-neutral-200 px-3 py-2 text-sm" placeholder="Kode MFA super admin" /><div className="space-y-3">{rows.map((row) => <div key={row.id} className="rounded-xl border border-neutral-200 p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><div className="font-semibold">#{row.id} {row.backup_name || `Backup #${row.backup_id}`}</div><div className="mt-1 text-xs text-neutral-500">{row.requester_name || `User #${row.requested_by}`} - {row.reason}</div><div className="mt-2 flex flex-wrap gap-2"><Badge label={row.status} />{rowsOf(row.approvals).map((approval) => <span key={approval.role} className="rounded-full bg-neutral-100 px-2 py-1 text-xs">{titleFromSlug(approval.role)}</span>)}</div></div><div className="flex gap-2"><button type="button" disabled={mfaCode.length !== 6 || !['pending', 'approved'].includes(row.status)} onClick={() => action(row, 'approve')} className="rounded-lg border border-neutral-200 px-3 py-2 text-xs disabled:opacity-40">Setujui</button><button type="button" disabled={mfaCode.length !== 6 || row.status !== 'approved'} onClick={() => action(row, 'execute')} className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40">Eksekusi</button></div></div></div>)}{rows.length === 0 && <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada permintaan restore.</div>}</div></div></div></div>;
}

function CustomRoleModal({ onClose }) {
  const empty = { id: null, name: '', label: '', description: '', read: [], write: [], actions: [], status: 'active', membership_ids: [] };
  const [data, setData] = useState(null);
  const [draft, setDraft] = useState(empty);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const load = async () => { try { setData(await apiJson('/web-api/permissions/custom-roles')); setError(''); } catch (err) { setError(err.message); } };
  useEffect(() => { load(); }, []);
  const select = (role) => setDraft({ ...role, membership_ids: rowsOf(data?.memberships).filter((item) => String(item.tenant_role_id) === String(role.id)).map((item) => item.id) });
  const toggle = (resource, ability) => setDraft((current) => {
    const enabled = !current[ability].includes(resource);
    const next = { ...current, [ability]: enabled ? [...current[ability], resource] : current[ability].filter((item) => item !== resource) };
    if (ability === 'write' && !enabled) next.actions = current.actions.filter((item) => !item.startsWith(`${resource}.`));
    return next;
  });
  const toggleAction = (ability) => setDraft((current) => ({ ...current, actions: current.actions.includes(ability) ? current.actions.filter((item) => item !== ability) : [...current.actions, ability] }));
  const save = async (event) => { event.preventDefault(); setError(''); try { const mfa_code = requestStepUpMfa('menyimpan custom role'); const endpoint = draft.id ? `/web-api/permissions/custom-roles/${draft.id}` : '/web-api/permissions/custom-roles'; const method = draft.id ? 'PATCH' : 'POST'; const saved = await apiJson(endpoint, { method, body: JSON.stringify({ ...draft, mfa_code }) }); const roleId = draft.id || saved.id; await apiJson(`/web-api/permissions/custom-roles/${roleId}/assign`, { method: 'POST', body: JSON.stringify({ membership_ids: draft.membership_ids, mfa_code }) }); setMessage('Custom role dan assignment berhasil disimpan.'); setDraft(empty); await load(); } catch (err) { setError(err.message); } };
  const remove = async () => { if (!draft.id || !window.confirm('Hapus custom role ini?')) return; try { const mfa_code = requestStepUpMfa('menghapus custom role'); await apiJson(`/web-api/permissions/custom-roles/${draft.id}`, { method: 'DELETE', body: JSON.stringify({ mfa_code }) }); setDraft(empty); await load(); } catch (err) { setError(err.message); } };
  const dialogRef = useDialogLifecycle(onClose);
  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Custom role tenant"><form onSubmit={save} className="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4"><div><h2 className="font-semibold">Custom Role Tenant</h2><p className="text-xs text-neutral-500">Buat role khusus, permission resource, dan assignment pengguna.</p></div><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button></div><div className="grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[260px_1fr]"><aside className="overflow-y-auto border-r border-neutral-200 p-4"><button type="button" onClick={() => setDraft(empty)} className="mb-3 w-full rounded-xl bg-primary-600 px-3 py-2 text-sm font-semibold text-white">Role Baru</button>{rowsOf(data?.roles).map((role) => <button key={role.id} type="button" onClick={() => select(role)} className={`mb-2 w-full rounded-xl border px-3 py-2 text-left text-sm ${draft.id === role.id ? 'border-primary-600 bg-primary-50' : 'border-neutral-200'}`}><div className="font-semibold">{role.label}</div><div className="text-xs text-neutral-500">{role.name}</div></button>)}</aside><div className="min-h-0 space-y-4 overflow-y-auto p-5">{message && <Alert tone="success">{message}</Alert>}{error && <Alert tone="error">{error}</Alert>}<div className="grid gap-3 md:grid-cols-2"><label className="text-sm"><span className="mb-1 block font-medium">Kode Role</span><input required value={draft.name} onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '_') }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2" /></label><label className="text-sm"><span className="mb-1 block font-medium">Nama Role</span><input required value={draft.label} onChange={(event) => setDraft((current) => ({ ...current, label: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2" /></label><label className="text-sm md:col-span-2"><span className="mb-1 block font-medium">Deskripsi</span><textarea value={draft.description || ''} onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))} className="h-20 w-full rounded-xl border border-neutral-200 px-3 py-2" /></label></div><div className="overflow-x-auto rounded-xl border border-neutral-200"><table className="w-full text-sm"><thead><tr className="border-b bg-neutral-50 text-left text-xs text-neutral-500"><th className="px-3 py-2">Resource</th><th className="px-3 py-2">Baca</th><th className="px-3 py-2">Kelola</th></tr></thead><tbody>{rowsOf(data?.resources).map((resource) => <tr key={resource} className="border-b border-neutral-100"><td className="px-3 py-2 font-medium">{titleFromSlug(resource)}</td><td className="px-3 py-2"><input type="checkbox" checked={draft.read.includes(resource)} onChange={() => toggle(resource, 'read')} /></td><td className="px-3 py-2"><input type="checkbox" checked={draft.write.includes(resource)} onChange={() => toggle(resource, 'write')} /></td></tr>)}</tbody></table></div><div className="rounded-xl border border-neutral-200"><div className="border-b px-4 py-3 text-sm font-semibold">Aksi Workflow</div><div className="grid gap-2 p-4 md:grid-cols-2">{rowsOf(data?.workflow_abilities).map((ability) => { const resource = ability.slice(0, ability.lastIndexOf('.')); return <label key={ability} className="flex items-center gap-2 rounded-lg border border-neutral-100 p-2 text-sm"><input type="checkbox" checked={draft.actions.includes(ability)} disabled={!draft.write.includes(resource)} onChange={() => toggleAction(ability)} /><span>{titleFromSlug(ability.replace('.', '-'))}</span></label>; })}</div></div><div className="rounded-xl border border-neutral-200"><div className="border-b px-4 py-3 text-sm font-semibold">Assignment Pengguna</div><div className="grid gap-2 p-4 md:grid-cols-2">{rowsOf(data?.memberships).map((member) => <label key={member.id} className="flex items-center gap-2 rounded-lg border border-neutral-100 p-2 text-sm"><input type="checkbox" checked={draft.membership_ids.includes(member.id)} onChange={() => setDraft((current) => ({ ...current, membership_ids: current.membership_ids.includes(member.id) ? current.membership_ids.filter((id) => id !== member.id) : [...current.membership_ids, member.id] }))} /><span>{member.user_name} <span className="text-xs text-neutral-500">({member.base_role})</span></span></label>)}</div></div></div></div><div className="flex justify-between border-t border-neutral-200 bg-neutral-50 px-5 py-4"><div>{draft.id && <button type="button" onClick={remove} className="rounded-xl border border-red-200 px-4 py-2 text-sm text-red-600">Hapus Role</button>}</div><div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm">Tutup</button><button type="submit" className="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Simpan Role</button></div></div></form></div>
  );
}

function BulkModal({ count, canDelete, loading, onSubmit, onClose }) {
  const [action, setAction] = useState('status');
  const [status, setStatus] = useState('active');
  const dialogRef = useDialogLifecycle(onClose);

  const submit = (event) => {
    event.preventDefault();
    onSubmit({ action, status });
  };

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Aksi massal">
      <form onSubmit={submit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Aksi Massal</h2>
            <p className="text-xs text-neutral-500">{count} data terpilih. Aksi tetap dibatasi scope role aktif.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="grid gap-3 p-5">
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Aksi</span>
            <select value={action} onChange={(event) => setAction(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
              <option value="status">Ubah Status</option>
              {canDelete && <option value="delete">Hapus Data</option>}
            </select>
          </label>
          {action === 'status' && (
            <label className="block text-sm">
              <span className="mb-1 block font-medium">Status Baru</span>
              <select value={status} onChange={(event) => setStatus(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
                {statusOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>
          )}
          {action === 'delete' && <Alert tone="error">Data terpilih akan dihapus atau diarsipkan bila resource mendukung soft delete.</Alert>}
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={loading || count < 1} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {loading ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Terapkan
          </button>
        </div>
      </form>
    </div>
  );
}

function PermissionMatrixModal({ onClose }) {
  const [matrix, setMatrix] = useState(null);
  const [role, setRole] = useState('');
  const [read, setRead] = useState([]);
  const [write, setWrite] = useState([]);
  const [actions, setActions] = useState([]);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const dialogRef = useDialogLifecycle(onClose);

  useEffect(() => {
    let mounted = true;
    apiJson('/web-api/permissions/matrix')
      .then((json) => {
        if (!mounted) return;
        setMatrix(json);
        const firstRole = rowsOf(json.roles).find((item) => !['super_admin', 'owner_pesantren'].includes(item.name))?.name || '';
        setRole(firstRole);
      })
      .catch((err) => setError(err.message));
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    if (!matrix || !role) return;
    setRead(rowsOf(matrix.matrix?.[role]?.read));
    setWrite(rowsOf(matrix.matrix?.[role]?.write));
    setActions(rowsOf(matrix.matrix?.[role]?.actions));
  }, [matrix, role]);

  const toggle = (resource, ability) => {
    const setter = ability === 'write' ? setWrite : setRead;
    setter((current) => {
      const enabled = !current.includes(resource);
      if (ability === 'write' && !enabled) setActions((items) => items.filter((item) => !item.startsWith(`${resource}.`)));
      return enabled ? [...current, resource] : current.filter((item) => item !== resource);
    });
  };
  const toggleAction = (ability) => setActions((current) => current.includes(ability) ? current.filter((item) => item !== ability) : [...current, ability]);

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setMessage('');
    setError('');
    try {
      await apiJson('/web-api/permissions/matrix', {
        method: 'PATCH',
        body: JSON.stringify({ role, read, write, actions, mfa_code: requestStepUpMfa('mengubah permission') }),
      });
      const fresh = await apiJson('/web-api/permissions/matrix');
      setMatrix(fresh);
      setMessage('Permission berhasil diperbarui.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Matriks izin">
      <form onSubmit={save} className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Permission Matrix</h2>
            <p className="text-xs text-neutral-500">Atur akses baca dan kelola per role. Scope mengikuti {matrix?.source || 'tenant/global'}.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          {message && <Alert tone="success">{message}</Alert>}
          {error && <Alert tone="error">{error}</Alert>}
          {matrix && !matrix.editable && <Alert tone="error">Pilih tenant terlebih dahulu. Permission global hanya dapat dilihat dan tidak dapat diubah.</Alert>}
          <label className="block max-w-sm text-sm">
            <span className="mb-1 block font-medium">Role</span>
            <select value={role} onChange={(event) => setRole(event.target.value)} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm">
              {rowsOf(matrix?.roles).filter((item) => item.name !== 'super_admin').map((item) => <option key={item.name} value={item.name}>{item.label}</option>)}
            </select>
          </label>
          <div className="overflow-x-auto rounded-xl border border-neutral-200">
            <table className="w-full text-left text-sm">
              <thead><tr className="border-b border-neutral-200 bg-neutral-50 text-xs text-neutral-500"><th className="px-3 py-3">Resource</th><th className="px-3 py-3">Baca</th><th className="px-3 py-3">Kelola</th></tr></thead>
              <tbody>
                {rowsOf(matrix?.resources).map((resource) => (
                  <tr key={resource} className="border-b border-neutral-100">
                    <td className="px-3 py-2 font-medium">{titleFromSlug(resource)}</td>
                    <td className="px-3 py-2"><input type="checkbox" checked={read.includes(resource)} disabled={!matrix?.editable} onChange={() => toggle(resource, 'read')} className="h-4 w-4 accent-primary-600 disabled:cursor-not-allowed disabled:opacity-50" /></td>
                    <td className="px-3 py-2"><input type="checkbox" checked={write.includes(resource)} disabled={!matrix?.editable} onChange={() => toggle(resource, 'write')} className="h-4 w-4 accent-primary-600 disabled:cursor-not-allowed disabled:opacity-50" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="rounded-xl border border-neutral-200">
            <div className="border-b px-4 py-3 text-sm font-semibold">Aksi Workflow</div>
            <div className="grid gap-2 p-4 md:grid-cols-2">
              {rowsOf(matrix?.workflow_abilities).map((ability) => {
                const resource = ability.slice(0, ability.lastIndexOf('.'));
                return <label key={ability} className="flex items-center gap-2 rounded-lg border border-neutral-100 p-2 text-sm"><input type="checkbox" checked={actions.includes(ability)} disabled={!matrix?.editable || !write.includes(resource)} onChange={() => toggleAction(ability)} className="h-4 w-4 accent-primary-600 disabled:cursor-not-allowed disabled:opacity-50" /><span>{titleFromSlug(ability.replace('.', '-'))}</span></label>;
              })}
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Tutup</button>
          <button type="submit" disabled={saving || !role || !matrix?.editable} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Permission
          </button>
        </div>
      </form>
    </div>
  );
}

function PdfTemplateModal({ shared, onClose, onSaved }) {
  const templates = [
    { key: 'pdf.receipt_template', label: 'Kwitansi' },
    { key: 'pdf.letter_template', label: 'Surat' },
    { key: 'pdf.report_template', label: 'Laporan' },
  ];
  const [active, setActive] = useState(templates[0].key);
  const [settings, setSettings] = useState([]);
  const [draft, setDraft] = useState({ title: '', header: '', footer: '', accent_color: '#127A4F', logo_text: 'Pesantrenku' });
  const [loadingSettings, setLoadingSettings] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const dialogRef = useDialogLifecycle(onClose);

  useEffect(() => {
    let mounted = true;
    apiJson('/web-api/system-settings?per_page=100&search=pdf.')
      .then((json) => { if (mounted) setSettings(rowsOf(json.data)); })
      .catch(() => { if (mounted) setSettings(rowsOf(shared.settings)); })
      .finally(() => { if (mounted) setLoadingSettings(false); });
    return () => { mounted = false; };
  }, [shared.settings]);

  useEffect(() => {
    const row = settings.find((item) => item.key === active || item.kunci === active);
    const value = parseJsonValue(row?.value ?? row?.nilai) || {};
    setDraft({
      title: value.title || (active.includes('receipt') ? 'Kwitansi Pembayaran' : active.includes('letter') ? 'Surat Pesantren' : 'Laporan Pesantren'),
      header: value.header || 'Pondok Pesantren',
      footer: value.footer || 'Dokumen dibuat otomatis oleh Pesantrenku.',
      accent_color: value.accent_color || '#127A4F',
      logo_text: value.logo_text || 'Pesantrenku',
    });
  }, [active, settings]);

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    const row = settings.find((item) => item.key === active || item.kunci === active);
    const payload = { key: active, value: JSON.stringify(draft) };
    try {
      if (row?.id) {
        await apiJson(`/web-api/system-settings/${row.id}`, { method: 'PATCH', body: JSON.stringify(payload) });
      } else {
        await apiJson('/web-api/system-settings', { method: 'POST', body: JSON.stringify(payload) });
      }
      onSaved?.();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Template PDF tenant">
      <form onSubmit={save} className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Template PDF Tenant</h2>
            <p className="text-xs text-neutral-500">Atur tampilan dasar kwitansi, surat, dan laporan PDF.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {error && <Alert tone="error">{error}</Alert>}
          <div className="mb-4 flex flex-wrap gap-2">
            {templates.map((item) => <button key={item.key} type="button" onClick={() => setActive(item.key)} className={`rounded-xl border px-4 py-2 text-sm ${active === item.key ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-neutral-200'}`}>{item.label}</button>)}
          </div>
          <div className="grid gap-5 lg:grid-cols-2">
            <div className="grid gap-3">
              {['title', 'header', 'footer', 'logo_text'].map((field) => (
                <label key={field} className="block text-sm">
                  <span className="mb-1 block font-medium">{columnTitle(field)}</span>
                  {field === 'footer' ? (
                    <textarea value={draft[field]} onChange={(event) => setDraft((current) => ({ ...current, [field]: event.target.value }))} className="h-24 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" />
                  ) : (
                    <input value={draft[field]} onChange={(event) => setDraft((current) => ({ ...current, [field]: event.target.value }))} className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" />
                  )}
                </label>
              ))}
              <label className="block text-sm">
                <span className="mb-1 block font-medium">Warna Aksen</span>
                <input type="color" value={draft.accent_color} onChange={(event) => setDraft((current) => ({ ...current, accent_color: event.target.value }))} className="h-11 w-24 rounded-xl border border-neutral-200 p-1" />
              </label>
            </div>
            <div className="rounded-xl border border-neutral-200 bg-neutral-50 p-5">
              <div className="rounded-lg bg-white p-6 shadow-sm">
                <div className="flex items-center justify-between border-b border-neutral-200 pb-4">
                  <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-primary-700">{draft.logo_text}</div>
                    <div className="mt-1 text-xl font-semibold">{draft.title}</div>
                  </div>
                  <svg className="h-12 w-12" viewBox="0 0 48 48" width="48" height="48" role="img" aria-label={`Warna aksen ${draft.accent_color}`}>
                    <rect width="48" height="48" rx="8" fill={draft.accent_color} />
                  </svg>
                </div>
                <svg className="mt-3 h-1 w-full" viewBox="0 0 100 4" width="100%" height="4" aria-hidden="true" preserveAspectRatio="none"><rect width="100" height="4" rx="2" fill={draft.accent_color} /></svg>
                <div className="mt-5 text-sm font-medium">{draft.header}</div>
                <div className="mt-4 space-y-2 text-xs text-neutral-500">
                  <div>Santri: Contoh Nama Santri</div>
                  <div>Status: Lunas / Disetujui</div>
                  <div>Tanggal: {new Date().toLocaleDateString('id-ID')}</div>
                </div>
                <div className="mt-8 border-t pt-4 text-xs text-neutral-500">{draft.footer}</div>
              </div>
            </div>
          </div>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={saving || loadingSettings} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {(saving || loadingSettings) ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Template
          </button>
        </div>
      </form>
    </div>
  );
}

function CrudModal({ meta, shared, formData, setFormData, editing, mode, saving, formErrors = {}, onSubmit, onClose, timeline = [], timelineLoading = false }) {
  const viewOnly = mode === 'view';
  const title = viewOnly ? `Detail ${meta.title}` : `${editing && mode === 'edit' ? 'Edit' : 'Tambah'} ${meta.title}`;
  const dialogRef = useDialogLifecycle(onClose);

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-950/45 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label={title}>
      <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-white/70 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.24)]">
        <div className="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
          <div>
            <h2 className="font-semibold tracking-tight">{title}</h2>
            <p className="text-xs text-neutral-500">{viewOnly ? 'Baca data tanpa mengubah isi.' : 'Isi data lalu simpan. Data langsung tersambung ke API.'}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 bg-white p-2 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
          <div className="grid gap-4 overflow-y-auto p-5 md:grid-cols-2">
            {meta.fields.map((field) => <FieldInput key={field.name} field={field} shared={shared} value={formData[field.name]} disabled={viewOnly || (mode === 'edit' && ['permits', 'payments', 'medical-queues'].includes(meta.resource) && field.name === 'status')} error={formErrors[field.name]} onChange={(value) => setFormData((current) => ({ ...current, [field.name]: value }))} />)}
            {viewOnly && <div className="md:col-span-2"><TimelineList timeline={timeline} loading={timelineLoading} shared={shared} /></div>}
          </div>
          <div className="flex flex-col gap-2 border-t border-neutral-100 bg-neutral-50/80 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" onClick={onClose} className="inline-flex items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm font-medium shadow-sm transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.X className="h-4 w-4" /> {viewOnly ? 'Tutup' : 'Batal'}</button>
            {!viewOnly && (
              <button type="submit" disabled={saving} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
                {editing && mode === 'edit' ? 'Simpan Perubahan' : (meta.createLabel || `Tambah ${meta.title}`)}
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}

function WorkflowModal({ draft, note, setNote, saving, onSubmit, onClose }) {
  const { row, action } = draft;
  const required = action.requiresNote;
  const title = `${action.label} ${row.name || row.title || row.invoice_number || row.queue_number || `#${row.id}`}`;
  const dialogRef = useDialogLifecycle(onClose);

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label={title}>
      <form onSubmit={onSubmit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">{title}</h2>
            <p className="text-xs text-neutral-500">{required ? 'Catatan wajib diisi untuk aksi ini.' : 'Catatan opsional akan masuk ke audit timeline.'}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="p-5">
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Catatan</span>
            <textarea required={required} value={note} onChange={(event) => setNote(event.target.value)} className="h-28 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-600" placeholder={required ? 'Tuliskan alasan penolakan atau pembatalan.' : 'Tambahkan catatan bila diperlukan.'} />
          </label>
        </div>
        <div className="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Batal</button>
          <button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {saving ? <icons.Loader2 className="h-4 w-4 animate-spin" /> : <icons.Save className="h-4 w-4" />}
            Simpan Status
          </button>
        </div>
      </form>
    </div>
  );
}

function TimelineList({ timeline, loading, shared }) {
  if (loading) {
    return (
      <div className="rounded-xl border border-neutral-200">
        <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
        <div className="p-4 text-sm text-neutral-500">Memuat timeline...</div>
      </div>
    );
  }

  if (!timeline.length) {
    return (
      <div className="rounded-xl border border-neutral-200">
        <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
        <div className="p-4 text-sm text-neutral-500">Belum ada riwayat perubahan.</div>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-neutral-200">
      <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Timeline Perubahan</div>
      <div className="max-h-64 overflow-y-auto p-4">
        {timeline.map((item) => (
          <div key={item.id} className="border-l-2 border-primary-100 pb-4 pl-4 last:pb-0">
            <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
              <span>{titleFromSlug(String(item.action || '').toLowerCase())}</span>
              {item.to_status && <Badge label={item.to_status} />}
            </div>
            <div className="mt-1 text-xs text-neutral-500">{item.actor} - {item.created_at}</div>
            {item.note && <div className="mt-2 rounded-lg bg-neutral-50 px-3 py-2 text-sm">{item.note}</div>}
            {rowsOf(item.changes).length > 0 && (
              <div className="mt-2 space-y-1 text-xs text-neutral-600">
                {rowsOf(item.changes).slice(0, 4).map((change) => (
                  <div key={`${item.id}-${change.field}`}>{change.label || columnTitle(change.field)}: {String(change.before_label ?? displayCellValue(change.field, change.before, {}, shared, inferredFieldForColumn(change.field)) ?? '-')}{' -> '}{String(change.after_label ?? displayCellValue(change.field, change.after, {}, shared, inferredFieldForColumn(change.field)) ?? '-')}</div>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

function AuditLogModal({ row, shared, onClose }) {
  const metadata = parseJsonValue(row.metadata) || {};
  const before = metadata.before || {};
  const after = metadata.after || {};
  const fields = Array.from(new Set([...Object.keys(before || {}), ...Object.keys(after || {})])).filter((field) => field !== 'updated_at');
  const changes = fields
    .map((field) => ({ field, before: before?.[field], after: after?.[field] }))
    .filter((change) => JSON.stringify(change.before ?? null) !== JSON.stringify(change.after ?? null));
  const dialogRef = useDialogLifecycle(onClose);

  return (
    <div ref={dialogRef} className="fixed inset-0 z-50 grid place-items-center bg-neutral-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Detail audit log">
      <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h2 className="font-semibold">Detail Audit Log</h2>
            <p className="text-xs text-neutral-500">{row.aktivitas || row.action} - {row.waktu || row.created_at}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-neutral-200 p-2 hover:bg-neutral-50" aria-label="Tutup modal"><icons.X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
          <div className="grid gap-3 md:grid-cols-2">
            <StatPill label="Modul" value={row.modul || row.module || '-'} />
            <StatPill label="Pengguna" value={row.pengguna || row.actor || 'Sistem'} />
          </div>
          {metadata.note && <Alert tone="info">{metadata.note}</Alert>}
          <div className="rounded-xl border border-neutral-200">
            <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Diff Perubahan</div>
            <div className="max-h-80 overflow-y-auto p-4">
              {changes.length > 0 ? changes.map((change) => (
                <div key={change.field} className="border-b border-neutral-100 py-2 text-sm last:border-0">
                  <div className="font-medium">{columnTitle(change.field)}</div>
                  <div className="mt-1 text-xs text-neutral-600">
                    {String(displayCellValue(change.field, change.before, before, shared, inferredFieldForColumn(change.field)) ?? '-')} {' -> '}
                    {String(displayCellValue(change.field, change.after, after, shared, inferredFieldForColumn(change.field)) ?? '-')}
                  </div>
                </div>
              )) : <div className="text-sm text-neutral-500">Tidak ada diff field tersimpan.</div>}
            </div>
          </div>
          <div className="rounded-xl border border-neutral-200">
            <div className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold">Metadata Mentah</div>
            <pre className="max-h-52 overflow-auto p-4 text-xs text-neutral-600">{JSON.stringify(metadata, null, 2)}</pre>
          </div>
        </div>
        <div className="flex justify-end border-t border-neutral-200 bg-neutral-50 px-5 py-4">
          <button type="button" onClick={onClose} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-sm"><icons.X className="h-4 w-4" /> Tutup</button>
        </div>
      </div>
    </div>
  );
}

function FieldInput({ field, shared, value, disabled = false, error, onChange }) {
  const label = columnTitle(field.name);
  const options = optionsFor(field, shared);
  const errorText = Array.isArray(error) ? error[0] : error;
  const inputId = `field-${slug(field.name)}`;
  const errorId = `${inputId}-error`;
  const accessibilityProps = {
    id: inputId,
    'aria-invalid': Boolean(errorText),
    'aria-describedby': errorText ? errorId : undefined,
  };

  if (field.type === 'textarea') {
    return <label htmlFor={inputId} className="block text-sm md:col-span-2"><span className="mb-1.5 block font-semibold text-neutral-700">{label}</span><textarea {...accessibilityProps} disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`h-28 w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40 focus:ring-red-100' : 'border-neutral-200'}`} />{errorText && <span id={errorId} role="alert" className="mt-1.5 block text-xs font-medium text-red-600">{errorText}</span>}</label>;
  }

  if (field.type === 'boolean') {
    return <label htmlFor={inputId} className={`flex items-center gap-3 rounded-xl border bg-white px-3 py-3 text-sm shadow-sm transition ${errorText ? 'border-red-300 bg-red-50/40' : 'border-neutral-200 hover:border-primary-200'}`}><input {...accessibilityProps} type="checkbox" disabled={disabled} checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} className="h-4 w-4 rounded accent-primary-600 disabled:opacity-50" /><span className="font-semibold text-neutral-700">{label}</span>{errorText && <span id={errorId} role="alert" className="text-xs font-medium text-red-600">{errorText}</span>}</label>;
  }

  if (field.type?.startsWith('select')) {
    return (
      <label htmlFor={inputId} className="block text-sm">
        <span className="mb-1.5 block font-semibold text-neutral-700">{label}</span>
        <select {...accessibilityProps} disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40 focus:ring-red-100' : 'border-neutral-200'}`}>
          <option value="">Pilih {label}</option>
          {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        {errorText && <span id={errorId} role="alert" className="mt-1.5 block text-xs font-medium text-red-600">{errorText}</span>}
      </label>
    );
  }

  return <label htmlFor={inputId} className="block text-sm"><span className="mb-1.5 block font-semibold text-neutral-700">{label}</span><input {...accessibilityProps} disabled={disabled} type={field.type || 'text'} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`w-full rounded-xl border bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70 disabled:bg-neutral-50 disabled:text-neutral-500 ${errorText ? 'border-red-300 bg-red-50/40 focus:ring-red-100' : 'border-neutral-200'}`} />{errorText && <span id={errorId} role="alert" className="mt-1.5 block text-xs font-medium text-red-600">{errorText}</span>}</label>;
}
function useDialogLifecycle(onClose, open = true) {
  const dialogRef = useRef(null);
  const onCloseRef = useRef(onClose);

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) return undefined;
    const dialog = dialogRef.current;
    const previousFocus = document.activeElement;
    openDialogCount += 1;
    document.body.classList.add('app-scroll-locked');

    const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const focusFirst = () => (dialog?.querySelector('[data-dialog-initial-focus]') || dialog?.querySelector(focusableSelector))?.focus();
    const frame = window.requestAnimationFrame(focusFirst);
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onCloseRef.current?.();
        return;
      }
      if (event.key !== 'Tab' || !dialog) return;
      const focusable = Array.from(dialog.querySelectorAll(focusableSelector)).filter((element) => element.offsetParent !== null);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => {
      window.cancelAnimationFrame(frame);
      document.removeEventListener('keydown', handleKeyDown);
      openDialogCount = Math.max(0, openDialogCount - 1);
      if (openDialogCount === 0) document.body.classList.remove('app-scroll-locked');
      if (previousFocus instanceof HTMLElement) previousFocus.focus();
    };
  }, [open]);

  return dialogRef;
}

export {
  PanelCard, StatePanel, isVisibleTableColumn, columnTitle, renderCell, displayCellValue,
  relationLabelFor, inferredFieldForColumn, relationFallbackLabel, Badge, statusLabel, Alert,
  PaginationControls, ImportModal, StatPill, JobStatusStrip, DownloadManagerModal, QueueMonitorModal,
  RestoreBackupModal, RestoreApprovalModal, CustomRoleModal, BulkModal, PermissionMatrixModal,
  PdfTemplateModal, CrudModal, WorkflowModal, TimelineList, AuditLogModal, FieldInput, useDialogLifecycle,
};
