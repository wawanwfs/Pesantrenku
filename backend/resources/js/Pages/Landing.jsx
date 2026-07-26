import React, { useEffect, useRef, useState } from 'react';
import {
  ArrowRight,
  Baby,
  BarChart3,
  BookOpen,
  BookOpenCheck,
  ChevronRight,
  FileText,
  GraduationCap,
  HeartPulse,
  Home,
  Menu,
  MoveDown,
  Shield,
  Sparkles,
  Stethoscope,
  User,
  Users,
  UsersRound,
  Wallet,
  WalletCards,
  X,
} from 'lucide-react';
import '../../css/landing.css';

let THREE;

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

const roleCards = [
  {
    icon: Shield,
    title: 'Super Admin',
    scope: 'Platform',
    text: 'Menjaga tenant, paket layanan, kebijakan akses, dan kesehatan operasional platform.',
  },
  {
    icon: User,
    title: 'Owner Pesantren',
    scope: 'Manajemen',
    text: 'Membaca arah pesantren dan mengelola anggota, modul, serta kebutuhan lintas unit.',
  },
  {
    icon: Wallet,
    title: 'Admin Keuangan',
    scope: 'Keuangan',
    text: 'Mengelola tagihan, pembayaran, pengeluaran, dan laporan sesuai kewenangan.',
  },
  {
    icon: GraduationCap,
    title: 'Admin Akademik',
    scope: 'Akademik',
    text: 'Menyusun kelas, jadwal, mata pelajaran, nilai, presensi, dan perkembangan belajar.',
  },
  {
    icon: FileText,
    title: 'Tata Usaha',
    scope: 'Administrasi',
    text: 'Merawat data induk, surat, mutasi, alumni, pengumuman, dan kebutuhan administrasi.',
  },
  {
    icon: Baby,
    title: 'Santri',
    scope: 'Pembelajaran',
    text: 'Mengakses jadwal, akademik, perizinan, kesehatan, tagihan, dan informasi pribadi.',
  },
  {
    icon: Users,
    title: 'Wali Murid',
    scope: 'Pendampingan',
    text: 'Mengikuti informasi akademik, administrasi, perizinan, dan kebutuhan santri terkait.',
  },
  {
    icon: BookOpen,
    title: 'Guru',
    scope: 'Pengajaran',
    text: 'Mengelola kelas, presensi, nilai, tugas, materi, dan catatan perkembangan siswa.',
  },
  {
    icon: Home,
    title: 'Musyrif',
    scope: 'Asrama',
    text: 'Mendampingi santri binaan, kamar, perizinan, pelanggaran, dan catatan harian.',
  },
  {
    icon: Stethoscope,
    title: 'Klinik',
    scope: 'Kesehatan',
    text: 'Mengelola antrean, pemeriksaan, obat, rekam medis, surat sakit, dan rujukan.',
  },
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

  return (
    <main className="landing-shell">
      <div className="landing-noise" aria-hidden="true" />
      <header className="landing-header">
        <a href="#beranda" className="landing-brand" aria-label="Pesantrenku, ke beranda">
          <BrandMark />
          <span>
            <strong>Pesantrenku</strong>
            <small>Ruang tumbuh pesantren</small>
          </span>
        </a>
        <nav className="landing-nav" aria-label="Navigasi utama">
          <a href="#modul">Modul</a>
          <a href="#peran">Peran</a>
          <a href="#alur">Alur kerja</a>
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
        <a href="#modul" onClick={() => setMenuOpen(false)}>Modul</a>
        <a href="#peran" onClick={() => setMenuOpen(false)}>Peran</a>
        <a href="#alur" onClick={() => setMenuOpen(false)}>Alur kerja</a>
        <a href={destination} onClick={() => setMenuOpen(false)}>{isSignedIn ? 'Buka dashboard' : 'Masuk ke Pesantrenku'}</a>
      </nav>

      <section id="beranda" className="landing-hero">
        <ThreeCampus />
        <div className="landing-hero-shade" aria-hidden="true" />
        <div className="landing-hero-copy landing-reveal">
          <p className="landing-kicker"><Sparkles size={15} aria-hidden="true" /> Sistem operasi untuk pesantren masa kini</p>
          <h1>Satu ruang tenang untuk <em>seluruh denyut</em> pesantren.</h1>
          <p className="landing-lead">Pesantrenku menyatukan pendidikan, pengasuhan, keuangan, dan komunikasi agar waktu terbaik Anda kembali pada para santri.</p>
          <div className="landing-hero-actions">
            <a className="landing-button landing-button-primary" href={destination}>
              {isSignedIn ? 'Masuk ke dashboard' : 'Mulai dari sini'} <ArrowRight size={18} aria-hidden="true" />
            </a>
            <a className="landing-button landing-button-quiet" href="#modul">Lihat ekosistem <ChevronRight size={18} aria-hidden="true" /></a>
          </div>
          <div className="landing-proof" aria-label="Keunggulan platform">
            <div className="landing-avatar-stack" aria-hidden="true"><i>AK</i><i>NA</i><i>RH</i><b>+</b></div>
            <p><strong>Dibuat untuk ritme pesantren.</strong><br />Bukan template bisnis yang dipaksakan.</p>
          </div>
        </div>

        <div className="landing-hero-status landing-reveal landing-reveal-delay" aria-hidden="true">
          <span><i /> Ilustrasi kampus digital</span>
          <b>Pesantrenku / Terpadu</b>
        </div>
        <a className="landing-next-hint" href="#modul">
          <span>Jelajahi ekosistem</span>
          <MoveDown size={17} aria-hidden="true" />
        </a>
      </section>

      <section className="landing-marquee" aria-label="Area layanan Pesantrenku">
        <div><span>Akademik</span><i>*</i><span>Keuangan</span><i>*</i><span>Asrama</span><i>*</i><span>Kesehatan</span><i>*</i><span>Wali Santri</span><i>*</i><span>Akademik</span><i>*</i><span>Keuangan</span><i>*</i></div>
      </section>

      <section id="modul" className="landing-features landing-section" aria-labelledby="landing-modul-title">
        <div className="landing-section-heading">
          <p className="landing-kicker"><span /> Satu fondasi, banyak ketenangan</p>
          <h2 id="landing-modul-title">Modul yang saling <em>terhubung.</em></h2>
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

      <section id="peran" className="landing-roles landing-section" aria-labelledby="landing-peran-title">
        <div className="landing-section-heading landing-section-heading-wide">
          <p className="landing-kicker"><span /> Ruang kerja sesuai tanggung jawab</p>
          <h2 id="landing-peran-title">Satu sistem, <em>banyak peran.</em></h2>
          <p>Setiap pengguna melihat konteks dan tindakan yang relevan dengan kewenangannya, tanpa kehilangan hubungan dengan proses lain.</p>
        </div>
        <div className="landing-role-grid">
          {roleCards.map(({ icon: Icon, title, scope, text }) => (
            <article className="landing-role-card" key={title}>
              <div className="landing-role-card-top">
                <span className="landing-role-icon"><Icon size={19} strokeWidth={1.8} aria-hidden="true" /></span>
                <span className="landing-role-scope">{scope}</span>
              </div>
              <h3>{title}</h3>
              <p>{text}</p>
            </article>
          ))}
        </div>
      </section>

      <section id="alur" className="landing-workflow landing-section" aria-labelledby="landing-alur-title">
        <div className="landing-workflow-visual" aria-hidden="true">
          <div className="landing-workflow-glow" aria-hidden="true" />
          <div className="landing-workflow-console">
            <div className="landing-console-head"><span /><span /><span /><b>Pesantrenku</b><i>terhubung</i></div>
            <div className="landing-console-body">
              <aside><b /><b /><b /><b /></aside>
              <div className="landing-console-main">
                <p>Ringkasan operasional</p>
                <div className="landing-console-chart"><i /><i /><i /><i /><i /><i /><i /></div>
                <div className="landing-console-rows"><span /><span /><span /></div>
              </div>
            </div>
          </div>
          <div className="landing-scan-line" aria-hidden="true" />
          <div className="landing-workflow-chip chip-one"><UsersRound size={15} /><span>Data <small>santri</small></span></div>
          <div className="landing-workflow-chip chip-two"><BarChart3 size={15} /><span>Lintas <small>modul</small></span></div>
        </div>
        <div className="landing-workflow-copy">
          <p className="landing-kicker"><span /> Dari data, menjadi arah</p>
          <h2 id="landing-alur-title">Kerja yang semestinya mengalir.</h2>
          <p>Pesantrenku tidak hanya mengumpulkan data. Sistem menghubungkan peran, memberi konteks, dan menjaga setiap keputusan tetap dekat dengan kebutuhan santri.</p>
          <ol>
            {steps.map(([number, title, text]) => <li key={number}><b>{number}</b><div><h3>{title}</h3><p>{text}</p></div></li>)}
          </ol>
        </div>
      </section>

      <section className="landing-final-cta">
        <div className="landing-final-orbit orbit-a" aria-hidden="true" /><div className="landing-final-orbit orbit-b" aria-hidden="true" />
        <p className="landing-kicker"><Sparkles size={15} aria-hidden="true" /> Mulai ruang kerja yang lebih baik</p>
        <h2>Untuk pesantren yang<br /><em>terus bertumbuh.</em></h2>
        <p>Buka ruang yang menyatukan seluruh ikhtiar baik di pesantren Anda.</p>
        <a className="landing-button landing-button-light" href={destination}>{isSignedIn ? 'Buka dashboard' : 'Masuk ke Pesantrenku'} <ArrowRight size={18} aria-hidden="true" /></a>
      </section>

      <footer className="landing-footer">
        <a href="#beranda" className="landing-brand"><BrandMark /><span><strong>Pesantrenku</strong><small>Ruang tumbuh pesantren</small></span></a>
        <p>© {new Date().getFullYear()} Pesantrenku. Dibuat dengan penuh amanah.</p>
        <div><a href="#modul">Modul</a><a href="#peran">Peran</a><a href={destination}>Masuk</a></div>
      </footer>
    </main>
  );
}

function ThreeCampus() {
  const [runtimeState, setRuntimeState] = useState('loading');

  useEffect(() => {
    if (!supportsWebGL()) {
      setRuntimeState('fallback');
      return undefined;
    }

    let cancelled = false;
    const cancelLoad = scheduleAfterFirstPaint(async () => {
      try {
        THREE = await import('./LandingThreeRuntime.js');
        if (!cancelled) setRuntimeState('ready');
      } catch {
        if (!cancelled) setRuntimeState('fallback');
      }
    });

    return () => {
      cancelled = true;
      cancelLoad();
    };
  }, []);

  if (runtimeState === 'ready') return <ThreeCampusScene />;

  return (
    <div
      className={`landing-three-scene is-${runtimeState}`}
      role="img"
      aria-label="Kampus Pesantrenku tiga dimensi dengan masjid, asrama, taman, dan santri yang bergerak"
    >
      {runtimeState === 'loading' && (
        <div className="landing-three-loading" aria-live="polite">
          <i aria-hidden="true" />
          <span>Menyiapkan kampus</span>
        </div>
      )}
      <CampusFallback hidden={runtimeState !== 'fallback'} />
    </div>
  );
}

function ThreeCampusScene() {
  const mountRef = useRef(null);
  const canvasRef = useRef(null);
  const [sceneState, setSceneState] = useState('loading');

  useEffect(() => {
    const mount = mountRef.current;
    const hero = mount?.closest('.landing-hero');
    if (!mount || !hero) return undefined;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pointerTarget = new THREE.Vector2();
    const pointer = new THREE.Vector2();
    const startTime = performance.now();
    const lookTarget = new THREE.Vector3();
    let renderer;
    let frameId;
    let mounted = true;
    let scrollProgress = 0;
    let mobile = false;
    let sceneVisible = true;

    try {
      const probe = document.createElement('canvas');
      if (!probe.getContext('webgl2') && !probe.getContext('webgl')) throw new Error('WebGL unavailable');
      if (!canvasRef.current) throw new Error('Canvas unavailable');

      renderer = new THREE.WebGLRenderer({
        canvas: canvasRef.current,
        antialias: true,
        alpha: false,
        powerPreference: 'high-performance',
      });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.35));
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.08;
      renderer.shadowMap.enabled = true;
      renderer.shadowMap.type = THREE.PCFShadowMap;
    } catch {
      setSceneState('fallback');
      return undefined;
    }

    const scene = new THREE.Scene();
    scene.background = new THREE.Color('#92b7b4');
    scene.fog = new THREE.FogExp2('#92b7b4', 0.021);

    const camera = new THREE.PerspectiveCamera(39, 1, 0.1, 90);
    const world = new THREE.Group();
    scene.add(world);

    const materials = createCampusMaterials();
    const campus = buildCampus(world, materials);

    const hemisphere = new THREE.HemisphereLight('#dff7ec', '#355b47', 2.5);
    scene.add(hemisphere);

    const sun = new THREE.DirectionalLight('#fff2c8', 4.2);
    sun.position.set(-9, 15, 8);
    sun.castShadow = true;
    sun.shadow.mapSize.set(512, 512);
    sun.shadow.camera.left = -22;
    sun.shadow.camera.right = 22;
    sun.shadow.camera.top = 22;
    sun.shadow.camera.bottom = -22;
    scene.add(sun);

    const rim = new THREE.DirectionalLight('#78bed0', 1.25);
    rim.position.set(12, 7, -12);
    scene.add(rim);

    const updateScroll = () => {
      scrollProgress = THREE.MathUtils.clamp(window.scrollY / Math.max(hero.offsetHeight * 0.9, 1), 0, 1);
      mount.dataset.scrollProgress = scrollProgress.toFixed(3);
      if (reducedMotion) renderScene(0);
    };

    const updatePointer = (event) => {
      if (reducedMotion) return;
      const bounds = hero.getBoundingClientRect();
      pointerTarget.set(
        THREE.MathUtils.clamp(((event.clientX - bounds.left) / bounds.width) * 2 - 1, -1, 1),
        THREE.MathUtils.clamp(((event.clientY - bounds.top) / bounds.height) * 2 - 1, -1, 1),
      );
    };

    const resetPointer = () => pointerTarget.set(0, 0);

    function renderScene(elapsed = (performance.now() - startTime) / 1000) {
      if (!renderer) return;

      if (!reducedMotion) pointer.lerp(pointerTarget, 0.045);
      else pointer.copy(pointerTarget);

      const cameraBase = mobile ? [10.5, 9.3, 19.5] : [13.5, 8.6, 18.5];
      const targetBase = mobile ? [0, 4.15, 0] : [2.2, 1.5, 0];
      camera.position.set(
        cameraBase[0] + pointer.x * 1.15 + scrollProgress * 2.4,
        cameraBase[1] - pointer.y * 0.55 + scrollProgress * 2,
        cameraBase[2] - scrollProgress * 3.4,
      );
      lookTarget.set(
        targetBase[0] + pointer.x * 0.65,
        targetBase[1] - pointer.y * 0.32 + scrollProgress * 1.5,
        targetBase[2],
      );
      camera.lookAt(lookTarget);

      world.rotation.y = pointer.x * 0.025 + scrollProgress * 0.11;

      if (!reducedMotion) {
        campus.people.forEach((person) => {
          const { phase, speed, centerX, centerZ, radiusX, radiusZ } = person.userData;
          const angle = elapsed * speed + phase;
          person.position.x = centerX + Math.cos(angle) * radiusX;
          person.position.z = centerZ + Math.sin(angle) * radiusZ;
          person.rotation.y = -angle;
        });
        campus.trees.forEach((tree) => {
          tree.rotation.z = Math.sin(elapsed * 0.8 + tree.userData.phase) * 0.018;
        });
        campus.clouds.forEach((cloud, index) => {
          cloud.position.x = ((elapsed * (0.18 + index * 0.035) + cloud.userData.offset) % 34) - 17;
        });
        campus.flags.forEach((flag, index) => {
          flag.rotation.y = Math.sin(elapsed * 2.2 + index) * 0.16;
        });
      }

      renderer.render(scene, camera);
    }

    let lastFrame = 0;
    const animate = (time) => {
      if (!sceneVisible) {
        frameId = undefined;
        return;
      }
      frameId = window.requestAnimationFrame(animate);
      if (time - lastFrame < 1000 / 30) return;
      lastFrame = time;
      renderScene();
    };

    const startAnimation = () => {
      if (!reducedMotion && sceneVisible && frameId === undefined) frameId = window.requestAnimationFrame(animate);
    };

    const resize = () => {
      const { width, height } = mount.getBoundingClientRect();
      if (!width || !height) return;
      mobile = width < 760;
      camera.aspect = width / height;
      camera.fov = mobile ? 44 : 39;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height, false);
      campus.root.position.x = mobile ? 0 : 3.4;
      campus.root.position.z = mobile ? 1.2 : 0;
      campus.root.scale.setScalar(mobile ? 0.86 : 1);
      renderScene(reducedMotion ? 0 : (performance.now() - startTime) / 1000);
    };

    const loseContext = (event) => {
      event.preventDefault();
      window.cancelAnimationFrame(frameId);
      frameId = undefined;
      if (mounted) setSceneState('fallback');
    };

    const resizeObserver = new ResizeObserver(resize);
    const visibilityObserver = new IntersectionObserver(([entry]) => {
      sceneVisible = entry.isIntersecting;
      if (sceneVisible) startAnimation();
      else {
        window.cancelAnimationFrame(frameId);
        frameId = undefined;
      }
    }, { rootMargin: '120px 0px' });
    resizeObserver.observe(mount);
    visibilityObserver.observe(hero);
    hero.addEventListener('pointermove', updatePointer, { passive: true });
    hero.addEventListener('pointerleave', resetPointer);
    window.addEventListener('scroll', updateScroll, { passive: true });
    renderer.domElement.addEventListener('webglcontextlost', loseContext);
    updateScroll();
    resize();

    if (mounted) setSceneState('ready');
    startAnimation();

    return () => {
      mounted = false;
      window.cancelAnimationFrame(frameId);
      resizeObserver.disconnect();
      visibilityObserver.disconnect();
      hero.removeEventListener('pointermove', updatePointer);
      hero.removeEventListener('pointerleave', resetPointer);
      window.removeEventListener('scroll', updateScroll);
      renderer.domElement.removeEventListener('webglcontextlost', loseContext);
      scene.traverse((object) => {
        object.geometry?.dispose();
        if (Array.isArray(object.material)) object.material.forEach((material) => material.dispose());
        else object.material?.dispose();
      });
      renderer.dispose();
    };
  }, []);

  return (
    <div
      ref={mountRef}
      className={`landing-three-scene is-${sceneState}`}
      role="img"
      aria-label="Kampus Pesantrenku tiga dimensi dengan masjid, asrama, taman, dan santri yang bergerak"
    >
      <canvas ref={canvasRef} aria-hidden="true" />
      {sceneState === 'loading' && (
        <div className="landing-three-loading" aria-live="polite">
          <i aria-hidden="true" />
          <span>Menyiapkan kampus</span>
        </div>
      )}
      <CampusFallback hidden={sceneState !== 'fallback'} />
    </div>
  );
}

function CampusFallback({ hidden }) {
  return (
    <div className="landing-three-fallback" aria-hidden={hidden}>
      <span className="fallback-building" />
      <span className="fallback-minaret fallback-minaret-left" />
      <span className="fallback-minaret fallback-minaret-right" />
    </div>
  );
}

function supportsWebGL() {
  try {
    const probe = document.createElement('canvas');
    return Boolean(probe.getContext('webgl2') || probe.getContext('webgl'));
  } catch {
    return false;
  }
}

function scheduleAfterFirstPaint(task) {
  let idleId;
  let timeoutId;
  const frameId = window.requestAnimationFrame(() => {
    if ('requestIdleCallback' in window) {
      idleId = window.requestIdleCallback(task, { timeout: 1200 });
      return;
    }

    timeoutId = window.setTimeout(task, 0);
  });

  return () => {
    window.cancelAnimationFrame(frameId);
    if (idleId !== undefined) window.cancelIdleCallback(idleId);
    if (timeoutId !== undefined) window.clearTimeout(timeoutId);
  };
}

function createCampusMaterials() {
  const standard = (color, options = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.78, metalness: 0.02, ...options });

  return {
    grass: standard('#537f4b'),
    grassLight: standard('#79a75c'),
    earth: standard('#82644f'),
    path: standard('#d4b56c'),
    wall: standard('#eee5c6'),
    wallShade: standard('#c9d2ae'),
    green: standard('#174f44'),
    greenLight: standard('#2f7561'),
    roof: standard('#9f5136'),
    roofDark: standard('#69362c'),
    gold: standard('#d6ad4c', { metalness: 0.18 }),
    window: standard('#78b8bd', { emissive: '#20484d', emissiveIntensity: 0.45 }),
    trunk: standard('#654536'),
    leaf: standard('#2f704d'),
    leafLight: standard('#74a84f'),
    water: new THREE.MeshPhysicalMaterial({ color: '#5d9da9', roughness: 0.2, metalness: 0.05, transmission: 0.08 }),
  };
}

function buildCampus(world, materials) {
  const root = new THREE.Group();
  const people = [];
  const trees = [];
  const clouds = [];
  const flags = [];
  world.add(root);

  const water = new THREE.Mesh(new THREE.PlaneGeometry(80, 80), materials.water);
  water.rotation.x = -Math.PI / 2;
  water.position.y = -0.82;
  water.receiveShadow = true;
  world.add(water);

  const island = new THREE.Mesh(new THREE.CylinderGeometry(16.5, 17.2, 1.25, 8), materials.earth);
  island.position.y = -0.55;
  island.receiveShadow = true;
  root.add(island);

  const lawn = new THREE.Mesh(new THREE.CylinderGeometry(16.2, 16.2, 0.34, 8), materials.grass);
  lawn.position.y = 0.07;
  lawn.receiveShadow = true;
  root.add(lawn);

  addBox(root, [6.8, 0.14, 9.5], [0, 0.3, 6.2], materials.path, false);
  addBox(root, [20, 0.14, 2.2], [0, 0.31, 2.1], materials.path, false);
  addBox(root, [9, 0.16, 7.2], [0, 0.33, 0], materials.wallShade, false);

  const mosque = new THREE.Group();
  root.add(mosque);
  addBox(mosque, [7.4, 3, 4.8], [0, 1.8, 0], materials.wall);
  addBox(mosque, [7.8, 0.38, 5.2], [0, 3.48, 0], materials.green);

  const domeBase = new THREE.Mesh(new THREE.CylinderGeometry(1.7, 1.82, 0.48, 32), materials.greenLight);
  domeBase.position.y = 3.82;
  domeBase.castShadow = true;
  mosque.add(domeBase);

  const dome = new THREE.Mesh(new THREE.SphereGeometry(1.58, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), materials.green);
  dome.position.y = 4.02;
  dome.scale.y = 0.85;
  dome.castShadow = true;
  mosque.add(dome);

  const finial = new THREE.Group();
  addCylinder(finial, 0.06, 0.62, [0, 0.25, 0], materials.gold);
  const finialTip = new THREE.Mesh(new THREE.ConeGeometry(0.16, 0.42, 12), materials.gold);
  finialTip.position.y = 0.7;
  finial.add(finialTip);
  finial.position.y = 5.25;
  mosque.add(finial);

  [-2.55, -1.25, 0, 1.25, 2.55].forEach((x, index) => {
    addArchedWindow(mosque, [x, 1.75, 2.425], materials.window, index === 2 ? 0.85 : 0.58);
  });

  [-5.7, 5.7].forEach((x, index) => {
    const wing = new THREE.Group();
    addBox(wing, [4.2, 2.25, 4.2], [0, 1.35, 0.1], materials.wallShade);
    addBox(wing, [4.5, 0.42, 4.5], [0, 2.7, 0.1], index ? materials.roof : materials.roofDark);
    [-1.25, 0, 1.25].forEach((windowX) => addArchedWindow(wing, [windowX, 1.35, 2.22], materials.window, 0.46));
    wing.position.x = x;
    root.add(wing);
  });

  [-4.35, 4.35].forEach((x) => root.add(createMinaret(x, 0.6, materials)));

  [-6.6, 6.6].forEach((x, index) => {
    const dorm = new THREE.Group();
    addBox(dorm, [5.3, 2.1, 3.2], [0, 1.25, 0], materials.wall);
    const roof = new THREE.Mesh(new THREE.CylinderGeometry(2.12, 2.12, 5.65, 3), index ? materials.roof : materials.green);
    roof.rotation.z = Math.PI / 2;
    roof.rotation.y = Math.PI / 2;
    roof.position.y = 2.85;
    roof.castShadow = true;
    dorm.add(roof);
    [-1.7, -0.55, 0.55, 1.7].forEach((windowX) => addArchedWindow(dorm, [windowX, 1.25, 1.63], materials.window, 0.38));
    dorm.position.set(x, 0, -6.1);
    root.add(dorm);
  });

  const treePositions = [
    [-11, 0.3, -5], [-12.5, 0.3, 1], [-10.5, 0.3, 7], [-6.5, 0.3, 8.8],
    [10.8, 0.3, -5.5], [12.5, 0.3, 0], [11, 0.3, 7], [7.1, 0.3, 9.3],
    [-8.8, 0.3, -9.2], [8.4, 0.3, -9.4], [0, 0.3, -10.8],
  ];
  treePositions.forEach((position, index) => {
    const tree = createTree(materials, index);
    tree.position.set(...position);
    tree.scale.setScalar(0.82 + (index % 3) * 0.12);
    trees.push(tree);
    root.add(tree);
  });

  const personColors = ['#e1aa4a', '#d76b4d', '#3c7f78', '#e8d7b0', '#7c4663', '#4d739b'];
  personColors.forEach((color, index) => {
    const person = createPerson(color);
    person.userData = {
      phase: index * 1.04,
      speed: 0.12 + (index % 3) * 0.018,
      centerX: index % 2 ? 2.8 : -2.8,
      centerZ: 5.2 + (index % 2) * 1.7,
      radiusX: 2.2 + (index % 2),
      radiusZ: 1.4 + (index % 3) * 0.2,
    };
    people.push(person);
    root.add(person);
  });

  [-7.8, 7.8].forEach((x, index) => {
    const flag = createFlag(index ? '#d7b34f' : '#f0e6c6', materials.green);
    flag.position.set(x, 0.3, 4.6);
    flags.push(flag.children[1]);
    root.add(flag);
  });

  for (let index = 0; index < 3; index += 1) {
    const cloud = createCloud();
    cloud.position.set(index * 8 - 8, 9 + index * 1.4, -14 - index * 2);
    cloud.userData.offset = index * 11;
    clouds.push(cloud);
    world.add(cloud);
  }

  root.traverse((object) => {
    if (object.isMesh) {
      object.castShadow = object.castShadow !== false;
      object.receiveShadow = true;
    }
  });

  return { root, people, trees, clouds, flags };
}

function addBox(parent, size, position, material, castShadow = true) {
  const mesh = new THREE.Mesh(new THREE.BoxGeometry(...size), material);
  mesh.position.set(...position);
  mesh.castShadow = castShadow;
  mesh.receiveShadow = true;
  parent.add(mesh);
  return mesh;
}

function addCylinder(parent, radius, height, position, material, segments = 16) {
  const mesh = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius, height, segments), material);
  mesh.position.set(...position);
  mesh.castShadow = true;
  parent.add(mesh);
  return mesh;
}

function addArchedWindow(parent, position, material, width) {
  const group = new THREE.Group();
  addBox(group, [width, 0.72, 0.08], [0, -0.16, 0], material, false);
  const top = new THREE.Mesh(new THREE.CircleGeometry(width / 2, 18), material);
  top.position.y = 0.2;
  group.add(top);
  group.position.set(...position);
  parent.add(group);
}

function createMinaret(x, z, materials) {
  const minaret = new THREE.Group();
  addCylinder(minaret, 0.48, 4.8, [0, 2.45, 0], materials.wall, 20);
  addCylinder(minaret, 0.7, 0.28, [0, 4.5, 0], materials.greenLight, 20);
  addCylinder(minaret, 0.38, 1.15, [0, 5.08, 0], materials.wallShade, 20);
  const cap = new THREE.Mesh(new THREE.ConeGeometry(0.62, 1.25, 20), materials.green);
  cap.position.y = 6.25;
  cap.castShadow = true;
  minaret.add(cap);
  addCylinder(minaret, 0.04, 0.48, [0, 6.98, 0], materials.gold, 10);
  minaret.position.set(x, 0.3, z);
  return minaret;
}

function createTree(materials, index) {
  const tree = new THREE.Group();
  addCylinder(tree, 0.18, 1.8, [0, 0.9, 0], materials.trunk, 8);
  const lower = new THREE.Mesh(new THREE.IcosahedronGeometry(0.95, 1), index % 2 ? materials.leaf : materials.leafLight);
  lower.position.y = 2;
  lower.scale.set(1.15, 1, 1.05);
  lower.castShadow = true;
  tree.add(lower);
  const upper = new THREE.Mesh(new THREE.IcosahedronGeometry(0.72, 1), materials.leaf);
  upper.position.set(0.18, 2.72, 0.05);
  upper.castShadow = true;
  tree.add(upper);
  tree.userData.phase = index * 0.71;
  return tree;
}

function createPerson(color) {
  const person = new THREE.Group();
  const clothing = new THREE.MeshStandardMaterial({ color, roughness: 0.82 });
  const skin = new THREE.MeshStandardMaterial({ color: '#8d5d45', roughness: 0.9 });
  const body = new THREE.Mesh(new THREE.CapsuleGeometry(0.16, 0.42, 4, 8), clothing);
  body.position.y = 0.52;
  body.castShadow = true;
  person.add(body);
  const head = new THREE.Mesh(new THREE.SphereGeometry(0.15, 12, 8), skin);
  head.position.y = 1.02;
  head.castShadow = true;
  person.add(head);
  person.scale.setScalar(0.78);
  return person;
}

function createFlag(color, poleMaterial) {
  const flag = new THREE.Group();
  addCylinder(flag, 0.04, 3, [0, 1.5, 0], poleMaterial, 8);
  const fabric = new THREE.Mesh(
    new THREE.PlaneGeometry(1.15, 0.58, 4, 1),
    new THREE.MeshStandardMaterial({ color, side: THREE.DoubleSide, roughness: 0.76 }),
  );
  fabric.position.set(0.56, 2.7, 0);
  flag.add(fabric);
  return flag;
}

function createCloud() {
  const cloud = new THREE.Group();
  const material = new THREE.MeshStandardMaterial({ color: '#d9e6df', roughness: 1, transparent: true, opacity: 0.68 });
  [[0, 0, 0, 1.1], [1, 0.15, 0, 0.85], [-1, 0.05, 0, 0.72], [0.25, 0.45, 0, 0.78]].forEach(([x, y, z, scale]) => {
    const puff = new THREE.Mesh(new THREE.IcosahedronGeometry(0.85, 1), material);
    puff.position.set(x, y, z);
    puff.scale.setScalar(scale);
    cloud.add(puff);
  });
  return cloud;
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
