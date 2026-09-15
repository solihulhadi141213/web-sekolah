# SEO dan publikasi

## Sudah diterapkan

- Judul halaman dan deskripsi yang menjelaskan sekolah, lokasi, serta penerimaan murid baru.
- Satu H1 permanen dan metadata Open Graph/Twitter.
- JSON-LD `School` berdasarkan informasi yang tampil di halaman dan akun sosial yang diberikan.
- Isi, tautan, dan kartu video tersedia dalam HTML tanpa menunggu JavaScript.
- Gambar Imgix responsif dan terkompresi; pratinjau foto tetap memakai sumber asli.
- Hanya gambar hero pertama yang mendapat prioritas tinggi; gambar lain memakai lazy loading.
- jQuery yang tidak digunakan dan preloader dihapus. Skrip memakai `defer` dengan urutan dependensi tetap.
- CSS font lokal khusus halaman, tanpa definisi keluarga font yang tidak digunakan.
- `robots.txt` mengizinkan crawling. CSS dan JavaScript tetap dapat diakses crawler.
- `.htaccess` menyiapkan kompresi dan cache aset pada Apache yang mendukung modul terkait.

## Memerlukan domain publik

Canonical, `og:url`, URL/logo organisasi, gambar berbagi, dan sitemap perlu memakai URL produksi yang benar. Belum dipasang karena domain publik belum diberikan; jangan menggunakan alamat localhost atau domain dari alamat email sebagai pengganti.

Setelah domain diberikan:

1. Tetapkan satu URL HTTPS utama di canonical dan metadata sosial.
2. Tambahkan URL dan logo absolut ke JSON-LD sekolah.
3. Buat `sitemap.xml` berisi URL halaman utama; bagian `#profile`, `#contact`, dan anchor lainnya bukan halaman terpisah.
4. Tambahkan URL sitemap absolut ke `robots.txt`. File robots harus tersedia di akar domain, termasuk bila situs berada dalam subfolder.
5. Verifikasi kepemilikan di Google Search Console, kirim sitemap, lalu gunakan URL Inspection pada halaman utama.

## Server dan pengujian

- Deploy aset yang dirujuk dari `node_modules` bersama situs; jangan mengecualikannya atau memblokirnya lewat robots.
- Apache harus mengizinkan aturan `.htaccess` serta memuat `mod_deflate` dan `mod_expires`. Respons WAMP saat pengujian belum mengandung `Content-Encoding` atau `Cache-Control`; pengaturan server global tidak diubah.
- Setelah publikasi, periksa status HTTP 200, HTTPS, canonical, robots, sitemap, kompresi, dan cache. Perbarui parameter versi CSS/JS bila aset berubah.
- Pengujian lokal Chrome pada lebar 390 dan 1440 piksel: satu H1 tetap, hero muat, tujuh Swiper aktif, popup video dapat dibuka/ditutup, tanpa overflow horizontal atau exception JavaScript.
- JSON-LD, ID elemen, dependensi lokal, font, dan sintaks JavaScript diperiksa. Dua sampel gambar Imgix yang dioptimalkan mengembalikan HTTP 200.
- Skor Lighthouse/Core Web Vitals dan status indeks Google belum diukur pada domain produksi.

## Referensi

- [Google: SEO untuk pengembang](https://developers.google.com/search/docs/fundamentals/get-started-developers)
- [Google: sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Schema.org: School](https://schema.org/School)
- [Imgix: optimasi gambar](https://www.imgix.com/blog/core-web-vitals2)
- [Apache: kompresi](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html) dan [cache](https://httpd.apache.org/docs/2.4/mod/mod_expires.html)
