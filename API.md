# Dokumentasi API Internal Jeda

Semua endpoint berada di prefix `/jeda/api`, dipakai oleh JavaScript halaman Jeda sendiri (`public/js/*`). Request dan response berformat JSON, kecuali unggah file (`multipart/form-data`). Karena ini route `web`, permintaan `POST` membawa token CSRF Laravel.

## Ringkasan

| Method | Path | Fungsi | Batas laju |
|---|---|---|---|
| POST | `/analisis-pesan` | Catat hasil Bedah Pesan lokal | 30/menit |
| POST | `/bedah-ai` | Analisis AI Bedah Pesan | 10/menit |
| POST | `/cek-tautan` | Pemeriksaan tautan lengkap | 10/menit, 120/jam per IP |
| POST | `/cek-tautan/penjelasan` | Penjelasan AI hasil Cek Tautan | 5/menit per IP |
| POST | `/cek-file` | Pemeriksaan file (ringkasan + SHA-256) | 10/menit, 120/jam per IP |
| POST | `/cek-file/pindai` | Unggah file ke VirusTotal (opsional) | 3/menit, 15/jam per IP |
| GET | `/cek-file/pindai/status` | Status pemindaian file | 12/menit per IP |
| POST | `/simulasi` | Simpan hasil simulasi (belum dipakai tampilan) | 30/menit |
| GET | `/quiz-progress` | Progres kuis dan level | 60/menit |
| POST | `/quiz-attempt` | Simpan hasil satu ronde kuis | 30/menit |
| POST | `/quiz-recovery/create` | Buat kode pemulihan | 5/menit per IP |
| POST | `/quiz-recovery/redeem` | Pakai kode pemulihan | 5/menit per IP |
| POST | `/checklist` | Simpan status checklist | 60/menit |
| POST | `/aksi-bantuan` | Catat aksi bantuan | 30/menit |
| GET | `/progres` | Ringkasan progres semua modul | 60/menit |

Batas laju bisa diubah lewat `.env` (`JEDA_CEK_PER_MINUTE`, `JEDA_CEK_PER_HOUR`, `JEDA_UPLOAD_PER_MINUTE`, `JEDA_UPLOAD_PER_HOUR`). Respons `429` berarti batas terlampaui.

## Rincian Endpoint

### POST `/analisis-pesan`

```json
{ "indicators": ["tekanan_waktu", "minta_uang"], "text_length": 240 }
```

- `indicators`: array id indikator yang dikenal `BedahScorer` (tanpa duplikat).
- `text_length`: 1–12000.
- Response: `{ "ok": true, "id": 1, "score": 62, "level": "...", "indicators": [...] }`.

### POST `/bedah-ai`

```json
{ "text": "isi pesan yang dicurigai" }
```

- `text`: 10–4000 karakter.
- Error: `503` jika key AI belum diisi, `502` jika layanan AI bermasalah.

### POST `/cek-tautan`

```json
{ "url": "https://contoh.com/login", "submit_to_virustotal": false }
```

- `url`: wajib, maks 2048 karakter.
- `submit_to_virustotal`: opsional; persetujuan eksplisit mengirim alamat ke VirusTotal.
- Response memuat skor 0–10, tingkat (`Tinggi`/`Sedang`/`Rendah`), daftar sinyal, status tiap pemeriksaan, `coverage` (lengkap atau belum lengkap), dan `token` untuk penjelasan AI.

### POST `/cek-tautan/penjelasan`

```json
{ "token": "<40 karakter alfanumerik dari hasil cek-tautan>" }
```

### POST `/cek-file`

```json
{
  "filename": "undangan.apk",
  "size": 4821032,
  "sha256": "<64 heksadesimal, opsional>",
  "detected_type": "apk",
  "content_scanned": true,
  "content_partial": false,
  "flags": { },
  "apk_package": "com.contoh.app",
  "apk_permissions": ["android.permission.READ_SMS"]
}
```

- `detected_type` harus salah satu nilai di `config/jeda_cek.php` → `file_types`.
- Isi file **tidak** dikirim.

### POST `/cek-file/pindai`

`multipart/form-data` dengan field `file` (maks sesuai `UploadLimit`, bawaan 10 MB, mutlak 32 MB) dan `consent` (harus dicentang). Response: `{ "ok": true, "status": "queued", "analysis_id": "..." }`.

### GET `/cek-file/pindai/status?id=<analysis_id>`

Status: `pending`, `busy` (kuota VirusTotal sementara penuh), atau hasil akhir. `404` jika `id` bukan milik sesi.

### POST `/quiz-attempt`

```json
{
  "level": 1, "score": 8, "total_questions": 10, "correct_count": 8,
  "quiz_date": "2026-11-10",
  "answers": [
    { "question_id": "1-01", "selected": 0, "correct": true, "answer": 0, "topic": "Permintaan OTP" }
  ]
}
```

- `answers` harus tepat 10 item dengan `question_id` unik berformat `<level>-<nomor>`.
- EXP dihitung server; klien tidak mengirim EXP.

### GET `/quiz-progress`

Mengembalikan total EXP, level saat ini, level terbuka, dan info kode pemulihan.

### POST `/quiz-recovery/create` dan `/quiz-recovery/redeem`

- `create`: tanpa body. `422` jika belum ada ronde yang dimainkan. Response berisi `code` (`JEDA-XXXX-XXXX-XXXX`), hanya ditampilkan sekali.
- `redeem`: `{ "code": "JEDA-XXXX-XXXX-XXXX" }` (huruf kecil, spasi, atau tanpa tanda hubung tetap diterima).

### POST `/checklist`

```json
{ "feature": "jam-emas", "situation": "umum", "item": "ganti-kata-sandi", "done": true }
```

- `feature` ∈ `jam-emas`, `keluarga`, `kartu`.
- `item`: huruf, angka, titik, garis bawah, atau tanda hubung (maks 100).

### POST `/aksi-bantuan`

```json
{ "feature": "jam-emas", "action": "salin-draf-laporan" }
```

### GET `/progres`

Ringkasan progres pengguna dari semua modul untuk sesi saat ini.

## Format Error

Validasi gagal mengembalikan `422` dengan struktur standar Laravel (`message` dan `errors`). Kegagalan layanan eksternal ditandai lewat `ok: false` dan `message` berbahasa Indonesia, atau status pemeriksaan "belum lengkap" pada hasil tautan/file.
