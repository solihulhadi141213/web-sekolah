# Web Sekolah — MI Plus Annur Kuningan

**Profil sekolah, cerita siswa, dan informasi penerimaan murid dalam satu halaman.**

Template website sekolah responsif berbasis HTML, CSS, dan JavaScript, dengan tampilan MI Plus Annur Kuningan. Membantu orang tua mengenal sekolah melalui profil, tenaga pendidik, fasilitas, dokumentasi kegiatan, serta informasi pendaftaran.

Konten dapat diedit langsung dari file proyek. Tidak memerlukan database, backend, atau proses build untuk menjalankan halaman.

[Lihat tampilan](#tangkapan-layar) · [Mulai menjalankan](#menjalankan-proyek) · [Kustomisasi](#kustomisasi) · [Panduan SEO](SEO.md)

## Tangkapan layar

[![Tangkapan layar website MI Plus Annur Kuningan](assets/img/Screenshot.png)](assets/img/Screenshot.png)

*Klik gambar untuk melihat tangkapan layar dalam ukuran penuh.*

## Fitur unggulan

- **Hero interaktif:** foto bergantian, animasi teks, dua kolom seimbang, dan ukuran subtitle yang menyesuaikan ruang tersedia.
- **Profil sekolah yang rapi:** kartu Sejarah, Visi, Misi, dan Akreditasi dengan aksen pastel serta teks yang mudah dipindai.
- **Slider responsif:** daftar guru, testimonial, fasilitas, berita, foto, dan video menggunakan Swiper.
- **Galeri foto dan video:** foto dapat diperbesar; kartu YouTube memiliki tombol play dan pemutar popup.
- **Informasi penerimaan murid:** alur pendaftaran dan FAQ tersedia dalam satu bagian.
- **Kontak lengkap:** alamat, telepon, jam layanan, Google Maps, dan akun media sosial sekolah.
- **Dasar SEO tersedia:** metadata, judul utama permanen, data terstruktur `School`, serta konten dan tautan dalam HTML.

## Isi halaman

| Bagian | Isi |
| --- | --- |
| Beranda | Hero foto dan subtitle bergantian |
| Statistik | Angka sekolah dengan animasi penghitung |
| Selayang Pandang | Sambutan dan profil kepala sekolah |
| Profil Sekolah | Sejarah, visi, misi, dan akreditasi |
| Guru & Testimonial | Tenaga pendidik dan cerita keluarga sekolah |
| Kesiswaan | Kegiatan dan pengembangan siswa |
| Fasilitas & Berita | Sarana sekolah serta kabar kegiatan |
| Galeri Foto | Dokumentasi dengan pratinjau gambar |
| Penerimaan Murid Baru | Informasi pendaftaran dan pertanyaan umum |
| Galeri Video | Lima video YouTube dalam slider |
| Kontak & Alamat | Peta, Instagram, Facebook, WordPress, YouTube, dan TikTok |
| Footer | Copyright, ketentuan penggunaan, privasi, dan cookie |

## Teknologi

| Teknologi | Penggunaan |
| --- | --- |
| HTML5 & CSS3 | Konten, tema, dan tata letak responsif |
| JavaScript | Interaksi halaman tanpa framework |
| Bootstrap 5 | Grid, navigasi offcanvas, accordion, dan modal |
| Bootstrap Icons | Ikon antarmuka dan media sosial |
| Swiper | Slider foto dan kartu |
| npm | Instalasi dependensi frontend |

YouTube digunakan untuk video, Google Maps untuk peta, serta Imgix dan Unsplash untuk sebagian gambar. Font menggunakan kombinasi aset lokal dan Google Fonts. Layanan eksternal tersebut memerlukan koneksi internet.

`package.json` juga masih mencantumkan jQuery dan MDB UI Kit, tetapi keduanya tidak dimuat oleh `index.html` saat ini.

## Menjalankan proyek

### 1. Ambil kode dan instal dependensi

Siapkan Git, Node.js, dan npm, lalu jalankan:

```bash
git clone https://github.com/solihulhadi141213/web-sekolah.git
cd web-sekolah
npm ci
```

`npm ci` memasang dependensi sesuai `package-lock.json`. Halaman mengambil CSS dan JavaScript pustaka langsung dari `node_modules`, sehingga langkah ini diperlukan.

### 2. Jalankan melalui server lokal

**WAMP / XAMPP**

Letakkan folder proyek di direktori web server, misalnya:

```text
WAMP   : C:\wamp64\www\web-sekolah
XAMPP  : C:\xampp\htdocs\web-sekolah
```

Aktifkan Apache, lalu buka <http://localhost/web-sekolah/>.

**Alternatif dengan Python 3**

Dari direktori proyek:

```bash
python -m http.server 8000
```

Buka <http://localhost:8000>. Server ini cukup untuk pratinjau halaman, tetapi tidak menerapkan konfigurasi Apache dalam `.htaccess`.

## Struktur proyek

```text
web-sekolah/
├── index.html               # Seluruh konten halaman
├── assets/
│   ├── css/style.css        # Tema dan tata letak
│   ├── js/main.js           # Slider, modal, dan interaksi
│   ├── fonts/               # Font lokal dan site-fonts.css
│   └── img/
│       ├── Screenshot.png   # Pratinjau website di README
│       └── favicon/         # Ikon situs
├── node_modules/            # Dibuat oleh npm ci; tidak disimpan di Git
├── package.json
├── package-lock.json
├── robots.txt               # Aturan crawling
├── .htaccess                # Kompresi dan cache Apache
├── SEO.md                   # Catatan SEO dan publikasi
└── LICENSE                  # Apache License 2.0
```

## Kustomisasi

| Ingin mengubah… | Edit di… |
| --- | --- |
| Nama sekolah, teks, guru, kegiatan, dan kontak | [index.html](index.html) |
| Warna, tipografi, kartu, dan ukuran tampilan | [assets/css/style.css](assets/css/style.css), mulai dari variabel `:root` |
| Kecepatan slider, animasi, dan perilaku popup | [assets/js/main.js](assets/js/main.js) |
| Logo dan favicon | [assets/img/favicon/](assets/img/favicon/) beserta referensinya di HTML |
| Judul pencarian dan deskripsi berbagi | Elemen `<head>` di `index.html` |
| Identitas sekolah untuk mesin pencari | Blok JSON-LD `School` di `index.html` |

### Mengganti video YouTube

Cari bagian `id="videos"` di `index.html`. Setiap kartu memiliki tautan YouTube, atribut `data-youtube-id`, thumbnail, keterangan, dan judul.

Untuk URL `https://www.youtube.com/watch?v=dpvPcsMVbWA`, ID videonya adalah `dpvPcsMVbWA`. Sesuaikan ID pada ketiga atribut berikut:

- `href="https://www.youtube.com/watch?v=..."`
- `data-youtube-id="..."`
- `src="https://i.ytimg.com/vi/.../hqdefault.jpg"`

Perbarui juga judul, keterangan, dan `aria-label` tombol. Untuk menambah video, salin satu blok `.swiper-slide` di dalam `.videoSwiper`.

### Mengganti gambar

Sesuaikan `src` dan deskripsi `alt`. Pada gambar responsif, perbarui juga `srcset`, `sizes`, serta `data-full-src` yang dipakai untuk pratinjau ukuran penuh. Pertahankan `loading="lazy"` untuk gambar di luar hero pertama.

## Performa dan aksesibilitas

- Gambar Imgix tersedia dalam beberapa ukuran agar browser dapat memilih sumber yang sesuai.
- Gambar hero pertama diprioritaskan; gambar lain dan peta memakai *lazy loading*.
- Pemutar YouTube baru dibuat setelah tombol play ditekan dan dihentikan ketika popup ditutup.
- Skrip memakai `defer`, dan CSS font lokal dibatasi pada keluarga font yang digunakan.
- Tersedia tautan lewati navigasi, indikator fokus, label tombol, serta dukungan preferensi pengurangan gerakan.
- Konten utama tersedia dalam HTML; JavaScript menambahkan interaktivitas.

## Publikasi dan SEO

Situs dapat dipasang pada hosting yang melayani file statis. Tidak ada perintah build khusus. Unggah `index.html`, `assets`, `robots.txt`, dan dependensi yang dirujuk dari `node_modules`. Sertakan `.htaccess` bila menggunakan Apache.

**Jangan hanya mengunggah file yang dilacak Git:** `node_modules` diabaikan oleh `.gitignore`. Jalankan `npm ci` sebelum menyiapkan berkas publikasi, atau sertakan langkah instalasi dalam proses deployment.

Sebelum dipublikasikan:

1. Sesuaikan data sekolah, materi gambar/video, kontak, dan redaksi footer.
2. Gunakan domain HTTPS yang benar untuk canonical, metadata URL, dan sitemap. Bagian ini masih menunggu domain produksi.
3. Pastikan `robots.txt` tersedia di akar domain dan aset CSS/JS dapat diakses.
4. Periksa kompresi dan cache pada hosting; aturan `.htaccess` bergantung pada dukungan modul Apache.
5. Uji tampilan ponsel, tautan, pemutar video, serta kecepatan halaman pada hosting tujuan.

Lihat [SEO.md](SEO.md) untuk rincian konfigurasi, pengujian lokal, dan langkah pengindeksan. Skor performa produksi dan status indeks Google belum diukur.

## Kontribusi

Masukan desain, laporan bug, dan perbaikan dokumentasi dipersilakan melalui issue atau pull request. Untuk laporan tampilan, sertakan ukuran layar, browser, langkah reproduksi, dan tangkapan layar bila memungkinkan.

## Lisensi

Kode proyek menggunakan [Apache License 2.0](LICENSE). Lisensi dan izin penggunaan foto, video, logo, serta font mengikuti ketentuan pemilik masing-masing.
