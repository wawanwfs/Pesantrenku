import React, { useEffect, useRef, useState } from 'react';

export const chartColors = ['#127A4F', '#2563EB', '#C2410C', '#D97706', '#DC2626', '#0F766E'];

const DEFAULT_WIDTH = 640;
const MARGIN = { top: 18, right: 18, bottom: 46, left: 52 };
const GRID_LINES = 4;

export function LineVisualization({
  data = [],
  labelKey = 'name',
  seriesKeys,
  height = 280,
  ariaLabel = 'Grafik garis',
}) {
  const [containerRef, width] = useChartWidth();
  const rows = normalizeRows(data);
  const keys = seriesKeys?.length ? seriesKeys : numericKeys(rows, labelKey);
  if (!rows.length || !keys.length) return <div ref={containerRef} className="native-chart-wrap"><EmptyChart label={ariaLabel} height={height} /></div>;

  const plotWidth = width - MARGIN.left - MARGIN.right;
  const plotHeight = height - MARGIN.top - MARGIN.bottom;
  const maximum = niceMaximum(Math.max(...rows.flatMap((row) => keys.map((key) => numberOf(row[key])))));
  const xAt = (index) => MARGIN.left + (rows.length === 1 ? plotWidth / 2 : (index / (rows.length - 1)) * plotWidth);
  const yAt = (value) => MARGIN.top + plotHeight - (numberOf(value) / maximum) * plotHeight;

  return (
    <div ref={containerRef} className="native-chart-wrap">
      <svg className="native-chart" viewBox={`0 0 ${width} ${height}`} width="100%" height={height} role="img" aria-label={ariaLabel}>
        <title>{ariaLabel}</title>
        <desc>{`${rows.length} titik data untuk ${keys.join(', ')}`}</desc>
        {gridValues(maximum).map((value) => {
          const y = yAt(value);
          return (
            <g key={value}>
              <line className="native-chart-grid" x1={MARGIN.left} x2={width - MARGIN.right} y1={y} y2={y} />
              <text className="native-chart-axis-label" x={MARGIN.left - 8} y={y + 4} textAnchor="end">{formatNumber(value)}</text>
            </g>
          );
        })}
        <line className="native-chart-axis" x1={MARGIN.left} x2={width - MARGIN.right} y1={MARGIN.top + plotHeight} y2={MARGIN.top + plotHeight} />
        {rows.map((row, index) => shouldShowLabel(index, rows.length, plotWidth) && (
          <text
            key={`${labelOf(row, labelKey)}-${index}`}
            className="native-chart-axis-label"
            x={xAt(index)}
            y={height - 17}
            textAnchor="middle"
          >
            {shortLabel(labelOf(row, labelKey))}
          </text>
        ))}
        {keys.map((key, seriesIndex) => {
          const color = chartColors[seriesIndex % chartColors.length];
          const points = rows.map((row, index) => `${xAt(index)},${yAt(row[key])}`).join(' ');
          return (
            <g key={key}>
              <polyline fill="none" stroke={color} strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" points={points} />
              {rows.map((row, index) => (
                <circle key={`${key}-${index}`} cx={xAt(index)} cy={yAt(row[key])} r="3.5" fill={color} stroke="#FFFFFF" strokeWidth="1.5">
                  <title>{`${labelOf(row, labelKey)} — ${key}: ${formatNumber(numberOf(row[key]))}`}</title>
                </circle>
              ))}
            </g>
          );
        })}
      </svg>
      {keys.length > 1 && (
        <div className="native-chart-legend" aria-label="Legenda grafik">
          {keys.map((key, index) => (
            <span key={key}><ChartColorMarker color={chartColors[index % chartColors.length]} />{humanize(key)}</span>
          ))}
        </div>
      )}
    </div>
  );
}

export function BarVisualization({
  data = [],
  labelKey = 'name',
  valueKey = 'value',
  height = 224,
  ariaLabel = 'Grafik batang',
  color = chartColors[1],
}) {
  const [containerRef, width] = useChartWidth();
  const rows = normalizeRows(data);
  if (!rows.length) return <div ref={containerRef} className="native-chart-wrap"><EmptyChart label={ariaLabel} height={height} /></div>;

  const plotWidth = width - MARGIN.left - MARGIN.right;
  const plotHeight = height - MARGIN.top - MARGIN.bottom;
  const maximum = niceMaximum(Math.max(...rows.map((row) => numberOf(row[valueKey]))));
  const slotWidth = plotWidth / rows.length;
  const barWidth = Math.max(8, Math.min(52, slotWidth * 0.62));
  const yAt = (value) => MARGIN.top + plotHeight - (numberOf(value) / maximum) * plotHeight;

  return (
    <div ref={containerRef} className="native-chart-wrap">
      <svg className="native-chart" viewBox={`0 0 ${width} ${height}`} width="100%" height={height} role="img" aria-label={ariaLabel}>
        <title>{ariaLabel}</title>
        <desc>{`${rows.length} kategori data`}</desc>
        {gridValues(maximum).map((value) => {
          const y = yAt(value);
          return (
            <g key={value}>
              <line className="native-chart-grid" x1={MARGIN.left} x2={width - MARGIN.right} y1={y} y2={y} />
              <text className="native-chart-axis-label" x={MARGIN.left - 8} y={y + 4} textAnchor="end">{formatNumber(value)}</text>
            </g>
          );
        })}
        <line className="native-chart-axis" x1={MARGIN.left} x2={width - MARGIN.right} y1={MARGIN.top + plotHeight} y2={MARGIN.top + plotHeight} />
        {rows.map((row, index) => {
          const value = numberOf(row[valueKey]);
          const x = MARGIN.left + index * slotWidth + (slotWidth - barWidth) / 2;
          const y = yAt(value);
          const label = labelOf(row, labelKey);
          return (
            <g key={`${label}-${index}`}>
              <rect x={x} y={y} width={barWidth} height={Math.max(0, MARGIN.top + plotHeight - y)} rx="4" fill={color}>
                <title>{`${label}: ${formatNumber(value)}`}</title>
              </rect>
              {shouldShowLabel(index, rows.length, plotWidth) && <text className="native-chart-axis-label" x={x + barWidth / 2} y={height - 17} textAnchor="middle">{shortLabel(label)}</text>}
            </g>
          );
        })}
      </svg>
    </div>
  );
}

export function DonutVisualization({ data = [], height = 280, ariaLabel = 'Grafik donat' }) {
  const rows = normalizeRows(data).filter((row) => numberOf(row.value) > 0);
  const total = rows.reduce((sum, row) => sum + numberOf(row.value), 0);
  if (!rows.length || total <= 0) return <EmptyChart label={ariaLabel} height={height} />;

  const cx = 160;
  const cy = height / 2;
  const radius = 78;
  let offset = 0;

  return (
    <svg className="native-chart native-chart-donut" viewBox={`0 0 320 ${height}`} width="100%" height={height} role="img" aria-label={ariaLabel}>
      <title>{ariaLabel}</title>
      <desc>{`${rows.length} kategori dengan total ${formatNumber(total)}`}</desc>
      <circle cx={cx} cy={cy} r={radius} fill="none" stroke="#E2E8F0" strokeWidth="34" />
      {rows.map((row, index) => {
        const value = numberOf(row.value);
        const percentage = (value / total) * 100;
        const dashOffset = -offset;
        offset += percentage;
        return (
          <circle
            key={`${row.name}-${index}`}
            cx={cx}
            cy={cy}
            r={radius}
            fill="none"
            stroke={chartColors[index % chartColors.length]}
            strokeWidth="34"
            pathLength="100"
            strokeDasharray={`${percentage} ${100 - percentage}`}
            strokeDashoffset={dashOffset}
            transform={`rotate(-90 ${cx} ${cy})`}
          >
            <title>{`${labelOf(row, 'name')}: ${formatNumber(value)} (${percentage.toFixed(1)}%)`}</title>
          </circle>
        );
      })}
      <text className="native-chart-total" x={cx} y={cy - 3} textAnchor="middle">{formatNumber(total)}</text>
      <text className="native-chart-total-label" x={cx} y={cy + 19} textAnchor="middle">Total</text>
    </svg>
  );
}

export function ChartColorMarker({ color, className = 'h-3 w-3 shrink-0' }) {
  return (
    <svg className={className} viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false">
      <rect width="12" height="12" rx="2" fill={color} />
    </svg>
  );
}

function EmptyChart({ label, height }) {
  return <div className="native-chart-empty" role="img" aria-label={label} data-chart-height={height}>Belum ada data grafik.</div>;
}

function normalizeRows(rows) {
  return Array.isArray(rows) ? rows.filter((row) => row && typeof row === 'object') : [];
}

function numericKeys(rows, labelKey) {
  return Object.keys(rows[0] || {}).filter((key) => key !== labelKey && rows.some((row) => Number.isFinite(Number(row[key]))));
}

function labelOf(row, key) {
  return String(row?.[key] ?? '-');
}

function numberOf(value) {
  const number = Number(value);
  return Number.isFinite(number) && number > 0 ? number : 0;
}

function niceMaximum(value) {
  if (!Number.isFinite(value) || value <= 0) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(value));
  const normalized = value / magnitude;
  const ceiling = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
  return ceiling * magnitude;
}

function gridValues(maximum) {
  return Array.from({ length: GRID_LINES + 1 }, (_, index) => (maximum / GRID_LINES) * index);
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID', { notation: Math.abs(value) >= 1000 ? 'compact' : 'standard', maximumFractionDigits: 1 }).format(value);
}

function shortLabel(value) {
  const label = String(value);
  return label.length > 11 ? `${label.slice(0, 10)}…` : label;
}

function humanize(value) {
  return String(value).replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function shouldShowLabel(index, count, plotWidth) {
  const maximumLabels = Math.max(2, Math.floor(plotWidth / 66));
  const step = Math.max(1, Math.ceil(count / maximumLabels));
  return index === count - 1 || index % step === 0;
}

function useChartWidth() {
  const containerRef = useRef(null);
  const [width, setWidth] = useState(DEFAULT_WIDTH);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return undefined;
    const update = ({ width: measuredWidth }) => setWidth(Math.max(280, Math.min(960, Math.round(measuredWidth))));
    update(container.getBoundingClientRect());
    const observer = new ResizeObserver(([entry]) => update(entry.contentRect));
    observer.observe(container);
    return () => observer.disconnect();
  }, []);

  return [containerRef, width];
}
