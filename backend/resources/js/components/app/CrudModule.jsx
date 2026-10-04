import React, { Suspense, useEffect, useRef, useState } from 'react';
import { router, icons } from '../../lib/deps.jsx';
import { apiJson, apiUpload, defaultFormData, downloadAuthenticatedFile, exportResource, formDataFromRow, normalizePayload, requestStepUpMfa, rowsOf, statusOptions } from './appSupport.jsx';
import { Alert, BulkModal, CrudModal, CustomRoleModal, DownloadManagerModal, ImportModal, JobStatusStrip, PanelCard, PaginationControls, PdfTemplateModal, PermissionMatrixModal, QueueMonitorModal, RestoreApprovalModal, RestoreBackupModal, statusLabel, WorkflowModal } from './AppDialogs.jsx';
import { ChartLoading, DensityToggle, Header, Table } from './Widgets.jsx';
import { AnalyticsPanel } from './lazyCharts.jsx';
import { workflowActionsFor } from './moduleMeta.jsx';

export function CrudModule({ meta, shared }) {
  const [rows, setRows] = useState(rowsOf(meta.rows));
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('Semua Status');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [classFilter, setClassFilter] = useState('');
  const [studentFilter, setStudentFilter] = useState('');
  const [formData, setFormData] = useState(() => defaultFormData(meta, shared));
  const [editing, setEditing] = useState(null);
  const [modalMode, setModalMode] = useState(null);
  const [timeline, setTimeline] = useState([]);
  const [timelineLoading, setTimelineLoading] = useState(false);
  const [analytics, setAnalytics] = useState(null);
  const [workflowDraft, setWorkflowDraft] = useState(null);
  const [workflowNote, setWorkflowNote] = useState('');
  const [importOpen, setImportOpen] = useState(false);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [permissionOpen, setPermissionOpen] = useState(false);
  const [customRoleOpen, setCustomRoleOpen] = useState(false);
  const [pdfTemplateOpen, setPdfTemplateOpen] = useState(false);
  const [downloadManagerOpen, setDownloadManagerOpen] = useState(false);
  const [queueMonitorOpen, setQueueMonitorOpen] = useState(false);
  const [restoreDraft, setRestoreDraft] = useState(null);
  const [restoreApprovalOpen, setRestoreApprovalOpen] = useState(false);
  const [jobs, setJobs] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: rowsOf(meta.rows).length, per_page: 20 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [formErrors, setFormErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const requestSequence = useRef(0);
  const filterFields = meta.fields || [];
  const supportsStatusFilter = filterFields.some((field) => field.name === 'status');
  const supportsDateFilter = filterFields.some((field) => ['date', 'datetime-local'].includes(field.type));
  const supportsClassFilter = filterFields.some((field) => field.name === 'class_id');
  const supportsStudentFilter = filterFields.some((field) => field.name === 'student_id');
  const workflowResource = ['permits', 'payments', 'medical-queues'].includes(meta.resource);
  const bulkProtectedResource = ['users', 'pesantrens', 'memberships', 'roles', 'plans', 'subscriptions', 'notifications', 'audit-logs', 'backups', 'permits', 'payments', 'medical-queues'].includes(meta.resource);
  const canCreate = meta.canCreate !== false && Boolean(meta.resource);
  const canEdit = meta.canEdit !== false && Boolean(meta.resource);
  const canDelete = meta.canDelete !== false && Boolean(meta.resource) && !workflowResource;
  const canExport = meta.canExport !== false && Boolean(meta.resource);
  const canImport = canCreate;
  const canBulk = (canEdit || canDelete) && Boolean(meta.resource) && !bulkProtectedResource;
  const canView = meta.canView !== false;
  const canEditPermissions = ['super-admin', 'owner'].includes(meta.panel) && ['users', 'memberships'].includes(meta.resource);
  const canMonitorQueue = meta.panel === 'super-admin' && ['backups', 'system-settings'].includes(meta.resource);
  const canEditPdfTemplates = meta.resource === 'system-settings' && canEdit;
  const ImportIcon = icons.Upload || icons.Download;
  const BulkIcon = icons.ListChecks || icons.CheckSquare || icons.CircleCheck;

  useEffect(() => {
    setRows(rowsOf(meta.rows));
    setSearch('');
    setStatus('Semua Status');
    setDateFrom('');
    setDateTo('');
    setClassFilter('');
    setStudentFilter('');
    setEditing(null);
    setModalMode(null);
    setTimeline([]);
    setAnalytics(null);
    setWorkflowDraft(null);
    setWorkflowNote('');
    setImportOpen(false);
    setBulkOpen(false);
    setRestoreApprovalOpen(false);
    setPermissionOpen(false);
    setCustomRoleOpen(false);
    setPdfTemplateOpen(false);
    setDownloadManagerOpen(false);
    setQueueMonitorOpen(false);
    setRestoreDraft(null);
    setJobs([]);
    setSelectedIds([]);
    setPagination({ current_page: 1, last_page: 1, total: rowsOf(meta.rows).length, per_page: 20 });
    setPage(1);
    setFormErrors({});
    setFormData(defaultFormData(meta, shared));
    setMessage('');
    setError('');
  }, [meta.resource, meta.title]);

  useEffect(() => {
    const closeToolbarMenus = (event) => {
      if (event.type === 'mousedown' && event.target.closest?.('.crud-toolbar')) return;
      if (event.type === 'keydown' && event.key !== 'Escape') return;
      document.querySelectorAll('.crud-toolbar details[open]').forEach((detail) => detail.removeAttribute('open'));
    };
    document.addEventListener('mousedown', closeToolbarMenus);
    document.addEventListener('keydown', closeToolbarMenus);
    return () => {
      document.removeEventListener('mousedown', closeToolbarMenus);
      document.removeEventListener('keydown', closeToolbarMenus);
    };
  }, []);

  useEffect(() => {
    setPage(1);
  }, [search, status, dateFrom, dateTo, classFilter, studentFilter, perPage, meta.resource]);

  useEffect(() => {
    if (!meta.resource) return;
    const timer = window.setTimeout(() => loadRows(search), 300);
    return () => window.clearTimeout(timer);
  }, [search, status, dateFrom, dateTo, classFilter, studentFilter, page, perPage, meta.resource]);

  const buildParams = (query = search) => {
    const params = new URLSearchParams({ per_page: String(perPage), page: String(page) });
    if (query) params.set('search', query);
    if (status !== 'Semua Status') params.set('status', status);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    if (classFilter) params.set('class_id', classFilter);
    if (studentFilter) params.set('student_id', studentFilter);
    return params;
  };

  const loadRows = async (query = search) => {
    if (!meta.resource) return;
    const sequence = ++requestSequence.current;
    setLoading(true);
    setError('');
    const params = buildParams(query);
    try {
      const [json, report] = await Promise.all([
        apiJson(`/web-api/${meta.resource}?${params.toString()}`),
        apiJson(`/web-api/${meta.resource}/analytics?${params.toString()}`).catch(() => null),
      ]);
      if (sequence !== requestSequence.current) return;
      setRows(rowsOf(json.data));
      setSelectedIds([]);
      setPagination({
        current_page: json.current_page || 1,
        last_page: json.last_page || 1,
        total: json.total ?? rowsOf(json.data).length,
        per_page: json.per_page || perPage,
      });
      setAnalytics(report);
    } catch (err) {
      if (sequence !== requestSequence.current) return;
      setError(err.message);
    } finally {
      if (sequence === requestSequence.current) setLoading(false);
    }
  };

  const backupProcessing = meta.resource === 'backups' && rows.some((row) => ['queued', 'processing', 'restoring'].includes(row.status));

  useEffect(() => {
    if (!backupProcessing) return undefined;
    const timer = window.setInterval(() => loadRows(search), 5000);
    return () => window.clearInterval(timer);
  }, [backupProcessing, meta.resource, search, status, dateFrom, dateTo, classFilter, studentFilter, page, perPage]);

  const filteredRows = rows;

  const submit = async (event) => {
    event.preventDefault();
    if (!meta.resource) return;
    setSaving(true);
    setError('');
    setMessage('');
    setFormErrors({});
    try {
      const writableFields = editing && workflowResource ? meta.fields.filter((field) => field.name !== 'status') : meta.fields;
      const payload = normalizePayload(formData, writableFields);
      if (meta.resource === 'memberships') payload.mfa_code = requestStepUpMfa('mengubah membership');
      const endpoint = editing ? `/web-api/${meta.resource}/${editing.id}` : `/web-api/${meta.resource}`;
      await apiJson(endpoint, {
        method: editing ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      });
      setMessage(editing ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.');
      setEditing(null);
      setModalMode(null);
      setFormData(defaultFormData(meta, shared));
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setFormErrors(err.errors || {});
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const createRow = () => {
    setEditing(null);
    setFormData(defaultFormData(meta, shared));
    setModalMode('create');
    setMessage('');
    setError('');
    setFormErrors({});
  };

  useEffect(() => {
    if (!meta.resource || !canCreate) return;
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') !== 'create') return;
    window.history.replaceState(window.history.state, '', window.location.pathname);
    createRow();
  }, [meta.resource, meta.title, canCreate]);

  const viewRow = (row) => {
    setEditing(row);
    setFormData(formDataFromRow(row, meta));
    setModalMode('view');
    loadTimeline(row);
    setMessage('');
    setError('');
  };

  const editRow = (row) => {
    setEditing(row);
    setFormData(formDataFromRow(row, meta));
    setModalMode('edit');
    setTimeline([]);
    setMessage('');
    setError('');
    setFormErrors({});
  };

  const loadTimeline = async (row) => {
    if (!meta.resource || !row?.id) return;
    setTimeline([]);
    setTimelineLoading(true);
    try {
      const json = await apiJson(`/web-api/${meta.resource}/${row.id}/timeline`);
      setTimeline(rowsOf(json.data));
    } catch {
      setTimeline([]);
    } finally {
      setTimelineLoading(false);
    }
  };

  const deleteRow = async (row) => {
    if (!meta.resource || !window.confirm(`Hapus data ${row.name || row.title || row.invoice_number || row.number || row.id}?`)) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const body = meta.resource === 'memberships' ? JSON.stringify({ mfa_code: requestStepUpMfa('menghapus membership') }) : undefined;
      await apiJson(`/web-api/${meta.resource}/${row.id}`, { method: 'DELETE', ...(body ? { body } : {}) });
      setMessage('Data berhasil dihapus.');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const openWorkflow = (row, action) => {
    setWorkflowDraft({ row, action });
    setWorkflowNote('');
    setMessage('');
    setError('');
  };

  const submitWorkflow = async (event) => {
    event.preventDefault();
    if (!workflowDraft) return;
    const { row, action } = workflowDraft;
    if (action.requiresNote && !workflowNote.trim()) {
      setError('Catatan wajib diisi untuk aksi ini.');
      return;
    }

    setLoading(true);
    setError('');
    setMessage('');
    try {
      await apiJson(`/web-actions/workflow/${meta.resource}/${row.id}/${action.transition}`, {
        method: 'POST',
        body: JSON.stringify({ note: workflowNote.trim() || action.label }),
      });
      setMessage(action.success || 'Status berhasil diperbarui.');
      setWorkflowDraft(null);
      setWorkflowNote('');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const cancelEdit = () => {
    setEditing(null);
    setModalMode(null);
    setFormData(defaultFormData(meta, shared));
    setMessage('');
  };

  const toggleSelected = (rowId) => {
    setSelectedIds((current) => current.includes(rowId) ? current.filter((id) => id !== rowId) : [...current, rowId]);
  };

  const setSelectedForPage = (ids, checked) => {
    setSelectedIds((current) => {
      const withoutPage = current.filter((id) => !ids.includes(id));
      return checked ? [...withoutPage, ...ids] : withoutPage;
    });
  };

  const submitBulk = async ({ action, status: nextStatus }) => {
    if (!selectedIds.length) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiJson(`/web-api/${meta.resource}/bulk`, {
        method: 'POST',
        body: JSON.stringify({ action, status: nextStatus, ids: selectedIds }),
      });
      setMessage(json.message || 'Aksi massal berhasil diproses.');
      setBulkOpen(false);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const loadJobs = async () => {
    try {
      const json = await apiJson('/web-actions/jobs');
      setJobs(rowsOf(json.data));
    } catch {
      setJobs([]);
    }
  };

  const submitImport = async ({ file, mapping, queue }) => {
    if (!file || !meta.resource) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiUpload(`/web-api/${meta.resource}/import`, file, { mapping: JSON.stringify(mapping || {}), queue: queue ? '1' : '0' });
      if (json.queued) {
        setMessage(`Import masuk antrian. Job: ${json.job?.uuid || '-'}`);
        setImportOpen(false);
        await loadJobs();
        return;
      }
      const skipped = json.skipped ? ` ${json.skipped} baris dilewati.` : '';
      const errorText = rowsOf(json.errors).length ? ` Validasi pertama: ${rowsOf(json.errors)[0].messages?.join(', ')}` : '';
      setMessage(`${json.message || 'Import selesai.'}${skipped}${errorText}`);
      setImportOpen(false);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const queueExport = async (format = 'excel') => {
    if (!meta.resource) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const json = await apiJson(`/web-api/${meta.resource}/export/queue`, {
        method: 'POST',
        body: JSON.stringify({ format, filters: Object.fromEntries(buildParams()) }),
      });
      setMessage(`Export masuk antrian. Job: ${json.job?.uuid || '-'}`);
      await loadJobs();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const runBackup = async () => {
    setLoading(true);
    setError('');
    setMessage('');
    try {
      await apiJson('/web-actions/backup/run', { method: 'POST', body: JSON.stringify({}) });
      setMessage('Backup masuk antrean. Status akan diperbarui otomatis.');
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const downloadBackup = async (row) => {
    await downloadAuthenticatedFile(`/web-actions/backup/${row.id}/download`, row.name || `backup-${row.id}.json`);
  };

  const restoreBackup = async (event) => {
    event.preventDefault();
    if (!restoreDraft) return;
    setLoading(true);
    setError('');
    try {
      const json = await apiJson(`/web-actions/backup/${restoreDraft.id}/restore`, {
        method: 'POST',
        body: JSON.stringify({ confirmation: restoreDraft.confirmation, mfa_code: restoreDraft.mfa_code, reason: restoreDraft.reason }),
      });
      setMessage(`${json.message} Request #${json.request?.id || '-'}.`);
      setRestoreDraft(null);
      await loadRows();
      router.reload({ only: ['shared', 'dashboard'], preserveScroll: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const filtersActive = Boolean(search || status !== 'Semua Status' || dateFrom || dateTo || classFilter || studentFilter);
  const resetFilters = () => {
    setSearch('');
    setStatus('Semua Status');
    setDateFrom('');
    setDateTo('');
    setClassFilter('');
    setStudentFilter('');
  };
  const emptyState = filtersActive
    ? { icon: 'Search', title: 'Tidak ada hasil yang cocok', description: 'Ubah kata kunci atau atur ulang filter untuk melihat semua data.', action: { label: 'Atur ulang filter', onClick: resetFilters } }
    : canCreate
      ? { title: `Belum ada data ${String(meta.title || '').toLowerCase()}`, description: 'Mulai dengan menambahkan data pertama.', action: { label: 'Tambah data', onClick: createRow } }
      : undefined;
  const resourceActions = [
    ...workflowActionsFor(meta).map((action) => ({ ...action, onClick: (row) => openWorkflow(row, action) })),
    ...(meta.resource === 'backups' ? [
      { key: 'download-backup', label: 'Download Backup', icon: 'Download', show: (row) => ['success', 'restored', 'restore_failed'].includes(row.status), onClick: downloadBackup },
      { key: 'restore-backup', label: 'Restore Backup', icon: 'RefreshCcw', tone: 'danger', show: (row) => ['success', 'restored', 'restore_failed'].includes(row.status), onClick: (row) => setRestoreDraft({ ...row, confirmation: '' }) },
    ] : []),
  ];

  return (
    <div className="space-y-5">
      {meta.showHeader !== false && <Header title={meta.title} subtitle={meta.subtitle} />}
      {(message || error) && <Alert tone={error ? 'error' : 'success'}>{error || message}</Alert>}
      <PanelCard>
        <div className="crud-command-bar mb-4 flex flex-col gap-4 border-b border-neutral-200 pb-4 2xl:flex-row 2xl:items-start 2xl:justify-between">
          <div className="filter-grid grid min-w-0 flex-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
            <div className="relative w-full"><icons.Search className="absolute left-3 top-3 h-4 w-4 text-neutral-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-xl border border-neutral-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" placeholder={`Cari ${meta.title.toLowerCase()}...`} aria-label={`Cari ${meta.title.toLowerCase()}`} /></div>
            {supportsStatusFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Status</span><select value={status} onChange={(event) => setStatus(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70"><option>Semua Status</option>{statusOptions.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}</select></label>}
            {meta.enableFilters !== false && supportsDateFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Dari tanggal</span><input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" /></label>}
            {meta.enableFilters !== false && supportsDateFilter && <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Sampai tanggal</span><input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70" /></label>}
            {meta.enableFilters !== false && supportsClassFilter && (
              <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Kelas</span><select value={classFilter} onChange={(event) => setClassFilter(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70">
                <option value="">Semua Kelas</option>
                {rowsOf(shared.classes).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select></label>
            )}
            {meta.enableFilters !== false && supportsStudentFilter && (
              <label className="grid gap-1 text-xs font-medium text-neutral-600"><span>Santri</span><select value={studentFilter} onChange={(event) => setStudentFilter(event.target.value)} className="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm text-neutral-700 shadow-sm outline-none transition focus:border-primary-500 focus:ring-4 focus:ring-primary-100/70">
                <option value="">Semua Santri</option>
                {rowsOf(shared.students).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select></label>
            )}
          </div>
          <div className="crud-toolbar relative flex flex-wrap items-start gap-2 2xl:max-w-[48rem] 2xl:justify-end" aria-label="Aksi data" onToggle={(event) => { if (event.target.open) event.currentTarget.querySelectorAll('details[open]').forEach((detail) => { if (detail !== event.target) detail.removeAttribute('open'); }); }}>
            {meta.resource === 'backups' && <button onClick={runBackup} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Database className="h-4 w-4" /> Jalankan Backup</button>}
            {meta.resource === 'backups' && meta.panel === 'super-admin' && <button onClick={() => setRestoreApprovalOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Shield className="h-4 w-4" /> Persetujuan Restore</button>}
            {canImport && <button onClick={() => setImportOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><ImportIcon className="h-4 w-4" /> Import Excel</button>}
            {canBulk && selectedIds.length > 0 && <button onClick={() => setBulkOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 shadow-sm transition hover:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-200"><BulkIcon className="h-4 w-4" /> Aksi Massal ({selectedIds.length})</button>}
            {canEditPdfTemplates && <button onClick={() => setPdfTemplateOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.FileText className="h-4 w-4" /> Template PDF</button>}
            <button onClick={() => loadRows()} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.RefreshCcw className="h-4 w-4" /> Refresh</button>
            {canExport && <details className="relative"><summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.Download className="h-4 w-4" /> Ekspor</summary><div className="absolute right-0 top-12 z-20 grid min-w-52 gap-1 rounded-xl border border-neutral-200 bg-white p-2 shadow-xl"><button onClick={() => exportResource(meta.resource, meta.title, 'csv', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh CSV</button><button onClick={() => exportResource(meta.resource, meta.title, 'excel', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh Excel</button><button onClick={() => exportResource(meta.resource, meta.title, 'pdf', buildParams())} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Unduh PDF</button><button onClick={() => queueExport('excel')} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Ekspor di latar belakang</button></div></details>}
            {(canEditPermissions || canMonitorQueue || canExport || canImport) && <details className="relative"><summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-medium shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"><icons.MoreHorizontal className="h-4 w-4" /> Lainnya</summary><div className="absolute right-0 top-12 z-20 grid min-w-56 gap-1 rounded-xl border border-neutral-200 bg-white p-2 shadow-xl"><button onClick={() => { setDownloadManagerOpen(true); loadJobs(); }} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Hasil unduhan</button>{canEditPermissions && <button onClick={() => setPermissionOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Matriks izin</button>}{canEditPermissions && <button onClick={() => setCustomRoleOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Role khusus</button>}{canMonitorQueue && <button onClick={() => setQueueMonitorOpen(true)} className="rounded-lg px-3 py-2 text-left text-sm hover:bg-neutral-50">Monitor antrean</button>}</div></details>}
            {canCreate && <button onClick={createRow} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"><icons.Plus className="h-4 w-4" /> {meta.createLabel || 'Tambah Data'}</button>}
          </div>
        </div>
        <div className="mb-3 flex items-center justify-end"><DensityToggle /></div>
        <Table rows={filteredRows} empty={emptyState} columns={meta.fields.map((field) => field.name)} meta={meta} shared={shared} loading={loading} selectable={canBulk} selectedIds={selectedIds} onToggleRow={toggleSelected} onToggleAll={setSelectedForPage} onView={canView ? viewRow : null} onEdit={canEdit ? editRow : null} onDelete={canDelete ? deleteRow : null} customActions={resourceActions} />
        <PaginationControls pagination={pagination} page={page} setPage={setPage} perPage={perPage} setPerPage={setPerPage} />
      </PanelCard>
      {jobs.length > 0 && <JobStatusStrip jobs={jobs} onRefresh={loadJobs} />}
      {analytics && <Suspense fallback={<ChartLoading />}><AnalyticsPanel analytics={analytics} title={meta.title} /></Suspense>}
      {modalMode && <CrudModal meta={meta} shared={shared} formData={formData} setFormData={setFormData} editing={editing} mode={modalMode} saving={saving} formErrors={formErrors} onSubmit={submit} onClose={cancelEdit} timeline={timeline} timelineLoading={timelineLoading} />}
      {workflowDraft && <WorkflowModal draft={workflowDraft} note={workflowNote} setNote={setWorkflowNote} saving={loading} onSubmit={submitWorkflow} onClose={() => setWorkflowDraft(null)} />}
      {importOpen && <ImportModal title={meta.title} resource={meta.resource} loading={loading} onSubmit={submitImport} onClose={() => setImportOpen(false)} />}
      {bulkOpen && <BulkModal count={selectedIds.length} canDelete={canDelete} loading={loading} onSubmit={submitBulk} onClose={() => setBulkOpen(false)} />}
      {permissionOpen && <PermissionMatrixModal onClose={() => setPermissionOpen(false)} />}
      {customRoleOpen && <CustomRoleModal onClose={() => setCustomRoleOpen(false)} />}
      {pdfTemplateOpen && <PdfTemplateModal shared={shared} onClose={() => setPdfTemplateOpen(false)} onSaved={() => { setPdfTemplateOpen(false); loadRows(); router.reload({ only: ['shared'], preserveScroll: true }); }} />}
      {downloadManagerOpen && <DownloadManagerModal jobs={jobs} onRefresh={loadJobs} onClose={() => setDownloadManagerOpen(false)} />}
      {queueMonitorOpen && <QueueMonitorModal onClose={() => setQueueMonitorOpen(false)} />}
      {restoreDraft && <RestoreBackupModal draft={restoreDraft} setDraft={setRestoreDraft} loading={loading} onSubmit={restoreBackup} onClose={() => setRestoreDraft(null)} />}
      {restoreApprovalOpen && <RestoreApprovalModal onClose={() => setRestoreApprovalOpen(false)} />}
    </div>
  );
}
