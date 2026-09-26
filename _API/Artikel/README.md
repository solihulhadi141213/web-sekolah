# API Artikel

Mengelola data utama `articles`, master `tags`, dan relasi `article_tag_map`. Tidak membaca atau mengedit isi `article_contents`. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT menggunakan `Content-Type: application/json`; OPTIONS mengembalikan 204.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `creat_artikel.php` | POST | Tambah artikel, gambar opsional, dan tag |
| `update_artikel.php` | PUT | Ubah data utama dan relasi tag |
| `get_artikel.php` | GET | Daftar, pagination, pencarian, filter status/tag |
| `detail_artikel.php?id=1` | GET | Detail berdasarkan id atau slug |
| `get_artikel_by_tags.php?tag_id=1` | GET | Daftar berdasarkan tag_id atau tag_slug |
| `get_tags.php` | GET | Daftar master tag dengan pencarian/pagination |
| `delete_artikel.php?id=1` | DELETE | Hapus artikel, relasi tag, dan tangani gambar sampul |
| `update_image.php` | PUT | Ganti atau lepas gambar sampul |

Nama `creat_artikel.php` mengikuti nama file yang sudah disediakan proyek. Tidak diperlukan perubahan schema database.

## Tambah

```json
{
  "title": "Kegiatan Literasi Sekolah",
  "slug": "kegiatan-literasi-sekolah",
  "category_tag": "Berita",
  "summary": "Siswa mengikuti kegiatan membaca bersama.",
  "status": "published",
  "tag_ids": [1],
  "tags": [{"name":"Literasi","slug":"literasi"}]
}
```

`title`, `slug`, `category_tag`, dan `summary` wajib berupa teks tidak kosong, masing-masing maksimal 255, 255, 50, dan 500 karakter. Slug berupa huruf kecil/angka yang dipisahkan tanda hubung dan harus unik. `status` opsional: published (default schema) atau draft. Field yang tidak dikenal ditolak, termasuk `content` dan `sort_order`.

Tag opsional:

- `tag_ids`: array ID tag yang sudah ada. ID yang tidak ditemukan ditolak (400).
- `tags`: array objek `{name, slug}`, masing-masing maksimal 50 karakter. Pasangan yang sudah ada digunakan kembali; tag baru dibuat jika nama dan slug belum ada. Nama atau slug yang bertabrakan dengan pasangan berbeda ditolak (409).
- Masing-masing array maksimal 50 elemen. Relasi duplikat dihilangkan.
- Artikel, pembuatan tag, dan relasi disimpan dalam satu transaksi. Kegagalan tidak meninggalkan perubahan parsial.

`category_tag` adalah label utama kartu artikel, terpisah dari master `tags`.

Gambar mengikuti pola Fasilitas: tidak wajib disertakan. Untuk unggahan, tambahkan `file_source` (Local Directory/Cloudinary/Imagekit) dan `base64`. Untuk External Link, gunakan `image_url` tanpa `base64`. Jika menyertakan field gambar, payload harus valid; untuk tanpa gambar, hilangkan field gambar.

```json
{
  "title":"Kegiatan Literasi",
  "slug":"kegiatan-literasi",
  "category_tag":"Berita",
  "summary":"Kegiatan membaca bersama.",
  "file_source":"External Link",
  "image_url":"https://example.com/literasi.jpg"
}
```

File lokal berada di `assets/img/local_directory`; folder provider `web_sekolah/articles`. Validasi dan metadata mengikuti helper ImageStorage. External Link hanya diperiksa format URL/ekstensinya jika verifikasi isi dinonaktifkan.

## Update

```json
{
  "id":1,
  "title":"Kegiatan Literasi Terbaru",
  "slug":"kegiatan-literasi-terbaru",
  "category_tag":"Berita",
  "summary":"Ringkasan terbaru kegiatan sekolah.",
  "status":"draft",
  "tag_ids":[1,2]
}
```

ID integer positif sampai 4294967295. Empat field teks utama wajib. Jika status tidak dikirim, status sebelumnya dipertahankan. Jika `tags` dan `tag_ids` tidak dikirim, relasi lama dipertahankan. Jika salah satu dikirim, seluruh relasi diganti dengan gabungan keduanya; array kosong melepas semua tag. Master tag tidak dihapus atau diganti namanya. Gambar diperbarui melalui endpoint tersendiri.

## Daftar dan detail

```text
get_artikel.php?limit=12&page=1&order_by=created_at&short_by=DESC&status=published&keyword=literasi
get_artikel_by_tags.php?tag_slug=literasi&status=published
detail_artikel.php?slug=kegiatan-literasi
get_tags.php?limit=12&page=1&keyword=sekolah&order_by=name&short_by=ASC
```

`limit`: 1–100, default 12; `page`: integer positif, default 1. Pengurutan artikel menerima id, title, slug, category_tag, status, created_at, updated_at (default created_at DESC). Keyword mencari id, title, summary; `%`, `_`, `!` diperlakukan literal. Status kosong menampilkan semua artikel, termasuk draft. Pemanggil halaman publik perlu menyertakan `status=published`.

Filter tag memakai tepat satu `tag_id` atau `tag_slug`; tag tanpa artikel menghasilkan array kosong. Pagination menghitung artikel unik, bukan jumlah relasi. Detail memakai tepat satu id atau slug dan menghasilkan 404 jika tidak ditemukan.

Daftar memuat `data`, `pagination`, `filter`. Data artikel/detail menyertakan field utama, timestamp, `tags: [{id,name,slug}]`, serta `id_file_manager`, `file_source`, objek `file_metadata`, dan `image_url`. URL file lokal mengikuti root instalasi. Konten artikel tidak disertakan. `get_tags.php` menerima order_by id/name/slug, default name ASC.

## Gambar sampul

```json
{"id":1,"file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

```json
{"id":1,"file_source":"DELETE","base64":""}
```

Upload baru disimpan dan relasinya di-commit sebelum file lama dibersihkan. Kegagalan cleanup setelah commit menghasilkan respons sukses dengan warnings dan `cleanup_pending`. File yang digunakan data lain dipertahankan. Provider nonaktif tidak dipanggil dan metadata disimpan; External Link tidak menghapus aset pemilik URL. Penghapusan fisik tidak dapat dipulihkan oleh rollback SQL; kegagalan commit setelah penghapusan dicatat untuk rekonsiliasi.

## Penghapusan dan batas cakupan

DELETE melepas relasi `article_tag_map` tetapi mempertahankan master `tags`. Artikel yang masih memiliki `article_contents` ditolak (409), agar tidak terhapus melalui ON DELETE CASCADE sebelum fitur konten dikelola. Gambar sampul bersama tidak dihapus.

Pengelolaan artikel tidak menggunakan sort_order. Pengurutan daftar menggunakan parameter order_by/short_by, misalnya created_at DESC.

## Pengujian

`php tests/artikel_test.php`

Menggunakan tabel MySQL sementara dan file lokal khusus pengujian; tidak mengubah data artikel asli. Mencakup tag baru/duplikat/konflik, rollback artikel-tag-gambar, pencarian, detail, filter tag/status, pagination, file bersama, dan penolakan penghapusan artikel berkonten. Provider cloud memakai helper bersama; suite ini tidak menghubungi akun cloud.
