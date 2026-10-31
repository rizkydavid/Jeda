@extends('layouts.app')

@section('title', 'Batasan Jeda')

@section('content')

<main class="wrap">
    <section class="hero">
        <h1>
            Batasan Jeda
        </h1>
        <p class="mut" style="max-width:640px;font-size:18px">
            Jeda membantu kamu berhenti dan berpikir. Jeda tidak menggantikan penilaianmu.
        </p>
    </section>
    <div class="grid2">
        <div class="card">
            <h2>
                Yang bisa Jeda lakukan
            </h2>
            <div class="check">
                <i>
                </i>
                <div>
                    Menunjukkan tanda tekanan dan alasannya
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    Memberi pertanyaan yang bisa kamu ajukan ke pengirim
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    Melatih refleks lewat simulasi
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    Menuntun langkah saat sudah terlanjur
                </div>
            </div>
        </div>
        <div class="card dark">
            <h2>
                Yang tidak bisa Jeda lakukan
            </h2>
            <div class="check" style="border-color:#2b3a55">
                <i style="border-color:#8fa3ff">
                </i>
                <div>
                    Memastikan sebuah pesan aman atau pasti penipuan
                </div>
            </div>
            <div class="check" style="border-color:#2b3a55">
                <i style="border-color:#8fa3ff">
                </i>
                <div>
                    Mengenali semua pola penipuan, terutama yang baru
                </div>
            </div>
            <div class="check" style="border-color:#2b3a55">
                <i style="border-color:#8fa3ff">
                </i>
                <div>
                    Memastikan pengirim itu asli
                </div>
            </div>
            <div class="check" style="border-color:#2b3a55">
                <i style="border-color:#8fa3ff">
                </i>
                <div>
                    Menghindari salah tandai pada pesan yang wajar
                </div>
            </div>
        </div>
    </div>
    <div class="card" style="margin-top:20px">
        <h2>
            Apa yang terjadi pada datamu
        </h2>
        <table>
            <tr>
                <th>
                    Bagian
                </th>
                <th>
                    Diproses di mana
                </th>
                <th>
                    Disimpan?
                </th>
            </tr>
            <tr>
                <td>
                    Pemeriksaan dasar
                </td>
                <td>
                    Di perangkatmu
                </td>
                <td>
                    Tidak
                </td>
            </tr>
            <tr>
                <td>
                    Analisis AI (opsional)
                </td>
                <td>
                    Dikirim ke layanan AI pihak ketiga
                </td>
                <td>
                    Tidak oleh Jeda
                </td>
            </tr>
            <tr>
                <td>
                    Riwayat latihan
                </td>
                <td>
                    Di perangkatmu (penyimpanan browser)
                </td>
                <td>
                    Ya, hanya di perangkatmu
                </td>
            </tr>
        </table>
        <p class="small" style="margin:14px 0 0">
            Jeda tanpa akun dan tidak meminta data pribadi. Seluruh contoh bersifat fiktif.
        </p>
    </div>
</main>

@endsection
