# API Videos

Mengelola tabel `videos` untuk kurasi video YouTube. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT memakai `Content-Type: application/json`. OPTIONS mengembalikan 204.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `get_videos.php` | GET | Daftar, pencarian, pagination, pengurutan |
| `add_videos.php` | POST | Tambah video |
| `update_videos.php` | PUT | Ubah informasi video |
| `delete_videos.php?id=1` | DELETE | Hapus data video dari website |
| `update_sort_order.php` | PUT | Tukar urutan UP/DOWN |

## Tambah

```json
{"youtube_id":"dpvPcsMVbWA","title":"Kegiatan Literasi","student_name":"Siswa Kelas 4"}
```

Ketiga field wajib berupa teks tidak kosong. `youtube_id` berupa ID 11 karakter huruf, angka, `_`, atau `-`, bukan URL lengkap. Validasi format tidak memeriksa keberadaan video atau izin embed di YouTube. `title` maksimal 255 karakter dan `student_name` maksimal 100 karakter. Urutan otomatis terbesar + 1, mulai dari 1 untuk tabel kosong. Respons sukses HTTP 201.

## Ubah

```json
{"id":1,"youtube_id":"dpvPcsMVbWA","title":"Kegiatan Literasi Sekolah","student_name":"Kelompok Literasi"}
```

Semua field wajib. ID harus integer positif sampai 2147483647. Field di luar kontrak ditolak. Urutan tetap dipertahankan. Data tidak ditemukan menghasilkan 404; data identik tetap sukses.

## Daftar

```text
get_videos.php?limit=12&page=1&order_by=sort_order&short_by=ASC&keyword=literasi
```

`limit` 1–100 (default 12), `page` integer positif (default 1). `order_by` menerima `id`, `youtube_id`, `title`, `student_name`, atau `sort_order`. `short_by` ASC/DESC. Keyword mencari id, ID YouTube, judul, dan nama siswa; `%`, `_`, `!` diperlakukan literal. Respons memuat `data`, `pagination`, `filter`, dan `requested_by_app`.

Frontend memakai `youtube_id` sebagai nilai `data-youtube-id`, seperti pemutar video yang sudah ada pada situs. API hanya mengelola data kurasi; tidak mengunggah atau menghapus video pada akun YouTube, dan tidak menggunakan file_manager.

## Urutan

```json
{"id":2,"sort_order":"UP"}
```

Kirim ke `update_sort_order.php`. Gunakan DOWN untuk turun. Posisi ditukar dalam satu transaksi dengan tetangga terdekat, termasuk jika urutan bercelah. Pertama tidak bisa UP dan terakhir tidak bisa DOWN (409). Urutan 0 didukung; null, negatif, dan duplikat yang terlibat ditolak.

## Pengujian

`php tests/videos_test.php`

Menggunakan tabel MySQL sementara sesuai schema proyek, tanpa mengubah data video asli atau menghubungi YouTube.
