import React from 'react';
import { DonutVisualization, LineVisualization } from './NativeCharts.jsx';

export function DashboardLineVisualization({ data = [] }) {
  return <LineVisualization data={data} labelKey="month" ariaLabel="Tren dashboard per bulan" />;
}

export function DashboardDonutVisualization({ data = [] }) {
  return <DonutVisualization data={data} ariaLabel="Distribusi dashboard" />;
}
