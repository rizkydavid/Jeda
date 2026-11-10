/*
 * Service worker Jeda. Hanya dua tugas:
 *
 * 1. Membuat Jeda bisa dipasang di layar utama (syarat PWA).
 * 2. Menerima teks dari menu "Bagikan" Android TANPA mengirimnya ke server Jeda:
 *    isi kiriman disimpan sementara di Cache lokal browser, lalu halaman /bagikan
 *    membacanya dan langsung menghapusnya.
 *
 * Halaman, CSS, dan JS lain TIDAK di-cache di sini, jadi setiap pembaruan web langsung terlihat.
 * Letak file ini harus di root public/ supaya cakupannya mencakup /bagikan.
 */
const SHARE_CACHE = 'jeda-share-v1';
const SHARE_KEY_PATH = '__jeda_terima__';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
  const request = event.request;
  // Selain kiriman dari menu Bagikan, biarkan browser menangani seperti biasa (tanpa cache).
  if (request.method !== 'POST') return;

  const target = new URL('bagikan', self.registration.scope);
  const asked = new URL(request.url);
  if (asked.origin !== target.origin || asked.pathname !== target.pathname) return;

  event.respondWith(terimaKiriman(request));
});

async function terimaKiriman(request) {
  try {
    const form = await request.formData();
    const kiriman = {
      title: String(form.get('title') || ''),
      text: String(form.get('text') || ''),
      url: String(form.get('url') || ''),
      t: Date.now(),
    };
    const cache = await caches.open(SHARE_CACHE);
    const kunci = new URL(SHARE_KEY_PATH, self.registration.scope).href;
    await cache.put(kunci, new Response(JSON.stringify(kiriman), {
      headers: { 'Content-Type': 'application/json' },
    }));
  } catch (_) {
    // Kalau gagal, tetap arahkan ke halaman Bagikan. Pengguna masih bisa menempel pesan manual.
  }
  return Response.redirect(new URL('bagikan?dibagikan=1', self.registration.scope).href, 303);
}