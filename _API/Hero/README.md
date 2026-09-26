# API Hero Slide

Mengikuti pola Fasilitas/Galleries, menggunakan tabel `hero_slides` dan `file_manager`. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT menerima `Content-Type: application/json`; OPTIONS mengembalikan 204 untuk preflight.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `get_hero.php` | GET | Daftar, pencarian, pagination, filter status, metadata gambar |
| `add_hero.php` | POST | Tambah slide dengan gambar wajib |
| `update_hero.php` | PUT | Ubah judul, subjudul, dan status aktif |
| `delete_hero.php?id=1` | DELETE | Hapus slide dan tangani gambar terkait |
| `update_image.php` | PUT | Ganti atau lepas gambar |
| `update_sort_order.php` | PUT | Tukar posisi UP/DOWN |

## Tambah slide

`title` wajib berupa teks tidak kosong, maksimal 100 karakter; `subtitle` wajib berupa teks tidak kosong, maksimal 255 karakter. `is_active` opsional, berupa integer 0 atau 1, default 1. Boolean, string, null, dan nilai lainnya ditolak. Urutan otomatis terbesar + 1, dimulai dari 1 jika tabel kosong.

Gambar wajib disertakan, termasuk untuk slide nonaktif. Kirim `file_source` dan `base64` untuk unggahan, atau `image_url` untuk External Link. Field gambar yang hilang, null, bukan string, atau kosong ditolak dengan HTTP 400 tanpa menyimpan data.

Contoh request:

```json
{
  "title": "KEGIATAN SEKOLAH",
  "subtitle": "Belajar dan berkembang bersama.",
  "is_active": 1,
  "file_source": "Local Directory",
  "base64": "<base64 lengkap gambar>"
}
```

`Local Directory`, `Cloudinary`, dan `Imagekit` menggunakan `base64`. `External Link` menggunakan `image_url` tanpa field `base64`. File lokal disimpan di `assets/img/local_directory`; folder provider `web_sekolah/hero_slides`. Validasi status storage, ukuran, format, dan isi gambar mengikuti helper `ImageStorage`. Jika verifikasi external link nonaktif, hanya format URL dan ekstensi diperiksa.

## Daftar slide

```text
get_hero.php?limit=12&page=1&order_by=sort_order&short_by=ASC&is_active=1&keyword=sekolah
```

- `limit`: 1–100, default 12; `page`: integer positif, default 1.
- `order_by`: `id`, `title`, `subtitle`, `sort_order`, atau `is_active`.
- `short_by`: ASC/DESC, default ASC; ejaan mengikuti API Teachers.
- `is_active`: 0 atau 1; jika tidak dikirim atau kosong, semua status ditampilkan.
- `keyword`: mencari id, title, subtitle. `%`, `_`, dan `!` diperlakukan sebagai teks literal.

Respons memuat `data`, `pagination`, dan `filter`. Setiap item berisi `id`, `title`, `subtitle`, `is_active`, `sort_order`, `id_file_manager`, `file_source`, objek `file_metadata`, dan `image_url`. Field gambar bernilai null jika tidak ada gambar. URL lokal mengikuti root instalasi saat ini.

## Update slide

```json
{
  "id": 1,
  "title": "KEGIATAN LITERASI",
  "subtitle": "Menumbuhkan minat baca siswa.",
  "is_active": 0
}
```

`id`, `title`, dan `subtitle` wajib. `is_active` opsional; jika tidak dikirim, status sebelumnya dipertahankan. ID harus integer positif sampai 2147483647. Field selain keempat field ini ditolak. Gambar dan urutan tidak berubah.

## Ganti atau lepas gambar

Kirim ke `update_image.php`:

```json
{"id":1,"file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

```json
{"id":1,"file_source":"External Link","image_url":"https://example.com/hero.jpg"}
```

```json
{"id":1,"file_source":"DELETE","base64":""}
```

Upload baru dilakukan sebelum transaksi. Gambar lama dibersihkan setelah relasi baru berhasil di-commit. Jika pembersihan lama gagal, gambar baru tetap terpasang dan respons sukses memuat `warnings` serta `old_file_deletion: cleanup_pending`. Konflik perubahan gambar bersamaan menghasilkan 409.

File yang masih dipakai slide lain, galeri, fasilitas, guru, atau tabel terkait tetap dipertahankan. Provider nonaktif tidak dipanggil dan metadata tetap disimpan. External Link tidak menghapus aset milik pemilik URL. Kegagalan penghapusan provider aktif membatalkan DELETE. Penghapusan fisik tidak dapat dipulihkan oleh rollback SQL; kegagalan commit setelah penghapusan dicatat untuk rekonsiliasi.

## Ubah urutan

Kirim ke `update_sort_order.php`:

```json
{"id":2,"sort_order":"UP"}
```

`DOWN` memindahkan ke bawah. Posisi ditukar dengan tetangga terdekat, termasuk jika nomor urutan bercelah. Urutan mencakup slide aktif dan nonaktif. Posisi pertama tidak bisa UP dan posisi terakhir tidak bisa DOWN (409). Nilai 0 didukung sesuai schema; null, negatif, atau duplikat pada urutan yang terlibat ditolak.

## Pengujian

```text
php tests/hero_test.php
```

Menggunakan tabel MySQL sementara sesuai schema proyek dan file lokal khusus pengujian. Cloudinary/Imagekit disimulasikan sehingga aset provider sungguhan tidak diubah. Mencakup CRUD, validasi, status aktif, filter status beserta pencarian, pagination, urutan, konflik penggantian gambar, rollback, dan perlindungan file bersama.
