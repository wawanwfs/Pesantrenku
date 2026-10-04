import React, { useEffect, useMemo, useState } from 'react';
import { router, icons } from '../../lib/deps.jsx';
import { exportRowsToCsv, rowsOf } from './appSupport.jsx';
import { Alert, AuditLogModal, PanelCard } from './AppDialogs.jsx';
import { Header, ProgressList, StatCard, Table } from './Widgets.jsx';

export function SystemModule({ meta, shared }) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [message, setMessage] = useState('');
  const [detail, setDetail] = useState(null);
  const [health, setHealth] = useState(null);
  const rows = useMemo(() => {
    const term = search.toLowerCase();
    return rowsOf(meta.rows).filter((row) => {
      const matchesSearch = !term || Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(term));
      const rowStatus = String(row.status || row.level || '').toLowerCase();
      return matchesSearch && (!status || rowStatus.includes(status));
    });
  }, [meta.rows, search, status]);

  useEffect(() => {
    fetch('/health', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error('Health endpoint gagal')))
      .then(setHealth)
      .catch(() => setHealth({ status: 'error', database: 'error', storage: 'error', queue_table: 'missing' }));
  }, []);

  const exportRows = () => {
    exportRowsToCsv(rows, meta.title);
    setMessage('Export CSV berhasil disiapkan.');
  };

  return (
    <div className="space-y-5">
      <Header title={meta.title} subtitle={meta.subtitle} />
      {message && <Alert tone="success">{message}</Alert>}
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {meta.stats.map((item) => <StatCard key={item.label} item={item} />)}
      </section>
      <PanelCard title={meta.tableTitle}>
        <div className="system-toolbar mb-4 flex flex-col gap-3 border-b border-neutral-200 pb-4 md:flex-row md:items-center md:justify-between">
          <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row">
            <div className="relative max-w-xl flex-1"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input aria-label={`Cari ${meta.title}`} value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 py-2.5 pl-10 pr-3 text-sm outline-none focus:border-primary-600" placeholder={meta.searchPlaceholder} /></div>
            <select aria-label={`Filter status ${meta.title}`} value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-neutral-200 px-3 text-sm"><option value="">Semua Status</option><option value="berhasil">Berhasil</option><option value="peringatan">Peringatan</option><option value="diblokir">Diblokir</option><option value="error">Error</option></select>
          </div>
          <div className="flex flex-wrap gap-2">
            <button onClick={exportRows} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.Download className="h-4 w-4" /> Export CSV</button>
            <button onClick={() => router.reload({ only: ['shared', 'dashboard'], preserveScroll: true })} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-4 py-2 text-sm"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
          </div>
        </div>
        <Table rows={rows} columns={meta.columns} shared={shared} onView={meta.audit ? setDetail : null} />
      </PanelCard>
      {detail && <AuditLogModal row={detail} shared={shared} onClose={() => setDetail(null)} />}
      <section className="grid gap-5 xl:grid-cols-3">
        <PanelCard title="Login Monitoring">
          <Table rows={shared.loginMonitoring} columns={['waktu', 'pengguna', 'role', 'ip', 'status']} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Error Log Terbaru">
          <Table rows={shared.errorLogs} columns={['waktu', 'level', 'modul', 'pesan', 'status']} shared={shared} compact />
        </PanelCard>
        <PanelCard title="Kesehatan Sistem">
          {health ? <ProgressList items={[
            { name: 'Web Server', value: health.status === 'ok' ? 100 : 0 },
            { name: 'Database', value: health.database === 'ok' ? 100 : 0 },
            { name: 'Queue Table', value: health.queue_table === 'ok' ? 100 : 0 },
            { name: 'Storage', value: health.storage === 'ok' ? 100 : 0 },
          ]} /> : <div className="text-sm text-neutral-500">Memeriksa kesehatan layanan...</div>}
        </PanelCard>
      </section>
    </div>
  );
}
