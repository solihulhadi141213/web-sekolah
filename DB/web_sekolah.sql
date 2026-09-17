-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 17, 2026 at 07:59 PM
-- Server version: 9.1.0
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `web_sekolah`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_credentials`
--

DROP TABLE IF EXISTS `api_credentials`;
CREATE TABLE IF NOT EXISTS `api_credentials` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key kredensial',
  `app_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama aplikasi klien yang menggunakan API (cth: Aplikasi PPDB Android)',
  `api_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Kunci publik unik untuk identifikasi klien (Header: x-api-key)',
  `api_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Kunci rahasia (sebaiknya di-hash) untuk validasi otentikasi ketat',
  `permissions` json DEFAULT NULL COMMENT 'Daftar hak akses dalam format JSON (cth: ["read_article", "write_gallery"])',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Saklar (Toggle) untuk mematikan akses API sewaktu-waktu jika diperlukan',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Mencatat waktu terakhir klien melakukan request ke API',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu kredensial ini diterbitkan',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel otorisasi klien untuk mengakses endpoint API web';

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'URL ramah SEO (cth: jadwal-kegiatan-tahfidz)',
  `category_tag` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Sesuai tag di HTML: Berita, Agenda, Pengumuman',
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Teks singkat untuk tampilan landing page',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Isi artikel penuh (HTML/Rich Text)',
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Gambar sampul artikel',
  `status` enum('published','draft') DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_contents`
--

DROP TABLE IF EXISTS `article_contents`;
CREATE TABLE IF NOT EXISTS `article_contents` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key blok konten',
  `article_id` int UNSIGNED NOT NULL COMMENT 'ID artikel pemilik blok ini',
  `block_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jenis blok: paragraph, image, video_embed, list, alert, quote',
  `content_data` json NOT NULL COMMENT 'Isi blok dalam format JSON agar dinamis mengikuti tipe blok',
  `sort_order` int UNSIGNED DEFAULT '0' COMMENT 'Urutan tampil blok dari atas ke bawah',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel Child: Menyusun isi artikel berupa susunan blok dinamis';

-- --------------------------------------------------------

--
-- Table structure for table `article_tag_map`
--

DROP TABLE IF EXISTS `article_tag_map`;
CREATE TABLE IF NOT EXISTS `article_tag_map` (
  `article_id` int UNSIGNED NOT NULL COMMENT 'ID dari tabel articles',
  `tag_id` int UNSIGNED NOT NULL COMMENT 'ID dari tabel tags',
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel Pivot: Menghubungkan 1 artikel dengan banyak tag';

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

DROP TABLE IF EXISTS `facilities`;
CREATE TABLE IF NOT EXISTS `facilities` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key fasilitas',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama fasilitas (cth: Laboratorium, Perpustakaan)',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Deskripsi singkat mengenai fungsi fasilitas',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path atau URL foto fasilitas',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider fasilitas',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel sarana dan prasarana sekolah';

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key FAQ',
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pertanyaan yang sering diajukan',
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jawaban resmi dari pihak sekolah',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil akordeon FAQ',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel tanya jawab untuk seksi PPDB';

-- --------------------------------------------------------

--
-- Table structure for table `galleries`
--

DROP TABLE IF EXISTS `galleries`;
CREATE TABLE IF NOT EXISTS `galleries` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key foto galeri',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path atau URL gambar penuh kegiatan',
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teks alternatif (alt) atau keterangan foto',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di grid/slider galeri',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel kumpulan foto dokumentasi kegiatan sekolah';

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

DROP TABLE IF EXISTS `hero_slides`;
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key slide hero',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Judul besar pada slide (cth: KEDISIPLINAN)',
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks sub-judul pendukung di bawah judul besar',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path atau URL gambar latar belakang slide',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil (angka terkecil tampil duluan)',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Status aktif/tidaknya slide untuk ditampilkan',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk mengelola slide utama (Hero Section)';

-- --------------------------------------------------------

--
-- Table structure for table `student_activities`
--

DROP TABLE IF EXISTS `student_activities`;
CREATE TABLE IF NOT EXISTS `student_activities` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key kegiatan kesiswaan',
  `category` enum('organisasi','ekstrakurikuler','prestasi') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pengelompokan jenis kegiatan',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kegiatan/prestasi (cth: Pramuka, OSIS)',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Deskripsi detail dari kegiatan/prestasi tersebut',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di dalam kategori masing-masing',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk daftar organisasi, ekskul, dan prestasi siswa';

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key tag',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama tag (cth: Berita, Prestasi, Akademik)',
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Slug URL tag (cth: berita, prestasi)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel Master: Daftar tag/kategori yang tersedia';

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key data guru',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama lengkap guru beserta gelar',
  `role` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jabatan atau peran (cth: Wali Kelas 1, Guru PJOK)',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mata pelajaran khusus atau fokus utama pendidik',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path atau URL pas foto guru',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider guru',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel profil tenaga pendidik';

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key testimonial',
  `parent_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama orang tua yang memberikan ulasan',
  `quote` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Isi teks testimonial atau ulasan',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path atau URL foto profil orang tua (opsional)',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider testimonial',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel ulasan dan testimoni orang tua siswa';

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key video',
  `youtube_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID unik YouTube (cth: dpvPcsMVbWA dari youtube.com/watch?v=...)',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Judul video atau kegiatan',
  `student_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama siswa atau kelompok yang terlibat di video',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider video',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel kurasi konten video YouTube sekolah';

-- --------------------------------------------------------

--
-- Table structure for table `web_settings`
--

DROP TABLE IF EXISTS `web_settings`;
CREATE TABLE IF NOT EXISTS `web_settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `article_contents`
--
ALTER TABLE `article_contents`
  ADD CONSTRAINT `article_contents_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `article_tag_map`
--
ALTER TABLE `article_tag_map`
  ADD CONSTRAINT `article_tag_map_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_tag_map_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
