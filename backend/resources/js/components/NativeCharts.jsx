import React, { useCallback, useEffect, useId, useRef, useState } from 'react';

export const chartColors = ['#127A4F', '#2563EB', '#C2410C', '#D97706', '#DC2626', '#0F766E'];

const DEFAULT_WIDTH = 640;
const MARGIN = { top: 18, right: 18, bottom: 46, left: 52 };
const GRID_LINES = 4;

/* ------------------------------------------------------------------ garis */

export function LineVisualization({
  data = [],
  labelKey = 'name',
  seriesKeys,
  height = 280,
  ariaLabel = 'Grafik garis',
}) {
  const [containerRef, width] = useChartWidth();
  const gradientId = useId().replace(/:/g, '');
  const [hover, setHover] = useState(null);
  const [hidden, setHidden] = useState([]);
  const rows = normalizeRows(data);
  const keys = seriesKeys?.length ? seriesKeys : numericKeys(rows, labelKey);
  const visibleKeys = keys.filter((key) => !hidden.includes(key));
  useEffect(() => setHover(null), [rows.length]);

  const plotWidth = width - MARGIN.left - MARGIN.right;
  const plotHeight = height - MARGIN.top - MARGIN.bottom;
  const maximum = niceMaximum(Math.max(0, ...rows.flatMap((row) => visibleKeys.map((key) => numberOf(row[key])))));
  const xAt = (index) => MARGIN.left + (rows.length === 1 ? plotWidth / 2 : (index / (rows.length - 1)) * plotWidth);
  const yAt = (value) => MARGIN.top + plotHeight - (numberOf(value) / maximum) * plotHeight;

  const indexFromPointer = useCallback((event) => {
    const box = event.currentTarget.getBoundingClientRect();
    const x = ((event.clientX - box.left) / box.width) * width;
    if (rows.length <= 1) return 0;
    return Math.max(0, Math.min(rows.length - 1, Math.round(((x - MARGIN.left) / plotWidth) * (rows.length - 1))));
  }, [rows.length, width, plotWidth]);

  const toggleSeries = (key) => setHidden((current) => {
    if (current.includes(key)) return current.filter((item) => item !== key);
    return keys.length - current.length <= 1 ? current : [...current, key];
  });

  if (!rows.length || !keys.length) return <div ref={containerRef} className="native-chart-wrap"><EmptyChart label={ariaLabel} height={height} /></div>;

  const onKeyDown = (event) => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      setHover((current) => {
        const base = current ?? (event.key === 'ArrowRight' ? -1 : rows.length);
        return Math.max(0, Math.min(rows.length - 1, base + (event.key === 'ArrowRight' ? 1 : -1)));
      });
    } else if (event.key === 'Home') {
      event.preventDefault();
      setHover(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      setHover(rows.length - 1);
    } else if (event.key === 'Escape') {
      setHover(null);
    }
  };

  const tip = hover === null ? null : {
    x: xAt(hover),
    title: labelOf(rows[hover], labelKey),
    lines: visibleKeys.map((key) => ({ key, color: chartColors[keys.indexOf(key) % chartColors.length], text: `${humanize(key)}: ${formatFull(numberOf(rows[hover][key]))}` })),
  };

  return (
    <div ref={containerRef} className="native-chart-wrap">
      <svg
        className="native-chart native-chart-interactive"
        viewBox={`0 0 ${width} ${height}`}
        width="100%"
        height={height}
        role="group"
        aria-roledescription="grafik garis interaktif"
        aria-label={`${ariaLabel}. Gunakan panah kiri dan kanan untuk membaca nilai tiap titik.`}
        tabIndex={0}
        onKeyDown={onKeyDown}
        onBlur={() => setHover(null)}
      >
        <title>{ariaLabel}</title>
        <defs>
          {keys.map((key, index) => (
            <linearGradient key={key} id={`${gradientId}-${index}`} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={chartColors[index % chartColors.length]} stopOpacity="0.22" />
              <stop offset="100%" stopColor={chartColors[index % chartColors.length]} stopOpacity="0" />
            </linearGradient>
          ))}
        </defs>
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
            className={`native-chart-axis-label ${hover === index ? 'is-active' : ''}`}
            x={xAt(index)}
            y={height - 17}
            textAnchor="middle"
          >
            {shortLabel(labelOf(row, labelKey))}
          </text>
        ))}
        {tip && <line className="native-chart-crosshair" x1={tip.x} x2={tip.x} y1={MARGIN.top} y2={MARGIN.top + plotHeight} />}
        {keys.map((key, seriesIndex) => {
          if (hidden.includes(key)) return null;
          const color = chartColors[seriesIndex % chartColors.length];
          const points = rows.map((row, index) => [xAt(index), yAt(row[key])]);
          const line = smoothPath(points);
          const baseline = MARGIN.top + plotHeight;
          return (
            <g key={key}>
              {rows.length > 1 && <path className="native-chart-area" d={`${line} L${points[points.length - 1][0]},${baseline} L${points[0][0]},${baseline} Z`} fill={`url(#${gradientId}-${seriesIndex})`} />}
              <path className="native-chart-line" d={line} fill="none" stroke={color} strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" pathLength="1" />
              {points.map(([cx, cy], index) => (
                <circle key={`${key}-${index}`} className={`native-chart-point ${hover === index ? 'is-active' : ''}`} cx={cx} cy={cy} r={hover === index ? 5.5 : 3.5} fill={color} stroke="#FFFFFF" strokeWidth="1.5" />
              ))}
            </g>
          );
        })}
        <rect
          className="native-chart-hit"
          x={MARGIN.left - 8}
          y={MARGIN.top}
          width={plotWidth + 16}
          height={plotHeight}
          fill="transparent"
          onPointerMove={(event) => setHover(indexFromPointer(event))}
          onPointerDown={(event) => setHover(indexFromPointer(event))}
          onPointerLeave={() => setHover(null)}
        />
        {tip && <ChartTooltip x={tip.x} top={MARGIN.top + 4} chartWidth={width} title={tip.title} lines={tip.lines} />}
      </svg>
      {keys.length > 1 && (
        <div className="native-chart-legend" role="group" aria-label="Legenda grafik; tekan untuk menampilkan atau menyembunyikan seri">
          {keys.map((key, index) => (
            <button key={key} type="button" className={`native-chart-legend-item ${hidden.includes(key) ? 'is-off' : ''}`} aria-pressed={!hidden.includes(key)} onClick={() => toggleSeries(key)}>
              <ChartColorMarker color={chartColors[index % chartColors.length]} />{humanize(key)}
            </button>
          ))}
        </div>
      )}
      <ChartDataTable caption={ariaLabel} rows={rows} labelKey={labelKey} keys={keys} />
    </div>
  );
}

/* ----------------------------------------------------------------- batang */

export function BarVisualization({
  data = [],
  labelKey = 'name',
  valueKey = 'value',
  height = 224,
  ariaLabel = 'Grafik batang',
  color = chartColors[1],
}) {
  const [containerRef, width] = useChartWidth();
  const [hover, setHover] = useState(null);
  const rows = normalizeRows(data);
  useEffect(() => setHover(null), [rows.length]);
  if (!rows.length) return <div ref={containerRef} className="native-chart-wrap"><EmptyChart label={ariaLabel} height={height} /></div>;

  const plotWidth = width - MARGIN.left - MARGIN.right;
  const plotHeight = height - MARGIN.top - MARGIN.bottom;
  const maximum = niceMaximum(Math.max(...rows.map((row) => numberOf(row[valueKey]))));
  const slotWidth = plotWidth / rows.length;
  const barWidth = Math.max(8, Math.min(52, slotWidth * 0.62));
  const yAt = (value) => MARGIN.top + plotHeight - (numberOf(value) / maximum) * plotHeight;
  const centerOf = (index) => MARGIN.left + index * slotWidth + slotWidth / 2;

  const onKeyDown = (event) => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      setHover((current) => {
        const base = current ?? (event.key === 'ArrowRight' ? -1 : rows.length);
        return Math.max(0, Math.min(rows.length - 1, base + (event.key === 'ArrowRight' ? 1 : -1)));
      });
    } else if (event.key === 'Escape') {
      setHover(null);
    }
  };

  return (
    <div ref={containerRef} className="native-chart-wrap">
      <svg
        className="native-chart native-chart-interactive"
        viewBox={`0 0 ${width} ${height}`}
        width="100%"
        height={height}
        role="group"
        aria-roledescription="grafik batang interaktif"
        aria-label={`${ariaLabel}. Gunakan panah kiri dan kanan untuk membaca nilai tiap batang.`}
        tabIndex={0}
        onKeyDown={onKeyDown}
        onBlur={() => setHover(null)}
        onPointerLeave={() => setHover(null)}
      >
        <title>{ariaLabel}</title>
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
            <g key={`${label}-${index}`} onPointerEnter={() => setHover(index)} onPointerDown={() => setHover(index)}>
              <rect className="native-chart-slot" x={MARGIN.left + index * slotWidth} y={MARGIN.top} width={slotWidth} height={plotHeight} fill="transparent" />
              <rect className={`native-chart-bar ${hover === index ? 'is-active' : ''}`} x={x} y={y} width={barWidth} height={Math.max(0, MARGIN.top + plotHeight - y)} rx="4" fill={color} />
              {shouldShowLabel(index, rows.length, plotWidth) && <text className={`native-chart-axis-label ${hover === index ? 'is-active' : ''}`} x={x + barWidth / 2} y={height - 17} textAnchor="middle">{shortLabel(label)}</text>}
            </g>
          );
        })}
        {hover !== null && (
          <ChartTooltip
            x={centerOf(hover)}
            top={Math.max(MARGIN.top + 2, yAt(numberOf(rows[hover][valueKey])) - 54)}
            chartWidth={width}
            title={labelOf(rows[hover], labelKey)}
            lines={[{ key: valueKey, color, text: `${humanize(valueKey)}: ${formatFull(numberOf(rows[hover][valueKey]))}` }]}
          />
        )}
      </svg>
      <ChartDataTable caption={ariaLabel} rows={rows} labelKey={labelKey} keys={[valueKey]} />
    </div>
  );
}

/* ------------------------------------------------------------------ donat */

export function DonutVisualization({ data = [], height = 280, ariaLabel = 'Grafik donat' }) {
  const [hover, setHover] = useState(null);
  const rows = normalizeRows(data).filter((row) => numberOf(row.value) > 0);
  useEffect(() => setHover(null), [rows.length]);
  const total = rows.reduce((sum, row) => sum + numberOf(row.value), 0);
  if (!rows.length || total <= 0) return <EmptyChart label={ariaLabel} height={height} />;

  const cx = 160;
  const cy = height / 2;
  const radius = 78;
  let offset = 0;
  const active = hover === null ? null : rows[hover];

  const onKeyDown = (event) => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
      event.preventDefault();
      setHover((current) => (current === null ? 0 : (current + 1) % rows.length));
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
      event.preventDefault();
      setHover((current) => (current === null ? rows.length - 1 : (current - 1 + rows.length) % rows.length));
    } else if (event.key === 'Escape') {
      setHover(null);
    }
  };

  return (
    <svg
      className="native-chart native-chart-donut native-chart-interactive"
      viewBox={`0 0 320 ${height}`}
      width="100%"
      height={height}
      role="group"
      aria-roledescription="grafik donat interaktif"
      aria-label={`${ariaLabel}. ${rows.length} kategori dengan total ${formatFull(total)}. Gunakan panah untuk membaca tiap bagian.`}
      tabIndex={0}
      onKeyDown={onKeyDown}
      onBlur={() => setHover(null)}
      onPointerLeave={() => setHover(null)}
    >
      <title>{ariaLabel}</title>
      <desc>{rows.map((row) => `${labelOf(row, 'name')}: ${formatFull(numberOf(row.value))}`).join('; ')}</desc>
      <circle cx={cx} cy={cy} r={radius} fill="none" stroke="#E2E8F0" strokeWidth="34" />
      <g className="native-donut-sweep">
        {rows.map((row, index) => {
          const value = numberOf(row.value);
          const percentage = (value / total) * 100;
          const dashOffset = -offset;
          offset += percentage;
          return (
            <circle
              key={`${row.name}-${index}`}
              className={`native-donut-segment ${hover === index ? 'is-active' : ''} ${hover !== null && hover !== index ? 'is-dim' : ''}`}
              cx={cx}
              cy={cy}
              r={radius}
              fill="none"
              stroke={chartColors[index % chartColors.length]}
              strokeWidth={hover === index ? 40 : 34}
              pathLength="100"
              strokeDasharray={`${percentage} ${100 - percentage}`}
              strokeDashoffset={dashOffset}
              transform={`rotate(-90 ${cx} ${cy})`}
              onPointerEnter={() => setHover(index)}
              onPointerDown={() => setHover(index)}
            />
          );
        })}
      </g>
      <text className="native-chart-total" x={cx} y={cy - 3} textAnchor="middle">{formatNumber(active ? numberOf(active.value) : total)}</text>
      <text className="native-chart-total-label" x={cx} y={cy + 19} textAnchor="middle">
        {active ? `${shortLabel(labelOf(active, 'name'))} · ${((numberOf(active.value) / total) * 100).toFixed(1)}%` : 'Total'}
      </text>
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

/* ------------------------------------------------------------- pendukung */

function ChartTooltip({ x, top, chartWidth, title, lines }) {
  const longest = Math.max(title.length, ...lines.map((line) => line.text.length));
  const tipWidth = Math.max(116, Math.round(longest * 6.3) + 34);
  const tipHeight = 26 + lines.length * 17;
  const flip = x + 14 + tipWidth > chartWidth - 4;
  const left = flip ? x - 14 - tipWidth : x + 14;
  return (
    <g className="native-chart-tip" transform={`translate(${Math.max(4, left)} ${top})`} pointerEvents="none">
      <rect className="native-chart-tip-box" width={tipWidth} height={tipHeight} rx="8" />
      <text className="native-chart-tip-title" x="12" y="18">{title}</text>
      {lines.map((line, index) => (
        <g key={line.key} transform={`translate(12 ${32 + index * 17})`}>
          <rect width="8" height="8" y="-8" rx="2" fill={line.color} />
          <text className="native-chart-tip-text" x="14" y="0">{line.text}</text>
        </g>
      ))}
    </g>
  );
}

function ChartDataTable({ caption, rows, labelKey, keys }) {
  return (
    <table className="sr-only">
      <caption>{caption}</caption>
      <thead>
        <tr><th scope="col">{humanize(labelKey)}</th>{keys.map((key) => <th scope="col" key={key}>{humanize(key)}</th>)}</tr>
      </thead>
      <tbody>
        {rows.map((row, index) => (
          <tr key={`${labelOf(row, labelKey)}-${index}`}>
            <th scope="row">{labelOf(row, labelKey)}</th>
            {keys.map((key) => <td key={key}>{formatFull(numberOf(row[key]))}</td>)}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/** Kurva monoton (Fritsch-Carlson): halus tetapi tidak pernah melampaui nilai data. */
function smoothPath(points) {
  if (points.length < 3) return points.map(([x, y], index) => `${index ? 'L' : 'M'}${x},${y}`).join(' ');
  const n = points.length;
  const dx = [];
  const slope = [];
  for (let i = 0; i < n - 1; i += 1) {
    dx.push(points[i + 1][0] - points[i][0]);
    slope.push((points[i + 1][1] - points[i][1]) / (dx[i] || 1));
  }
  const tangent = [slope[0]];
  for (let i = 1; i < n - 1; i += 1) {
    tangent.push(slope[i - 1] * slope[i] <= 0 ? 0 : (slope[i - 1] + slope[i]) / 2);
  }
  tangent.push(slope[n - 2]);
  for (let i = 0; i < n - 1; i += 1) {
    if (slope[i] === 0) {
      tangent[i] = 0;
      tangent[i + 1] = 0;
    } else {
      const a = tangent[i] / slope[i];
      const b = tangent[i + 1] / slope[i];
      const h = Math.hypot(a, b);
      if (h > 3) {
        tangent[i] = (3 * a * slope[i]) / h;
        tangent[i + 1] = (3 * b * slope[i]) / h;
      }
    }
  }
  let d = `M${points[0][0]},${points[0][1]}`;
  for (let i = 0; i < n - 1; i += 1) {
    const [x0, y0] = points[i];
    const [x1, y1] = points[i + 1];
    d += ` C${x0 + dx[i] / 3},${y0 + (tangent[i] * dx[i]) / 3} ${x1 - dx[i] / 3},${y1 - (tangent[i + 1] * dx[i]) / 3} ${x1},${y1}`;
  }
  return d;
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

function formatFull(value) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
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
