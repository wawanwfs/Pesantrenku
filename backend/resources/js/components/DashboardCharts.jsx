import React from 'react';
import {
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

const colors = ['#127A4F', '#3B82F6', '#C2410C', '#F59E0B', '#DC2626', '#0F766E'];

export function DashboardLineVisualization({ data = [] }) {
  return (
    <ResponsiveContainer height={280}>
      <LineChart data={data}>
        <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
        <XAxis dataKey="month" tick={{ fontSize: 11 }} />
        <YAxis tick={{ fontSize: 11 }} />
        <Tooltip />
        {Object.keys(data[0] || {}).filter((key) => key !== 'month').map((key, index) => (
          <Line key={key} type="monotone" dataKey={key} stroke={colors[index % colors.length]} strokeWidth={3} dot={false} />
        ))}
      </LineChart>
    </ResponsiveContainer>
  );
}

export function DashboardDonutVisualization({ data = [] }) {
  return (
    <ResponsiveContainer height={280}>
      <PieChart>
        <Pie data={data} dataKey="value" innerRadius={62} outerRadius={92} paddingAngle={2}>
          {data.map((item, index) => <Cell key={item.name || index} fill={colors[index % colors.length]} />)}
        </Pie>
        <Tooltip />
      </PieChart>
    </ResponsiveContainer>
  );
}
