import { lazy } from 'react';

export const DashboardLineVisualization = lazy(() => import('../DashboardCharts.jsx').then((module) => ({ default: module.DashboardLineVisualization })));
export const DashboardDonutVisualization = lazy(() => import('../DashboardCharts.jsx').then((module) => ({ default: module.DashboardDonutVisualization })));
export const AnalyticsPanel = lazy(() => import('../AnalyticsPanel.jsx'));
