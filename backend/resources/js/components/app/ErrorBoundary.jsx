import React from 'react';
import { StatePanel } from './AppDialogs.jsx';

/**
 * Menangkap galat render (mis. chunk lazy gagal dimuat) agar satu komponen yang rusak tidak
 * mengosongkan seluruh shell. Menawarkan tindakan nyata alih-alih kode status.
 * `onRetry` menggantikan perilaku coba-lagi bawaan (mis. muat ulang halaman untuk chunk yang gagal). Ganti `resetKey` (mis. halaman aktif) untuk memulihkan otomatis saat pengguna berpindah.
 */
export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { failed: false };
    this.retry = this.retry.bind(this);
  }

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidUpdate(previous) {
    if (this.state.failed && previous.resetKey !== this.props.resetKey) this.setState({ failed: false });
  }

  componentDidCatch(error) {
    // Jangan menelan galat tanpa jejak: tampilkan di konsol agar tetap bisa didiagnosis.
    console.error('Komponen gagal dirender:', error);
  }

  retry() {
    // Modul lazy yang gagal diimpor tetap gagal di dalam cache peramban; pemanggil dapat meminta muat ulang penuh.
    if (this.props.onRetry) this.props.onRetry();
    else this.setState({ failed: false });
  }

  render() {
    if (!this.state.failed) return this.props.children;
    return (
      <StatePanel
        icon="TriangleAlert"
        tone="error"
        compact={this.props.compact}
        title={this.props.title || 'Bagian ini gagal dimuat'}
        description={this.props.description || 'Terjadi masalah saat menampilkan bagian ini. Data Anda aman; coba muat ulang bagian ini.'}
        action={{ label: 'Coba lagi', onClick: this.retry }}
      />
    );
  }
}
