import React from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

const colors = ['#127A4F', '#2563EB', '#C2410C', '#DC2626'];

export default function AnalyticsPanel({ analytics, title }) {
  const statusRows = rowsOf(analytics.by_status);
  const dateRows = rowsOf(analytics.by_date);
  const classRows = rowsOf(analytics.by_class);
  const studentRows = rowsOf(analytics.by_student);
  const cards = [
    ['Total Data', analytics.total ?? 0, 'bg-blue-50 text-blue-700'],
    ['Selesai/Aktif', analytics.completed ?? 0, 'bg-green-50 text-green-700'],
    ['Menunggu', analytics.pending ?? 0, 'bg-amber-50 text-amber-700'],
    ['Ditolak/Batal', analytics.rejected ?? 0, 'bg-red-50 text-red-700'],
  ];

  return (
    <section className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {cards.map(([label, value, tone]) => (
          <div key={label} className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div className={`inline-flex rounded-lg px-2 py-1 text-xs font-semibold ${tone}`}>{label}</div>
            <div className="mt-3 text-2xl font-semibold">{value}</div>
            <div className="mt-1 text-xs text-neutral-500">Analitik {title.toLowerCase()}</div>
          </div>
        ))}
      </div>
      <div className="grid gap-5 xl:grid-cols-2">
        <AnalyticsCard title="Distribusi Status">{statusRows.length ? <SmallBarChart data={statusRows} /> : <EmptyAnalytics />}</AnalyticsCard>
        <AnalyticsCard title="Tren Tanggal">{dateRows.length ? <SmallLineChart data={dateRows} /> : <EmptyAnalytics />}</AnalyticsCard>
        <AnalyticsCard title="Ringkasan Kelas">{classRows.length ? <LegendList items={classRows.slice(0, 6)} /> : <EmptyAnalytics />}</AnalyticsCard>
        <AnalyticsCard title="Ringkasan Santri">{studentRows.length ? <LegendList items={studentRows.slice(0, 6)} /> : <EmptyAnalytics />}</AnalyticsCard>
      </div>
    </section>
  );
}

function AnalyticsCard({ title, children }) {
  return <section className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm"><h3 className="mb-4 text-sm font-semibold">{title}</h3>{children}</section>;
}

function SmallBarChart({ data }) {
  return <div className="h-56"><ResponsiveContainer width="100%" height="100%"><BarChart data={data}><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="name" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip /><Bar dataKey="value" fill={colors[1]} radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div>;
}

function SmallLineChart({ data }) {
  return <div className="h-56"><ResponsiveContainer width="100%" height="100%"><LineChart data={data}><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="name" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip /><Line type="monotone" dataKey="value" stroke={colors[0]} strokeWidth={2} dot={{ r: 3 }} /></LineChart></ResponsiveContainer></div>;
}

function LegendList({ items }) {
  return <div className="space-y-2">{items.map((item, index) => <div key={item.name} className="flex items-center justify-between text-sm"><span className="flex items-center gap-2"><span className="h-3 w-3 rounded-full" style={{ background: colors[index % colors.length] }} />{item.name}</span><span className="font-medium">{item.value}</span></div>)}</div>;
}

function EmptyAnalytics() {
  return <div className="rounded-xl bg-neutral-50 p-8 text-center text-sm text-neutral-500">Belum ada data analitik untuk filter ini.</div>;
}

function rowsOf(rows) {
  if (Array.isArray(rows)) return rows;
  return Object.values(rows || {});
}
