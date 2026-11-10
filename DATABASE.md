# Dokumentasi Database Jeda

Database bawaan: **MySQL** (`DB_DATABASE=jeda`). Seluruh tabel Jeda dikaitkan lewat `session_key` (hash SHA-256 dari session ID), **bukan** lewat akun pengguna. Skema dibuat oleh migrasi di `database/migrations/` (`php artisan migrate`).

## Tabel Jeda

### `jeda_message_analyses`
Catatan hasil Bedah Pesan. Isi pesan tidak disimpan.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| session_key | string(100), index | |
| risk_score | tinyint unsigned | Skor 0–100 |
| risk_level | string(30) | |
| indicators | json, null | Daftar id indikator |
| text_length | int unsigned | Panjang teks, bukan isinya |
| created_at, updated_at | timestamp | |

### `jeda_link_checks`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| session_key | string(100), index | |
| token | string(40), unique, null | Dipakai untuk penjelasan AI |
| hostname | string(253), null | |
| final_hostname | string(253), null | Host setelah pengalihan |
| risk_score | tinyint unsigned | Skor 0–10 |
| risk_level | string(30) | |
| indicators | json, null | |
| signals | json, null | Sinyal terperinci |
| domain_age_days | int unsigned, null | Dari RDAP |
| coverage | string(20), null | `lengkap` atau belum lengkap |
| checks | json, null | Status tiap pemeriksaan |
| explanation | json, null | Penjelasan AI |

### `jeda_file_checks`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| session_key | string(100), index | |
| extension | string(20), null | |
| detected_type | string(20), null | Jenis dari byte awal |
| size_bytes | bigint unsigned, null | |
| double_extension | boolean | Mis. `laporan.pdf.exe` |
| executable_type | boolean | |
| risk_score | tinyint unsigned | |
| risk_level | string(30), null | |
| indicators | json, null | |
| coverage | string(20), null | |
| signals | json, null | |
| checks | json, null | |

Nama file lengkap dan isi file tidak disimpan.

### `jeda_simulation_attempts`
| Kolom | Tipe |
|---|---|
| id, session_key (index) | |
| scenario | string(80), bawaan `rekruter-1` |
| selected_option | tinyint unsigned |
| strong_choice | boolean |
| score | tinyint unsigned |

### `jeda_quiz_attempts`
Satu baris per ronde kuis.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| session_key | string(100), index | |
| level | tinyint unsigned | |
| quiz_date | date | |
| score | tinyint unsigned | |
| total_questions | tinyint unsigned | Bawaan 10 |
| correct_count | tinyint unsigned | |
| answers | json, null | Jawaban per soal |
| exp_earned | int unsigned | EXP yang didapat, dihitung server dari `config/jeda_quiz.php` |
| xp_earned | int unsigned | Kolom lama dari migrasi `2026_11_05_053530`; yang dipakai aplikasi adalah `exp_earned` |

Indeks gabungan: (`session_key`, `level`, `quiz_date`).

### `jeda_checklist_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| session_key | string(100) | |
| feature | string(50) | `jam-emas`, `keluarga`, `kartu` |
| situation | string(80) | Bawaan `umum` |
| item_key | string(100) | |
| is_done | boolean | |

Unik pada (`session_key`, `feature`, `situation`, `item_key`); indeks pada (`session_key`, `feature`).

### `jeda_support_actions`
| Kolom | Tipe |
|---|---|
| id, session_key (index) | |
| feature | string(50) |
| action | string(80) |
| metadata | json, null |

### `jeda_recovery_codes`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| code_hash | string(64), unique | SHA-256 dari kode; kode asli tidak disimpan |
| session_key | string(100), index | Pemegang progres saat ini |
| redeemed_count | int unsigned | |
| last_redeemed_at | timestamp, null | |

## Tabel Bawaan Laravel

| Tabel | Fungsi |
|---|---|
| `users` | Bawaan Laravel; Jeda tidak memakai login, jadi tabel ini tidak dipakai fitur utama |
| `password_reset_tokens`, `sessions` | Sesi pengguna (`SESSION_DRIVER=database`) |
| `cache`, `cache_locks` | Cache dan rate limiter (`CACHE_STORE=database`) |
| `jobs`, `job_batches`, `failed_jobs` | Antrean (`QUEUE_CONNECTION=database`) |

## Urutan Migrasi

1. `0001_01_01_000000` users, sessions
2. `0001_01_01_000001` cache
3. `0001_01_01_000002` jobs
4. `2026_10_31_000001` tabel fitur Jeda
5. `2026_11_02_000001` jeda_quiz_attempts
6. `2026_11_05_*` kolom deteksi tautan, kolom `xp_earned` dan `exp_earned` pada kuis
7. `2026_11_06_*` kolom penjelasan tautan dan deteksi file
8. `2026_11_09_000001` jeda_recovery_codes

## Catatan Operasional

- Karena sesi, cache, dan antrean memakai database, pastikan `php artisan migrate` dijalankan sebelum aplikasi dipakai.
- Data per sesi bersifat sementara; tidak ada mekanisme akun. Pemulihan lintas perangkat memakai kode pemulihan.
- Untuk produksi gunakan database terkelola (MySQL/PostgreSQL), bukan SQLite di filesystem container.
