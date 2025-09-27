-- MySQL dump 10.13  Distrib 8.0.34, for macos13 (arm64)
--
-- Host: 127.0.0.1    Database: lexis
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log_on_pull_requests`
--

DROP TABLE IF EXISTS `activity_log_on_pull_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log_on_pull_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `pull_request_id` bigint unsigned NOT NULL,
  `comment_id` bigint unsigned DEFAULT NULL,
  `reviewer_id` bigint unsigned DEFAULT NULL,
  `pull_request_edit_session_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fix_request_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_pull_request_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_pull_request_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activity_log_pr_user_id` (`user_id`),
  KEY `fk_activity_log_pr_request_id` (`pull_request_id`),
  KEY `fk_activity_log_pr_comment_id` (`comment_id`),
  KEY `fk_activity_log_pr_reviewer_id` (`reviewer_id`),
  KEY `fk_activity_log_pr_edit_session_id` (`pull_request_edit_session_id`),
  CONSTRAINT `fk_activity_log_pr_comment_id` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_log_pr_edit_session_id` FOREIGN KEY (`pull_request_edit_session_id`) REFERENCES `pull_request_edit_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_log_pr_request_id` FOREIGN KEY (`pull_request_id`) REFERENCES `pull_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_log_pr_reviewer_id` FOREIGN KEY (`reviewer_id`) REFERENCES `pull_request_reviewers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_log_pr_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log_on_pull_requests`
--

LOCK TABLES `activity_log_on_pull_requests` WRITE;
/*!40000 ALTER TABLE `activity_log_on_pull_requests` DISABLE KEYS */;
INSERT INTO `activity_log_on_pull_requests` VALUES (29,73,122,NULL,NULL,NULL,'pull_request_merged',NULL,NULL,NULL,'2025-09-17 07:10:16','2025-09-17 07:10:16'),(30,73,123,NULL,NULL,NULL,'pull_request_merged',NULL,NULL,NULL,'2025-09-17 07:11:00','2025-09-17 07:11:00');
/*!40000 ALTER TABLE `activity_log_on_pull_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pull_request_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_pull_request_id_foreign` (`pull_request_id`),
  KEY `comments_user_id_foreign` (`user_id`),
  CONSTRAINT `comments_pull_request_id_foreign` FOREIGN KEY (`pull_request_id`) REFERENCES `pull_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_categories`
--

DROP TABLE IF EXISTS `document_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `parent_id` bigint unsigned DEFAULT NULL,
  `user_branch_id` bigint unsigned DEFAULT NULL,
  `pull_request_edit_session_id` bigint unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_categories_parent_id_foreign` (`parent_id`),
  KEY `document_categories_user_branch_id_foreign` (`user_branch_id`),
  KEY `document_categories_pull_request_edit_session_id_foreign` (`pull_request_edit_session_id`),
  KEY `document_categories_organization_id_foreign` (`organization_id`),
  CONSTRAINT `document_categories_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `document_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_categories_pull_request_edit_session_id_foreign` FOREIGN KEY (`pull_request_edit_session_id`) REFERENCES `pull_request_edit_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_categories_user_branch_id_foreign` FOREIGN KEY (`user_branch_id`) REFERENCES `user_branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=671 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_categories`
--

LOCK TABLES `document_categories` WRITE;
/*!40000 ALTER TABLE `document_categories` DISABLE KEYS */;
INSERT INTO `document_categories` VALUES (407,73,'korehatesuto','ssss','merged',NULL,344,NULL,1,'2025-09-17 07:11:00','2025-09-17 07:09:49','2025-09-17 07:11:00'),(408,73,'これはてすと','cxcvxv','merged',NULL,344,NULL,0,NULL,'2025-09-17 07:10:03','2025-09-17 07:10:16'),(409,73,'っっd','っっっd','merged',NULL,345,NULL,0,NULL,'2025-09-17 07:10:27','2025-09-17 07:11:00'),(410,73,'2個目','でした','merged',NULL,345,NULL,0,NULL,'2025-09-17 07:10:38','2025-09-17 07:11:00'),(496,73,'あいうえお','ああああ','draft',NULL,493,NULL,0,NULL,'2025-09-17 08:15:18','2025-09-17 08:15:18');
/*!40000 ALTER TABLE `document_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_branch_id` bigint unsigned DEFAULT NULL,
  `pull_request_edit_session_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `description` text COLLATE utf8mb4_unicode_ci,
  `category_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_edited_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_reviewed_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_versions_user_id_foreign` (`user_id`),
  KEY `document_versions_user_branch_id_foreign` (`user_branch_id`),
  KEY `document_versions_pull_request_edit_session_id_foreign` (`pull_request_edit_session_id`),
  KEY `document_versions_category_id_foreign` (`category_id`),
  KEY `document_versions_organization_id_foreign` (`organization_id`),
  CONSTRAINT `document_versions_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_pull_request_edit_session_id_foreign` FOREIGN KEY (`pull_request_edit_session_id`) REFERENCES `pull_request_edit_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_versions_user_branch_id_foreign` FOREIGN KEY (`user_branch_id`) REFERENCES `user_branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_versions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
/*!40000 ALTER TABLE `document_versions` DISABLE KEYS */;
INSERT INTO `document_versions` VALUES (228,73,73,493,NULL,'draft','ddfvvfd',408,'dddd','eito.55855@gmail.com',NULL,0,NULL,'2025-09-18 09:05:34','2025-09-18 09:05:34');
/*!40000 ALTER TABLE `document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `edit_start_versions`
--

DROP TABLE IF EXISTS `edit_start_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `edit_start_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_branch_id` bigint unsigned DEFAULT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_version_id` bigint unsigned DEFAULT NULL,
  `current_version_id` bigint unsigned NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edit_start_versions_user_branch_id_foreign` (`user_branch_id`),
  KEY `edit_start_versions_original_version_id_foreign` (`original_version_id`),
  KEY `edit_start_versions_current_version_id_foreign` (`current_version_id`),
  CONSTRAINT `edit_start_versions_user_branch_id_foreign` FOREIGN KEY (`user_branch_id`) REFERENCES `user_branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=415 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `edit_start_versions`
--

LOCK TABLES `edit_start_versions` WRITE;
/*!40000 ALTER TABLE `edit_start_versions` DISABLE KEYS */;
INSERT INTO `edit_start_versions` VALUES (149,344,'category',408,408,0,NULL,'2025-09-17 07:10:03','2025-09-17 07:10:03'),(150,345,'category',409,409,0,NULL,'2025-09-17 07:10:27','2025-09-17 07:10:27'),(312,493,'category',496,496,0,NULL,'2025-09-17 08:15:18','2025-09-17 08:15:18'),(339,493,'document',228,228,0,NULL,'2025-09-18 09:05:34','2025-09-18 09:05:34');
/*!40000 ALTER TABLE `edit_start_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fix_requests`
--

DROP TABLE IF EXISTS `fix_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fix_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_version_id` bigint unsigned DEFAULT NULL,
  `document_category_id` bigint unsigned DEFAULT NULL,
  `base_document_version_id` bigint unsigned DEFAULT NULL,
  `base_category_version_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `pull_request_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fix_requests_document_version_id_foreign` (`document_version_id`),
  KEY `fix_requests_document_category_id_foreign` (`document_category_id`),
  KEY `fix_requests_base_document_version_id_foreign` (`base_document_version_id`),
  KEY `fix_requests_base_category_version_id_foreign` (`base_category_version_id`),
  KEY `fix_requests_user_id_foreign` (`user_id`),
  KEY `fix_requests_pull_request_id_foreign` (`pull_request_id`),
  CONSTRAINT `fix_requests_base_category_version_id_foreign` FOREIGN KEY (`base_category_version_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fix_requests_base_document_version_id_foreign` FOREIGN KEY (`base_document_version_id`) REFERENCES `document_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fix_requests_document_category_id_foreign` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fix_requests_document_version_id_foreign` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fix_requests_pull_request_id_foreign` FOREIGN KEY (`pull_request_id`) REFERENCES `pull_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fix_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fix_requests`
--

LOCK TABLES `fix_requests` WRITE;
/*!40000 ALTER TABLE `fix_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `fix_requests` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2025_01_15_000000_drop_refresh_tokens_table',1),(2,'2025_08_31_094500_create_users_table',1),(3,'2025_08_31_094501_create_organizations_table',1),(4,'2025_08_31_094502_create_user_branches_table',1),(5,'2025_08_31_094503_create_pull_requests_table',1),(6,'2025_08_31_094504_create_pull_request_edit_sessions_table',1),(7,'2025_08_31_094505_create_document_categories_table',1),(8,'2025_08_31_094506_create_document_versions_table',1),(9,'2025_08_31_094507_create_pull_request_edit_session_diffs_table',1),(10,'2025_08_31_094508_create_pull_request_reviewers_table',1),(11,'2025_08_31_094509_create_comments_table',1),(12,'2025_08_31_094510_create_edit_start_versions_table',1),(13,'2025_08_31_094511_create_fix_requests_table',1),(14,'2025_08_31_094512_create_organization_members_table',1),(15,'2025_08_31_094513_create_organization_role_bindings_table',1),(16,'2025_08_31_094514_create_sessions_table',1),(17,'2025_08_31_094515_create_activity_log_on_pull_requests_table',1),(18,'2025_09_01_000001_create_pre_users_table',1),(19,'2025_09_02_000001_alter_organizations_slug_to_uuid',1),(20,'2025_09_02_000002_add_nickname_to_users_table',1),(21,'2025_09_03_085642_add_last_login_to_users_table',1),(22,'2025_09_03_085919_create_personal_access_tokens_table',1),(23,'2025_09_03_085920_create_refresh_tokens_table',1),(24,'2025_09_04_000000_drop_sessions_table',1),(25,'2025_09_07_191356_modify_document_categories_table',1),(26,'2025_09_07_203959_remove_position_from_document_categories_table',1),(27,'2025_09_08_213333_add_organization_id_to_document_versions_table',1),(28,'2025_09_08_213347_add_organization_id_to_document_categories_table',1),(29,'2025_09_08_213401_add_organization_id_to_user_branches_table',1),(30,'2025_09_08_213416_add_organization_id_to_pull_requests_table',1),(31,'2025_09_10_093756_remove_snapshot_commit_from_user_branches_table',1),(32,'2025_09_10_095500_fix_edit_start_versions_foreign_key_constraints',1),(33,'2025_09_11_204433_modify_pull_request_edit_session_diffs_foreign_key_constraints',2),(34,'2025_09_13_081443_remove_unique_constraint_from_user_branches_user_id',3),(35,'2025_09_14_095027_remove_pr_number_and_github_url_from_pull_requests_table',4),(36,'2025_09_17_082921_modify_document_versions_table_structure',4),(37,'2025_09_18_085501_fix_description_column_nullable_in_document_versions_table',5);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_members`
--

DROP TABLE IF EXISTS `organization_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_members` (
  `user_id` bigint unsigned NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `joined_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `organization_members_user_id_foreign` (`user_id`),
  KEY `organization_members_organization_id_foreign` (`organization_id`),
  CONSTRAINT `organization_members_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organization_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_members`
--

LOCK TABLES `organization_members` WRITE;
/*!40000 ALTER TABLE `organization_members` DISABLE KEYS */;
INSERT INTO `organization_members` VALUES (73,73,'2025-09-11 20:52:04','2025-09-11 20:52:04','2025-09-11 20:52:04'),(458,351,'2025-09-14 11:04:48','2025-09-14 11:04:48','2025-09-14 11:04:48'),(459,351,'2025-09-14 11:04:48','2025-09-14 11:04:48','2025-09-14 11:04:48'),(460,351,'2025-09-14 11:04:48','2025-09-14 11:04:48','2025-09-14 11:04:48');
/*!40000 ALTER TABLE `organization_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_role_bindings`
--

DROP TABLE IF EXISTS `organization_role_bindings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_role_bindings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `organization_role_bindings_user_id_foreign` (`user_id`),
  KEY `organization_role_bindings_organization_id_foreign` (`organization_id`),
  CONSTRAINT `organization_role_bindings_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organization_role_bindings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=474 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_role_bindings`
--

LOCK TABLES `organization_role_bindings` WRITE;
/*!40000 ALTER TABLE `organization_role_bindings` DISABLE KEYS */;
INSERT INTO `organization_role_bindings` VALUES (1,73,73,'owner','2025-09-11 20:52:04','2025-09-11 20:52:04'),(23,458,351,'admin','2025-09-14 11:04:48','2025-09-14 11:04:48'),(24,459,351,'owner','2025-09-14 11:04:48','2025-09-14 11:04:48'),(25,460,351,'editor','2025-09-14 11:04:48','2025-09-14 11:04:48');
/*!40000 ALTER TABLE `organization_role_bindings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_slug_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=632 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (73,'nexis-inc','nexis-inc','2025-09-11 20:52:04','2025-09-11 20:52:04'),(351,'61d512e6-ab8f-4b6c-86eb-ae8c7715ce5c','株式会社 山田','2025-09-14 11:04:48','2025-09-14 11:04:48');
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pre_users`
--

DROP TABLE IF EXISTS `pre_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_invalidated` tinyint(1) NOT NULL DEFAULT '0',
  `invalidated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pre_users_token_unique` (`token`),
  KEY `pre_users_email_index` (`email`),
  KEY `pre_users_is_invalidated_index` (`is_invalidated`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pre_users`
--

LOCK TABLES `pre_users` WRITE;
/*!40000 ALTER TABLE `pre_users` DISABLE KEYS */;
INSERT INTO `pre_users` VALUES (1,'eito.55855@gmail.com','QS27kYsZu0X1JPMtoRWDpGAOfbd3z6Uv4acweh9x','2025-09-11 21:21:39','$2y$12$BPPPLXrNZTbn.symGZE.9e6oRbXuYmWF7ftJaQdRvkvw8gnUI372y',1,NULL,'2025-09-11 20:51:39','2025-09-11 20:52:04');
/*!40000 ALTER TABLE `pre_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pull_request_edit_session_diffs`
--

DROP TABLE IF EXISTS `pull_request_edit_session_diffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pull_request_edit_session_diffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pull_request_edit_session_id` bigint unsigned NOT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diff_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_version_id` bigint unsigned DEFAULT NULL,
  `current_version_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pr_edit_session_diffs_session_id` (`pull_request_edit_session_id`),
  KEY `fk_pr_edit_session_diffs_original_ver` (`original_version_id`),
  KEY `fk_pr_edit_session_diffs_current_ver` (`current_version_id`),
  CONSTRAINT `fk_pr_edit_session_diffs_session_id` FOREIGN KEY (`pull_request_edit_session_id`) REFERENCES `pull_request_edit_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pull_request_edit_session_diffs`
--

LOCK TABLES `pull_request_edit_session_diffs` WRITE;
/*!40000 ALTER TABLE `pull_request_edit_session_diffs` DISABLE KEYS */;
/*!40000 ALTER TABLE `pull_request_edit_session_diffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pull_request_edit_sessions`
--

DROP TABLE IF EXISTS `pull_request_edit_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pull_request_edit_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pull_request_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pull_request_edit_sessions_token_unique` (`token`),
  KEY `pull_request_edit_sessions_pull_request_id_foreign` (`pull_request_id`),
  KEY `pull_request_edit_sessions_user_id_foreign` (`user_id`),
  CONSTRAINT `pull_request_edit_sessions_pull_request_id_foreign` FOREIGN KEY (`pull_request_id`) REFERENCES `pull_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pull_request_edit_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pull_request_edit_sessions`
--

LOCK TABLES `pull_request_edit_sessions` WRITE;
/*!40000 ALTER TABLE `pull_request_edit_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `pull_request_edit_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pull_request_reviewers`
--

DROP TABLE IF EXISTS `pull_request_reviewers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pull_request_reviewers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pull_request_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pull_request_reviewers_pull_request_id_foreign` (`pull_request_id`),
  KEY `pull_request_reviewers_user_id_foreign` (`user_id`),
  CONSTRAINT `pull_request_reviewers_pull_request_id_foreign` FOREIGN KEY (`pull_request_id`) REFERENCES `pull_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pull_request_reviewers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pull_request_reviewers`
--

LOCK TABLES `pull_request_reviewers` WRITE;
/*!40000 ALTER TABLE `pull_request_reviewers` DISABLE KEYS */;
/*!40000 ALTER TABLE `pull_request_reviewers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pull_requests`
--

DROP TABLE IF EXISTS `pull_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pull_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `user_branch_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'opened',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pull_requests_user_branch_id_foreign` (`user_branch_id`),
  KEY `pull_requests_organization_id_foreign` (`organization_id`),
  CONSTRAINT `pull_requests_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pull_requests_user_branch_id_foreign` FOREIGN KEY (`user_branch_id`) REFERENCES `user_branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pull_requests`
--

LOCK TABLES `pull_requests` WRITE;
/*!40000 ALTER TABLE `pull_requests` DISABLE KEYS */;
INSERT INTO `pull_requests` VALUES (122,73,344,'っっd','このPRはハンドブックの更新を含みます。','merged','2025-09-17 07:10:09','2025-09-17 07:10:16'),(123,73,345,'っっv','このPRはハンドブックの更新を含みます。','merged','2025-09-17 07:10:47','2025-09-17 07:11:00');
/*!40000 ALTER TABLE `pull_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refresh_tokens`
--

DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `hashed_refresh_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expired_at` timestamp NOT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT '0',
  `blacklisted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `refresh_tokens_user_id_foreign` (`user_id`),
  CONSTRAINT `refresh_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refresh_tokens`
--

LOCK TABLES `refresh_tokens` WRITE;
/*!40000 ALTER TABLE `refresh_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_branches`
--

DROP TABLE IF EXISTS `user_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `branch_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_branches_organization_id_foreign` (`organization_id`),
  KEY `user_branches_user_id_foreign` (`user_id`),
  CONSTRAINT `user_branches_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_branches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=627 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_branches`
--

LOCK TABLES `user_branches` WRITE;
/*!40000 ALTER TABLE `user_branches` DISABLE KEYS */;
INSERT INTO `user_branches` VALUES (344,73,73,'branch_73_1758060589',0,'2025-09-17 07:09:49','2025-09-17 07:10:09'),(345,73,73,'branch_73_1758060627',0,'2025-09-17 07:10:27','2025-09-17 07:10:47'),(493,73,73,'branch_73_1758064518',1,'2025-09-17 08:15:18','2025-09-17 08:15:18');
/*!40000 ALTER TABLE `user_branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1242 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (73,'eito.55855@gmail.com','$2y$12$BPPPLXrNZTbn.symGZE.9e6oRbXuYmWF7ftJaQdRvkvw8gnUI372y',NULL,'2025-09-18 20:19:20',0,NULL,'2025-09-11 20:52:04','2025-09-18 20:19:20'),(458,'ywatanabe@example.com','$2y$12$tE.A/vp8tbba5f4wdPPuoumEBLvf6panysh/4FdCNVclhXlYOoZz.','杉山 洋介','2025-09-14 11:04:48',0,NULL,'2025-09-14 11:04:48','2025-09-14 11:04:48'),(459,'rei.sugiyama@example.net','$2y$12$tE.A/vp8tbba5f4wdPPuoumEBLvf6panysh/4FdCNVclhXlYOoZz.','笹田 美加子','2025-09-14 11:04:48',0,NULL,'2025-09-14 11:04:48','2025-09-14 11:04:48'),(460,'uno.kazuya@example.com','$2y$12$tE.A/vp8tbba5f4wdPPuoumEBLvf6panysh/4FdCNVclhXlYOoZz.','工藤 直人','2025-09-14 11:04:48',0,NULL,'2025-09-14 11:04:48','2025-09-14 11:04:48'),(461,'ekoda.naoki@example.org','$2y$12$tE.A/vp8tbba5f4wdPPuoumEBLvf6panysh/4FdCNVclhXlYOoZz.','宇野 翔太','2025-09-14 11:04:48',0,NULL,'2025-09-14 11:04:48','2025-09-14 11:04:48');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-19  7:34:32
