# API Galleries

Mengikuti pola API Fasilitas dan Teachers, menggunakan tabel `galleries` dan `file_manager` dalam `DB/web_sekolah.sql`. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT menggunakan `Content-Type: application/json`. OPTIONS mengembalikan 204 untuk preflight.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `get_galleries.php` | GET | Daftar, pencarian, pagination, metadata dan URL gambar |
| `add_galleries.php` | POST | Tambah galeri dengan gambar wajib |
| `update_galleries.php` | PUT | Ubah caption |
| `delete_galleries.php?id=1` | DELETE | Hapus galeri beserta penanganan file terkait |
| `update_image.php` | PUT | Ganti atau lepas gambar |
| `update_sort_order.php` | PUT | Tukar urutan UP/DOWN |

## Tambah galeri

`caption` wajib berupa teks tidak kosong, maksimal 255 karakter. Nilai `sort_order` ditentukan server dari urutan terbesar + 1; tabel kosong dimulai dari 1.

Gambar wajib disertakan: `file_source` dan `base64` untuk unggahan, atau `file_source` dan `image_url` untuk External Link. Field gambar yang hilang, null, bukan string, atau kosong ditolak dengan HTTP 400 tanpa menambah data galeri maupun file. Isi gambar tetap diperiksa oleh helper storage.

Contoh unggahan gambar:

```json
{"caption":"Kegiatan belajar di perpustakaan","file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

`file_source`: `Local Directory`, `Cloudinary`, atau `Imagekit` menggunakan `base64`. Untuk `External Link`, gunakan `image_url` tanpa `base64`:

```json
{"caption":"Kegiatan olahraga","file_source":"External Link","image_url":"https://example.com/kegiatan.jpg"}
```

Gambar divalidasi oleh helper bersama `ImageStorage` sesuai status storage, format, ukuran, dan isi gambar. File lokal berada di `assets/img/local_directory`; folder provider `web_sekolah/galleries`. Metadata mengikuti `Image-Metadata-Explanation.md`. Jika verifikasi external link nonaktif, hanya format URL dan ekstensi yang diperiksa.

## Daftar galeri

`get_galleries.php?limit=12&page=1&order_by=sort_order&short_by=ASC&keyword=kegiatan`

- `limit`: 1–100, default 12; `page`: integer positif, default 1.
- `order_by`: `id`, `caption`, atau `sort_order`.
- `short_by`: `ASC`/`DESC`, default `ASC` (ejaan mengikuti API Teachers).
- `keyword`: mencari `id` dan `caption`; `%`, `_`, dan `!` diperlakukan sebagai teks biasa.
- Respons: `data`, `pagination`, dan `filter`. Setiap item berisi `id`, `caption`, `sort_order`, `id_file_manager`, `file_source`, objek `file_metadata`, serta `image_url`. Field gambar bernilai null jika belum ada gambar. URL lokal mengikuti root instalasi saat ini.

## Ubah caption

```json
{"id":1,"caption":"Dokumentasi kegiatan literasi siswa"}
```

Hanya `id` dan `caption` diterima. ID harus integer positif, maksimal 2147483647. Gambar dan urutan tetap dipertahankan.

## Ganti atau lepas gambar

Kirim ke `update_image.php`:

```json
{"id":1,"file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

```json
{"id":1,"file_source":"External Link","image_url":"https://example.com/foto-baru.jpg"}
```

```json
{"id":1,"file_source":"DELETE","base64":""}
```

Upload baru dilakukan sebelum transaksi, kemudian relasi baru di-commit, lalu gambar lama dibersihkan. Jika pembersihan lama gagal setelah commit, respons sukses memuat `warnings` dan `old_file_deletion: cleanup_pending`. Konflik perubahan gambar bersamaan menghasilkan 409.

File yang masih dipakai guru, fasilitas, galeri lain, atau tabel terkait dipertahankan. Storage nonaktif tidak dipanggil dan metadata tetap disimpan. External Link tidak menghapus aset milik pemilik URL. Jika penghapusan provider aktif gagal, DELETE dibatalkan. Penghapusan fisik tidak dapat dipulihkan oleh rollback SQL; kegagalan commit setelah penghapusan dicatat untuk rekonsiliasi.

## Ubah urutan

Kirim ke `update_sort_order.php`:

```json
{"id":2,"sort_order":"UP"}
```

Gunakan `DOWN` untuk turun. Posisi ditukar dengan tetangga terdekat, termasuk jika ada celah nomor. Posisi pertama tidak bisa UP dan posisi terakhir tidak bisa DOWN (409). Urutan 0 didukung sesuai schema. Nilai null/negatif atau duplikat yang terlibat ditolak.

## Pengujian

```text
php tests/galleries_test.php
```

Tes menggunakan tabel MySQL sementara sesuai schema proyek dan file lokal khusus pengujian. Cloudinary dan Imagekit disimulasikan; aset provider sungguhan tidak diubah. Mencakup CRUD, mandatory caption, pagination, pencarian, batas urutan, penggantian gambar, rollback, konflik perubahan, dan file bersama lintas tabel.
