import React, { useEffect, useRef, useState } from 'react';
import {
  ArrowRight,
  BarChart3,
  BellRing,
  BookOpenCheck,
  Check,
  ChevronRight,
  Clock3,
  HeartPulse,
  Menu,
  Quote,
  ShieldCheck,
  Sparkles,
  UsersRound,
  WalletCards,
  X,
} from 'lucide-react';
import '../../css/landing.css';

const featureCards = [
  {
    icon: BookOpenCheck,
    eyebrow: 'Akademik yang jernih',
    title: 'Belajar, terarah.',
    text: 'Jadwal, presensi, nilai, hafalan, dan catatan perkembangan bergerak dalam satu alur yang mudah dipahami.',
    tone: 'mint',
  },
  {
    icon: WalletCards,
    eyebrow: 'Keuangan yang tenang',
    title: 'Amanah, terbaca.',
    text: 'Tagihan, pembayaran, dan laporan disusun rapi supaya setiap keputusan berdasar pada angka yang benar.',
    tone: 'amber',
  },
  {
    icon: HeartPulse,
    eyebrow: 'Pengasuhan yang dekat',
    title: 'Setiap santri terlihat.',
    text: 'Asrama, kesehatan, perizinan, dan komunikasi wali hadir sebagai satu rekam jejak yang utuh.',
    tone: 'sky',
  },
];

const steps = [
  ['01', 'Satukan fondasi', 'Data santri, unit, kelas, dan pengguna disusun dalam satu rumah yang aman.'],
  ['02', 'Jalankan keseharian', 'Tim bekerja dari ruang yang sesuai perannya, dengan notifikasi di saat yang tepat.'],
  ['03', 'Lihat arah tumbuh', 'Pimpinan membaca pola, bukan sekadar tumpukan laporan, untuk mengambil langkah berikutnya.'],
];

export default function Landing({ auth }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButtonRef = useRef(null);
  const mobileMenuRef = useRef(null);
  const isSignedIn = Boolean(auth?.user);
  const destination = isSignedIn ? dashboardHref(auth?.roles?.[0]) : '/login';

  useEffect(() => {
    if (!menuOpen) return undefined;

    mobileMenuRef.current?.querySelector('a')?.focus();

    const closeMenu = (event) => {
      if (event.type === 'keydown' && event.key !== 'Escape') return;
      if (event.type === 'pointerdown' && (mobileMenuRef.current?.contains(event.target) || menuButtonRef.current?.contains(event.target))) return;

      setMenuOpen(false);
      window.requestAnimationFrame(() => menuButtonRef.current?.focus());
    };

    document.addEventListener('keydown', closeMenu);
    document.addEventListener('pointerdown', closeMenu);

    return () => {
      document.removeEventListener('keydown', closeMenu);
      document.removeEventListener('pointerdown', closeMenu);
    };
  }, [menuOpen]);

  const tiltScene = (event) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;
    event.currentTarget.style.setProperty('--scene-x', `${x * 7}deg`);
    event.currentTarget.style.setProperty('--scene-y', `${y * -6}deg`);
  };

  const resetScene = (event) => {
    event.currentTarget.style.setProperty('--scene-x', '0deg');
    event.currentTarget.style.setProperty('--scene-y', '0deg');
  };

  return (
    <main className="landing-shell">
      <div className="landing-noise" aria-hidden="true" />
      <header className="landing-header">
        <a href="#beranda" className="landing-brand" aria-label="Pesantenku, ke beranda">
          <BrandMark />
          <span>
            <strong>Pesantenku</strong>
            <small>Ruang tumbuh pesantren</small>
          </span>
        </a>
        <nav className="landing-nav" aria-label="Navigasi utama">
          <a href="#keunggulan">Keunggulan</a>
          <a href="#alur">Cara kerja</a>
          <a href="#cerita">Cerita kami</a>
        </nav>
        <a className="landing-header-cta" href={destination}>
          {isSignedIn ? 'Buka dashboard' : 'Masuk'} <ArrowRight size={16} aria-hidden="true" />
        </a>
        <button
          ref={menuButtonRef}
          type="button"
          className="landing-menu-button"
          aria-expanded={menuOpen}
          aria-controls="landing-mobile-menu"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span className="sr-only">{menuOpen ? 'Tutup menu' : 'Buka menu'}</span>
          {menuOpen ? <X aria-hidden="true" /> : <Menu aria-hidden="true" />}
        </button>
      </header>

      <nav ref={mobileMenuRef} id="landing-mobile-menu" className={`landing-mobile-menu ${menuOpen ? 'is-open' : ''}`} aria-label="Navigasi seluler">
        <a href="#keunggulan" onClick={() => setMenuOpen(false)}>Keunggulan</a>
        <a href="#alur" onClick={() => setMenuOpen(false)}>Cara kerja</a>
        <a href="#cerita" onClick={() => setMenuOpen(false)}>Cerita kami</a>
        <a href={destination} onClick={() => setMenuOpen(false)}>{isSignedIn ? 'Buka dashboard' : 'Masuk ke Pesantenku'}</a>
      </nav>

      <section id="beranda" className="landing-hero">
        <div className="landing-hero-copy landing-reveal">
          <p className="landing-kicker"><Sparkles size={15} aria-hidden="true" /> Sistem operasi untuk pesantren masa kini</p>
          <h1>Satu ruang tenang untuk <em>seluruh denyut</em> pesantren.</h1>
          <p className="landing-lead">Pesantenku menyatukan pendidikan, pengasuhan, keuangan, dan komunikasi agar waktu terbaik Anda kembali pada para santri.</p>
          <div className="landing-hero-actions">
            <a className="landing-button landing-button-primary" href={destination}>
              {isSignedIn ? 'Masuk ke dashboard' : 'Mulai dari sini'} <ArrowRight size={18} aria-hidden="true" />
            </a>
            <a className="landing-button landing-button-quiet" href="#keunggulan">Lihat ekosistem <ChevronRight size={18} aria-hidden="true" /></a>
          </div>
          <div className="landing-proof" aria-label="Keunggulan platform">
            <div className="landing-avatar-stack" aria-hidden="true"><i>AK</i><i>NA</i><i>RH</i><b>+</b></div>
            <p><strong>Dibuat untuk ritme pesantren.</strong><br />Bukan template bisnis yang dipaksakan.</p>
          </div>
        </div>

        <div className="landing-scene-wrap landing-reveal landing-reveal-delay" onPointerMove={tiltScene} onPointerLeave={resetScene}>
          <p className="landing-scene-caption"><span /> Pesantenku / live campus</p>
          <div className="landing-orb landing-orb-one" aria-hidden="true" />
          <div className="landing-orb landing-orb-two" aria-hidden="true" />
          <div className="landing-scene" aria-label="Ilustrasi kampus pesantren digital" role="img">
            <div className="landing-grid-floor" aria-hidden="true" />
            <div className="landing-path landing-path-one" aria-hidden="true" />
            <div className="landing-path landing-path-two" aria-hidden="true" />
            <div className="landing-tower landing-tower-left" aria-hidden="true"><span /><span /><span /></div>
            <div className="landing-tower landing-tower-right" aria-hidden="true"><span /><span /><span /></div>
            <div className="landing-campus" aria-hidden="true">
              <div className="landing-campus-roof"><i /><i /><i /></div>
              <div className="landing-campus-front">
                <span className="landing-campus-arch"><i /></span>
                <span className="landing-campus-window one" /><span className="landing-campus-window two" />
                <span className="landing-campus-window three" /><span className="landing-campus-window four" />
              </div>
              <div className="landing-campus-side"><span /><span /><span /></div>
              <div className="landing-campus-sign">PESANTENKU</div>
            </div>
            <div className="landing-tree landing-tree-one" aria-hidden="true"><i /><b /></div>
            <div className="landing-tree landing-tree-two" aria-hidden="true"><i /><b /></div>
            <div className="landing-tree landing-tree-three" aria-hidden="true"><i /><b /></div>
            <div className="landing-scene-person person-one" aria-hidden="true"><i /><b /></div>
            <div className="landing-scene-person person-two" aria-hidden="true"><i /><b /></div>
            <div className="landing-float-card landing-float-card-attendance">
              <span className="landing-float-icon icon-mint"><Check size={14} aria-hidden="true" /></span>
              <span><b>98,4%</b><small>Presensi hari ini</small></span>
            </div>
            <div className="landing-float-card landing-float-card-note">
              <BellRing size={15} aria-hidden="true" />
              <span>Catatan wali<br /><b>terkirim aman</b></span>
            </div>
          </div>
          <div className="landing-scene-status"><span><i /> Semua unit terhubung</span><time>08.24 WIB</time></div>
        </div>
      </section>

      <section className="landing-marquee" aria-label="Area layanan Pesantenku">
        <div><span>Akademik</span><i>*</i><span>Keuangan</span><i>*</i><span>Asrama</span><i>*</i><span>Kesehatan</span><i>*</i><span>Wali Santri</span><i>*</i><span>Akademik</span><i>*</i><span>Keuangan</span><i>*</i></div>
      </section>

      <section id="keunggulan" className="landing-features landing-section">
        <div className="landing-section-heading">
          <p className="landing-kicker"><span /> Satu fondasi, banyak ketenangan</p>
          <h2>Rapi di layar.<br /><em>Hangat</em> dalam keseharian.</h2>
          <p>Setiap modul berbicara dalam bahasa yang sama, sehingga seluruh tim bisa bekerja lebih dekat tanpa kerja ulang.</p>
        </div>
        <div className="landing-feature-grid">
          {featureCards.map(({ icon: Icon, eyebrow, title, text, tone }, index) => (
            <article className={`landing-feature-card ${tone}`} key={title}>
              <div className="landing-card-top"><span className="landing-feature-number">0{index + 1}</span><Icon aria-hidden="true" size={24} strokeWidth={1.8} /></div>
              <p>{eyebrow}</p>
              <h3>{title}</h3>
              <span>{text}</span>
              <a href={destination}>Jelajahi modul <ArrowRight aria-hidden="true" size={16} /></a>
              <div className="landing-card-shape" aria-hidden="true" />
            </article>
          ))}
        </div>
      </section>

      <section id="alur" className="landing-workflow landing-section">
        <div className="landing-workflow-visual">
          <div className="landing-workflow-glow" aria-hidden="true" />
          <div className="landing-workflow-console">
            <div className="landing-console-head"><span /><span /><span /><b>Pesantenku</b><i>live</i></div>
            <div className="landing-console-body">
              <aside><b /><b /><b /><b /></aside>
              <div className="landing-console-main">
                <p>Ringkasan hari ini</p>
                <div className="landing-console-chart"><i /><i /><i /><i /><i /><i /><i /></div>
                <div className="landing-console-rows"><span /><span /><span /></div>
              </div>
            </div>
          </div>
          <div className="landing-scan-line" aria-hidden="true" />
          <div className="landing-workflow-chip chip-one"><UsersRound size={15} /><span>1.284 <small>santri</small></span></div>
          <div className="landing-workflow-chip chip-two"><BarChart3 size={15} /><span>Semua <small>terbaca</small></span></div>
        </div>
        <div className="landing-workflow-copy">
          <p className="landing-kicker"><span /> Dari data, menjadi arah</p>
          <h2>Kerja yang semestinya mengalir.</h2>
          <p>Pesantenku tidak hanya mengumpulkan data. Ia menghubungkan peran, memberi konteks, dan menjaga setiap keputusan tetap dekat dengan kebutuhan santri.</p>
          <ol>
            {steps.map(([number, title, text]) => <li key={number}><b>{number}</b><div><h3>{title}</h3><p>{text}</p></div></li>)}
          </ol>
        </div>
      </section>

      <section id="cerita" className="landing-story landing-section">
        <div className="landing-story-card">
          <div className="landing-story-quote"><Quote aria-hidden="true" size={38} fill="currentColor" /></div>
          <blockquote>"Administrasi adalah cara kita menjaga amanah. Ketika sistemnya tenang, kami punya lebih banyak ruang untuk mendidik."</blockquote>
          <div className="landing-story-person"><span>UH</span><p><strong>Ustadz Hadi</strong><small>Pengasuh & kepala madrasah</small></p></div>
          <div className="landing-story-lines" aria-hidden="true"><i /><i /><i /></div>
        </div>
        <div className="landing-story-side">
          <p className="landing-kicker"><span /> Dibangun dari lapangan</p>
          <h2>Teknologi yang tahu kapan harus <em>hening.</em></h2>
          <p>Kami merancang Pesantenku supaya tidak menambah kebisingan kerja. Yang hadir hanya informasi yang relevan, pada orang yang tepat, pada waktunya.</p>
          <div className="landing-values"><span><ShieldCheck aria-hidden="true" /> Data terjaga</span><span><Clock3 aria-hidden="true" /> Ritme teratur</span></div>
        </div>
      </section>

      <section className="landing-final-cta">
        <div className="landing-final-orbit orbit-a" aria-hidden="true" /><div className="landing-final-orbit orbit-b" aria-hidden="true" />
        <p className="landing-kicker"><Sparkles size={15} aria-hidden="true" /> Mulai ruang kerja yang lebih baik</p>
        <h2>Untuk pesantren yang<br /><em>terus bertumbuh.</em></h2>
        <p>Buka ruang yang menyatukan seluruh ikhtiar baik di pesantren Anda.</p>
        <a className="landing-button landing-button-light" href={destination}>{isSignedIn ? 'Buka dashboard' : 'Masuk ke Pesantenku'} <ArrowRight size={18} aria-hidden="true" /></a>
      </section>

      <footer className="landing-footer">
        <a href="#beranda" className="landing-brand"><BrandMark /><span><strong>Pesantenku</strong><small>Ruang tumbuh pesantren</small></span></a>
        <p>(c) {new Date().getFullYear()} Pesantenku. Dibuat dengan penuh amanah.</p>
        <div><a href="#keunggulan">Platform</a><a href={destination}>Masuk</a></div>
      </footer>
    </main>
  );
}

function BrandMark() {
  return <span className="landing-brand-mark" aria-hidden="true"><i /><b /><em /></span>;
}

function dashboardHref(role = 'super_admin') {
  const panel = {
    owner_pesantren: 'owner',
    admin_keuangan: 'keuangan',
    admin_akademik: 'akademik',
    admin_tu: 'tu',
  }[role] || String(role).replaceAll('_', '-');

  return `/dashboard/${panel}`;
}
