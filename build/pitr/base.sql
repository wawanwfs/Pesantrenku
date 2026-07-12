-- MySQL dump 10.13  Distrib 8.4.10, for Linux (x86_64)
--
-- Host: localhost    Database: pesantenku
-- ------------------------------------------------------
-- Server version	8.4.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Position to start replication or point-in-time recovery from
--

-- CHANGE REPLICATION SOURCE TO SOURCE_LOG_FILE='mysql-bin.000003', SOURCE_LOG_POS=292371;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_years` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_years_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (1,1,'2024/2025','2024-07-01','2025-06-30',1,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,'2024/2025','2024-07-01','2025-06-30',1,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alumni`
--

DROP TABLE IF EXISTS `alumni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alumni` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `graduation_year` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `program` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alumni_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alumni`
--

LOCK TABLES `alumni` WRITE;
/*!40000 ALTER TABLE `alumni` DISABLE KEYS */;
INSERT INTO `alumni` VALUES (1,1,'Rizky Maulana','2024','Aliyah','Mahasiswa','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'Siti Nurhaliza','2024','Aliyah','Mahasiswi','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'Muhammad Fadli','2023','Aliyah','Karyawan','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,'Rizky Maulana','2024','Aliyah','Mahasiswa','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,'Siti Nurhaliza','2024','Aliyah','Mahasiswi','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,'Muhammad Fadli','2023','Aliyah','Karyawan','0812-3456-7890','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `alumni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Umum',
  `audience` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `published_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,1,'Ujian Semester Genap','1 - 10 Juni 2025','Akademik','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'Libur Awal Ramadhan','Kegiatan belajar mengajar diliburkan mulai 10 - 14 Maret 2025','Umum','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'Pembagian Rapor','Akan dilaksanakan pada 20 Juni 2025','Akademik','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,'Jaga Kebersihan Lingkungan','Buang sampah pada tempatnya.','Kesehatan','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,2,'Ujian Semester Genap','1 - 10 Juni 2025','Akademik','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,2,'Libur Awal Ramadhan','Kegiatan belajar mengajar diliburkan mulai 10 - 14 Maret 2025','Umum','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,2,'Pembagian Rapor','Akan dilaksanakan pada 20 Juni 2025','Akademik','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,2,'Jaga Kebersihan Lingkungan','Buang sampah pada tempatnya.','Kesehatan','all','2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'given',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignments_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,1,5,4,'Hafalan surat pendek','2025-05-25','given','{\"point\": 100}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,13,11,'Hafalan surat pendek','2025-05-25','given','{\"point\": 100}','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `session` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendances_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (1,1,1,5,'2025-05-21','Bahasa Arab','hadir','Hadir tepat waktu','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,11,'2025-05-21','Bahasa Arab','hadir','Hadir tepat waktu','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,NULL,'Tagihan SPP massal dibuat','Keuangan','103.147.12.46','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,NULL,'Nilai siswa diimpor','Akademik','103.147.12.47','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,NULL,'Surat keluar dibuat','Tata Usaha','103.147.12.48','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,NULL,'Tagihan SPP massal dibuat','Keuangan','103.147.12.46','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,NULL,'Nilai siswa diimpor','Akademik','103.147.12.47','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,NULL,'Surat keluar dibuat','Tata Usaha','103.147.12.48','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,NULL,1,'Login dashboard super admin','Autentikasi','103.147.12.45','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,NULL,1,'Pesantren baru didaftarkan','Manajemen Sistem','103.147.12.45','{\"status\": \"success\"}','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_restore_requests`
--

DROP TABLE IF EXISTS `backup_restore_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backup_restore_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint unsigned NOT NULL,
  `pesantren_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approvals` json DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `executed_by` bigint unsigned DEFAULT NULL,
  `result` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_restore_requests_backup_id_index` (`backup_id`),
  KEY `backup_restore_requests_pesantren_id_index` (`pesantren_id`),
  KEY `backup_restore_requests_requested_by_index` (`requested_by`),
  KEY `backup_restore_requests_status_index` (`status`),
  KEY `backup_restore_requests_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_restore_requests`
--

LOCK TABLES `backup_restore_requests` WRITE;
/*!40000 ALTER TABLE `backup_restore_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_restore_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backups`
--

DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encryption_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encryption_key_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backups_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backups`
--

LOCK TABLES `backups` WRITE;
/*!40000 ALTER TABLE `backups` DISABLE KEYS */;
INSERT INTO `backups` VALUES (1,1,'dh-backup-2025-05-21.zip','success','backups/dh-backup-2025-05-21.zip',NULL,NULL,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,'af-backup-2025-05-21.zip','success','backups/af-backup-2025-05-21.zip',NULL,NULL,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,NULL,'platform-backup-2025-05-21.zip','success','backups/platform-backup-2025-05-21.zip',NULL,NULL,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `backups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_accounts_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
INSERT INTO `bank_accounts` VALUES (1,1,'Bank Syariah Indonesia (BSI)','7182 0100 1234 5678','Pondok Pesantren Darul Hikmah',2125450000.00,1,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'Bank Muamalat','30110 0012 3456 7890','Pondok Pesantren Darul Hikmah',1250300000.00,0,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'Bank BCA','1234 5678 9012 3456','Pondok Pesantren Darul Hikmah',1070470000.00,0,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,2,'Bank Syariah Indonesia (BSI)','7182 0100 1234 5678','Pesantren Al-Falah',2125450000.00,1,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,2,'Bank Muamalat','30110 0012 3456 7890','Pesantren Al-Falah',1250300000.00,0,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,2,'Bank BCA','1234 5678 9012 3456','Pesantren Al-Falah',1070470000.00,0,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cashflow_entries`
--

DROP TABLE IF EXISTS `cashflow_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashflow_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashflow_entries_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cashflow_entries`
--

LOCK TABLES `cashflow_entries` WRITE;
/*!40000 ALTER TABLE `cashflow_entries` DISABLE KEYS */;
INSERT INTO `cashflow_entries` VALUES (1,1,'pemasukan','SPP',1850450000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'pemasukan','Uang Makan',780120000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'pengeluaran','Gaji Guru & Staf',650000000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,'pengeluaran','Belanja Operasional',280450000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,'pemasukan','SPP',1850450000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,'pemasukan','Uang Makan',780120000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,2,'pengeluaran','Gaji Guru & Staf',650000000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,2,'pengeluaran','Belanja Operasional',280450000.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `cashflow_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `program_id` bigint unsigned DEFAULT NULL,
  `academic_year_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `homeroom_teacher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_count` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classes_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,1,1,'7A','Tsanawiyah','7','Ust. Ahmad Fauzi',32,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,1,1,'7B','Tsanawiyah','7','Ust. Nur Halimah',31,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,1,1,'8A','Tsanawiyah','8','Ust. Muhammad Iqbal',30,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,1,1,'8B','Tsanawiyah','8','Usth. Siti Aisyah',31,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,1,1,'9A','Tsanawiyah','9','Bapak Nur Hidayat',32,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,1,1,1,'9B','Tsanawiyah','9','Ust. Ahmad Fauzi',31,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,1,2,1,'10A','Aliyah','10','Ust. Nur Halimah',29,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,1,2,1,'10B','Aliyah','10','Ust. Muhammad Iqbal',28,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,1,2,1,'11A','Aliyah','11','Usth. Siti Aisyah',27,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,1,2,1,'11B','Aliyah','11','Bapak Nur Hidayat',26,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(11,2,4,2,'7A','Tsanawiyah','7','Ust. Ahmad Fauzi',32,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(12,2,4,2,'8A','Tsanawiyah','8','Ust. Nur Halimah',31,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(13,2,4,2,'9A','Tsanawiyah','9','Ust. Muhammad Iqbal',30,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(14,2,4,2,'10A','Tsanawiyah','10','Usth. Siti Aisyah',31,'active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinics`
--

DROP TABLE IF EXISTS `clinics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinics_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinics`
--

LOCK TABLES `clinics` WRITE;
/*!40000 ALTER TABLE `clinics` DISABLE KEYS */;
INSERT INTO `clinics` VALUES (1,1,'Klinik Pesantren','031-7654321','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,'Klinik Pesantren','031-7654321','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `clinics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_notes`
--

DROP TABLE IF EXISTS `daily_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `noted_at` datetime DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_notes_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_notes`
--

LOCK TABLES `daily_notes` WRITE;
/*!40000 ALTER TABLE `daily_notes` DISABLE KEYS */;
INSERT INTO `daily_notes` VALUES (1,1,1,'2025-05-20 20:00:00','Mengikuti halaqah malam dengan baik.','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'2025-05-20 20:00:00','Mengikuti halaqah malam dengan baik.','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `daily_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discounts`
--

DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discounts_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discounts`
--

LOCK TABLES `discounts` WRITE;
/*!40000 ALTER TABLE `discounts` DISABLE KEYS */;
INSERT INTO `discounts` VALUES (1,1,1,'Keringanan Syahriyah',150000.00,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,'Keringanan Syahriyah',150000.00,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,2,10,'Keringanan Syahriyah',150000.00,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,11,'Keringanan Syahriyah',150000.00,'active','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `discounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doctors_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (1,1,'dr. Ahmad Fauzi','Dokter Umum','Senin - Jumat 08:00 - 12:00','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'dr. Nur Halimah','Dokter Umum','Senin - Kamis 13:00 - 16:00','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,2,'dr. Ahmad Fauzi','Dokter Umum','Senin - Jumat 08:00 - 12:00','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,'dr. Nur Halimah','Dokter Umum','Senin - Kamis 13:00 - 16:00','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dorm_attendances`
--

DROP TABLE IF EXISTS `dorm_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dorm_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `activity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dorm_attendances_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dorm_attendances`
--

LOCK TABLES `dorm_attendances` WRITE;
/*!40000 ALTER TABLE `dorm_attendances` DISABLE KEYS */;
INSERT INTO `dorm_attendances` VALUES (1,1,1,1,'2025-05-21','Halaqah malam','hadir','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,7,'2025-05-21','Halaqah malam','hadir','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `dorm_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_categories_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,1,'Operasional','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,'Operasional','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `expense_category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `date` date DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,1,1,'Belanja dapur pesantren',280450000.00,'2025-05-20','Kebutuhan operasional bulanan','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,2,'Belanja dapur pesantren',280450000.00,'2025-05-20','Kebutuhan operasional bulanan','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `semester` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Genap 2024/2025',
  `assignment_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `midterm_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `final_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `attitude_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `average_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grades_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
INSERT INTO `grades` VALUES (1,1,1,5,1,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,1,5,2,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,1,5,3,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,1,5,4,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,1,5,5,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,2,4,1,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,2,4,2,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,2,4,3,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,2,4,4,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,1,2,4,5,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,1,3,5,1,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,1,3,5,2,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(13,1,3,5,3,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(14,1,3,5,4,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(15,1,3,5,5,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(16,1,4,6,1,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(17,1,4,6,2,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(18,1,4,6,3,'Genap 2024/2025',79.00,84.00,86.00,88.00,84.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(19,1,4,6,4,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(20,1,4,6,5,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(21,1,5,3,1,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(22,1,5,3,2,'Genap 2024/2025',79.00,84.00,86.00,88.00,84.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(23,1,5,3,3,'Genap 2024/2025',78.00,83.00,85.00,88.00,83.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(24,1,5,3,4,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(25,1,5,3,5,'Genap 2024/2025',79.00,84.00,86.00,88.00,84.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(26,1,6,3,1,'Genap 2024/2025',79.00,84.00,86.00,88.00,84.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(27,1,6,3,2,'Genap 2024/2025',78.00,83.00,85.00,88.00,83.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(28,1,6,3,3,'Genap 2024/2025',77.00,82.00,84.00,88.00,82.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(29,1,6,3,4,'Genap 2024/2025',79.00,84.00,86.00,88.00,84.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(30,1,6,3,5,'Genap 2024/2025',78.00,83.00,85.00,88.00,83.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(31,1,7,2,1,'Genap 2024/2025',85.00,90.00,92.00,88.00,90.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(32,1,7,2,2,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(33,1,7,2,3,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(34,1,7,2,4,'Genap 2024/2025',85.00,90.00,92.00,88.00,90.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(35,1,7,2,5,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(36,1,8,7,1,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(37,1,8,7,2,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(38,1,8,7,3,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(39,1,8,7,4,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(40,1,8,7,5,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(41,1,9,9,1,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(42,1,9,9,2,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(43,1,9,9,3,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(44,1,9,9,4,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(45,1,9,9,5,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(46,2,10,11,8,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(47,2,10,11,9,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(48,2,10,11,10,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(49,2,10,11,11,'Genap 2024/2025',84.00,89.00,91.00,88.00,89.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(50,2,10,11,12,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(51,2,11,12,8,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(52,2,11,12,9,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(53,2,11,12,10,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(54,2,11,12,11,'Genap 2024/2025',83.00,88.00,90.00,88.00,88.00,'A','2026-07-11 04:03:02','2026-07-11 04:03:02'),(55,2,11,12,12,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(56,2,12,13,8,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(57,2,12,13,9,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(58,2,12,13,10,'Genap 2024/2025',80.00,85.00,87.00,88.00,85.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(59,2,12,13,11,'Genap 2024/2025',82.00,87.00,89.00,88.00,87.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02'),(60,2,12,13,12,'Genap 2024/2025',81.00,86.00,88.00,88.00,86.00,'A-','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guardians` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guardians_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guardians`
--

LOCK TABLES `guardians` WRITE;
/*!40000 ALTER TABLE `guardians` DISABLE KEYS */;
INSERT INTO `guardians` VALUES (1,1,7,'Nur Azizah Ghafur','0857-1001-1270','dh.wali@example.test','Jl. Raya Pegantenan KM 09, Pamekasan, Jawa Timur','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,2,NULL,'Wali Santri Al-Falah','0857-1001-1270','af.wali@example.test','Jl. Raya Pegantenan KM 09, Pamekasan, Jawa Timur','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `guardians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `idempotency_keys`
--

DROP TABLE IF EXISTS `idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `scope` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `response_status` smallint unsigned DEFAULT NULL,
  `response_body` longtext COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_scope_key_unique` (`user_id`,`scope`,`key`),
  KEY `idempotency_keys_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `idempotency_keys`
--

LOCK TABLES `idempotency_keys` WRITE;
/*!40000 ALTER TABLE `idempotency_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `idempotency_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,1,1,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,1,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,1,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,1,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,2,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,2,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,2,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,2,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,3,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,1,3,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,1,3,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,1,3,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(13,1,4,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(14,1,4,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(15,1,4,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(16,1,4,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(17,1,5,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(18,1,5,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(19,1,5,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(20,1,5,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(21,1,6,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(22,1,6,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(23,1,6,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(24,1,6,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(25,2,7,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(26,2,7,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(27,2,7,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(28,2,7,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(29,2,8,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(30,2,8,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(31,2,8,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(32,2,8,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(33,2,9,'SPP Mei 2025',800000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(34,2,9,'Uang Makan Mei 2025',500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(35,2,9,'Uang Kegiatan',150000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(36,2,9,'Seragam Olahraga',50000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_pesantren_id_invoice_number_unique` (`pesantren_id`,`invoice_number`),
  KEY `invoices_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,1,'INV/DH/2025/05/001','Mei 2025',1350000.00,'2025-05-10','paid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,2,'INV/DH/2025/05/002','Mei 2025',900000.00,'2025-05-10','paid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,3,'INV/DH/2025/05/003','Mei 2025',1350000.00,'2025-05-10','unpaid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,4,'INV/DH/2025/05/004','Mei 2025',900000.00,'2025-05-10','unpaid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,5,'INV/DH/2025/05/005','Mei 2025',1350000.00,'2025-05-10','unpaid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,1,6,'INV/DH/2025/05/006','Mei 2025',900000.00,'2025-05-10','unpaid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,2,10,'INV/AF/2025/05/001','Mei 2025',1350000.00,'2025-05-10','paid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,2,11,'INV/AF/2025/05/002','Mei 2025',900000.00,'2025-05-10','paid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,2,12,'INV/AF/2025/05/003','Mei 2025',1350000.00,'2025-05-10','unpaid','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `letters`
--

DROP TABLE IF EXISTS `letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'done',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `letters_pesantren_id_number_unique` (`pesantren_id`,`number`),
  KEY `letters_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `letters`
--

LOCK TABLES `letters` WRITE;
/*!40000 ALTER TABLE `letters` DISABLE KEYS */;
INSERT INTO `letters` VALUES (1,1,'DH/SK/052/TU/2025','Surat Keterangan Aktif','outgoing','Wali Santri','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'DH/SM/05/TU/2025','Surat Izin Pulang','incoming','Orang Tua','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'DH/SK/050/TU/2025','Surat Keterangan','outgoing','Bank Syariah','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,'AF/SK/052/TU/2025','Surat Keterangan Aktif','outgoing','Wali Santri','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,'AF/SM/05/TU/2025','Surat Izin Pulang','incoming','Orang Tua','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,'AF/SK/050/TU/2025','Surat Keterangan','outgoing','Bank Syariah','2025-05-20','done','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `letters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `materials_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materials`
--

LOCK TABLES `materials` WRITE;
/*!40000 ALTER TABLE `materials` DISABLE KEYS */;
INSERT INTO `materials` VALUES (1,1,5,4,'Materi Fiqih Ibadah','pdf','2.4 MB','/storage/materials/fiqih-ibadah.pdf','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,13,11,'Materi Fiqih Ibadah','pdf','2.4 MB','/storage/materials/fiqih-ibadah.pdf','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_examinations`
--

DROP TABLE IF EXISTS `medical_examinations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_examinations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pulse` int unsigned DEFAULT NULL,
  `doctor_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_examinations_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_examinations`
--

LOCK TABLES `medical_examinations` WRITE;
/*!40000 ALTER TABLE `medical_examinations` DISABLE KEYS */;
INSERT INTO `medical_examinations` VALUES (1,1,1,37.8,'110/70',82,'Istirahat dan cukup minum.','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,2,37.8,'110/70',82,'Istirahat dan cukup minum.','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `medical_examinations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_queues`
--

DROP TABLE IF EXISTS `medical_queues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_queues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `queue_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `estimated_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_queues_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_queues`
--

LOCK TABLES `medical_queues` WRITE;
/*!40000 ALTER TABLE `medical_queues` DISABLE KEYS */;
INSERT INTO `medical_queues` VALUES (1,1,1,'A-012','waiting','09:30:00','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'A-012','waiting','09:30:00','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `medical_queues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_records`
--

DROP TABLE IF EXISTS `medical_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `complaint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnosis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `doctor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_records_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_records`
--

LOCK TABLES `medical_records` WRITE;
/*!40000 ALTER TABLE `medical_records` DISABLE KEYS */;
INSERT INTO `medical_records` VALUES (1,1,1,'Demam dan sakit kepala','Demam','2025-05-12','dr. Ahmad Fauzi','{\"temperature\": 37.8, \"blood_pressure\": \"110/70 mmHg\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'Demam dan sakit kepala','Demam','2025-05-12','dr. Ahmad Fauzi','{\"temperature\": 37.8, \"blood_pressure\": \"110/70 mmHg\"}','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `medical_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicine_stocks`
--

DROP TABLE IF EXISTS `medicine_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicine_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `medicine_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medicine_stocks_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicine_stocks`
--

LOCK TABLES `medicine_stocks` WRITE;
/*!40000 ALTER TABLE `medicine_stocks` DISABLE KEYS */;
INSERT INTO `medicine_stocks` VALUES (1,1,1,40,'in','2025-05-18','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,6,40,'in','2025-05-18','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `medicine_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int unsigned NOT NULL DEFAULT '0',
  `minimum_stock` int unsigned NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medicines_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
INSERT INTO `medicines` VALUES (1,1,'Paracetamol 500 mg','tablet',120,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'Ibuprofen 400 mg','tablet',85,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'CTM 4 mg','tablet',30,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,'Vitamin C 500 mg','tablet',60,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,'Minyak Kayu Putih 60 ml','botol',15,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,2,'Paracetamol 500 mg','tablet',120,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,2,'Ibuprofen 400 mg','tablet',85,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,2,'CTM 4 mg','tablet',30,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,2,'Vitamin C 500 mg','tablet',60,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,2,'Minyak Kayu Putih 60 ml','botol',15,20,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memorization_records`
--

DROP TABLE IF EXISTS `memorization_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `memorization_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `juz` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `surah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ayat_range` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `recorded_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `memorization_records_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memorization_records`
--

LOCK TABLES `memorization_records` WRITE;
/*!40000 ALTER TABLE `memorization_records` DISABLE KEYS */;
INSERT INTO `memorization_records` VALUES (1,1,1,'Juz 6','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,'Juz 7','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,3,'Juz 8','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,4,'Juz 9','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,5,'Juz 10','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,6,'Juz 11','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,7,'Juz 12','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,8,'Juz 1','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,9,'Juz 2','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,2,10,'Juz 6','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,2,11,'Juz 7','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,2,12,'Juz 8','Al-Baqarah','1-20',75.00,'2025-05-20','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `memorization_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_06_000001_create_pesantenku_domain_tables',1),(5,'2026_07_06_172446_create_personal_access_tokens_table',1),(6,'2026_07_08_000002_create_qr_tokens_table',1),(7,'2026_07_09_000003_create_operational_assignment_tables',1),(8,'2026_07_10_000004_create_operation_jobs_table',1),(9,'2026_07_11_000005_add_scaling_and_tenant_roles',1),(10,'2026_07_11_000006_add_reliability_tables',1),(11,'2026_07_11_000007_add_compliance_security',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `musyrif_room_assignments`
--

DROP TABLE IF EXISTS `musyrif_room_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `musyrif_room_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `assigned_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `musyrif_room_assignments_pesantren_id_index` (`pesantren_id`),
  KEY `musyrif_room_assignments_user_id_index` (`user_id`),
  KEY `musyrif_room_assignments_room_id_index` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `musyrif_room_assignments`
--

LOCK TABLES `musyrif_room_assignments` WRITE;
/*!40000 ALTER TABLE `musyrif_room_assignments` DISABLE KEYS */;
INSERT INTO `musyrif_room_assignments` VALUES (1,1,9,1,'2024-07-01',NULL,'active','Binaan asrama putri','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,9,2,'2024-07-01',NULL,'active','Binaan asrama putri','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,9,3,'2024-07-01',NULL,'active','Binaan asrama putri','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,9,4,'2024-07-01',NULL,'active','Binaan asrama putri','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `musyrif_room_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_logs_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
INSERT INTO `notification_logs` VALUES (1,1,NULL,'Pembayaran Berhasil','Syahriyah Mei 2025 telah lunas','success',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,NULL,'Perizinan Disetujui','Izin keluarga 10-12 Mei disetujui','info',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,NULL,'Tagihan Baru','Tagihan bulan Juni 2025 telah dibuat','warning',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,NULL,'Pembayaran Berhasil','Syahriyah Mei 2025 telah lunas','success',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,NULL,'Perizinan Disetujui','Izin keluarga 10-12 Mei disetujui','info',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,NULL,'Tagihan Baru','Tagihan bulan Juni 2025 telah dibuat','warning',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operation_jobs`
--

DROP TABLE IF EXISTS `operation_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `progress` tinyint unsigned NOT NULL DEFAULT '0',
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `processed_rows` int unsigned NOT NULL DEFAULT '0',
  `queue_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `result` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operation_jobs_uuid_unique` (`uuid`),
  KEY `operation_jobs_pesantren_id_index` (`pesantren_id`),
  KEY `operation_jobs_user_id_index` (`user_id`),
  KEY `operation_jobs_type_index` (`type`),
  KEY `operation_jobs_resource_index` (`resource`),
  KEY `operation_jobs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operation_jobs`
--

LOCK TABLES `operation_jobs` WRITE;
/*!40000 ALTER TABLE `operation_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `operation_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_methods_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` VALUES (1,1,'Transfer Bank','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'Bayar di Kantor','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'E-Wallet','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,'Virtual Account','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,'Transfer Bank','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,'Bayar di Kantor','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,2,'E-Wallet','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,2,'Virtual Account','active','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,1,1,1,1350000.00,'2026-07-11 04:03:02','success','PAY-DH-20260711-0','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,2,1,900000.00,'2026-07-11 04:03:02','success','PAY-DH-20260711-1','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,3,3,1,500000.00,'2026-07-11 04:03:02','pending','WAIT-DH-20260711-2','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,7,10,5,1350000.00,'2026-07-11 04:03:02','success','PAY-AF-20260711-0','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,8,11,5,900000.00,'2026-07-11 04:03:02','success','PAY-AF-20260711-1','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,9,12,5,500000.00,'2026-07-11 04:03:02','pending','WAIT-AF-20260711-2','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_role_permission_id_role_id_unique` (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'users.read','Baca Users','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,'users.write','Kelola Users','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,'roles.read','Baca Roles','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,'roles.write','Kelola Roles','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,'pesantrens.read','Baca Pesantrens','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,'pesantrens.write','Kelola Pesantrens','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,'plans.read','Baca Plans','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,'plans.write','Kelola Plans','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,'subscriptions.read','Baca Subscriptions','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,'subscriptions.write','Kelola Subscriptions','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,'tenant-modules.read','Baca Tenant Modules','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,'tenant-modules.write','Kelola Tenant Modules','2026-07-11 04:03:02','2026-07-11 04:03:02'),(13,'memberships.read','Baca Memberships','2026-07-11 04:03:02','2026-07-11 04:03:02'),(14,'memberships.write','Kelola Memberships','2026-07-11 04:03:02','2026-07-11 04:03:02'),(15,'academic-years.read','Baca Academic Years','2026-07-11 04:03:02','2026-07-11 04:03:02'),(16,'academic-years.write','Kelola Academic Years','2026-07-11 04:03:02','2026-07-11 04:03:02'),(17,'classes.read','Baca Classes','2026-07-11 04:03:02','2026-07-11 04:03:02'),(18,'classes.write','Kelola Classes','2026-07-11 04:03:02','2026-07-11 04:03:02'),(19,'programs.read','Baca Programs','2026-07-11 04:03:02','2026-07-11 04:03:02'),(20,'programs.write','Kelola Programs','2026-07-11 04:03:02','2026-07-11 04:03:02'),(21,'subjects.read','Baca Subjects','2026-07-11 04:03:02','2026-07-11 04:03:02'),(22,'subjects.write','Kelola Subjects','2026-07-11 04:03:02','2026-07-11 04:03:02'),(23,'teacher-assignments.read','Baca Teacher Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(24,'teacher-assignments.write','Kelola Teacher Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(25,'students.read','Baca Students','2026-07-11 04:03:02','2026-07-11 04:03:02'),(26,'students.write','Kelola Students','2026-07-11 04:03:02','2026-07-11 04:03:02'),(27,'guardians.read','Baca Guardians','2026-07-11 04:03:02','2026-07-11 04:03:02'),(28,'guardians.write','Kelola Guardians','2026-07-11 04:03:02','2026-07-11 04:03:02'),(29,'teachers.read','Baca Teachers','2026-07-11 04:03:02','2026-07-11 04:03:02'),(30,'teachers.write','Kelola Teachers','2026-07-11 04:03:02','2026-07-11 04:03:02'),(31,'staff.read','Baca Staff','2026-07-11 04:03:02','2026-07-11 04:03:02'),(32,'staff.write','Kelola Staff','2026-07-11 04:03:02','2026-07-11 04:03:02'),(33,'rooms.read','Baca Rooms','2026-07-11 04:03:02','2026-07-11 04:03:02'),(34,'rooms.write','Kelola Rooms','2026-07-11 04:03:02','2026-07-11 04:03:02'),(35,'room-assignments.read','Baca Room Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(36,'room-assignments.write','Kelola Room Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(37,'musyrif-room-assignments.read','Baca Musyrif Room Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(38,'musyrif-room-assignments.write','Kelola Musyrif Room Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(39,'attendances.read','Baca Attendances','2026-07-11 04:03:02','2026-07-11 04:03:02'),(40,'attendances.write','Kelola Attendances','2026-07-11 04:03:02','2026-07-11 04:03:02'),(41,'dorm-attendances.read','Baca Dorm Attendances','2026-07-11 04:03:02','2026-07-11 04:03:02'),(42,'dorm-attendances.write','Kelola Dorm Attendances','2026-07-11 04:03:02','2026-07-11 04:03:02'),(43,'grades.read','Baca Grades','2026-07-11 04:03:02','2026-07-11 04:03:02'),(44,'grades.write','Kelola Grades','2026-07-11 04:03:02','2026-07-11 04:03:02'),(45,'report-cards.read','Baca Report Cards','2026-07-11 04:03:02','2026-07-11 04:03:02'),(46,'report-cards.write','Kelola Report Cards','2026-07-11 04:03:02','2026-07-11 04:03:02'),(47,'memorization-records.read','Baca Memorization Records','2026-07-11 04:03:02','2026-07-11 04:03:02'),(48,'memorization-records.write','Kelola Memorization Records','2026-07-11 04:03:02','2026-07-11 04:03:02'),(49,'schedules.read','Baca Schedules','2026-07-11 04:03:02','2026-07-11 04:03:02'),(50,'schedules.write','Kelola Schedules','2026-07-11 04:03:02','2026-07-11 04:03:02'),(51,'assignments.read','Baca Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(52,'assignments.write','Kelola Assignments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(53,'materials.read','Baca Materials','2026-07-11 04:03:02','2026-07-11 04:03:02'),(54,'materials.write','Kelola Materials','2026-07-11 04:03:02','2026-07-11 04:03:02'),(55,'announcements.read','Baca Announcements','2026-07-11 04:03:02','2026-07-11 04:03:02'),(56,'announcements.write','Kelola Announcements','2026-07-11 04:03:02','2026-07-11 04:03:02'),(57,'violations.read','Baca Violations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(58,'violations.write','Kelola Violations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(59,'permits.read','Baca Permits','2026-07-11 04:03:02','2026-07-11 04:03:02'),(60,'permits.write','Kelola Permits','2026-07-11 04:03:02','2026-07-11 04:03:02'),(61,'daily-notes.read','Baca Daily Notes','2026-07-11 04:03:02','2026-07-11 04:03:02'),(62,'daily-notes.write','Kelola Daily Notes','2026-07-11 04:03:02','2026-07-11 04:03:02'),(63,'invoices.read','Baca Invoices','2026-07-11 04:03:02','2026-07-11 04:03:02'),(64,'invoices.write','Kelola Invoices','2026-07-11 04:03:02','2026-07-11 04:03:02'),(65,'invoice-items.read','Baca Invoice Items','2026-07-11 04:03:02','2026-07-11 04:03:02'),(66,'invoice-items.write','Kelola Invoice Items','2026-07-11 04:03:02','2026-07-11 04:03:02'),(67,'payments.read','Baca Payments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(68,'payments.write','Kelola Payments','2026-07-11 04:03:02','2026-07-11 04:03:02'),(69,'payment-methods.read','Baca Payment Methods','2026-07-11 04:03:02','2026-07-11 04:03:02'),(70,'payment-methods.write','Kelola Payment Methods','2026-07-11 04:03:02','2026-07-11 04:03:02'),(71,'bank-accounts.read','Baca Bank Accounts','2026-07-11 04:03:02','2026-07-11 04:03:02'),(72,'bank-accounts.write','Kelola Bank Accounts','2026-07-11 04:03:02','2026-07-11 04:03:02'),(73,'expense-categories.read','Baca Expense Categories','2026-07-11 04:03:02','2026-07-11 04:03:02'),(74,'expense-categories.write','Kelola Expense Categories','2026-07-11 04:03:02','2026-07-11 04:03:02'),(75,'expenses.read','Baca Expenses','2026-07-11 04:03:02','2026-07-11 04:03:02'),(76,'expenses.write','Kelola Expenses','2026-07-11 04:03:02','2026-07-11 04:03:02'),(77,'cashflow-entries.read','Baca Cashflow Entries','2026-07-11 04:03:02','2026-07-11 04:03:02'),(78,'cashflow-entries.write','Kelola Cashflow Entries','2026-07-11 04:03:02','2026-07-11 04:03:02'),(79,'discounts.read','Baca Discounts','2026-07-11 04:03:02','2026-07-11 04:03:02'),(80,'discounts.write','Kelola Discounts','2026-07-11 04:03:02','2026-07-11 04:03:02'),(81,'letters.read','Baca Letters','2026-07-11 04:03:02','2026-07-11 04:03:02'),(82,'letters.write','Kelola Letters','2026-07-11 04:03:02','2026-07-11 04:03:02'),(83,'room-mutations.read','Baca Room Mutations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(84,'room-mutations.write','Kelola Room Mutations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(85,'alumni.read','Baca Alumni','2026-07-11 04:03:02','2026-07-11 04:03:02'),(86,'alumni.write','Kelola Alumni','2026-07-11 04:03:02','2026-07-11 04:03:02'),(87,'clinics.read','Baca Clinics','2026-07-11 04:03:02','2026-07-11 04:03:02'),(88,'clinics.write','Kelola Clinics','2026-07-11 04:03:02','2026-07-11 04:03:02'),(89,'doctors.read','Baca Doctors','2026-07-11 04:03:02','2026-07-11 04:03:02'),(90,'doctors.write','Kelola Doctors','2026-07-11 04:03:02','2026-07-11 04:03:02'),(91,'medicines.read','Baca Medicines','2026-07-11 04:03:02','2026-07-11 04:03:02'),(92,'medicines.write','Kelola Medicines','2026-07-11 04:03:02','2026-07-11 04:03:02'),(93,'medicine-stocks.read','Baca Medicine Stocks','2026-07-11 04:03:02','2026-07-11 04:03:02'),(94,'medicine-stocks.write','Kelola Medicine Stocks','2026-07-11 04:03:02','2026-07-11 04:03:02'),(95,'medical-queues.read','Baca Medical Queues','2026-07-11 04:03:02','2026-07-11 04:03:02'),(96,'medical-queues.write','Kelola Medical Queues','2026-07-11 04:03:02','2026-07-11 04:03:02'),(97,'medical-records.read','Baca Medical Records','2026-07-11 04:03:02','2026-07-11 04:03:02'),(98,'medical-records.write','Kelola Medical Records','2026-07-11 04:03:02','2026-07-11 04:03:02'),(99,'medical-examinations.read','Baca Medical Examinations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(100,'medical-examinations.write','Kelola Medical Examinations','2026-07-11 04:03:02','2026-07-11 04:03:02'),(101,'prescriptions.read','Baca Prescriptions','2026-07-11 04:03:02','2026-07-11 04:03:02'),(102,'prescriptions.write','Kelola Prescriptions','2026-07-11 04:03:02','2026-07-11 04:03:02'),(103,'sick-letters.read','Baca Sick Letters','2026-07-11 04:03:02','2026-07-11 04:03:02'),(104,'sick-letters.write','Kelola Sick Letters','2026-07-11 04:03:02','2026-07-11 04:03:02'),(105,'referrals.read','Baca Referrals','2026-07-11 04:03:02','2026-07-11 04:03:02'),(106,'referrals.write','Kelola Referrals','2026-07-11 04:03:02','2026-07-11 04:03:02'),(107,'notifications.read','Baca Notifications','2026-07-11 04:03:02','2026-07-11 04:03:02'),(108,'notifications.write','Kelola Notifications','2026-07-11 04:03:02','2026-07-11 04:03:02'),(109,'audit-logs.read','Baca Audit Logs','2026-07-11 04:03:02','2026-07-11 04:03:02'),(110,'audit-logs.write','Kelola Audit Logs','2026-07-11 04:03:02','2026-07-11 04:03:02'),(111,'system-settings.read','Baca System Settings','2026-07-11 04:03:02','2026-07-11 04:03:02'),(112,'system-settings.write','Kelola System Settings','2026-07-11 04:03:02','2026-07-11 04:03:02'),(113,'backups.read','Baca Backups','2026-07-11 04:03:02','2026-07-11 04:03:02'),(114,'backups.write','Kelola Backups','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permits`
--

DROP TABLE IF EXISTS `permits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permits_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permits`
--

LOCK TABLES `permits` WRITE;
/*!40000 ALTER TABLE `permits` DISABLE KEYS */;
INSERT INTO `permits` VALUES (1,1,1,'Izin Keluar','2025-05-22 16:00:00','2025-05-22 20:00:00','Pulang ke rumah','pending','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'Izin Keluar','2025-05-22 16:00:00','2025-05-22 20:00:00','Pulang ke rumah','pending','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `permits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pesantren_user`
--

DROP TABLE IF EXISTS `pesantren_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pesantren_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `tenant_role_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pesantren_user_pesantren_id_user_id_role_id_unique` (`pesantren_id`,`user_id`,`role_id`),
  KEY `pesantren_user_user_id_foreign` (`user_id`),
  KEY `pesantren_user_role_id_foreign` (`role_id`),
  KEY `pesantren_user_pesantren_id_index` (`pesantren_id`),
  KEY `pesantren_user_tenant_role_id_index` (`tenant_role_id`),
  CONSTRAINT `pesantren_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pesantren_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pesantren_user`
--

LOCK TABLES `pesantren_user` WRITE;
/*!40000 ALTER TABLE `pesantren_user` DISABLE KEYS */;
INSERT INTO `pesantren_user` VALUES (1,1,2,2,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,2,2,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,3,3,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,4,4,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,5,5,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,6,6,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,7,7,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,8,8,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,9,9,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,1,10,10,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,2,11,3,NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `pesantren_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pesantrens`
--

DROP TABLE IF EXISTS `pesantrens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pesantrens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pesantrens`
--

LOCK TABLES `pesantrens` WRITE;
/*!40000 ALTER TABLE `pesantrens` DISABLE KEYS */;
INSERT INTO `pesantrens` VALUES (1,'Pondok Pesantren Darul Hikmah','Surabaya','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur','031-1234567','info@darulhikmah.sch.id',NULL,'active','{\"total_santri\": 1248, \"server_status\": 100}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,'Pesantren Al-Falah','Bandung','Jl. Cibiru Indah No. 45, Bandung, Jawa Barat','022-778899','admin@alfalah.sch.id',NULL,'active','{\"total_santri\": 642, \"server_status\": 99}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `pesantrens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monthly_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `max_students` int unsigned NOT NULL DEFAULT '0',
  `max_users` int unsigned NOT NULL DEFAULT '0',
  `features` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` VALUES (1,'Starter Pesantren','starter',750000.00,750,1500,'[\"akademik\", \"keuangan\", \"tu\"]','active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,'Growth Pesantren','growth',1500000.00,2500,6000,'[\"akademik\", \"keuangan\", \"tu\", \"asrama\", \"klinik\", \"api\"]','active','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `medical_record_id` bigint unsigned DEFAULT NULL,
  `medicine_id` bigint unsigned DEFAULT NULL,
  `dosage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescriptions_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
INSERT INTO `prescriptions` VALUES (1,1,1,1,'3x1 setelah makan',6,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,2,6,'3x1 setelah makan',6,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_pesantren_id_code_unique` (`pesantren_id`,`code`),
  KEY `programs_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,1,'Tsanawiyah','MTS','Menengah','Program Tsanawiyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'Aliyah','MA','Atas','Program Aliyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'Tahfidz','THF','Khusus','Program Tahfidz','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,2,'Tsanawiyah','MTS','Menengah','Program Tsanawiyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,2,'Aliyah','MA','Atas','Program Aliyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,2,'Tahfidz','THF','Khusus','Program Tahfidz','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_tokens`
--

DROP TABLE IF EXISTS `qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'identity',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL,
  `scanned_at` timestamp NULL DEFAULT NULL,
  `scanned_by` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qr_tokens_code_unique` (`code`),
  KEY `qr_tokens_pesantren_id_index` (`pesantren_id`),
  KEY `qr_tokens_user_id_index` (`user_id`),
  KEY `qr_tokens_scanned_by_index` (`scanned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_tokens`
--

LOCK TABLES `qr_tokens` WRITE;
/*!40000 ALTER TABLE `qr_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `hospital_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrals_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrals`
--

LOCK TABLES `referrals` WRITE;
/*!40000 ALTER TABLE `referrals` DISABLE KEYS */;
INSERT INTO `referrals` VALUES (1,1,1,'RS Islam Surabaya','031-998877','Surabaya','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'RS Islam Surabaya','031-998877','Surabaya','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_cards`
--

DROP TABLE IF EXISTS `report_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `semester` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `average_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `items` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_cards_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_cards`
--

LOCK TABLES `report_cards` WRITE;
/*!40000 ALTER TABLE `report_cards` DISABLE KEYS */;
INSERT INTO `report_cards` VALUES (1,1,1,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,3,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,4,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,5,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,6,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,7,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,8,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,9,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,2,10,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,2,11,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,2,12,'Semester 1 - 2024/2025',85.60,'published','[]','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `report_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_role_id_user_id_unique` (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,1,1,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,2,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,3,3,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,4,4,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,5,5,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,6,6,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,7,7,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,8,8,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,9,9,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,10,10,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,3,11,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Admin','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,'owner_pesantren','Owner Pesantren','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,'admin_keuangan','Admin Keuangan','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,'admin_akademik','Admin Akademik','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,'admin_tu','Tata Usaha','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,'santri','Santri','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,'wali_murid','Wali Murid','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,'guru','Guru','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,'musyrif','Musyrif','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,'klinik','Klinik','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_assignments`
--

DROP TABLE IF EXISTS `room_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `assigned_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_assignments_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_assignments`
--

LOCK TABLES `room_assignments` WRITE;
/*!40000 ALTER TABLE `room_assignments` DISABLE KEYS */;
INSERT INTO `room_assignments` VALUES (1,1,1,1,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,5,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,3,2,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,4,6,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,5,5,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,6,3,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,1,7,2,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,1,8,4,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,1,9,4,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,2,10,7,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,2,11,11,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,2,12,8,'2024-07-01',NULL,'active','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `room_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_mutations`
--

DROP TABLE IF EXISTS `room_mutations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_mutations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `from_room` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_room` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_mutations_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_mutations`
--

LOCK TABLES `room_mutations` WRITE;
/*!40000 ALTER TABLE `room_mutations` DISABLE KEYS */;
INSERT INTO `room_mutations` VALUES (1,1,1,'C-08','C-07','2025-05-18','approved','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'C-08','C-07','2025-05-18','approved','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `room_mutations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int unsigned NOT NULL DEFAULT '0',
  `occupied` int unsigned NOT NULL DEFAULT '0',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Reguler',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rooms_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,1,'C-07','Putri Blok C',6,4,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'C-08','Putri Blok C',6,5,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'C-09','Putri Blok C',6,6,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,'C-10','Putri Blok C',6,3,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,'A-01','Putra Blok A',10,9,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,1,'A-02','Putra Blok A',10,10,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,2,'C-07','Putri Blok C',6,4,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,2,'C-08','Putri Blok C',6,5,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,2,'C-09','Putri Blok C',6,6,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,2,'C-10','Putri Blok C',6,3,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(11,2,'A-01','Putra Blok A',10,9,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(12,2,'A-02','Putra Blok A',10,10,'Reguler','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `day` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'academic',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedules_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` VALUES (1,1,1,1,'Senin','07:00 - 07:45','Ust. Ahmad Fauzi','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,1,2,'Senin','07:45 - 08:30','Ust. Nur Halimah','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,5,4,'Selasa','08:30 - 09:15','Usth. Siti Aisyah','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,1,7,'Selasa','10:15 - 11:00','Usth. Devi Rahma','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,11,8,'Senin','07:00 - 07:45','Ust. Ahmad Fauzi','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,11,9,'Senin','07:45 - 08:30','Ust. Nur Halimah','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,2,13,11,'Selasa','08:30 - 09:15','Usth. Siti Aisyah','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,2,11,14,'Selasa','10:15 - 11:00','Usth. Devi Rahma','R-01','academic','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `secret_rotations`
--

DROP TABLE IF EXISTS `secret_rotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `secret_rotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `secret_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_key_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_key_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rotated_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `secret_rotations_secret_name_index` (`secret_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secret_rotations`
--

LOCK TABLES `secret_rotations` WRITE;
/*!40000 ALTER TABLE `secret_rotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `secret_rotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sick_letters`
--

DROP TABLE IF EXISTS `sick_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sick_letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date DEFAULT NULL,
  `duration_days` int unsigned NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sick_letters_pesantren_id_number_unique` (`pesantren_id`,`number`),
  KEY `sick_letters_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sick_letters`
--

LOCK TABLES `sick_letters` WRITE;
/*!40000 ALTER TABLE `sick_letters` DISABLE KEYS */;
INSERT INTO `sick_letters` VALUES (1,1,1,'DH/SS/05/OS/2025/012','2025-05-12',1,'Istirahat selama 1 hari karena sakit.','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,10,'AF/SS/05/OS/2025/012','2025-05-12',1,'Istirahat selama 1 hari karena sakit.','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `sick_letters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` VALUES (1,1,NULL,'Bapak Nur Hidayat','DH-TU-001','Tata Usaha','0812-3456-7890','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,NULL,'Ibu Rina Agustina','DH-TU-002','Administrasi','0812-3456-7890','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,2,NULL,'Bapak Nur Hidayat','AF-TU-001','Tata Usaha','0812-3456-7890','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,2,NULL,'Ibu Rina Agustina','AF-TU-002','Administrasi','0812-3456-7890','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `guardian_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `nis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `room` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_pesantren_id_nis_unique` (`pesantren_id`,`nis`),
  KEY `students_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,1,6,1,5,'20230001','Najwa Karimah','Perempuan','Surabaya','2011-07-11','C-07','active','{\"average\": 85.6, \"hafalan\": 12, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,NULL,1,4,'20230002','Ahmad Zaky','Laki-laki','Gresik','2010-06-11','A-01','active','{\"average\": 84.6, \"hafalan\": 11, \"blood_type\": \"A\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,NULL,1,5,'20230003','Zahra Azzahra','Perempuan','Surabaya','2009-05-11','C-08','active','{\"average\": 83.6, \"hafalan\": 10, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,NULL,1,6,'20230004','Rafi Nur Fadli','Laki-laki','Gresik','2011-04-11','A-02','active','{\"average\": 82.6, \"hafalan\": 9, \"blood_type\": \"A\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,NULL,1,3,'20230005','Muhammad Faris','Laki-laki','Gresik','2010-03-11','A-01','active','{\"average\": 81.6, \"hafalan\": 8, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,1,NULL,1,3,'20230006','Salma Az Zahra','Perempuan','Surabaya','2009-02-11','C-09','active','{\"average\": 80.6, \"hafalan\": 7, \"blood_type\": \"A\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,1,NULL,1,2,'20230007','Aisyah Putri','Perempuan','Surabaya','2011-01-11','C-08','active','{\"average\": 79.6, \"hafalan\": 6, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,1,NULL,1,7,'20230008','Hana Nabila','Perempuan','Surabaya','2009-12-11','C-10','active','{\"average\": 78.6, \"hafalan\": 5, \"blood_type\": \"A\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,1,NULL,1,9,'20230009','Zahra Nur','Perempuan','Surabaya','2008-11-11','C-10','active','{\"average\": 77.6, \"hafalan\": 5, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,2,NULL,2,11,'20230001','Nabila Azizah','Perempuan','Surabaya','2011-07-11','C-07','active','{\"average\": 85.6, \"hafalan\": 12, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(11,2,NULL,2,12,'20230002','Fathan Mubarak','Laki-laki','Gresik','2010-06-11','A-01','active','{\"average\": 84.6, \"hafalan\": 11, \"blood_type\": \"A\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(12,2,NULL,2,13,'20230003','Alya Humaira','Perempuan','Surabaya','2009-05-11','C-08','active','{\"average\": 83.6, \"hafalan\": 10, \"blood_type\": \"B\"}','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kkm` int unsigned NOT NULL DEFAULT '75',
  `teacher_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subjects_pesantren_id_code_unique` (`pesantren_id`,`code`),
  KEY `subjects_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,1,'Al-Qur\'an','QUR','Tsanawiyah, Aliyah',75,'Ust. Ahmad Fauzi','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,'Fiqih','FIQ','Tsanawiyah, Aliyah',75,'Ust. Nur Halimah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,'Akidah Akhlak','AKH','Tsanawiyah, Aliyah',75,'Usth. Siti Aisyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,1,'Bahasa Arab','ARB','Tsanawiyah, Aliyah',75,'Ust. Muhammad Iqbal','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,1,'Bahasa Indonesia','BIN','Tsanawiyah, Aliyah',75,'Ust. Riky Maulana','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,1,'Matematika','MTK','Tsanawiyah, Aliyah',75,'Bapak Nur Hidayat','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,1,'IPA','IPA','Tsanawiyah, Aliyah',75,'Usth. Devi Rahma','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,2,'Al-Qur\'an','QUR','Tsanawiyah, Aliyah',75,'Ust. Ahmad Fauzi','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,2,'Fiqih','FIQ','Tsanawiyah, Aliyah',75,'Ust. Nur Halimah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,2,'Akidah Akhlak','AKH','Tsanawiyah, Aliyah',75,'Usth. Siti Aisyah','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(11,2,'Bahasa Arab','ARB','Tsanawiyah, Aliyah',75,'Ust. Muhammad Iqbal','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(12,2,'Bahasa Indonesia','BIN','Tsanawiyah, Aliyah',75,'Ust. Riky Maulana','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(13,2,'Matematika','MTK','Tsanawiyah, Aliyah',75,'Bapak Nur Hidayat','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(14,2,'IPA','IPA','Tsanawiyah, Aliyah',75,'Usth. Devi Rahma','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned NOT NULL,
  `plan_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `starts_at` date DEFAULT NULL,
  `ends_at` date DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_pesantren_id_index` (`pesantren_id`),
  KEY `subscriptions_plan_id_index` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,1,2,'active','2025-01-01','2025-12-31',1500000.00,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,2,1,'trial','2025-05-01','2025-06-30',0.00,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_pesantren_id_key_unique` (`pesantren_id`,`key`),
  KEY `system_settings_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,1,'institution','{\"academic_year\": \"2024/2025\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'pdf.receipt_template','{\"title\": \"Kwitansi Pembayaran Pesantenku\", \"footer\": \"Terima kasih. Simpan kwitansi ini sebagai bukti pembayaran.\", \"header\": \"Kwitansi Pembayaran Resmi Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'pdf.letter_template','{\"title\": \"Surat Pesantren\", \"footer\": \"Dokumen ini diterbitkan oleh tata usaha pesantren.\", \"header\": \"Surat Resmi Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,'pdf.report_template','{\"title\": \"Laporan Pesantenku\", \"footer\": \"Data mengikuti filter dan scope role aktif.\", \"header\": \"Laporan Operasional Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,'institution','{\"academic_year\": \"2024/2025\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,'pdf.receipt_template','{\"title\": \"Kwitansi Pembayaran Pesantenku\", \"footer\": \"Terima kasih. Simpan kwitansi ini sebagai bukti pembayaran.\", \"header\": \"Kwitansi Pembayaran Resmi Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,2,'pdf.letter_template','{\"title\": \"Surat Pesantren\", \"footer\": \"Dokumen ini diterbitkan oleh tata usaha pesantren.\", \"header\": \"Surat Resmi Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,2,'pdf.report_template','{\"title\": \"Laporan Pesantenku\", \"footer\": \"Data mengikuti filter dan scope role aktif.\", \"header\": \"Laporan Operasional Pesantren\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,NULL,'platform','{\"name\": \"Pesantenku\", \"academic_year\": \"2024/2025\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,NULL,'pdf.report_template','{\"title\": \"Laporan Platform Pesantenku\", \"footer\": \"Dokumen platform dibuat otomatis oleh Pesantenku.\", \"header\": \"Laporan SaaS Pesantenku\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,1,'pitr.drill.marker','\"pitr-20260711044641\"','2026-07-11 04:08:37','2026-07-11 04:46:41');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_assignments`
--

DROP TABLE IF EXISTS `teacher_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `teacher_id` bigint unsigned DEFAULT NULL,
  `class_id` bigint unsigned DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `assigned_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_assignments_pesantren_id_index` (`pesantren_id`),
  KEY `teacher_assignments_teacher_id_index` (`teacher_id`),
  KEY `teacher_assignments_class_id_index` (`class_id`),
  KEY `teacher_assignments_subject_id_index` (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_assignments`
--

LOCK TABLES `teacher_assignments` WRITE;
/*!40000 ALTER TABLE `teacher_assignments` DISABLE KEYS */;
INSERT INTO `teacher_assignments` VALUES (1,1,2,5,4,'2024-07-01',NULL,'active','Penugasan mengajar aktif','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,2,5,3,'2024-07-01',NULL,'active','Penugasan mengajar aktif','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,2,5,13,11,'2024-07-01',NULL,'active','Penugasan mengajar aktif','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,5,13,10,'2024-07-01',NULL,'active','Penugasan mengajar aktif','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `teacher_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teachers_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,1,NULL,'Ust. Ahmad Fauzi','DH-GURU-001','Al-Qur\'an Hadits','0812-3456-7810','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,1,8,'Usth. Siti Aisyah','DH-GURU-002','Bahasa Arab','0812-3456-7811','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,1,NULL,'Ust. Muhammad Iqbal','DH-GURU-003','Fiqih','0812-3456-7812','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,2,NULL,'Ust. Ahmad Fauzi','AF-GURU-001','Al-Qur\'an Hadits','0812-3456-7810','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,2,NULL,'Usth. Siti Aisyah','AF-GURU-002','Bahasa Arab','0812-3456-7811','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,2,NULL,'Ust. Muhammad Iqbal','AF-GURU-003','Fiqih','0812-3456-7812','active','2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_modules`
--

DROP TABLE IF EXISTS `tenant_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_modules_pesantren_id_module_unique` (`pesantren_id`,`module`),
  KEY `tenant_modules_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_modules`
--

LOCK TABLES `tenant_modules` WRITE;
/*!40000 ALTER TABLE `tenant_modules` DISABLE KEYS */;
INSERT INTO `tenant_modules` VALUES (1,1,'akademik',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,'keuangan',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,'tata_usaha',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,1,'asrama',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,1,'klinik',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,1,'monitoring',0,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(7,2,'akademik',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(8,2,'keuangan',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(9,2,'tata_usaha',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(10,2,'asrama',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(11,2,'klinik',1,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02'),(12,2,'monitoring',0,'{\"enabled_by\": \"seed\"}','2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `tenant_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_roles`
--

DROP TABLE IF EXISTS `tenant_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `permissions` json NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_roles_pesantren_id_name_unique` (`pesantren_id`,`name`),
  KEY `tenant_roles_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_roles`
--

LOCK TABLES `tenant_roles` WRITE;
/*!40000 ALTER TABLE `tenant_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `address` text COLLATE utf8mb4_unicode_ci,
  `profile` json DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mfa_secret` text COLLATE utf8mb4_unicode_ci,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_phone_unique` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super Admin','superadmin@pesantenku.test','superadmin','0857-1001-001',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$Dog5QzhRvGuN3aD1JQiLMOXwmaoWJXIVyVGBnOU6vnve9pEn/gHfu','eyJpdiI6InBlenlXbUpmZmN6RFFXNFkxS1FZaVE9PSIsInZhbHVlIjoiVHhQclBjZ05QRW5BaGtNSDBQOS9aVWh3amNiUXJ3VHAzdmJHNGRpRVZBdz0iLCJtYWMiOiJjNWRlNjQ4YTIyMWFiODNmNjg0ZWJkMzk1NjA1Yzg1MGRkNGZkNTMwZTg0OTE4NGEwZTg1ZTI3NDk4ZTQ2ZTZlIiwidGFnIjoiIn0=',1,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(2,'Owner Darul Hikmah','owner@pesantenku.test','owner','0857-1001-002',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$aCaaDl5zb7.8e4A1BWOeke0mKaM54Gk6oMfDL2adbA7seEGhquJmu','eyJpdiI6Ii9EbXA1U0VrWHFscHJMdHhlZVF0cEE9PSIsInZhbHVlIjoiQW1PVEhOK1Z4ZEdvNHJzTVUzRW1NeUwrK09KTUk4aXlxY2FadzU1bmRwYz0iLCJtYWMiOiI0ZWI0MjAwNjZhYjBhNzRlZWFlYzEwY2FmYzkyNTdhNDVmNjljYjY4YTk0YWIxNzZhNzYzYTNjMTcyOTNiNTMyIiwidGFnIjoiIn0=',1,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(3,'Keuangan Admin','keuangan@pesantenku.test','keuangan','0857-1001-003',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$D40x.bzLpboLfZMil192HOr7DUn6sfj5iP1DgvTHKdwNVv4kFHsJu',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(4,'Akademik Admin','akademik@pesantenku.test','akademik','0857-1001-004',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$CseMHV4bndG0ApwQpFRkVuYfvDZxV8/RrxeGwg/9jF0HOXgincAj2',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(5,'TU Admin','tu@pesantenku.test','tu','0857-1001-005',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$p4j1KkMuGUl9Iz46oIG0NeOvocdWFnRJtd2eeYBUoLGrClkJuxTYu',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(6,'Najwa Karimah','santri@pesantenku.test','santri','0857-1001-006',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$I7L9ejqlhl9GZ42o1c3p9OBbdDbYnMSloPsYBRIvlO7jFrnOeTk1i',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(7,'Nur Azizah Ghafur','wali@pesantenku.test','wali','0857-1001-007',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$0wwsscpxwfa4IiQZbiOyI.SYlqC/FW2ttORKF7.FN8kfcoAB82Mu.',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(8,'Ustadzah Siti Aisyah','guru@pesantenku.test','guru','0857-1001-008',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$m8G.UWIpbTx5zSOWjikfeO9FBk3NNIrtfjOTu3i5D.NuiwNEbHE7q',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(9,'Ustadzah Siti Aisyah Musyrif','musyrif@pesantenku.test','musyrif','0857-1001-009',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$QDP954yCJqSgSrBYLcPLneA1uZiQXTOr7U9f30WBpZow8mCzvVYeW',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(10,'Klinik Admin','klinik@pesantenku.test','klinik','0857-1001-010',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$kbP2MT2LXyx5Om5F/cTfluKDJrd4yuuxrpA76Gq.D3mXW8BGMAK8m',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL),(11,'Keuangan Al-Falah','keuangan.alfalah@pesantenku.test','keuangan_alfalah','0857-1001-011',NULL,'active','Jl. Raya Pesantren No. 123, Surabaya, Jawa Timur',NULL,NULL,'$2y$12$x03ofXmz30rE6sD/8b7mb.KKbPrrbs.Jc.XddYED6PihsiynkNNMu',NULL,0,NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `violations`
--

DROP TABLE IF EXISTS `violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `violations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pesantren_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int unsigned NOT NULL DEFAULT '0',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ringan',
  `date` date DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violations_pesantren_id_index` (`pesantren_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `violations`
--

LOCK TABLES `violations` WRITE;
/*!40000 ALTER TABLE `violations` DISABLE KEYS */;
INSERT INTO `violations` VALUES (1,1,1,'Terlambat masuk kelas',2,'Ringan','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(2,1,1,'Tidak ikut jamaah',5,'Sedang','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(3,1,1,'Rambut tidak rapi',2,'Ringan','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(4,2,10,'Terlambat masuk kelas',2,'Ringan','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(5,2,10,'Tidak ikut jamaah',5,'Sedang','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02'),(6,2,10,'Rambut tidak rapi',2,'Ringan','2025-05-20',NULL,'2026-07-11 04:03:02','2026-07-11 04:03:02');
/*!40000 ALTER TABLE `violations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'pesantenku'
--

--
-- Dumping routines for database 'pesantenku'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-11  4:50:10
