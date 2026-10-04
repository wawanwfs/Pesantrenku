import React, { useLayoutEffect, useRef } from 'react';

const NUMBER_PATTERN = /(\d{1,3}(?:\.\d{3})+|\d+)/g;
const DATE_LIKE = /\d[-/:]\d|\d[-/:]$|^[-/:]\d/;

function prefersReducedMotion() {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function formatGrouped(number, grouped) {
  const digits = String(Math.round(number));
  if (!grouped) return digits;
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Menampilkan nilai statistik dan menggulirkan angka di dalamnya dari 0 saat pertama terlihat.
 * Nilai akhir selalu persis string aslinya (format id-ID, mis. "Rp 284.500.000"), dan tidak ada
 * animasi untuk nilai non-numerik, tanggal/jam, atau bila pengguna meminta gerak dikurangi.
 */
export default function AnimatedNumber({ value, className = '', duration = 900 }) {
  const ref = useRef(null);
  const text = String(value ?? '');

  useLayoutEffect(() => {
    const element = ref.current;
    if (!element || prefersReducedMotion() || DATE_LIKE.test(text)) return undefined;
    const tokens = [...text.matchAll(NUMBER_PATTERN)];
    if (!tokens.length || tokens.length > 2) return undefined;
    const targets = tokens.map((token) => ({
      index: token.index,
      length: token[0].length,
      grouped: token[0].includes('.'),
      value: Number(token[0].replaceAll('.', '')),
    }));
    if (targets.every((target) => target.value < 10)) return undefined;

    let frame = 0;
    let observer = null;
    let cancelled = false;

    const render = (progress) => {
      let output = '';
      let cursor = 0;
      targets.forEach((target) => {
        output += text.slice(cursor, target.index) + formatGrouped(target.value * progress, target.grouped);
        cursor = target.index + target.length;
      });
      element.textContent = output + text.slice(cursor);
    };

    const run = () => {
      const startedAt = performance.now();
      const tick = (now) => {
        if (cancelled) return;
        const t = Math.min(1, (now - startedAt) / duration);
        render(1 - (1 - t) ** 3);
        if (t < 1) frame = window.requestAnimationFrame(tick);
        else element.textContent = text;
      };
      frame = window.requestAnimationFrame(tick);
    };

    render(0);
    if ('IntersectionObserver' in window) {
      observer = new IntersectionObserver(([entry]) => {
        if (!entry.isIntersecting) return;
        observer.disconnect();
        run();
      }, { threshold: 0.4 });
      observer.observe(element);
    } else {
      run();
    }

    return () => {
      cancelled = true;
      window.cancelAnimationFrame(frame);
      observer?.disconnect();
      element.textContent = text;
    };
  }, [text, duration]);

  return <span ref={ref} className={className}>{text}</span>;
}
