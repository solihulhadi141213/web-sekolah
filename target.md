# DESKRIPSI WEB-SEKOLAH
Merupakan template website yang dibangun menggunakan HTML, CSS dan Javascript (jquery).
Berfungsi menampilkan profil sekolah dan memungkinkan dikembangkan untuk sistem lebih kompleks terkait urusan sekolah seperti pembayaran SPP, pendaftaran siswa baru dan lain-lain.

# Pustaka dan Depedensi
- Bootstrap5 (sudah diinstal pada node_mudules) -> Sebagai pustaka css untuk membuat tampilan responsif dan menarik. [jangan gunakan CDN untuk memasangnya pada index.html - Gunakan url lokal]
- MDB UI kit (sudah diinstal pada node_mudules) -> Sebagai pustaka membantu bootstrap agar lebih menarik menarik. [jangan gunakan CDN untuk memasangnya pada index.html - Gunakan URL lokal]
- Jquery (sudah diinstal pada node_mudules) -> Untuk handdle berbagai prilaku, event dan proses. [jangan gunakan CDN untuk memasangnya pada index.html]
- Bootstrap Icon (sudah diinstal pada node_mudules) -> Membuat icon lebih menarik. [jangan gunakan CDN untuk memasangnya pada index.html]
- Swiper (sudah diinstal pada node_mudules) -> Membuat konten dalam swiper. [jangan gunakan CDN untuk memasangnya pada index.html]

# Aturan Penting!
1. Jangan gunakan depedensi/pustaka luar, jika memang diperlukan maka utamakan instal dengan NPM agar menggunakan node_modules
2. Jangan gunakan efek hover yang mengubah ukuran pada card
3. Web harus ringan. Setiap melakukan perubahan, utamakan pertimbangan efisiensi.
4. Boleh generate gambar dummy, namun harus ringan.
5. Jangan gunakan efek yang memerlukan kerja berat browser
6. Web harus responsif, mobile first.
7. Setiap block script harus disertai dokumentasi komentar yang jelas menggunakan bahasa indonesia.

# Fitur / Halaman
1. Beranda
2. Selayang Pandang Dari Kepala Sekolah (Foto + Nama + text)
3. Profil Sekolah (Sejarah, visi dan misi, sambutan kepala sekolah, identitas dan akreditasi)
4. Guru dan Tenaga Pendidikan (Foto, nama, jabatan, bidang studi)
5. Akademik (Kurikulum, program unggulan, jurusan jika ada, kalender akademik)
6. Kesiswaan (Organisasi siswa, ekstrakurikuler, kegiatan, prestasi siswa)
7. Fasilitas (Ruang kelas, laboratorium, perpustakaan, lapangan, tempat ibadah)
8. Berita dan Pengumuman (Berita sekolah, agenda, pengumuman penting)
9. Galeri (Album foto dan video berdasarkan kegiatan)
10. Penerimaan Murid baru (Persyaratan, jadwal, alur, biaya, FAQ, kontak panitia, formulir)
11. Kontak & Alamat (Alamat, peta, telepon, email, jam pelayanan, media sosial)

# Tampilan
1. Memiliki Navbar yang sticky, selalu di atas. Navbar nampak timbul mengandung box shadow dan radius pada sudutnya agar lebih menarik.
2. Menu pada saat mode mobile ditampilkan dengan Offcanvas.
3. Warna-warna yang digunakan, tema web, diantaranya : #5833dc , #e2eafa, #2f1b77, #ffffff, #e7efff
4. Untuk konten yang ditampilkan dalam bentuk card dengan jumlah yang banyak sebaiknya menggunakan swiper.
5. Menggunakan preloader yang menarik, menggunakan spiner bootstrap yang di kostumasi.
6. Jika menampilkan gambar, gunakan rasio yang konsisten. Jangan di tarik, tapi buat agar gambar dengan rasio yang berbeda tetap memiliki rasio yang konsisten saat ditampilkan.
7. Gambar pada halaman galeri, atau apapun yang menampilkan gambar. Agar lebih jelas, ketika di sorot, hover maka gambar seakan mendekat dengan tetap mempertahankan rasio.
8. Buat card dengan radius yang akan terlihat lembut, santai namun profesional.
9. Ketika gambar di click maka akan tampil full sebagai popup yang bisa di close


# Perintah Yang Perlui Diingat
Bertindaklah sebagai Senior UI/UX Designer & Conversion Copywriter. 
Buatkan copywriting yang persuasif menggunakan formula AIDA (Attention, Interest, Desire, Action), pastikan tampilannya mobile-friendly.


# Directory dan kegunaannya (pelajari isi dan kegunaannya)
1. assets : menyimpan script css, javascript, font dan file gambar
2. DB : Akan digunakan sebagai penyimpanan struktur basic database (tidak dengan data)
3. node_modules : menyimpan depedensi NPM
4. Setiap tampilan yang di kostumasi, maka css nya selalu disimpan di assets\css\style.css
5. Jika diperlukan javascript untuk pilaku tampilan, handling event atau proses maka akan disimpan di assets\js\main.js
6. Jika diperlukan font custome maka akan selalu disimpan di assets\fonts
7. Penggunaan font diimplementasikan pada css assets\fonts\fonts.css

# Identitas Sekolah
1. Nama Sekolah : MI PLUS ANNUR Kuningan
2. Alamat : Jl. Buahgmana No.234, Manggari, Kec. Lebakwangi, Kabupaten Kuningan, Jawa Barat 45574
3. Telepon: (0232) 878112
4. Goole map : https://www.google.com/maps/place/MI+Plus+An-Nur+JARINGAN+FULL+EDGE/data=!4m2!3m1!1s0x0:0xc641e490f14b04b0?sa=X&ved=1t:2428&ictx=111
5. Visi : Membentuk, Generasi Beriman, Berilmu, Mandiri, Berakhlak Mulia, Memiliki Kemampuan Berbahasa serta Hafalan Al-Qur’an, Menurut tingkat Perkembangan Peserta Didik.
6. Misi : Menanamkan nilai-nilai keimanan dan ketaqwaan kepada Allah Subhanahuwata’ala.
    Menanamkan etika dan moral, melalui pemahaman akhlakul karimah
    Mengembangkan input akademik peserta didik sesuai dengan potensi yang dimiliki
    Mengembangkan potensi kecakapan hidup sesuai dengan bakat dan karakter yang dimiliki.
    Menyiapkan lulusan yang sehat, cerdas, berbudi pekerti luhur, sebagai persiapan melanjutkan pendidikan ke jenjang yang lebih tinggi.
7. Kepala Sekolah : D. Sukandar Yusuf, S.Pd.I, M.S.I.

