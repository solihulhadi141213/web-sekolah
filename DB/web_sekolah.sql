-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 20, 2026 at 08:10 PM
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
  `app_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama aplikasi klien yang menggunakan API (cth: CMS Production)',
  `api_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Kunci publik unik untuk identifikasi klien (Header: x-api-key)',
  `api_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Kunci rahasia (di-hash) untuk validasi otentikasi ketat',
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
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Judul artikel',
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'URL ramah SEO (cth: jadwal-kegiatan-tahfidz)',
  `category_tag` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Sesuai tag di HTML: Berita, Agenda, Pengumuman',
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Teks singkat untuk tampilan landing page (preview)',
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'Gambar sampul artikel',
  `status` enum('published','draft') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'published' COMMENT 'Status artikel di publis atau tidak',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `articles_to_file_manager` (`id_file_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_contents`
--

DROP TABLE IF EXISTS `article_contents`;
CREATE TABLE IF NOT EXISTS `article_contents` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key blok konten',
  `article_id` int UNSIGNED NOT NULL COMMENT 'ID artikel pemilik blok ini',
  `block_type` enum('Paragraph','List','Alert','Quote','Image URL','Image File','Video File','Video Embed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jenis blok: paragraph, image, video_embed, list, alert, quote',
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'Hanya Apabila konten adalah image file atau Video File',
  `content_metadata` json NOT NULL COMMENT 'Bagaimana conten ditampilkan pada halaman html',
  `sort_order` int UNSIGNED DEFAULT '0' COMMENT 'Urutan tampil blok dari atas ke bawah',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `article_contents_to_filemanager` (`id_file_manager`)
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
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'Foto Fasilitas',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider fasilitas',
  PRIMARY KEY (`id`),
  KEY `facilities_to_file_manager` (`id_file_manager`)
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
  `sort_order` int UNSIGNED DEFAULT '0' COMMENT 'Angka urutan tampil akordeon FAQ',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel tanya jawab untuk seksi PPDB';

-- --------------------------------------------------------

--
-- Table structure for table `file_manager`
--

DROP TABLE IF EXISTS `file_manager`;
CREATE TABLE IF NOT EXISTS `file_manager` (
  `id_file_manager` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID File Manager',
  `file_source` enum('Local Directory','External Link','Cloudinary','Imagekit') NOT NULL COMMENT 'Sumber file yang digunakan',
  `file_metadata` json NOT NULL COMMENT 'Metadata Dokumentasi (Image-Metadata-Explanation.md)',
  `creat_at` datetime NOT NULL COMMENT 'UTC',
  PRIMARY KEY (`id_file_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Mengelola file secara terpusat';

-- --------------------------------------------------------

--
-- Table structure for table `galleries`
--

DROP TABLE IF EXISTS `galleries`;
CREATE TABLE IF NOT EXISTS `galleries` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key foto galeri',
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'File Gambar',
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teks alternatif (alt) atau keterangan foto',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di grid/slider galeri',
  PRIMARY KEY (`id`),
  KEY `galleries_to_file_manager` (`id_file_manager`)
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
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'File Gambar',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil (angka terkecil tampil duluan)',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Status aktif/tidaknya slide untuk ditampilkan',
  PRIMARY KEY (`id`),
  KEY `hero_slides_to_file_manager` (`id_file_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk mengelola slide utama (Hero Section)';

-- --------------------------------------------------------

--
-- Table structure for table `rate_limit`
--

DROP TABLE IF EXISTS `rate_limit`;
CREATE TABLE IF NOT EXISTS `rate_limit` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `request_time` int UNSIGNED NOT NULL,
  `hit_count` smallint UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rate_limit` (`ip_address`,`endpoint`,`request_time`),
  KEY `idx_cleanup` (`request_time`),
  KEY `idx_endpoint` (`endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `siswa_baru`
--

DROP TABLE IF EXISTS `siswa_baru`;
CREATE TABLE IF NOT EXISTS `siswa_baru` (
  `id_siswa_baru` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_siswa` varchar(2555) NOT NULL,
  `gender` enum('Male','Female') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `tempat_lahir` varchar(255) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `alamat_tinggal` text NOT NULL,
  `nama_wali` varchar(255) NOT NULL,
  `kontak_wali` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id_siswa_baru`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key data guru',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama lengkap guru beserta gelar',
  `role` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jabatan atau peran (cth: Wali Kelas 1, Guru PJOK)',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mata pelajaran khusus atau fokus utama pendidik',
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'File Foto',
  `sort_order` int UNSIGNED DEFAULT '1' COMMENT 'Angka urutan tampil di slider guru',
  PRIMARY KEY (`id`),
  KEY `teachers_to_file_manager` (`id_file_manager`)
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
  `id_file_manager` int UNSIGNED DEFAULT NULL COMMENT 'Foto Testimonial',
  `sort_order` int DEFAULT '0' COMMENT 'Angka urutan tampil di slider testimonial',
  PRIMARY KEY (`id`),
  KEY `testimonials_to_file_manager` (`id_file_manager`)
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
  `setting_key` enum('site_title','site_description','site_theme_color','base_url','contact_phone','contact_whatsapp','contact_email','contact_address','contact_map_url','school_hours','social_instagram','social_facebook','social_blog','social_youtube','social_tiktok','stat_alumni','stat_students','stat_teachers','stat_achievements','kepsek_name','kepsek_title','kepsek_quote','ppdb_registration_fee','ppdb_monthly_spp') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
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
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `article_contents`
--
ALTER TABLE `article_contents`
  ADD CONSTRAINT `article_contents_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_contents_to_filemanager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `article_tag_map`
--
ALTER TABLE `article_tag_map`
  ADD CONSTRAINT `article_tag_map_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_tag_map_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `galleries`
--
ALTER TABLE `galleries`
  ADD CONSTRAINT `galleries_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD CONSTRAINT `hero_slides_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `testimonials_to_file_manager` FOREIGN KEY (`id_file_manager`) REFERENCES `file_manager` (`id_file_manager`) ON DELETE SET NULL ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
