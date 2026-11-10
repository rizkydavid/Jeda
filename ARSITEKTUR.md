# Arsitektur Jeda

Dokumen teknis pendukung `README.md`. Menjelaskan susunan aplikasi, alur data tiap fitur, dan sistem skor.

## 1. Gambaran Umum

Jeda adalah aplikasi Laravel 12 monolit dengan frontend Blade dan JavaScript biasa. Halaman dirender lewat `Route::view`, sedangkan interaksi dinamis memanggil API internal di prefix `/jeda/api`.

```
Browser (Blade + JS)
   │  halaman: /, /cek, /latihan, /jam-emas, /keluarga, /bagikan
   │  fetch JSON
   ▼
routes/web.php ──► Controller ──► Services/Cek, Support ──► Database (MySQL)
                                        │
                                        └──► API eksternal (opsional):
                                             Safe Browsing, VirusTotal, RDAP, Gemini, Anthropic
```

Prinsip desain:

1. **Guest-first.** Tanpa akun; data diikat ke `session_key`.
2. **Proses sensitif di browser.** Teks pesan dan isi file dibaca di browser; server hanya menerima ringkasan.
3. **Gagal bukan aman.** Pemeriksaan eksternal yang gagal ditandai "belum lengkap".
4. **Hasil = indikator risiko**, bukan vonis.

## 2. Sesi dan Identitas Pengguna

`HasSessionKey::sessionKey()` mengambil 64 karakter pertama dari `sha256(session_id)`. Nilai ini menjadi `session_key` di semua tabel Jeda. Session ID mentah tidak pernah disimpan di tabel Jeda.

Sesi memakai driver `database` (`SESSION_LIFETIME` bawaan 120 menit). Untuk memindahkan progres kuis antarperangkat dipakai **kode pemulihan** (lihat bagian 6).

## 3. Alur Per Fitur

### 3.1 Bedah Pesan

| Lapis | Lokasi | Data yang dikirim ke server |
|---|---|---|
| Lokal | `public/js/bedah.js` | Hanya id indikator dan panjang teks (`POST /analisis-pesan`) |
| AI (opsional) | `BedahAiService` | Teks (setelah nomor HP, rekening, dan email disamarkan) lewat `POST /bedah-ai` |

`BedahScorer` di server menghitung ulang skor 0–100 dari id indikator yang valid, jadi skor tidak bisa dipalsukan dari klien. Ada bonus untuk rantai aksi (uang, kode, unduh aplikasi, tautan menyamar) dan kombinasi minimal 3 kelompok tanda. Hasil AI hanya dipakai sebagai daftar indikator yang kemudian dinilai `BedahScorer` juga.

### 3.2 Cek Tautan

Orkestrasi di `LinkAnalysisService`:

```
URL masuk ──► UrlHeuristics (pola alamat, merek, TLD, pemendek)
          ──► NetworkGuard + HostResolver (tolak jaringan internal / port selain 80, 443)
          ──► RedirectTracer (maks 5 hop, 10 dtk total, 5 dtk per hop)
          ──► PageInspector (baca ≤ 256 KB isi halaman, cari form login palsu)
          ──► SafeBrowsingClient (opsional)
          ──► VirusTotalUrlClient (opsional; kirim URL hanya jika pengguna setuju)
          ──► DomainAgeLookup (RDAP)
          ──► RiskScorer ──► hasil + token (40 karakter)
```

Penjelasan AI dipisah: `POST /cek-tautan/penjelasan` menerima `token` hasil pemeriksaan, lalu `GeminiExplanationService` membuat penjelasan awam. Kegagalan AI tidak mengubah hasil utama.

### 3.3 Cek File

1. Browser (`file-inspect.js`) membaca file, mendeteksi jenis dari byte awal, menghitung SHA-256, dan untuk APK membaca daftar izin dari `AndroidManifest.xml`.
   - ≤ 50 MB: dibaca penuh.
   - 50 MB – 2 GB: dibaca sebagian dan bertahap.
   - > 2 GB: hanya nama dan ukuran.
2. Browser mengirim ringkasan ke `POST /cek-file`; `FileAnalysisService` menilai ekstensi, ekstensi ganda, ketidakcocokan jenis, makro, izin APK, dan nama bernada penipuan.
3. **Pemindaian antivirus (opsional):** `POST /cek-file/pindai` meneruskan file ke VirusTotal setelah `consent` dicentang. `GET /cek-file/pindai/status` mengecek hasil; hanya `analysis_id` milik sesi sendiri yang boleh ditanyakan.

### 3.4 Latihan (Kuis)

- Bank soal: `public/js/latihan-data.js` (10 level × 12 soal; tiap ronde 10 soal acak).
- Server memvalidasi hasil ronde (`POST /quiz-attempt`), menghitung EXP lewat `QuizProgression`, dan menyimpannya di `jeda_quiz_attempts`.
- `QuizProgressStore` menyusun payload progres (total EXP, level terbuka, info pemulihan).
- Level dan EXP diatur di `config/jeda_quiz.php`.

### 3.5 Jam Emas dan Keluarga

Halaman statis (controller `JamEmasController` dan `KeluargaController` masih kosong). Status checklist disimpan lewat `ProgresController::saveChecklist` (`feature` ∈ `jam-emas`, `keluarga`, `kartu`). Aksi bantuan yang diklik dicatat di `jeda_support_actions`.

### 3.6 Bagikan (PWA)

`manifest.json` mendaftarkan `share_target` (`POST /bagikan`). Service worker `public/sw.js` mencegat kiriman itu, menyimpannya sementara di Cache API lokal, lalu mengarahkan ke halaman `/bagikan` yang membaca dan menghapusnya. Route `POST /bagikan` di Laravel hanya pengalih cadangan (303) tanpa verifikasi CSRF dan tidak memproses isi kiriman.

## 4. Sistem Skor Cek Tautan dan Cek File

`RiskScorer::evaluate()` bekerja dalam lima langkah:

1. **Deduplikasi:** satu id sinyal dihitung sekali (bobot terbesar).
2. **Tanda lemah** (`needsOther`) hanya dihitung jika ada minimal satu tanda kuat.
3. **Batas per kelompok** (`group_caps` di `config/jeda_cek.php`) agar satu jenis tanda tidak mendominasi.
4. **Kombinasi:** bila ≥ 3 kelompok aktif, ditambah 1 poin.
5. **Lantai skor:** temuan terkonfirmasi (misalnya Safe Browsing/VirusTotal positif) memaksa skor minimal; skor maksimum 10.

Tingkat risiko: **Tinggi** (≥ 7), **Sedang** (≥ 4), **Rendah** (≥ 1), selain itu belum terdeteksi tanda.

**Cakupan pemeriksaan.** Pemeriksaan kritis: `safe_browsing`, `virustotal`, `domain_age`, `redirect`. Jika statusnya `unavailable`, `not_configured`, `pending`, atau `blocked`, cakupan hasil menjadi "belum lengkap" dan UI menampilkan catatan, bukan menyatakan aman.

## 5. Pengamanan

| Risiko | Mitigasi |
|---|---|
| SSRF saat server membuka tautan | `NetworkGuard` + `HostResolver`: tolak IP privat/loopback, hanya port 80 dan 443, alamat internal tidak diteruskan ke pihak ketiga |
| Pemborosan kuota API | Rate limiter per IP (`AppServiceProvider`), batas VirusTotal 4/menit dan 450/hari |
| Penebakan kode pemulihan | Kode 60 bit, hash SHA-256, throttle 5/menit per IP |
| Unduhan besar saat melacak tautan | Isi dibaca maks 256 KB, dihentikan jika melewati 1,5 MB |
| Kebocoran data pribadi ke AI | Penyamaran nomor HP, rekening, email di server; teks tidak disimpan |
| Prompt injection pada AI | Isi pesan diperlakukan sebagai data |
| Penyalahgunaan status pemindaian | `analysis_id` harus ada di sesi pemiliknya |

## 6. Kode Pemulihan Progres

- Format `JEDA-XXXX-XXXX-XXXX` (alfabet tanpa `I`, `O`, `0`, `1`).
- `POST /quiz-recovery/create`: butuh minimal satu ronde; membuat kode baru dan menghapus kode lama milik sesi tersebut.
- `POST /quiz-recovery/redeem`: memindahkan progres ke sesi yang memakai kode.
- Server hanya menyimpan `code_hash` (SHA-256).

## 7. Konfigurasi yang Bisa Diubah Tanpa Mengubah Kode

| File | Isi |
|---|---|
| `config/jeda_cek.php` | Batas ukuran file, batas redirect, daftar merek/TLD/pemendek/hosting gratis, ekstensi berbahaya, izin APK, bobot, batas laju |
| `config/jeda_quiz.php` | Soal per ronde, rumus EXP, nama dan syarat tiap level |
| `config/services.php` | Key dan model API eksternal |

## 8. Testing

| Lokasi | Yang diuji |
|---|---|
| `tests/Feature/Cek/` | RiskScorer, UrlHeuristics, RedirectTracer, NetworkGuard, LinkCheck, FileCheck, FileScan, GeminiExplanation |
| `tests/Feature/LatihanQuizTest.php`, `QuizRecoveryTest.php` | Kuis dan kode pemulihan |
| `tests/Unit/` | `QuizProgression`, `RecoveryCode` |
| `tests/js/file-inspect.test.cjs` | Deteksi jenis file di browser (`node --test`) |
