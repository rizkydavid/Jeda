# Jeda — Edukasi Keamanan Digital

**Jeda** merupakan sistem edukasi keamanan digital yang dirancang untuk membantu pengguna mengenali potensi penipuan, memahami risiko aktivitas digital, dan membangun kebiasaan yang lebih aman saat menggunakan teknologi.

Melalui berbagai fitur pemeriksaan, latihan, dan edukasi, Jeda mendorong pengguna untuk lebih teliti sebelum mempercayai pesan, membuka tautan, atau melakukan tindakan yang berpotensi membahayakan keamanan digital.

## 🎯 Tujuan Sistem

- Meningkatkan kesadaran pengguna terhadap ancaman keamanan digital.
- Membantu pengguna mengenali indikasi penipuan dan rekayasa sosial.
- Memberikan edukasi melalui latihan dan simulasi.
- Mendukung pembelajaran keamanan digital secara bertahap.
- Mendorong keterlibatan keluarga dalam membangun kebiasaan digital yang aman.

## ✨ Fitur Utama

### 1. Bedah Pesan
Membantu pengguna memahami dan memeriksa pesan yang berpotensi mengandung penipuan atau manipulasi. Hasilnya adalah indikator risiko, bukan vonis.

**Lapis 1: pemeriksaan lokal (selalu aktif).** Berjalan di browser (`public/js/bedah.js`), tanpa mengirim teks ke server. Berbasis aturan dengan skor berbobot (0-100), bukan machine learning. Yang diperiksa:
- Tekanan waktu, permintaan merahasiakan, permintaan uang, permintaan OTP/PIN/data pribadi.
- Iming-iming hadiah atau pekerjaan, mengaku pihak resmi, ancaman, mengaku kenalan dengan nomor baru.
- Tautan di dalam pesan: nama merek di subdomain, kata pancingan, IP langsung, pemendek tautan, link file `.apk`.
- Teks dinormalisasi dulu, jadi variasi seperti `0TP`, `tr4nsfer`, atau `o t p` tetap terdeteksi.
- Kombinasi pola berbahaya (misalnya uang + tekanan) menambah skor.

Server hanya mencatat skor, daftar id indikator, dan panjang teks untuk progres sesi. Isi pesan tidak dikirim.

**Lapis 2: analisis AI (opsional).** Tombol "Kirim ke AI dan analisis" memanggil `POST /jeda/api/bedah-ai`. Nomor HP, rekening, dan email disamarkan di server sebelum dikirim ke API, teks tidak disimpan, dan hasilnya diberi label "Dianalisis AI, bisa salah". Untuk mengaktifkan, isi di `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
JEDA_AI_MODEL=claude-haiku-5-5   # opsional
```

Tanpa API key, tombol ini menampilkan pesan bahwa AI belum aktif dan pemeriksaan lokal tetap berfungsi.

### 2. Cek Tautan
Membantu pengguna mengevaluasi tautan untuk mengenali indikator yang berpotensi berbahaya sebelum membukanya.

Pemeriksaan dapat mencakup struktur URL, informasi domain, dan pola tautan yang mencurigakan sesuai mekanisme yang diterapkan sistem.

### 3. Cek File
Membantu pengguna meningkatkan kewaspadaan terhadap file yang berpotensi memiliki risiko keamanan berdasarkan informasi dan indikator yang dapat diperiksa oleh sistem.

### 4. Latihan dan Simulasi
Menyediakan latihan interaktif untuk membantu pengguna mengenali ancaman keamanan digital melalui contoh kasus dan situasi simulasi.

Fitur ini mendukung pembelajaran, evaluasi pemahaman, serta pengenalan tindakan yang lebih aman ketika menghadapi potensi ancaman.

### 5. Jam Emas
Menyediakan edukasi dan panduan mengenai langkah yang dapat dipertimbangkan ketika menghadapi dugaan penipuan atau insiden keamanan digital.

Fitur ini bertujuan membantu pengguna memahami pentingnya tindakan yang tepat dan cepat dalam mengurangi risiko.

### 6. Keluarga
Mendukung keterlibatan keluarga dalam pembelajaran keamanan digital melalui pendampingan dan pemantauan perkembangan sesuai kemampuan sistem yang tersedia.

### 7. Kartu Edukasi
Menyajikan materi ringkas seputar keamanan digital untuk membantu pengguna memahami konsep penting, mengenali ancaman, dan mempelajari langkah pencegahan.

### 8. Pencatatan Hasil dan Progres
Mendukung pengelolaan data hasil aktivitas dan perkembangan pembelajaran melalui database untuk fitur-fitur yang telah terintegrasi dengan sistem penyimpanan.

## 🛠️ Teknologi

Teknologi yang digunakan dalam pengembangan Jeda meliputi:

- **Laravel** — framework pengembangan aplikasi berbasis PHP.
- **PHP** — pemrosesan logika sisi server.
- **MySQL** — pengelolaan dan penyimpanan data.
- **JavaScript** — mendukung interaksi dan pemrosesan fitur pada sisi klien.

## 🗄️ Pengelolaan Data

Database mendukung penyimpanan data yang diperlukan oleh fitur-fitur tertentu, seperti:

| Jenis Data | Kegunaan |
|---|---|
| Hasil pemeriksaan pesan | Mendukung pencatatan hasil analisis pesan |
| Hasil pemeriksaan tautan | Mendukung pencatatan hasil pemeriksaan tautan |
| Hasil pemeriksaan file | Mendukung pencatatan hasil pemeriksaan file |
| Hasil latihan | Mendukung pencatatan hasil atau skor latihan |
| Progres pembelajaran | Mendukung pemantauan perkembangan pengguna |
| Aktivitas keluarga | Mendukung pencatatan aktivitas pendampingan yang tersedia |

Data yang benar-benar disimpan mengikuti implementasi fitur dan integrasi database pada versi sistem yang digunakan.

## 🔐 Keamanan dan Privasi

Jeda dikembangkan dengan memperhatikan pentingnya keamanan dan privasi pengguna.

Prinsip yang perlu diperhatikan dalam pengembangan sistem meliputi:
- Menghindari penyimpanan data sensitif yang tidak diperlukan.
- Menjaga kerahasiaan kredensial dan konfigurasi internal.
- Melakukan validasi terhadap data yang diterima sistem.
- Mengelola akses terhadap data sesuai kebutuhan.
- Menyampaikan hasil pemeriksaan sebagai indikator risiko, bukan jaminan mutlak keamanan.

## 🚀 Pengembangan Selanjutnya

Pengembangan Jeda dapat diarahkan pada:
- Penyempurnaan mekanisme pemeriksaan pesan, tautan, dan file.
- Peningkatan kualitas latihan dan simulasi keamanan digital.
- Pengembangan pencatatan serta evaluasi progres pembelajaran.
- Penguatan validasi input dan perlindungan data.
- Pengujian fungsionalitas dan keandalan setiap fitur.

## 📌 Status Proyek

Jeda merupakan proyek pengembangan sistem edukasi keamanan digital. Ketersediaan dan kemampuan setiap fitur mengikuti implementasi pada versi proyek yang sedang dikembangkan.

---

**Jeda — Berhenti sejenak, periksa, lalu bertindak dengan lebih aman.**