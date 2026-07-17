-- MySQL dump 10.13  Distrib 8.4.10, for Linux (aarch64)
--
-- Host: localhost    Database: marketing_app
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
-- Table structure for table `admin_password_reset_tokens`
--

DROP TABLE IF EXISTS `admin_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_password_reset_tokens`
--

LOCK TABLES `admin_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `admin_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Super Admin','admin@mail.com','2026-07-17 09:06:02','$2y$12$dQnXqUHY1r7bOY5s10S8Q.SvVuc9IIJv6B.NBZZJg1LBPbkUrHRhi',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-17 09:06:02','2026-07-17 09:06:02',NULL);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agent_conversation_messages`
--

DROP TABLE IF EXISTS `agent_conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_conversation_messages` (
  `id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_calls` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_results` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `usage` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_index` (`conversation_id`,`user_id`,`updated_at`),
  KEY `agent_conversation_messages_user_id_index` (`user_id`),
  KEY `agent_conversation_messages_conversation_id_index` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agent_conversation_messages`
--

LOCK TABLES `agent_conversation_messages` WRITE;
/*!40000 ALTER TABLE `agent_conversation_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `agent_conversation_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agent_conversations`
--

DROP TABLE IF EXISTS `agent_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_conversations` (
  `id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_conversations_user_id_updated_at_index` (`user_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agent_conversations`
--

LOCK TABLES `agent_conversations` WRITE;
/*!40000 ALTER TABLE `agent_conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `agent_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_settings`
--

DROP TABLE IF EXISTS `ai_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_settings`
--

LOCK TABLES `ai_settings` WRITE;
/*!40000 ALTER TABLE `ai_settings` DISABLE KEYS */;
INSERT INTO `ai_settings` VALUES (1,'ai_default_text_provider','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'ai_default_text_model','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'ai_default_image_provider','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'ai_default_image_model','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'ai_default_tts_provider','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'ai_default_tts_model','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'ai_default_stt_provider','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,'ai_default_stt_model','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,'ai_default_embeddings_provider','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,'ai_default_embeddings_model','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'vector_database_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(12,'vector_database_mode','local','2026-07-17 09:06:03','2026-07-17 09:06:03'),(13,'vector_database_provider','qdrant','2026-07-17 09:06:03','2026-07-17 09:06:03'),(14,'qdrant_url','http://localhost:6333','2026-07-17 09:06:03','2026-07-17 09:06:03'),(15,'qdrant_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(16,'qdrant_collection','knowledge_base_chunks','2026-07-17 09:06:03','2026-07-17 09:06:03'),(17,'qdrant_vector_dimension','1536','2026-07-17 09:06:03','2026-07-17 09:06:03'),(18,'qdrant_search_limit','5','2026-07-17 09:06:03','2026-07-17 09:06:03'),(19,'qdrant_score_threshold','0.2','2026-07-17 09:06:03','2026-07-17 09:06:03'),(20,'qdrant_timeout','10','2026-07-17 09:06:03','2026-07-17 09:06:03'),(21,'openai_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(22,'openai_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(23,'openai_organization_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(24,'openai_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(25,'openai_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(26,'anthropic_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(27,'anthropic_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(28,'anthropic_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(29,'anthropic_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(30,'gemini_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(31,'gemini_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(32,'gemini_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(33,'gemini_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(34,'azure_openai_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(35,'azure_openai_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(36,'azure_openai_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(37,'azure_openai_api_version','2024-02-01','2026-07-17 09:06:03','2026-07-17 09:06:03'),(38,'azure_openai_deployment','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(39,'azure_openai_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(40,'groq_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(41,'groq_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(42,'groq_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(43,'groq_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(44,'xai_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(45,'xai_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(46,'xai_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(47,'xai_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(48,'deepseek_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(49,'deepseek_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(50,'deepseek_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(51,'deepseek_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(52,'mistral_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(53,'mistral_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(54,'mistral_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(55,'mistral_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(56,'ollama_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(57,'ollama_base_url','http://localhost:11434','2026-07-17 09:06:03','2026-07-17 09:06:03'),(58,'ollama_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(59,'elevenlabs_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(60,'elevenlabs_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(61,'elevenlabs_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(62,'elevenlabs_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(63,'cohere_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(64,'cohere_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(65,'cohere_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(66,'cohere_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(67,'jina_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(68,'jina_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(69,'jina_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(70,'jina_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(71,'voyageai_enabled','0','2026-07-17 09:06:03','2026-07-17 09:06:03'),(72,'voyageai_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(73,'voyageai_base_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(74,'voyageai_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `ai_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage_logs`
--

DROP TABLE IF EXISTS `ai_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `feature` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `input_tokens` int unsigned DEFAULT NULL,
  `output_tokens` int unsigned DEFAULT NULL,
  `total_tokens` int unsigned DEFAULT NULL,
  `estimated_cost` decimal(12,6) DEFAULT NULL,
  `request_excerpt` text COLLATE utf8mb4_unicode_ci,
  `response_excerpt` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_usage_logs_user_id_foreign` (`user_id`),
  KEY `ai_usage_logs_feature_created_at_index` (`feature`,`created_at`),
  KEY `ai_usage_logs_provider_created_at_index` (`provider`,`created_at`),
  KEY `ai_usage_logs_workspace_id_created_at_index` (`workspace_id`,`created_at`),
  KEY `ai_usage_logs_status_index` (`status`),
  CONSTRAINT `ai_usage_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_usage_logs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage_logs`
--

LOCK TABLES `ai_usage_logs` WRITE;
/*!40000 ALTER TABLE `ai_usage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'login','system',NULL,NULL,'{\"email\": \"user@mail.com\", \"user_id\": 1}','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','http://127.0.0.1:8000/login','2026-07-17 09:06:17','2026-07-17 09:06:17');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auto_reply_rules`
--

DROP TABLE IF EXISTS `auto_reply_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auto_reply_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'keyword',
  `trigger_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contains',
  `reply_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `reply_text` text COLLATE utf8mb4_unicode_ci,
  `reply_payload` json DEFAULT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '10',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `auto_reply_rules_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `auto_reply_rules_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auto_reply_rules`
--

LOCK TABLES `auto_reply_rules` WRITE;
/*!40000 ALTER TABLE `auto_reply_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `auto_reply_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_runs`
--

DROP TABLE IF EXISTS `automation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `automation_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_node_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `campaign_recipient_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `context` json DEFAULT NULL,
  `result` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `automation_runs_event_unique` (`automation_id`,`trigger_node_id`,`event_key`),
  KEY `automation_runs_workspace_id_foreign` (`workspace_id`),
  KEY `automation_runs_contact_id_foreign` (`contact_id`),
  KEY `automation_runs_conversation_id_foreign` (`conversation_id`),
  KEY `automation_runs_campaign_id_foreign` (`campaign_id`),
  KEY `automation_runs_campaign_recipient_id_foreign` (`campaign_recipient_id`),
  KEY `automation_runs_message_id_foreign` (`message_id`),
  KEY `automation_runs_status_index` (`status`),
  KEY `automation_runs_trigger_type_index` (`trigger_type`),
  KEY `automation_runs_event_key_index` (`event_key`),
  CONSTRAINT `automation_runs_automation_id_foreign` FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `automation_runs_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `automation_runs_campaign_recipient_id_foreign` FOREIGN KEY (`campaign_recipient_id`) REFERENCES `campaign_recipients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `automation_runs_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `automation_runs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `automation_runs_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `automation_runs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_runs`
--

LOCK TABLES `automation_runs` WRITE;
/*!40000 ALTER TABLE `automation_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_step_logs`
--

DROP TABLE IF EXISTS `automation_step_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_step_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `automation_run_id` bigint unsigned NOT NULL,
  `automation_id` bigint unsigned NOT NULL,
  `node_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `node_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `node_kind` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `selected_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input` json DEFAULT NULL,
  `output` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `scheduled_until` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `automation_step_logs_automation_run_id_foreign` (`automation_run_id`),
  KEY `automation_step_logs_automation_id_foreign` (`automation_id`),
  KEY `automation_step_logs_node_id_index` (`node_id`),
  KEY `automation_step_logs_status_index` (`status`),
  CONSTRAINT `automation_step_logs_automation_id_foreign` FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `automation_step_logs_automation_run_id_foreign` FOREIGN KEY (`automation_run_id`) REFERENCES `automation_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_step_logs`
--

LOCK TABLES `automation_step_logs` WRITE;
/*!40000 ALTER TABLE `automation_step_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_step_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automations`
--

DROP TABLE IF EXISTS `automations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `trigger` json DEFAULT NULL,
  `nodes` json DEFAULT NULL,
  `edges` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `runs_count` int unsigned NOT NULL DEFAULT '0',
  `completed_runs_count` int unsigned NOT NULL DEFAULT '0',
  `failed_runs_count` int unsigned NOT NULL DEFAULT '0',
  `last_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `automations_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `automations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automations`
--

LOCK TABLES `automations` WRITE;
/*!40000 ALTER TABLE `automations` DISABLE KEYS */;
/*!40000 ALTER TABLE `automations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_slug_unique` (`slug`),
  KEY `blog_categories_active_sort_order_index` (`active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
INSERT INTO `blog_categories` VALUES (1,'Automation','automation','Practical guides for WhatsApp automation, routing, and team workflows.',1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'Growth','growth','Broadcast campaigns, segmentation, and customer lifecycle messaging.',2,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'Product','product','CRM dashboards, reporting, and product strategy for WhatsApp teams.',3,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'Support','support','Customer support, chatbots, and handoff best practices.',4,1,'2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blog_category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `featured_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featured_image_media_id` bigint unsigned DEFAULT NULL,
  `read_time_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_slug_unique` (`slug`),
  KEY `blog_posts_active_status_published_at_index` (`active`,`status`,`published_at`),
  KEY `blog_posts_blog_category_id_index` (`blog_category_id`),
  KEY `blog_posts_featured_image_media_id_index` (`featured_image_media_id`),
  KEY `blog_posts_sort_order_index` (`sort_order`),
  CONSTRAINT `blog_posts_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
INSERT INTO `blog_posts` VALUES (1,1,'How WhatsApp Automation Helps SaaS Teams Reply Faster','whatsapp-automation-saas-teams-reply-faster','WaPro Editorial','A practical guide to using automation, routing, and saved replies without losing the human feel of customer conversations.','Fast replies are not only about speed. They are about context, routing, and giving your team the right next action before the customer asks twice.\n\nA strong WhatsApp automation setup starts with clear entry points. Welcome messages, qualification questions, and intent-based routing help every conversation land with the right owner.\n\nFor SaaS teams, the most useful automations are usually small. Trial questions, billing nudges, onboarding reminders, renewal prompts, and support triage can remove hours of repetitive work each week.\n\nThe best systems still leave space for humans. Automation should prepare the conversation, summarize the need, and hand off cleanly when a customer needs a personal answer.','assets/images/sections/solutions/1.webp',NULL,5,1,1,'published','WhatsApp Automation for SaaS Teams - WaPro Blog','Learn how SaaS teams can use WhatsApp automation to reply faster while keeping customer conversations personal.','2026-07-14 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,2,'Building Broadcast Campaigns That Customers Actually Read','building-broadcast-campaigns-customers-read','WaPro Growth Team','Better segmentation, cleaner copy, and timing can turn broadcast campaigns into useful customer touchpoints.','Broadcast campaigns work when they feel expected. Customers are more likely to read messages that match their lifecycle stage, recent behavior, and stated interests.\n\nStart with segmentation before writing copy. A campaign for new leads should not sound like a campaign for power users, and win-back messages should not look like product updates.\n\nKeep the message focused on one action. A single call to action gives the recipient less to parse and gives your team cleaner performance data after the send.\n\nFinally, measure replies as carefully as clicks. On WhatsApp, a thoughtful reply can be more valuable than a silent visit to a landing page.','assets/images/sections/solutions/2.webp',NULL,4,2,1,'published','Readable WhatsApp Broadcast Campaigns - WaPro Blog','Use segmentation, focused copy, and reply tracking to build WhatsApp broadcast campaigns customers read.','2026-07-11 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,3,'What to Track in a WhatsApp CRM Dashboard','what-to-track-whatsapp-crm-dashboard','WaPro Product Team','The most useful dashboards combine response speed, pipeline movement, campaign outcomes, and customer health.','A useful WhatsApp CRM dashboard should answer one question quickly: where does the team need to act now?\n\nResponse metrics show whether conversations are being handled on time. Track first response time, open conversations, overdue replies, and ownership by channel.\n\nPipeline metrics show whether conversations are becoming outcomes. Lead source, stage movement, conversion rate, and follow-up completion reveal the health of your sales motion.\n\nCampaign metrics complete the picture. Delivery, replies, opt-outs, and attributed revenue help you decide which messages deserve to be repeated.','assets/images/sections/solutions/03.webp',NULL,6,3,1,'published','WhatsApp CRM Dashboard Metrics - WaPro Blog','See which WhatsApp CRM metrics matter for support, sales, campaigns, and customer health.','2026-07-08 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,4,'Using Chatbots Without Making Support Feel Robotic','using-chatbots-without-robotic-support','WaPro Support Team','Chatbots work best when they handle repetitive structure and hand over gracefully when the conversation gets nuanced.','A chatbot does not need to pretend to be human. Customers are comfortable with automation when it is clear, useful, and easy to escape.\n\nBegin with narrow flows. Order status, appointment booking, qualification, and common troubleshooting are good candidates because they have predictable branches.\n\nWrite short prompts and give obvious choices. Long bot messages feel heavy inside chat, especially on mobile screens.\n\nMost importantly, design the handoff. A good bot collects context and passes it to a person with the conversation history intact.','assets/images/sections/solutions/04.webp',NULL,4,4,1,'published','Human-Friendly WhatsApp Chatbots - WaPro Blog','Design WhatsApp chatbots that automate repetitive support while keeping handoffs smooth and human.','2026-07-05 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
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
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-ai_settings_cache','a:74:{s:24:\"ai_default_text_provider\";s:0:\"\";s:21:\"ai_default_text_model\";s:0:\"\";s:25:\"ai_default_image_provider\";s:0:\"\";s:22:\"ai_default_image_model\";s:0:\"\";s:23:\"ai_default_tts_provider\";s:0:\"\";s:20:\"ai_default_tts_model\";s:0:\"\";s:23:\"ai_default_stt_provider\";s:0:\"\";s:20:\"ai_default_stt_model\";s:0:\"\";s:30:\"ai_default_embeddings_provider\";s:0:\"\";s:27:\"ai_default_embeddings_model\";s:0:\"\";s:23:\"vector_database_enabled\";s:1:\"0\";s:20:\"vector_database_mode\";s:5:\"local\";s:24:\"vector_database_provider\";s:6:\"qdrant\";s:10:\"qdrant_url\";s:21:\"http://localhost:6333\";s:14:\"qdrant_api_key\";s:0:\"\";s:17:\"qdrant_collection\";s:21:\"knowledge_base_chunks\";s:23:\"qdrant_vector_dimension\";s:4:\"1536\";s:19:\"qdrant_search_limit\";s:1:\"5\";s:22:\"qdrant_score_threshold\";s:3:\"0.2\";s:14:\"qdrant_timeout\";s:2:\"10\";s:14:\"openai_enabled\";s:1:\"0\";s:14:\"openai_api_key\";s:0:\"\";s:22:\"openai_organization_id\";s:0:\"\";s:15:\"openai_base_url\";s:0:\"\";s:11:\"openai_logo\";N;s:17:\"anthropic_enabled\";s:1:\"0\";s:17:\"anthropic_api_key\";s:0:\"\";s:18:\"anthropic_base_url\";s:0:\"\";s:14:\"anthropic_logo\";N;s:14:\"gemini_enabled\";s:1:\"0\";s:14:\"gemini_api_key\";s:0:\"\";s:15:\"gemini_base_url\";s:0:\"\";s:11:\"gemini_logo\";N;s:20:\"azure_openai_enabled\";s:1:\"0\";s:20:\"azure_openai_api_key\";s:0:\"\";s:21:\"azure_openai_base_url\";s:0:\"\";s:24:\"azure_openai_api_version\";s:10:\"2024-02-01\";s:23:\"azure_openai_deployment\";s:0:\"\";s:17:\"azure_openai_logo\";N;s:12:\"groq_enabled\";s:1:\"0\";s:12:\"groq_api_key\";s:0:\"\";s:13:\"groq_base_url\";s:0:\"\";s:9:\"groq_logo\";N;s:11:\"xai_enabled\";s:1:\"0\";s:11:\"xai_api_key\";s:0:\"\";s:12:\"xai_base_url\";s:0:\"\";s:8:\"xai_logo\";N;s:16:\"deepseek_enabled\";s:1:\"0\";s:16:\"deepseek_api_key\";s:0:\"\";s:17:\"deepseek_base_url\";s:0:\"\";s:13:\"deepseek_logo\";N;s:15:\"mistral_enabled\";s:1:\"0\";s:15:\"mistral_api_key\";s:0:\"\";s:16:\"mistral_base_url\";s:0:\"\";s:12:\"mistral_logo\";N;s:14:\"ollama_enabled\";s:1:\"0\";s:15:\"ollama_base_url\";s:22:\"http://localhost:11434\";s:11:\"ollama_logo\";N;s:18:\"elevenlabs_enabled\";s:1:\"0\";s:18:\"elevenlabs_api_key\";s:0:\"\";s:19:\"elevenlabs_base_url\";s:0:\"\";s:15:\"elevenlabs_logo\";N;s:14:\"cohere_enabled\";s:1:\"0\";s:14:\"cohere_api_key\";s:0:\"\";s:15:\"cohere_base_url\";s:0:\"\";s:11:\"cohere_logo\";N;s:12:\"jina_enabled\";s:1:\"0\";s:12:\"jina_api_key\";s:0:\"\";s:13:\"jina_base_url\";s:0:\"\";s:9:\"jina_logo\";N;s:16:\"voyageai_enabled\";s:1:\"0\";s:16:\"voyageai_api_key\";s:0:\"\";s:17:\"voyageai_base_url\";s:0:\"\";s:13:\"voyageai_logo\";N;}',1784375232),('laravel-cache-app_settings','a:75:{s:9:\"site_name\";s:11:\"Admin Panel\";s:16:\"site_description\";s:0:\"\";s:13:\"contact_email\";s:17:\"admin@example.com\";s:16:\"default_timezone\";s:3:\"UTC\";s:11:\"date_format\";s:6:\"d M, Y\";s:14:\"items_per_page\";s:2:\"15\";s:9:\"site_logo\";N;s:12:\"site_favicon\";N;s:13:\"primary_color\";s:7:\"#1fb254\";s:15:\"secondary_color\";s:7:\"#215ebf\";s:11:\"mail_mailer\";s:3:\"log\";s:14:\"mail_from_name\";s:7:\"Laravel\";s:17:\"mail_from_address\";s:17:\"hello@example.com\";s:9:\"mail_host\";s:9:\"127.0.0.1\";s:9:\"mail_port\";s:4:\"2525\";s:15:\"mail_encryption\";s:4:\"none\";s:13:\"mail_username\";N;s:13:\"mail_password\";N;s:14:\"mailgun_domain\";s:0:\"\";s:14:\"mailgun_secret\";s:0:\"\";s:16:\"mailgun_endpoint\";s:15:\"api.mailgun.net\";s:14:\"mailgun_scheme\";s:5:\"https\";s:19:\"enable_registration\";s:1:\"1\";s:10:\"enable_api\";s:1:\"1\";s:16:\"maintenance_mode\";s:0:\"\";s:22:\"require_2fa_for_admins\";s:0:\"\";s:20:\"enable_2fa_for_users\";s:1:\"1\";s:21:\"require_2fa_for_users\";s:0:\"\";s:20:\"cookie_popup_enabled\";s:1:\"1\";s:18:\"cookie_popup_title\";s:14:\"We use cookies\";s:20:\"cookie_popup_message\";s:153:\"We use cookies to improve your browsing experience, analyze site traffic, and personalize content. By clicking accept, you consent to our use of cookies.\";s:25:\"cookie_popup_accept_label\";s:6:\"Accept\";s:25:\"cookie_popup_policy_label\";s:13:\"Cookie Policy\";s:23:\"cookie_popup_policy_url\";s:14:\"/cookie-policy\";s:26:\"cookie_popup_lifetime_days\";s:3:\"365\";s:26:\"enable_email_notifications\";s:1:\"1\";s:24:\"enable_sms_notifications\";s:0:\"\";s:25:\"enable_push_notifications\";s:0:\"\";s:32:\"enable_mobile_push_notifications\";s:0:\"\";s:12:\"sms_provider\";s:3:\"log\";s:15:\"sms_from_number\";s:0:\"\";s:14:\"vonage_api_key\";s:0:\"\";s:17:\"vonage_api_secret\";s:0:\"\";s:10:\"twilio_sid\";s:0:\"\";s:17:\"twilio_auth_token\";s:0:\"\";s:16:\"vapid_public_key\";s:0:\"\";s:17:\"vapid_private_key\";s:0:\"\";s:25:\"firebase_credentials_json\";s:0:\"\";s:16:\"storage_provider\";s:5:\"local\";s:14:\"storage_s3_key\";s:0:\"\";s:17:\"storage_s3_secret\";s:0:\"\";s:17:\"storage_s3_region\";s:0:\"\";s:17:\"storage_s3_bucket\";s:0:\"\";s:19:\"storage_s3_endpoint\";s:0:\"\";s:14:\"storage_s3_url\";s:0:\"\";s:18:\"plugin_ga4_enabled\";s:0:\"\";s:25:\"plugin_ga4_measurement_id\";s:0:\"\";s:19:\"plugin_tawk_enabled\";s:0:\"\";s:23:\"plugin_tawk_property_id\";s:0:\"\";s:21:\"plugin_tawk_widget_id\";s:0:\"\";s:24:\"plugin_turnstile_enabled\";s:0:\"\";s:25:\"plugin_turnstile_site_key\";s:0:\"\";s:27:\"plugin_turnstile_secret_key\";s:0:\"\";s:21:\"social_google_enabled\";s:0:\"\";s:23:\"social_google_client_id\";s:0:\"\";s:27:\"social_google_client_secret\";s:0:\"\";s:26:\"social_google_callback_url\";N;s:23:\"social_facebook_enabled\";s:0:\"\";s:25:\"social_facebook_client_id\";s:0:\"\";s:29:\"social_facebook_client_secret\";s:0:\"\";s:28:\"social_facebook_callback_url\";N;s:21:\"social_github_enabled\";s:0:\"\";s:23:\"social_github_client_id\";s:0:\"\";s:27:\"social_github_client_secret\";s:0:\"\";s:26:\"social_github_callback_url\";N;}',1784375232),('laravel-cache-languages.active','O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:3:{i:0;O:37:\"App\\Modules\\Languages\\Models\\Language\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"languages\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:11:{s:2:\"id\";i:1;s:4:\"code\";s:2:\"en\";s:4:\"name\";s:7:\"English\";s:11:\"native_name\";s:7:\"English\";s:9:\"direction\";s:3:\"ltr\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:1;s:10:\"sort_order\";i:1;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:11:{s:2:\"id\";i:1;s:4:\"code\";s:2:\"en\";s:4:\"name\";s:7:\"English\";s:11:\"native_name\";s:7:\"English\";s:9:\"direction\";s:3:\"ltr\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:1;s:10:\"sort_order\";i:1;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:4:{s:9:\"is_active\";s:7:\"boolean\";s:10:\"is_default\";s:7:\"boolean\";s:10:\"sort_order\";s:7:\"integer\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"native_name\";i:3;s:9:\"direction\";i:4;s:9:\"is_active\";i:5;s:10:\"is_default\";i:6;s:10:\"sort_order\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:1;O:37:\"App\\Modules\\Languages\\Models\\Language\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"languages\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:11:{s:2:\"id\";i:2;s:4:\"code\";s:2:\"bn\";s:4:\"name\";s:7:\"Bengali\";s:11:\"native_name\";s:15:\"বাংলা\";s:9:\"direction\";s:3:\"ltr\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:0;s:10:\"sort_order\";i:2;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:11:{s:2:\"id\";i:2;s:4:\"code\";s:2:\"bn\";s:4:\"name\";s:7:\"Bengali\";s:11:\"native_name\";s:15:\"বাংলা\";s:9:\"direction\";s:3:\"ltr\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:0;s:10:\"sort_order\";i:2;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:4:{s:9:\"is_active\";s:7:\"boolean\";s:10:\"is_default\";s:7:\"boolean\";s:10:\"sort_order\";s:7:\"integer\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"native_name\";i:3;s:9:\"direction\";i:4;s:9:\"is_active\";i:5;s:10:\"is_default\";i:6;s:10:\"sort_order\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}i:2;O:37:\"App\\Modules\\Languages\\Models\\Language\":34:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"languages\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:11:{s:2:\"id\";i:3;s:4:\"code\";s:2:\"ar\";s:4:\"name\";s:6:\"Arabic\";s:11:\"native_name\";s:14:\"العربية\";s:9:\"direction\";s:3:\"rtl\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:0;s:10:\"sort_order\";i:3;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:11:\"\0*\0original\";a:11:{s:2:\"id\";i:3;s:4:\"code\";s:2:\"ar\";s:4:\"name\";s:6:\"Arabic\";s:11:\"native_name\";s:14:\"العربية\";s:9:\"direction\";s:3:\"rtl\";s:9:\"is_active\";i:1;s:10:\"is_default\";i:0;s:10:\"sort_order\";i:3;s:10:\"created_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"updated_at\";s:19:\"2026-07-17 09:06:03\";s:10:\"deleted_at\";N;}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:4:{s:9:\"is_active\";s:7:\"boolean\";s:10:\"is_default\";s:7:\"boolean\";s:10:\"sort_order\";s:7:\"integer\";s:10:\"deleted_at\";s:8:\"datetime\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:7:{i:0;s:4:\"code\";i:1;s:4:\"name\";i:2;s:11:\"native_name\";i:3;s:9:\"direction\";i:4;s:9:\"is_active\";i:5;s:10:\"is_default\";i:6;s:10:\"sort_order\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}s:16:\"\0*\0forceDeleting\";b:0;}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}',2099648879);
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
  `expiration` int NOT NULL,
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
-- Table structure for table `campaign_recipients`
--

DROP TABLE IF EXISTS `campaign_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_recipients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `contact_channel_id` bigint unsigned DEFAULT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `error_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `sending_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_recipients_campaign_id_contact_id_unique` (`campaign_id`,`contact_id`),
  UNIQUE KEY `campaign_recipients_uuid_unique` (`uuid`),
  KEY `campaign_recipients_workspace_id_foreign` (`workspace_id`),
  KEY `campaign_recipients_contact_id_foreign` (`contact_id`),
  KEY `campaign_recipients_contact_channel_id_foreign` (`contact_channel_id`),
  KEY `campaign_recipients_channel_account_id_foreign` (`channel_account_id`),
  KEY `campaign_recipients_provider_index` (`provider`),
  KEY `campaign_recipients_status_index` (`status`),
  KEY `campaign_recipients_provider_message_id_index` (`provider_message_id`),
  CONSTRAINT `campaign_recipients_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_recipients_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaign_recipients_contact_channel_id_foreign` FOREIGN KEY (`contact_channel_id`) REFERENCES `contact_provider_identities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaign_recipients_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_recipients_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_recipients`
--

LOCK TABLES `campaign_recipients` WRITE;
/*!40000 ALTER TABLE `campaign_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_template_id` bigint unsigned DEFAULT NULL,
  `automation_id` bigint unsigned DEFAULT NULL,
  `audience_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience_ids` json DEFAULT NULL,
  `segment_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','scheduled','queued','sending','completed','paused','cancelled','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `message_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `audience` json DEFAULT NULL,
  `message_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_body` text COLLATE utf8mb4_unicode_ci,
  `variables` json DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `message_payload` json DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `send_rate_per_minute` int unsigned NOT NULL DEFAULT '60',
  `total_recipients` int unsigned NOT NULL DEFAULT '0',
  `queued_count` int unsigned NOT NULL DEFAULT '0',
  `sending_count` int unsigned NOT NULL DEFAULT '0',
  `sent_count` int unsigned NOT NULL DEFAULT '0',
  `delivered_count` int unsigned NOT NULL DEFAULT '0',
  `opened_count` int unsigned NOT NULL DEFAULT '0',
  `read_count` int unsigned NOT NULL DEFAULT '0',
  `clicked_count` int unsigned NOT NULL DEFAULT '0',
  `replied_count` int unsigned NOT NULL DEFAULT '0',
  `failed_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_opt_out_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_invalid_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_policy_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`),
  KEY `campaigns_workspace_id_foreign` (`workspace_id`),
  KEY `campaigns_channel_account_id_foreign` (`channel_account_id`),
  KEY `campaigns_message_template_id_foreign` (`message_template_id`),
  KEY `campaigns_segment_id_foreign` (`segment_id`),
  KEY `campaigns_provider_index` (`provider`),
  KEY `campaigns_automation_id_index` (`automation_id`),
  CONSTRAINT `campaigns_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_message_template_id_foreign` FOREIGN KEY (`message_template_id`) REFERENCES `message_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_segment_id_foreign` FOREIGN KEY (`segment_id`) REFERENCES `segments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `channel_accounts`
--

DROP TABLE IF EXISTS `channel_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `channel_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','connected','disconnected','error','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `credentials` text COLLATE utf8mb4_unicode_ci,
  `webhook_verify_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhook_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_account_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_phone_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_display_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `connected_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_accounts_webhook_code_unique` (`webhook_code`),
  KEY `channel_accounts_workspace_id_provider_status_index` (`workspace_id`,`provider`,`status`),
  KEY `channel_accounts_provider_index` (`provider`),
  KEY `channel_accounts_status_index` (`status`),
  KEY `channel_accounts_provider_account_id_index` (`provider_account_id`),
  KEY `channel_accounts_provider_phone_id_index` (`provider_phone_id`),
  CONSTRAINT `channel_accounts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `channel_accounts`
--

LOCK TABLES `channel_accounts` WRITE;
/*!40000 ALTER TABLE `channel_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `channel_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `channel_webhook_events`
--

DROP TABLE IF EXISTS `channel_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `channel_webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `provider_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `headers` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_webhook_events_provider_provider_event_id_unique` (`provider`,`provider_event_id`),
  UNIQUE KEY `channel_webhook_events_payload_hash_unique` (`payload_hash`),
  KEY `channel_webhook_events_channel_account_id_foreign` (`channel_account_id`),
  KEY `channel_webhook_events_workspace_id_foreign` (`workspace_id`),
  KEY `channel_webhook_events_provider_index` (`provider`),
  KEY `channel_webhook_events_event_type_index` (`event_type`),
  KEY `channel_webhook_events_status_index` (`status`),
  CONSTRAINT `channel_webhook_events_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `channel_webhook_events_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `channel_webhook_events`
--

LOCK TABLES `channel_webhook_events` WRITE;
/*!40000 ALTER TABLE `channel_webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `channel_webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbot_knowledge_base`
--

DROP TABLE IF EXISTS `chatbot_knowledge_base`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbot_knowledge_base` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chatbot_id` bigint unsigned NOT NULL,
  `knowledge_base_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chatbot_knowledge_base_chatbot_id_knowledge_base_id_unique` (`chatbot_id`,`knowledge_base_id`),
  KEY `chatbot_knowledge_base_knowledge_base_id_foreign` (`knowledge_base_id`),
  CONSTRAINT `chatbot_knowledge_base_chatbot_id_foreign` FOREIGN KEY (`chatbot_id`) REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chatbot_knowledge_base_knowledge_base_id_foreign` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_bases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot_knowledge_base`
--

LOCK TABLES `chatbot_knowledge_base` WRITE;
/*!40000 ALTER TABLE `chatbot_knowledge_base` DISABLE KEYS */;
/*!40000 ALTER TABLE `chatbot_knowledge_base` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbot_widget_sessions`
--

DROP TABLE IF EXISTS `chatbot_widget_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbot_widget_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `widget_id` bigint unsigned NOT NULL,
  `chatbot_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `session_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `visitor_uid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitor_metadata` json DEFAULT NULL,
  `ip_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chatbot_widget_sessions_session_token_unique` (`session_token`),
  KEY `chatbot_widget_sessions_workspace_id_foreign` (`workspace_id`),
  KEY `chatbot_widget_sessions_chatbot_id_foreign` (`chatbot_id`),
  KEY `chatbot_widget_sessions_conversation_id_foreign` (`conversation_id`),
  KEY `chatbot_widget_sessions_contact_id_foreign` (`contact_id`),
  KEY `chatbot_widget_sessions_widget_id_visitor_uid_index` (`widget_id`,`visitor_uid`),
  KEY `chatbot_widget_sessions_visitor_uid_index` (`visitor_uid`),
  CONSTRAINT `chatbot_widget_sessions_chatbot_id_foreign` FOREIGN KEY (`chatbot_id`) REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chatbot_widget_sessions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chatbot_widget_sessions_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chatbot_widget_sessions_widget_id_foreign` FOREIGN KEY (`widget_id`) REFERENCES `chatbot_widgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chatbot_widget_sessions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot_widget_sessions`
--

LOCK TABLES `chatbot_widget_sessions` WRITE;
/*!40000 ALTER TABLE `chatbot_widget_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `chatbot_widget_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbot_widgets`
--

DROP TABLE IF EXISTS `chatbot_widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbot_widgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `chatbot_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_token` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `allowed_domains` json DEFAULT NULL,
  `lead_fields` json DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `greeting` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chatbot_widgets_public_token_unique` (`public_token`),
  KEY `chatbot_widgets_chatbot_id_foreign` (`chatbot_id`),
  KEY `chatbot_widgets_workspace_id_is_active_index` (`workspace_id`,`is_active`),
  CONSTRAINT `chatbot_widgets_chatbot_id_foreign` FOREIGN KEY (`chatbot_id`) REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chatbot_widgets_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot_widgets`
--

LOCK TABLES `chatbot_widgets` WRITE;
/*!40000 ALTER TABLE `chatbot_widgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `chatbot_widgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbots`
--

DROP TABLE IF EXISTS `chatbots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `persona` text COLLATE utf8mb4_unicode_ci,
  `greeting` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature` decimal(3,2) NOT NULL DEFAULT '0.40',
  `max_tokens` smallint unsigned NOT NULL DEFAULT '512',
  `fallback_only_knowledge_base` tinyint(1) NOT NULL DEFAULT '1',
  `confidence_threshold` decimal(3,2) NOT NULL DEFAULT '0.70',
  `handoff_rules` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chatbots_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `chatbots_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbots`
--

LOCK TABLES `chatbots` WRITE;
/*!40000 ALTER TABLE `chatbots` DISABLE KEYS */;
/*!40000 ALTER TABLE `chatbots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_audiences`
--

DROP TABLE IF EXISTS `commerce_audiences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_audiences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_audiences_workspace_id_slug_unique` (`workspace_id`,`slug`),
  CONSTRAINT `commerce_audiences_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_audiences`
--

LOCK TABLES `commerce_audiences` WRITE;
/*!40000 ALTER TABLE `commerce_audiences` DISABLE KEYS */;
INSERT INTO `commerce_audiences` VALUES (1,1,'Women','women',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,'Men','men',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,'Unisex','unisex',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,'Kids','kids',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,'Teen','teen',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,'Baby','baby',1,'2026-07-17 09:06:37','2026-07-17 09:06:37');
/*!40000 ALTER TABLE `commerce_audiences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_brands`
--

DROP TABLE IF EXISTS `commerce_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_brands_workspace_id_slug_unique` (`workspace_id`,`slug`),
  CONSTRAINT `commerce_brands_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_brands`
--

LOCK TABLES `commerce_brands` WRITE;
/*!40000 ALTER TABLE `commerce_brands` DISABLE KEYS */;
INSERT INTO `commerce_brands` VALUES (1,1,'Dhaka Loom Studio','dhaka-loom-studio',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,'Bengal Threadworks','bengal-threadworks',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,'River & Reed Apparel','river-reed-apparel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,'Northstar Garments','northstar-garments',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,'Urban Weave Co.','urban-weave-co',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,'Cotton House BD','cotton-house-bd',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,'Aarong Lane Basics','aarong-lane-basics',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,'Summit Activewear','summit-activewear',1,'2026-07-17 09:06:37','2026-07-17 09:06:37');
/*!40000 ALTER TABLE `commerce_brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_catalog_item_syncs`
--

DROP TABLE IF EXISTS `commerce_catalog_item_syncs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_catalog_item_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `catalog_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `retailer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_item_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `provider_response` json DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_catalog_item_syncs_catalog_id_variant_id_unique` (`catalog_id`,`variant_id`),
  UNIQUE KEY `commerce_catalog_item_syncs_catalog_id_retailer_id_unique` (`catalog_id`,`retailer_id`),
  KEY `commerce_catalog_item_syncs_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_catalog_item_syncs_variant_id_foreign` (`variant_id`),
  KEY `commerce_catalog_item_syncs_status_index` (`status`),
  CONSTRAINT `commerce_catalog_item_syncs_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `commerce_catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_catalog_item_syncs_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_catalog_item_syncs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_catalog_item_syncs`
--

LOCK TABLES `commerce_catalog_item_syncs` WRITE;
/*!40000 ALTER TABLE `commerce_catalog_item_syncs` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_catalog_item_syncs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_catalog_sync_runs`
--

DROP TABLE IF EXISTS `commerce_catalog_sync_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_catalog_sync_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `catalog_id` bigint unsigned NOT NULL,
  `mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `total_items` int unsigned NOT NULL DEFAULT '0',
  `successful_items` int unsigned NOT NULL DEFAULT '0',
  `failed_items` int unsigned NOT NULL DEFAULT '0',
  `summary` json DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commerce_catalog_sync_runs_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_catalog_sync_runs_catalog_id_foreign` (`catalog_id`),
  KEY `commerce_catalog_sync_runs_status_index` (`status`),
  CONSTRAINT `commerce_catalog_sync_runs_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `commerce_catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_catalog_sync_runs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_catalog_sync_runs`
--

LOCK TABLES `commerce_catalog_sync_runs` WRITE;
/*!40000 ALTER TABLE `commerce_catalog_sync_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_catalog_sync_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_catalogs`
--

DROP TABLE IF EXISTS `commerce_catalogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_catalogs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned NOT NULL,
  `meta_catalog_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feed_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sync_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'feed',
  `readiness_state` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'needs_setup',
  `cart_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `catalog_visible` tinyint(1) NOT NULL DEFAULT '0',
  `last_sync_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_sync_summary` json DEFAULT NULL,
  `last_item_count` int unsigned NOT NULL DEFAULT '0',
  `last_fetched_at` timestamp NULL DEFAULT NULL,
  `last_successful_at` timestamp NULL DEFAULT NULL,
  `last_reconciled_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_catalogs_workspace_id_channel_account_id_unique` (`workspace_id`,`channel_account_id`),
  UNIQUE KEY `commerce_catalogs_feed_token_unique` (`feed_token`),
  KEY `commerce_catalogs_channel_account_id_foreign` (`channel_account_id`),
  CONSTRAINT `commerce_catalogs_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_catalogs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_catalogs`
--

LOCK TABLES `commerce_catalogs` WRITE;
/*!40000 ALTER TABLE `commerce_catalogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_catalogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_categories`
--

DROP TABLE IF EXISTS `commerce_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_categories_workspace_id_slug_unique` (`workspace_id`,`slug`),
  KEY `commerce_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `commerce_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `commerce_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_categories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_categories`
--

LOCK TABLES `commerce_categories` WRITE;
/*!40000 ALTER TABLE `commerce_categories` DISABLE KEYS */;
INSERT INTO `commerce_categories` VALUES (1,1,NULL,'Shirts','shirts',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,NULL,'Trousers','trousers',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,NULL,'Dresses','dresses',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,NULL,'Jackets','jackets',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,NULL,'Activewear','activewear',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,NULL,'Kids Clothing','kids-clothing',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,NULL,'Uniforms','uniforms',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,NULL,'Hoodies & Sweaters','hoodies-sweaters',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,NULL,'Coats','coats',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,NULL,'Blouses','blouses',1,'2026-07-17 09:06:37','2026-07-17 09:06:37');
/*!40000 ALTER TABLE `commerce_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_inventory_movements`
--

DROP TABLE IF EXISTS `commerce_inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `quantity_delta` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_inventory_movements_idempotency_key_unique` (`idempotency_key`),
  KEY `commerce_inventory_movements_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_inventory_movements_variant_id_foreign` (`variant_id`),
  KEY `commerce_inventory_movements_order_id_foreign` (`order_id`),
  CONSTRAINT `commerce_inventory_movements_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_inventory_movements_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_inventory_movements_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_inventory_movements`
--

LOCK TABLES `commerce_inventory_movements` WRITE;
/*!40000 ALTER TABLE `commerce_inventory_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_message_attempts`
--

DROP TABLE IF EXISTS `commerce_message_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_message_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `idempotency_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `request_payload` json NOT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_message_attempts_idempotency_key_unique` (`idempotency_key`),
  KEY `commerce_message_attempts_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_message_attempts_conversation_id_foreign` (`conversation_id`),
  KEY `commerce_message_attempts_message_id_foreign` (`message_id`),
  KEY `commerce_message_attempts_status_index` (`status`),
  CONSTRAINT `commerce_message_attempts_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_message_attempts_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_message_attempts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_message_attempts`
--

LOCK TABLES `commerce_message_attempts` WRITE;
/*!40000 ALTER TABLE `commerce_message_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_message_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_order_items`
--

DROP TABLE IF EXISTS `commerce_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `retailer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attributes` json DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `provider_unit_price` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commerce_order_items_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_order_items_order_id_foreign` (`order_id`),
  KEY `commerce_order_items_variant_id_foreign` (`variant_id`),
  CONSTRAINT `commerce_order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_order_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_order_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_order_items`
--

LOCK TABLES `commerce_order_items` WRITE;
/*!40000 ALTER TABLE `commerce_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_orders`
--

DROP TABLE IF EXISTS `commerce_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `catalog_id` bigint unsigned DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_message_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_catalog_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shipping_amount` decimal(12,2) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `delivery_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_notes` text COLLATE utf8mb4_unicode_ci,
  `duties_disclosure` text COLLATE utf8mb4_unicode_ci,
  `payment_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_adjusted_at` timestamp NULL DEFAULT NULL,
  `inventory_restored_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `issues` json DEFAULT NULL,
  `provider_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_orders_number_unique` (`number`),
  UNIQUE KEY `commerce_orders_channel_account_id_provider_message_id_unique` (`channel_account_id`,`provider_message_id`),
  KEY `commerce_orders_contact_id_foreign` (`contact_id`),
  KEY `commerce_orders_conversation_id_foreign` (`conversation_id`),
  KEY `commerce_orders_catalog_id_foreign` (`catalog_id`),
  KEY `commerce_orders_workspace_id_status_created_at_index` (`workspace_id`,`status`,`created_at`),
  KEY `commerce_orders_status_index` (`status`),
  CONSTRAINT `commerce_orders_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `commerce_catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_orders_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_orders_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_orders_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_orders_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_orders`
--

LOCK TABLES `commerce_orders` WRITE;
/*!40000 ALTER TABLE `commerce_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `commerce_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_product_media`
--

DROP TABLE IF EXISTS `commerce_product_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_product_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `media_id` bigint unsigned NOT NULL,
  `media_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gallery',
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_media_product_id_media_id_unique` (`product_id`,`media_id`),
  KEY `commerce_product_media_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_product_media_media_id_foreign` (`media_id`),
  KEY `commerce_product_media_product_id_position_index` (`product_id`,`position`),
  CONSTRAINT `commerce_product_media_media_id_foreign` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_media_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_media_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_product_media`
--

LOCK TABLES `commerce_product_media` WRITE;
/*!40000 ALTER TABLE `commerce_product_media` DISABLE KEYS */;
INSERT INTO `commerce_product_media` VALUES (1,1,1,1,'image','primary','Essential Oxford Button-Down Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,1,20,'image','gallery','Essential Oxford Button-Down Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,2,8,'image','primary','Essential Washed Linen Camp Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,2,27,'image','gallery','Essential Washed Linen Camp Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,3,15,'image','primary','Essential Premium Pique Polo front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,3,34,'image','gallery','Essential Premium Pique Polo detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,4,22,'image','primary','Essential Stretch Chino Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,4,41,'image','gallery','Essential Stretch Chino Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,5,29,'image','primary','Essential High-Rise Tailored Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,5,48,'image','gallery','Essential High-Rise Tailored Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,1,6,36,'image','primary','Essential Tiered Viscose Maxi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,1,6,55,'image','gallery','Essential Tiered Viscose Maxi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,1,7,43,'image','primary','Essential Crepe Wrap Midi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,1,7,2,'image','gallery','Essential Crepe Wrap Midi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,1,8,50,'image','primary','Essential Classic Denim Trucker Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,1,8,9,'image','gallery','Essential Classic Denim Trucker Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,1,9,57,'image','primary','Essential Recycled Nylon Bomber Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,1,9,16,'image','gallery','Essential Recycled Nylon Bomber Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,1,10,4,'image','primary','Essential Seamless Performance Legging front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,1,10,23,'image','gallery','Essential Seamless Performance Legging detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,1,11,11,'image','primary','Essential Quick-Dry Training Short front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,1,11,30,'image','gallery','Essential Quick-Dry Training Short detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,1,12,18,'image','primary','Essential Kids Everyday Zip Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,1,12,37,'image','gallery','Essential Kids Everyday Zip Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,1,13,25,'image','primary','Essential School Uniform Poplin Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,1,13,44,'image','gallery','Essential School Uniform Poplin Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,1,14,32,'image','primary','Essential Industrial Workwear Coverall front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,1,14,51,'image','gallery','Essential Industrial Workwear Coverall detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,1,15,39,'image','primary','Essential Brushed Fleece Pullover Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,1,15,58,'image','gallery','Essential Brushed Fleece Pullover Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,1,16,46,'image','primary','Essential Cable Knit Cotton Sweater front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,1,16,5,'image','gallery','Essential Cable Knit Cotton Sweater detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,1,17,53,'image','primary','Essential Water-Repellent Trench Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,1,17,12,'image','gallery','Essential Water-Repellent Trench Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,1,18,60,'image','primary','Essential Double-Face Wool Blend Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,1,18,19,'image','gallery','Essential Double-Face Wool Blend Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,1,19,7,'image','primary','Essential Pleated Cotton Voile Blouse front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,1,19,26,'image','gallery','Essential Pleated Cotton Voile Blouse detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,1,20,14,'image','primary','Essential Ripstop Cargo Pant front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,1,20,33,'image','gallery','Essential Ripstop Cargo Pant detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,1,21,21,'image','primary','Heritage Oxford Button-Down Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,1,21,40,'image','gallery','Heritage Oxford Button-Down Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,1,22,28,'image','primary','Heritage Washed Linen Camp Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,1,22,47,'image','gallery','Heritage Washed Linen Camp Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,1,23,35,'image','primary','Heritage Premium Pique Polo front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,1,23,54,'image','gallery','Heritage Premium Pique Polo detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,1,24,42,'image','primary','Heritage Stretch Chino Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,1,24,1,'image','gallery','Heritage Stretch Chino Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,1,25,49,'image','primary','Heritage High-Rise Tailored Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,1,25,8,'image','gallery','Heritage High-Rise Tailored Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,1,26,56,'image','primary','Heritage Tiered Viscose Maxi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,1,26,15,'image','gallery','Heritage Tiered Viscose Maxi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,1,27,3,'image','primary','Heritage Crepe Wrap Midi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,1,27,22,'image','gallery','Heritage Crepe Wrap Midi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,1,28,10,'image','primary','Heritage Classic Denim Trucker Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,1,28,29,'image','gallery','Heritage Classic Denim Trucker Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,1,29,17,'image','primary','Heritage Recycled Nylon Bomber Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,1,29,36,'image','gallery','Heritage Recycled Nylon Bomber Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,1,30,24,'image','primary','Heritage Seamless Performance Legging front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,1,30,43,'image','gallery','Heritage Seamless Performance Legging detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(61,1,31,31,'image','primary','Heritage Quick-Dry Training Short front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(62,1,31,50,'image','gallery','Heritage Quick-Dry Training Short detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(63,1,32,38,'image','primary','Heritage Kids Everyday Zip Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(64,1,32,57,'image','gallery','Heritage Kids Everyday Zip Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(65,1,33,45,'image','primary','Heritage School Uniform Poplin Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(66,1,33,4,'image','gallery','Heritage School Uniform Poplin Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(67,1,34,52,'image','primary','Heritage Industrial Workwear Coverall front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(68,1,34,11,'image','gallery','Heritage Industrial Workwear Coverall detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(69,1,35,59,'image','primary','Heritage Brushed Fleece Pullover Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(70,1,35,18,'image','gallery','Heritage Brushed Fleece Pullover Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(71,1,36,6,'image','primary','Heritage Cable Knit Cotton Sweater front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(72,1,36,25,'image','gallery','Heritage Cable Knit Cotton Sweater detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(73,1,37,13,'image','primary','Heritage Water-Repellent Trench Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(74,1,37,32,'image','gallery','Heritage Water-Repellent Trench Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(75,1,38,20,'image','primary','Heritage Double-Face Wool Blend Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(76,1,38,39,'image','gallery','Heritage Double-Face Wool Blend Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(77,1,39,27,'image','primary','Heritage Pleated Cotton Voile Blouse front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(78,1,39,46,'image','gallery','Heritage Pleated Cotton Voile Blouse detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(79,1,40,34,'image','primary','Heritage Ripstop Cargo Pant front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(80,1,40,53,'image','gallery','Heritage Ripstop Cargo Pant detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(81,1,41,41,'image','primary','Urban Oxford Button-Down Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(82,1,41,60,'image','gallery','Urban Oxford Button-Down Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(83,1,42,48,'image','primary','Urban Washed Linen Camp Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(84,1,42,7,'image','gallery','Urban Washed Linen Camp Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(85,1,43,55,'image','primary','Urban Premium Pique Polo front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(86,1,43,14,'image','gallery','Urban Premium Pique Polo detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(87,1,44,2,'image','primary','Urban Stretch Chino Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(88,1,44,21,'image','gallery','Urban Stretch Chino Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(89,1,45,9,'image','primary','Urban High-Rise Tailored Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(90,1,45,28,'image','gallery','Urban High-Rise Tailored Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(91,1,46,16,'image','primary','Urban Tiered Viscose Maxi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(92,1,46,35,'image','gallery','Urban Tiered Viscose Maxi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(93,1,47,23,'image','primary','Urban Crepe Wrap Midi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(94,1,47,42,'image','gallery','Urban Crepe Wrap Midi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(95,1,48,30,'image','primary','Urban Classic Denim Trucker Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(96,1,48,49,'image','gallery','Urban Classic Denim Trucker Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(97,1,49,37,'image','primary','Urban Recycled Nylon Bomber Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(98,1,49,56,'image','gallery','Urban Recycled Nylon Bomber Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(99,1,50,44,'image','primary','Urban Seamless Performance Legging front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(100,1,50,3,'image','gallery','Urban Seamless Performance Legging detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(101,1,51,51,'image','primary','Urban Quick-Dry Training Short front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(102,1,51,10,'image','gallery','Urban Quick-Dry Training Short detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(103,1,52,58,'image','primary','Urban Kids Everyday Zip Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(104,1,52,17,'image','gallery','Urban Kids Everyday Zip Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(105,1,53,5,'image','primary','Urban School Uniform Poplin Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(106,1,53,24,'image','gallery','Urban School Uniform Poplin Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(107,1,54,12,'image','primary','Urban Industrial Workwear Coverall front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(108,1,54,31,'image','gallery','Urban Industrial Workwear Coverall detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(109,1,55,19,'image','primary','Urban Brushed Fleece Pullover Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(110,1,55,38,'image','gallery','Urban Brushed Fleece Pullover Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(111,1,56,26,'image','primary','Urban Cable Knit Cotton Sweater front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(112,1,56,45,'image','gallery','Urban Cable Knit Cotton Sweater detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(113,1,57,33,'image','primary','Urban Water-Repellent Trench Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(114,1,57,52,'image','gallery','Urban Water-Repellent Trench Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(115,1,58,40,'image','primary','Urban Double-Face Wool Blend Coat front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(116,1,58,59,'image','gallery','Urban Double-Face Wool Blend Coat detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(117,1,59,47,'image','primary','Urban Pleated Cotton Voile Blouse front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(118,1,59,6,'image','gallery','Urban Pleated Cotton Voile Blouse detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(119,1,60,54,'image','primary','Urban Ripstop Cargo Pant front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(120,1,60,13,'image','gallery','Urban Ripstop Cargo Pant detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(121,1,61,1,'image','primary','Studio Oxford Button-Down Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(122,1,61,20,'image','gallery','Studio Oxford Button-Down Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(123,1,62,8,'image','primary','Studio Washed Linen Camp Shirt front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(124,1,62,27,'image','gallery','Studio Washed Linen Camp Shirt detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(125,1,63,15,'image','primary','Studio Premium Pique Polo front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(126,1,63,34,'image','gallery','Studio Premium Pique Polo detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(127,1,64,22,'image','primary','Studio Stretch Chino Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(128,1,64,41,'image','gallery','Studio Stretch Chino Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(129,1,65,29,'image','primary','Studio High-Rise Tailored Trouser front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(130,1,65,48,'image','gallery','Studio High-Rise Tailored Trouser detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(131,1,66,36,'image','primary','Studio Tiered Viscose Maxi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(132,1,66,55,'image','gallery','Studio Tiered Viscose Maxi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(133,1,67,43,'image','primary','Studio Crepe Wrap Midi Dress front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(134,1,67,2,'image','gallery','Studio Crepe Wrap Midi Dress detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(135,1,68,50,'image','primary','Studio Classic Denim Trucker Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(136,1,68,9,'image','gallery','Studio Classic Denim Trucker Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(137,1,69,57,'image','primary','Studio Recycled Nylon Bomber Jacket front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(138,1,69,16,'image','gallery','Studio Recycled Nylon Bomber Jacket detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(139,1,70,4,'image','primary','Studio Seamless Performance Legging front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(140,1,70,23,'image','gallery','Studio Seamless Performance Legging detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(141,1,71,11,'image','primary','Studio Quick-Dry Training Short front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(142,1,71,30,'image','gallery','Studio Quick-Dry Training Short detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(143,1,72,18,'image','primary','Studio Kids Everyday Zip Hoodie front view',0,1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(144,1,72,37,'image','gallery','Studio Kids Everyday Zip Hoodie detail view',1,0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(145,1,73,25,'image','primary','Studio School Uniform Poplin Shirt front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(146,1,73,44,'image','gallery','Studio School Uniform Poplin Shirt detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(147,1,74,32,'image','primary','Studio Industrial Workwear Coverall front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(148,1,74,51,'image','gallery','Studio Industrial Workwear Coverall detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(149,1,75,39,'image','primary','Studio Brushed Fleece Pullover Hoodie front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(150,1,75,58,'image','gallery','Studio Brushed Fleece Pullover Hoodie detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(151,1,76,46,'image','primary','Studio Cable Knit Cotton Sweater front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(152,1,76,5,'image','gallery','Studio Cable Knit Cotton Sweater detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(153,1,77,53,'image','primary','Studio Water-Repellent Trench Coat front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(154,1,77,12,'image','gallery','Studio Water-Repellent Trench Coat detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(155,1,78,60,'image','primary','Studio Double-Face Wool Blend Coat front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(156,1,78,19,'image','gallery','Studio Double-Face Wool Blend Coat detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(157,1,79,7,'image','primary','Studio Pleated Cotton Voile Blouse front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(158,1,79,26,'image','gallery','Studio Pleated Cotton Voile Blouse detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(159,1,80,14,'image','primary','Studio Ripstop Cargo Pant front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(160,1,80,33,'image','gallery','Studio Ripstop Cargo Pant detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(161,1,81,21,'image','primary','Premium Oxford Button-Down Shirt front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(162,1,81,40,'image','gallery','Premium Oxford Button-Down Shirt detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(165,1,83,35,'image','primary','Premium Premium Pique Polo front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(166,1,83,54,'image','gallery','Premium Premium Pique Polo detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(167,1,84,42,'image','primary','Premium Stretch Chino Trouser front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(168,1,84,1,'image','gallery','Premium Stretch Chino Trouser detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(169,1,85,49,'image','primary','Premium High-Rise Tailored Trouser front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(170,1,85,8,'image','gallery','Premium High-Rise Tailored Trouser detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(171,1,86,56,'image','primary','Premium Tiered Viscose Maxi Dress front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(172,1,86,15,'image','gallery','Premium Tiered Viscose Maxi Dress detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(173,1,87,3,'image','primary','Premium Crepe Wrap Midi Dress front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(174,1,87,22,'image','gallery','Premium Crepe Wrap Midi Dress detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(175,1,88,10,'image','primary','Premium Classic Denim Trucker Jacket front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(176,1,88,29,'image','gallery','Premium Classic Denim Trucker Jacket detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(177,1,89,17,'image','primary','Premium Recycled Nylon Bomber Jacket front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(178,1,89,36,'image','gallery','Premium Recycled Nylon Bomber Jacket detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(179,1,90,24,'image','primary','Premium Seamless Performance Legging front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(180,1,90,43,'image','gallery','Premium Seamless Performance Legging detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(181,1,91,31,'image','primary','Premium Quick-Dry Training Short front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(182,1,91,50,'image','gallery','Premium Quick-Dry Training Short detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(183,1,92,38,'image','primary','Premium Kids Everyday Zip Hoodie front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(184,1,92,57,'image','gallery','Premium Kids Everyday Zip Hoodie detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(185,1,93,45,'image','primary','Premium School Uniform Poplin Shirt front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(186,1,93,4,'image','gallery','Premium School Uniform Poplin Shirt detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(187,1,94,52,'image','primary','Premium Industrial Workwear Coverall front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(188,1,94,11,'image','gallery','Premium Industrial Workwear Coverall detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(189,1,95,59,'image','primary','Premium Brushed Fleece Pullover Hoodie front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(190,1,95,18,'image','gallery','Premium Brushed Fleece Pullover Hoodie detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(191,1,96,6,'image','primary','Premium Cable Knit Cotton Sweater front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(192,1,96,25,'image','gallery','Premium Cable Knit Cotton Sweater detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(193,1,97,13,'image','primary','Premium Water-Repellent Trench Coat front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(194,1,97,32,'image','gallery','Premium Water-Repellent Trench Coat detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(195,1,98,20,'image','primary','Premium Double-Face Wool Blend Coat front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(196,1,98,39,'image','gallery','Premium Double-Face Wool Blend Coat detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(197,1,99,27,'image','primary','Premium Pleated Cotton Voile Blouse front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(198,1,99,46,'image','gallery','Premium Pleated Cotton Voile Blouse detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(199,1,100,34,'image','primary','Premium Ripstop Cargo Pant front view',0,1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(200,1,100,53,'image','gallery','Premium Ripstop Cargo Pant detail view',1,0,'2026-07-17 09:06:38','2026-07-17 09:06:38');
/*!40000 ALTER TABLE `commerce_product_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_product_option_values`
--

DROP TABLE IF EXISTS `commerce_product_option_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_product_option_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `option_id` bigint unsigned NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_option_values_option_id_value_unique` (`option_id`,`value`),
  KEY `commerce_product_option_values_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `commerce_product_option_values_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `commerce_product_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_option_values_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=401 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_product_option_values`
--

LOCK TABLES `commerce_product_option_values` WRITE;
/*!40000 ALTER TABLE `commerce_product_option_values` DISABLE KEYS */;
INSERT INTO `commerce_product_option_values` VALUES (1,1,1,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,1,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,2,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,2,'Sky Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,3,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,3,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,4,'Natural',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,4,'Sage',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,5,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,5,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,1,6,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,1,6,'Heather Grey',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,1,7,'30',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,1,7,'32',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,1,8,'Khaki',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,1,8,'Olive',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,1,9,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,1,9,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,1,10,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,1,10,'Taupe',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,1,11,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,1,11,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,1,12,'Floral Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,1,12,'Terracotta',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,1,13,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,1,13,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,1,14,'Emerald',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,1,14,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,1,15,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,1,15,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,1,16,'Indigo',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,1,16,'Washed Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,1,17,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,1,17,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,1,18,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,1,18,'Army Green',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,1,19,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,1,19,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,1,20,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,1,20,'Plum',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,1,21,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,1,21,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,1,22,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,1,22,'Cobalt',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,1,23,'4Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,1,23,'8Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,1,24,'Red',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,1,24,'Navy',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,1,25,'6Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,1,25,'10Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,1,26,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,1,26,'Light Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,1,27,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,1,27,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,1,28,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,1,28,'Graphite',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,1,29,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,1,29,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,1,30,'Oatmeal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,1,30,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(61,1,31,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(62,1,31,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(63,1,32,'Cream',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(64,1,32,'Dusty Rose',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(65,1,33,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(66,1,33,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(67,1,34,'Stone',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(68,1,34,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(69,1,35,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(70,1,35,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(71,1,36,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(72,1,36,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(73,1,37,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(74,1,37,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(75,1,38,'Ivory',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(76,1,38,'Powder Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(77,1,39,'XS',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(78,1,39,'S',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(79,1,40,'Olive',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(80,1,40,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(81,1,41,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(82,1,41,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(83,1,42,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(84,1,42,'Sky Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(85,1,43,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(86,1,43,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(87,1,44,'Natural',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(88,1,44,'Sage',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(89,1,45,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(90,1,45,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(91,1,46,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(92,1,46,'Heather Grey',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(93,1,47,'30',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(94,1,47,'32',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(95,1,48,'Khaki',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(96,1,48,'Olive',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(97,1,49,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(98,1,49,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(99,1,50,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(100,1,50,'Taupe',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(101,1,51,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(102,1,51,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(103,1,52,'Floral Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(104,1,52,'Terracotta',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(105,1,53,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(106,1,53,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(107,1,54,'Emerald',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(108,1,54,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(109,1,55,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(110,1,55,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(111,1,56,'Indigo',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(112,1,56,'Washed Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(113,1,57,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(114,1,57,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(115,1,58,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(116,1,58,'Army Green',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(117,1,59,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(118,1,59,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(119,1,60,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(120,1,60,'Plum',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(121,1,61,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(122,1,61,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(123,1,62,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(124,1,62,'Cobalt',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(125,1,63,'4Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(126,1,63,'8Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(127,1,64,'Red',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(128,1,64,'Navy',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(129,1,65,'6Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(130,1,65,'10Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(131,1,66,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(132,1,66,'Light Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(133,1,67,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(134,1,67,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(135,1,68,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(136,1,68,'Graphite',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(137,1,69,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(138,1,69,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(139,1,70,'Oatmeal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(140,1,70,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(141,1,71,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(142,1,71,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(143,1,72,'Cream',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(144,1,72,'Dusty Rose',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(145,1,73,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(146,1,73,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(147,1,74,'Stone',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(148,1,74,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(149,1,75,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(150,1,75,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(151,1,76,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(152,1,76,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(153,1,77,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(154,1,77,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(155,1,78,'Ivory',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(156,1,78,'Powder Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(157,1,79,'XS',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(158,1,79,'S',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(159,1,80,'Olive',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(160,1,80,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(161,1,81,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(162,1,81,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(163,1,82,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(164,1,82,'Sky Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(165,1,83,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(166,1,83,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(167,1,84,'Natural',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(168,1,84,'Sage',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(169,1,85,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(170,1,85,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(171,1,86,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(172,1,86,'Heather Grey',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(173,1,87,'30',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(174,1,87,'32',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(175,1,88,'Khaki',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(176,1,88,'Olive',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(177,1,89,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(178,1,89,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(179,1,90,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(180,1,90,'Taupe',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(181,1,91,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(182,1,91,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(183,1,92,'Floral Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(184,1,92,'Terracotta',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(185,1,93,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(186,1,93,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(187,1,94,'Emerald',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(188,1,94,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(189,1,95,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(190,1,95,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(191,1,96,'Indigo',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(192,1,96,'Washed Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(193,1,97,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(194,1,97,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(195,1,98,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(196,1,98,'Army Green',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(197,1,99,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(198,1,99,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(199,1,100,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(200,1,100,'Plum',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(201,1,101,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(202,1,101,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(203,1,102,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(204,1,102,'Cobalt',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(205,1,103,'4Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(206,1,103,'8Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(207,1,104,'Red',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(208,1,104,'Navy',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(209,1,105,'6Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(210,1,105,'10Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(211,1,106,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(212,1,106,'Light Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(213,1,107,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(214,1,107,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(215,1,108,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(216,1,108,'Graphite',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(217,1,109,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(218,1,109,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(219,1,110,'Oatmeal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(220,1,110,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(221,1,111,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(222,1,111,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(223,1,112,'Cream',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(224,1,112,'Dusty Rose',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(225,1,113,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(226,1,113,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(227,1,114,'Stone',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(228,1,114,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(229,1,115,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(230,1,115,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(231,1,116,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(232,1,116,'Camel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(233,1,117,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(234,1,117,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(235,1,118,'Ivory',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(236,1,118,'Powder Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(237,1,119,'XS',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(238,1,119,'S',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(239,1,120,'Olive',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(240,1,120,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(241,1,121,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(242,1,121,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(243,1,122,'White',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(244,1,122,'Sky Blue',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(245,1,123,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(246,1,123,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(247,1,124,'Natural',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(248,1,124,'Sage',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(249,1,125,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(250,1,125,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(251,1,126,'Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(252,1,126,'Heather Grey',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(253,1,127,'30',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(254,1,127,'32',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(255,1,128,'Khaki',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(256,1,128,'Olive',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(257,1,129,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(258,1,129,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(259,1,130,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(260,1,130,'Taupe',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(261,1,131,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(262,1,131,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(263,1,132,'Floral Navy',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(264,1,132,'Terracotta',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(265,1,133,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(266,1,133,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(267,1,134,'Emerald',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(268,1,134,'Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(269,1,135,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(270,1,135,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(271,1,136,'Indigo',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(272,1,136,'Washed Black',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(273,1,137,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(274,1,137,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(275,1,138,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(276,1,138,'Army Green',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(277,1,139,'S',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(278,1,139,'M',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(279,1,140,'Charcoal',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(280,1,140,'Plum',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(281,1,141,'M',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(282,1,141,'L',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(283,1,142,'Black',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(284,1,142,'Cobalt',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(285,1,143,'4Y',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(286,1,143,'8Y',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(287,1,144,'Red',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(288,1,144,'Navy',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(289,1,145,'6Y',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(290,1,145,'10Y',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(291,1,146,'White',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(292,1,146,'Light Blue',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(293,1,147,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(294,1,147,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(295,1,148,'Navy',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(296,1,148,'Graphite',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(297,1,149,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(298,1,149,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(299,1,150,'Oatmeal',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(300,1,150,'Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(301,1,151,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(302,1,151,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(303,1,152,'Cream',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(304,1,152,'Dusty Rose',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(305,1,153,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(306,1,153,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(307,1,154,'Stone',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(308,1,154,'Camel',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(309,1,155,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(310,1,155,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(311,1,156,'Charcoal',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(312,1,156,'Camel',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(313,1,157,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(314,1,157,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(315,1,158,'Ivory',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(316,1,158,'Powder Blue',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(317,1,159,'XS',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(318,1,159,'S',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(319,1,160,'Olive',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(320,1,160,'Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(321,1,161,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(322,1,161,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(323,1,162,'White',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(324,1,162,'Sky Blue',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(329,1,165,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(330,1,165,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(331,1,166,'Navy',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(332,1,166,'Heather Grey',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(333,1,167,'30',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(334,1,167,'32',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(335,1,168,'Khaki',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(336,1,168,'Olive',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(337,1,169,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(338,1,169,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(339,1,170,'Black',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(340,1,170,'Taupe',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(341,1,171,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(342,1,171,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(343,1,172,'Floral Navy',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(344,1,172,'Terracotta',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(345,1,173,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(346,1,173,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(347,1,174,'Emerald',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(348,1,174,'Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(349,1,175,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(350,1,175,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(351,1,176,'Indigo',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(352,1,176,'Washed Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(353,1,177,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(354,1,177,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(355,1,178,'Black',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(356,1,178,'Army Green',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(357,1,179,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(358,1,179,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(359,1,180,'Charcoal',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(360,1,180,'Plum',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(361,1,181,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(362,1,181,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(363,1,182,'Black',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(364,1,182,'Cobalt',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(365,1,183,'4Y',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(366,1,183,'8Y',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(367,1,184,'Red',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(368,1,184,'Navy',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(369,1,185,'6Y',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(370,1,185,'10Y',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(371,1,186,'White',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(372,1,186,'Light Blue',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(373,1,187,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(374,1,187,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(375,1,188,'Navy',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(376,1,188,'Graphite',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(377,1,189,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(378,1,189,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(379,1,190,'Oatmeal',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(380,1,190,'Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(381,1,191,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(382,1,191,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(383,1,192,'Cream',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(384,1,192,'Dusty Rose',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(385,1,193,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(386,1,193,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(387,1,194,'Stone',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(388,1,194,'Camel',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(389,1,195,'M',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(390,1,195,'L',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(391,1,196,'Charcoal',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(392,1,196,'Camel',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(393,1,197,'S',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(394,1,197,'M',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(395,1,198,'Ivory',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(396,1,198,'Powder Blue',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(397,1,199,'XS',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(398,1,199,'S',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(399,1,200,'Olive',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(400,1,200,'Black',1,'2026-07-17 09:06:38','2026-07-17 09:06:38');
/*!40000 ALTER TABLE `commerce_product_option_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_product_options`
--

DROP TABLE IF EXISTS `commerce_product_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_product_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_options_product_id_code_unique` (`product_id`,`code`),
  KEY `commerce_product_options_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `commerce_product_options_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_options_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_product_options`
--

LOCK TABLES `commerce_product_options` WRITE;
/*!40000 ALTER TABLE `commerce_product_options` DISABLE KEYS */;
INSERT INTO `commerce_product_options` VALUES (1,1,1,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,1,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,2,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,2,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,3,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,3,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,4,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,4,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,5,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,5,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,1,6,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,1,6,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,1,7,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,1,7,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,1,8,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,1,8,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,1,9,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,1,9,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,1,10,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,1,10,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,1,11,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,1,11,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,1,12,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,1,12,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,1,13,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,1,13,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,1,14,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,1,14,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,1,15,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,1,15,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,1,16,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,1,16,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,1,17,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,1,17,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,1,18,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,1,18,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,1,19,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,1,19,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,1,20,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,1,20,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,1,21,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,1,21,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,1,22,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,1,22,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,1,23,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,1,23,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,1,24,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,1,24,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,1,25,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,1,25,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,1,26,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,1,26,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,1,27,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,1,27,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,1,28,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,1,28,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,1,29,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,1,29,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,1,30,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,1,30,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(61,1,31,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(62,1,31,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(63,1,32,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(64,1,32,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(65,1,33,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(66,1,33,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(67,1,34,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(68,1,34,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(69,1,35,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(70,1,35,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(71,1,36,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(72,1,36,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(73,1,37,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(74,1,37,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(75,1,38,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(76,1,38,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(77,1,39,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(78,1,39,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(79,1,40,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(80,1,40,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(81,1,41,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(82,1,41,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(83,1,42,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(84,1,42,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(85,1,43,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(86,1,43,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(87,1,44,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(88,1,44,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(89,1,45,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(90,1,45,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(91,1,46,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(92,1,46,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(93,1,47,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(94,1,47,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(95,1,48,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(96,1,48,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(97,1,49,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(98,1,49,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(99,1,50,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(100,1,50,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(101,1,51,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(102,1,51,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(103,1,52,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(104,1,52,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(105,1,53,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(106,1,53,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(107,1,54,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(108,1,54,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(109,1,55,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(110,1,55,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(111,1,56,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(112,1,56,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(113,1,57,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(114,1,57,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(115,1,58,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(116,1,58,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(117,1,59,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(118,1,59,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(119,1,60,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(120,1,60,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(121,1,61,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(122,1,61,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(123,1,62,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(124,1,62,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(125,1,63,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(126,1,63,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(127,1,64,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(128,1,64,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(129,1,65,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(130,1,65,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(131,1,66,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(132,1,66,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(133,1,67,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(134,1,67,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(135,1,68,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(136,1,68,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(137,1,69,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(138,1,69,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(139,1,70,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(140,1,70,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(141,1,71,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(142,1,71,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(143,1,72,'Size','size',0,'2026-07-17 09:06:37','2026-07-17 09:06:38'),(144,1,72,'Color','color',1,'2026-07-17 09:06:37','2026-07-17 09:06:38'),(145,1,73,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(146,1,73,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(147,1,74,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(148,1,74,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(149,1,75,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(150,1,75,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(151,1,76,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(152,1,76,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(153,1,77,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(154,1,77,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(155,1,78,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(156,1,78,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(157,1,79,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(158,1,79,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(159,1,80,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(160,1,80,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(161,1,81,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(162,1,81,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(165,1,83,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(166,1,83,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(167,1,84,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(168,1,84,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(169,1,85,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(170,1,85,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(171,1,86,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(172,1,86,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(173,1,87,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(174,1,87,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(175,1,88,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(176,1,88,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(177,1,89,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(178,1,89,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(179,1,90,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(180,1,90,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(181,1,91,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(182,1,91,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(183,1,92,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(184,1,92,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(185,1,93,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(186,1,93,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(187,1,94,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(188,1,94,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(189,1,95,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(190,1,95,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(191,1,96,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(192,1,96,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(193,1,97,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(194,1,97,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(195,1,98,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(196,1,98,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(197,1,99,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(198,1,99,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(199,1,100,'Size','size',0,'2026-07-17 09:06:38','2026-07-17 09:06:38'),(200,1,100,'Color','color',1,'2026-07-17 09:06:38','2026-07-17 09:06:38');
/*!40000 ALTER TABLE `commerce_product_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_product_variants`
--

DROP TABLE IF EXISTS `commerce_product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_retailer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attributes` json DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `stock_quantity` int unsigned NOT NULL DEFAULT '0',
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `package_dimensions` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_variants_workspace_id_sku_unique` (`workspace_id`,`sku`),
  UNIQUE KEY `commerce_product_variants_workspace_id_meta_retailer_id_unique` (`workspace_id`,`meta_retailer_id`),
  KEY `commerce_product_variants_product_id_foreign` (`product_id`),
  KEY `commerce_product_variants_media_id_foreign` (`media_id`),
  KEY `commerce_product_variants_status_index` (`status`),
  CONSTRAINT `commerce_product_variants_media_id_foreign` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_variants_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=401 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_product_variants`
--

LOCK TABLES `commerce_product_variants` WRITE;
/*!40000 ALTER TABLE `commerce_product_variants` DISABLE KEYS */;
INSERT INTO `commerce_product_variants` VALUES (1,1,1,1,'DEMO-001-S-WHITE','demo-001-s-white','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',34.00,46.00,12,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,1,20,'DEMO-001-S-SKY-BLUE','demo-001-s-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',34.00,46.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,1,1,'DEMO-001-M-WHITE','demo-001-m-white','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',36.00,48.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,1,20,'DEMO-001-M-SKY-BLUE','demo-001-m-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',36.00,48.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,2,8,'DEMO-002-S-NATURAL','demo-002-s-natural','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',39.00,51.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,2,27,'DEMO-002-S-SAGE','demo-002-s-sage','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',39.00,51.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,2,8,'DEMO-002-M-NATURAL','demo-002-m-natural','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',41.00,53.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,2,27,'DEMO-002-M-SAGE','demo-002-m-sage','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',41.00,53.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,3,15,'DEMO-003-M-NAVY','demo-003-m-navy','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',29.00,41.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,3,34,'DEMO-003-M-HEATHER-GREY','demo-003-m-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',29.00,41.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,1,3,15,'DEMO-003-L-NAVY','demo-003-l-navy','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',31.00,43.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,1,3,34,'DEMO-003-L-HEATHER-GREY','demo-003-l-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',31.00,43.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,1,4,22,'DEMO-004-30-KHAKI','demo-004-30-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',44.00,56.00,15,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,1,4,41,'DEMO-004-30-OLIVE','demo-004-30-olive','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',44.00,56.00,16,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,1,4,22,'DEMO-004-32-KHAKI','demo-004-32-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',46.00,58.00,16,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,1,4,41,'DEMO-004-32-OLIVE','demo-004-32-olive','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',46.00,58.00,17,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,1,5,29,'DEMO-005-S-BLACK','demo-005-s-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',52.00,64.00,16,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,1,5,48,'DEMO-005-S-TAUPE','demo-005-s-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',52.00,64.00,17,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,1,5,29,'DEMO-005-M-BLACK','demo-005-m-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',54.00,66.00,17,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,1,5,48,'DEMO-005-M-TAUPE','demo-005-m-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',54.00,66.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,1,6,36,'DEMO-006-S-FLORAL-NAVY','demo-006-s-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',58.00,70.00,17,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,1,6,55,'DEMO-006-S-TERRACOTTA','demo-006-s-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',58.00,70.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,1,6,36,'DEMO-006-M-FLORAL-NAVY','demo-006-m-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',60.00,72.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,1,6,55,'DEMO-006-M-TERRACOTTA','demo-006-m-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',60.00,72.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,1,7,43,'DEMO-007-S-EMERALD','demo-007-s-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',54.00,66.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,1,7,2,'DEMO-007-S-BLACK','demo-007-s-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',54.00,66.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,1,7,43,'DEMO-007-M-EMERALD','demo-007-m-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',56.00,68.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,1,7,2,'DEMO-007-M-BLACK','demo-007-m-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',56.00,68.00,20,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,1,8,50,'DEMO-008-M-INDIGO','demo-008-m-indigo','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',69.00,81.00,19,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,1,8,9,'DEMO-008-M-WASHED-BLACK','demo-008-m-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',69.00,81.00,20,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,1,8,50,'DEMO-008-L-INDIGO','demo-008-l-indigo','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',71.00,83.00,20,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,1,8,9,'DEMO-008-L-WASHED-BLACK','demo-008-l-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',71.00,83.00,21,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,1,9,57,'DEMO-009-M-BLACK','demo-009-m-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',76.00,88.00,20,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,1,9,16,'DEMO-009-M-ARMY-GREEN','demo-009-m-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',76.00,88.00,21,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,1,9,57,'DEMO-009-L-BLACK','demo-009-l-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',78.00,90.00,21,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,1,9,16,'DEMO-009-L-ARMY-GREEN','demo-009-l-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',78.00,90.00,22,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,1,10,4,'DEMO-010-S-CHARCOAL','demo-010-s-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',38.00,50.00,21,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,1,10,23,'DEMO-010-S-PLUM','demo-010-s-plum','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',38.00,50.00,22,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,1,10,4,'DEMO-010-M-CHARCOAL','demo-010-m-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',40.00,52.00,22,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,1,10,23,'DEMO-010-M-PLUM','demo-010-m-plum','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',40.00,52.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,1,11,11,'DEMO-011-M-BLACK','demo-011-m-black','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',32.00,44.00,22,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,1,11,30,'DEMO-011-M-COBALT','demo-011-m-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',32.00,44.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,1,11,11,'DEMO-011-L-BLACK','demo-011-l-black','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',34.00,46.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,1,11,30,'DEMO-011-L-COBALT','demo-011-l-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',34.00,46.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,1,12,18,'DEMO-012-4Y-RED','demo-012-4y-red','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',31.00,43.00,23,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,1,12,37,'DEMO-012-4Y-NAVY','demo-012-4y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',31.00,43.00,24,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,1,12,18,'DEMO-012-8Y-RED','demo-012-8y-red','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',33.00,45.00,24,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,1,12,37,'DEMO-012-8Y-NAVY','demo-012-8y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',33.00,45.00,25,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,1,13,25,'DEMO-013-6Y-WHITE','demo-013-6y-white','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',24.00,36.00,24,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,1,13,44,'DEMO-013-6Y-LIGHT-BLUE','demo-013-6y-light-blue','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',24.00,36.00,25,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,1,13,25,'DEMO-013-10Y-WHITE','demo-013-10y-white','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',26.00,38.00,25,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,1,13,44,'DEMO-013-10Y-LIGHT-BLUE','demo-013-10y-light-blue','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',26.00,38.00,26,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,1,14,32,'DEMO-014-M-NAVY','demo-014-m-navy','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',64.00,76.00,25,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,1,14,51,'DEMO-014-M-GRAPHITE','demo-014-m-graphite','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',64.00,76.00,26,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,1,14,32,'DEMO-014-L-NAVY','demo-014-l-navy','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',66.00,78.00,26,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,1,14,51,'DEMO-014-L-GRAPHITE','demo-014-l-graphite','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',66.00,78.00,27,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,1,15,39,'DEMO-015-M-OATMEAL','demo-015-m-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',48.00,60.00,26,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,1,15,58,'DEMO-015-M-BLACK','demo-015-m-black','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',48.00,60.00,27,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,1,15,39,'DEMO-015-L-OATMEAL','demo-015-l-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',50.00,62.00,27,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,1,15,58,'DEMO-015-L-BLACK','demo-015-l-black','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',50.00,62.00,28,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(61,1,16,46,'DEMO-016-S-CREAM','demo-016-s-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',57.00,69.00,27,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(62,1,16,5,'DEMO-016-S-DUSTY-ROSE','demo-016-s-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',57.00,69.00,28,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(63,1,16,46,'DEMO-016-M-CREAM','demo-016-m-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',59.00,71.00,28,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(64,1,16,5,'DEMO-016-M-DUSTY-ROSE','demo-016-m-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',59.00,71.00,29,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(65,1,17,53,'DEMO-017-S-STONE','demo-017-s-stone','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',98.00,110.00,28,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(66,1,17,12,'DEMO-017-S-CAMEL','demo-017-s-camel','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',98.00,110.00,29,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(67,1,17,53,'DEMO-017-M-STONE','demo-017-m-stone','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',100.00,112.00,29,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(68,1,17,12,'DEMO-017-M-CAMEL','demo-017-m-camel','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',100.00,112.00,30,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(69,1,18,60,'DEMO-018-M-CHARCOAL','demo-018-m-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',112.00,124.00,29,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(70,1,18,19,'DEMO-018-M-CAMEL','demo-018-m-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',112.00,124.00,30,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(71,1,18,60,'DEMO-018-L-CHARCOAL','demo-018-l-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',114.00,126.00,30,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(72,1,18,19,'DEMO-018-L-CAMEL','demo-018-l-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',114.00,126.00,31,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(73,1,19,7,'DEMO-019-S-IVORY','demo-019-s-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',42.00,54.00,30,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(74,1,19,26,'DEMO-019-S-POWDER-BLUE','demo-019-s-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',42.00,54.00,31,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(75,1,19,7,'DEMO-019-M-IVORY','demo-019-m-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',44.00,56.00,31,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(76,1,19,26,'DEMO-019-M-POWDER-BLUE','demo-019-m-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',44.00,56.00,32,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(77,1,20,14,'DEMO-020-XS-OLIVE','demo-020-xs-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',49.00,61.00,31,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(78,1,20,33,'DEMO-020-XS-BLACK','demo-020-xs-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',49.00,61.00,32,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(79,1,20,14,'DEMO-020-S-OLIVE','demo-020-s-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',51.00,63.00,32,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(80,1,20,33,'DEMO-020-S-BLACK','demo-020-s-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',51.00,63.00,33,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(81,1,21,21,'DEMO-021-S-WHITE','demo-021-s-white','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',38.00,50.00,32,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(82,1,21,40,'DEMO-021-S-SKY-BLUE','demo-021-s-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',38.00,50.00,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(83,1,21,21,'DEMO-021-M-WHITE','demo-021-m-white','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',40.00,52.00,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(84,1,21,40,'DEMO-021-M-SKY-BLUE','demo-021-m-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',40.00,52.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(85,1,22,28,'DEMO-022-S-NATURAL','demo-022-s-natural','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',43.00,55.00,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(86,1,22,47,'DEMO-022-S-SAGE','demo-022-s-sage','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',43.00,55.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(87,1,22,28,'DEMO-022-M-NATURAL','demo-022-m-natural','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',45.00,57.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(88,1,22,47,'DEMO-022-M-SAGE','demo-022-m-sage','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',45.00,57.00,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(89,1,23,35,'DEMO-023-M-NAVY','demo-023-m-navy','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',33.00,45.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(90,1,23,54,'DEMO-023-M-HEATHER-GREY','demo-023-m-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',33.00,45.00,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(91,1,23,35,'DEMO-023-L-NAVY','demo-023-l-navy','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',35.00,47.00,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(92,1,23,54,'DEMO-023-L-HEATHER-GREY','demo-023-l-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',35.00,47.00,36,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(93,1,24,42,'DEMO-024-30-KHAKI','demo-024-30-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',48.00,60.00,35,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(94,1,24,1,'DEMO-024-30-OLIVE','demo-024-30-olive','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',48.00,60.00,36,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(95,1,24,42,'DEMO-024-32-KHAKI','demo-024-32-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',50.00,62.00,36,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(96,1,24,1,'DEMO-024-32-OLIVE','demo-024-32-olive','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',50.00,62.00,37,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(97,1,25,49,'DEMO-025-S-BLACK','demo-025-s-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',56.00,68.00,36,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(98,1,25,8,'DEMO-025-S-TAUPE','demo-025-s-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',56.00,68.00,37,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(99,1,25,49,'DEMO-025-M-BLACK','demo-025-m-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',58.00,70.00,37,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(100,1,25,8,'DEMO-025-M-TAUPE','demo-025-m-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',58.00,70.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(101,1,26,56,'DEMO-026-S-FLORAL-NAVY','demo-026-s-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',62.00,74.00,37,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(102,1,26,15,'DEMO-026-S-TERRACOTTA','demo-026-s-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',62.00,74.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(103,1,26,56,'DEMO-026-M-FLORAL-NAVY','demo-026-m-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',64.00,76.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(104,1,26,15,'DEMO-026-M-TERRACOTTA','demo-026-m-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',64.00,76.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(105,1,27,3,'DEMO-027-S-EMERALD','demo-027-s-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',58.00,70.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(106,1,27,22,'DEMO-027-S-BLACK','demo-027-s-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',58.00,70.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(107,1,27,3,'DEMO-027-M-EMERALD','demo-027-m-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',60.00,72.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(108,1,27,22,'DEMO-027-M-BLACK','demo-027-m-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',60.00,72.00,40,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(109,1,28,10,'DEMO-028-M-INDIGO','demo-028-m-indigo','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',73.00,85.00,39,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(110,1,28,29,'DEMO-028-M-WASHED-BLACK','demo-028-m-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',73.00,85.00,40,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(111,1,28,10,'DEMO-028-L-INDIGO','demo-028-l-indigo','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',75.00,87.00,40,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(112,1,28,29,'DEMO-028-L-WASHED-BLACK','demo-028-l-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',75.00,87.00,12,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(113,1,29,17,'DEMO-029-M-BLACK','demo-029-m-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',80.00,92.00,40,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(114,1,29,36,'DEMO-029-M-ARMY-GREEN','demo-029-m-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',80.00,92.00,12,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(115,1,29,17,'DEMO-029-L-BLACK','demo-029-l-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',82.00,94.00,12,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(116,1,29,36,'DEMO-029-L-ARMY-GREEN','demo-029-l-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',82.00,94.00,13,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(117,1,30,24,'DEMO-030-S-CHARCOAL','demo-030-s-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',42.00,54.00,12,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(118,1,30,43,'DEMO-030-S-PLUM','demo-030-s-plum','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',42.00,54.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(119,1,30,24,'DEMO-030-M-CHARCOAL','demo-030-m-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',44.00,56.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(120,1,30,43,'DEMO-030-M-PLUM','demo-030-m-plum','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',44.00,56.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(121,1,31,31,'DEMO-031-M-BLACK','demo-031-m-black','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',36.00,48.00,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(122,1,31,50,'DEMO-031-M-COBALT','demo-031-m-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',36.00,48.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(123,1,31,31,'DEMO-031-L-BLACK','demo-031-l-black','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',38.00,50.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(124,1,31,50,'DEMO-031-L-COBALT','demo-031-l-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',38.00,50.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(125,1,32,38,'DEMO-032-4Y-RED','demo-032-4y-red','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',35.00,47.00,14,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(126,1,32,57,'DEMO-032-4Y-NAVY','demo-032-4y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',35.00,47.00,15,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(127,1,32,38,'DEMO-032-8Y-RED','demo-032-8y-red','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',37.00,49.00,15,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(128,1,32,57,'DEMO-032-8Y-NAVY','demo-032-8y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',37.00,49.00,16,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(129,1,33,45,'DEMO-033-6Y-WHITE','demo-033-6y-white','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',28.00,40.00,15,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(130,1,33,4,'DEMO-033-6Y-LIGHT-BLUE','demo-033-6y-light-blue','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',28.00,40.00,16,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(131,1,33,45,'DEMO-033-10Y-WHITE','demo-033-10y-white','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',30.00,42.00,16,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(132,1,33,4,'DEMO-033-10Y-LIGHT-BLUE','demo-033-10y-light-blue','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',30.00,42.00,17,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(133,1,34,52,'DEMO-034-M-NAVY','demo-034-m-navy','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',68.00,80.00,16,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(134,1,34,11,'DEMO-034-M-GRAPHITE','demo-034-m-graphite','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',68.00,80.00,17,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(135,1,34,52,'DEMO-034-L-NAVY','demo-034-l-navy','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',70.00,82.00,17,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(136,1,34,11,'DEMO-034-L-GRAPHITE','demo-034-l-graphite','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',70.00,82.00,18,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(137,1,35,59,'DEMO-035-M-OATMEAL','demo-035-m-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',52.00,64.00,17,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(138,1,35,18,'DEMO-035-M-BLACK','demo-035-m-black','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',52.00,64.00,18,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(139,1,35,59,'DEMO-035-L-OATMEAL','demo-035-l-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',54.00,66.00,18,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(140,1,35,18,'DEMO-035-L-BLACK','demo-035-l-black','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',54.00,66.00,19,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(141,1,36,6,'DEMO-036-S-CREAM','demo-036-s-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',61.00,73.00,18,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(142,1,36,25,'DEMO-036-S-DUSTY-ROSE','demo-036-s-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',61.00,73.00,19,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(143,1,36,6,'DEMO-036-M-CREAM','demo-036-m-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',63.00,75.00,19,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(144,1,36,25,'DEMO-036-M-DUSTY-ROSE','demo-036-m-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',63.00,75.00,20,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(145,1,37,13,'DEMO-037-S-STONE','demo-037-s-stone','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',102.00,114.00,19,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(146,1,37,32,'DEMO-037-S-CAMEL','demo-037-s-camel','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',102.00,114.00,20,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(147,1,37,13,'DEMO-037-M-STONE','demo-037-m-stone','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',104.00,116.00,20,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(148,1,37,32,'DEMO-037-M-CAMEL','demo-037-m-camel','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',104.00,116.00,21,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(149,1,38,20,'DEMO-038-M-CHARCOAL','demo-038-m-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',116.00,128.00,20,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(150,1,38,39,'DEMO-038-M-CAMEL','demo-038-m-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',116.00,128.00,21,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(151,1,38,20,'DEMO-038-L-CHARCOAL','demo-038-l-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',118.00,130.00,21,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(152,1,38,39,'DEMO-038-L-CAMEL','demo-038-l-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',118.00,130.00,22,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(153,1,39,27,'DEMO-039-S-IVORY','demo-039-s-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',46.00,58.00,21,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(154,1,39,46,'DEMO-039-S-POWDER-BLUE','demo-039-s-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',46.00,58.00,22,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(155,1,39,27,'DEMO-039-M-IVORY','demo-039-m-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',48.00,60.00,22,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(156,1,39,46,'DEMO-039-M-POWDER-BLUE','demo-039-m-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',48.00,60.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(157,1,40,34,'DEMO-040-XS-OLIVE','demo-040-xs-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',53.00,65.00,22,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(158,1,40,53,'DEMO-040-XS-BLACK','demo-040-xs-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',53.00,65.00,23,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(159,1,40,34,'DEMO-040-S-OLIVE','demo-040-s-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',55.00,67.00,23,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(160,1,40,53,'DEMO-040-S-BLACK','demo-040-s-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',55.00,67.00,24,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(161,1,41,41,'DEMO-041-S-WHITE','demo-041-s-white','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',41.50,53.50,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(162,1,41,60,'DEMO-041-S-SKY-BLUE','demo-041-s-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',41.50,53.50,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(163,1,41,41,'DEMO-041-M-WHITE','demo-041-m-white','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',43.50,55.50,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(164,1,41,60,'DEMO-041-M-SKY-BLUE','demo-041-m-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',43.50,55.50,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(165,1,42,48,'DEMO-042-S-NATURAL','demo-042-s-natural','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',46.50,58.50,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(166,1,42,7,'DEMO-042-S-SAGE','demo-042-s-sage','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',46.50,58.50,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(167,1,42,48,'DEMO-042-M-NATURAL','demo-042-m-natural','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',48.50,60.50,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(168,1,42,7,'DEMO-042-M-SAGE','demo-042-m-sage','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',48.50,60.50,26,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(169,1,43,55,'DEMO-043-M-NAVY','demo-043-m-navy','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',36.50,48.50,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(170,1,43,14,'DEMO-043-M-HEATHER-GREY','demo-043-m-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',36.50,48.50,26,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(171,1,43,55,'DEMO-043-L-NAVY','demo-043-l-navy','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',38.50,50.50,26,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(172,1,43,14,'DEMO-043-L-HEATHER-GREY','demo-043-l-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',38.50,50.50,27,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(173,1,44,2,'DEMO-044-30-KHAKI','demo-044-30-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',51.50,63.50,26,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(174,1,44,21,'DEMO-044-30-OLIVE','demo-044-30-olive','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',51.50,63.50,27,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(175,1,44,2,'DEMO-044-32-KHAKI','demo-044-32-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',53.50,65.50,27,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(176,1,44,21,'DEMO-044-32-OLIVE','demo-044-32-olive','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',53.50,65.50,28,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(177,1,45,9,'DEMO-045-S-BLACK','demo-045-s-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',59.50,71.50,27,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(178,1,45,28,'DEMO-045-S-TAUPE','demo-045-s-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',59.50,71.50,28,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(179,1,45,9,'DEMO-045-M-BLACK','demo-045-m-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',61.50,73.50,28,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(180,1,45,28,'DEMO-045-M-TAUPE','demo-045-m-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',61.50,73.50,29,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(181,1,46,16,'DEMO-046-S-FLORAL-NAVY','demo-046-s-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',65.50,77.50,28,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(182,1,46,35,'DEMO-046-S-TERRACOTTA','demo-046-s-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',65.50,77.50,29,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(183,1,46,16,'DEMO-046-M-FLORAL-NAVY','demo-046-m-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',67.50,79.50,29,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(184,1,46,35,'DEMO-046-M-TERRACOTTA','demo-046-m-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',67.50,79.50,30,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(185,1,47,23,'DEMO-047-S-EMERALD','demo-047-s-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',61.50,73.50,29,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(186,1,47,42,'DEMO-047-S-BLACK','demo-047-s-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',61.50,73.50,30,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(187,1,47,23,'DEMO-047-M-EMERALD','demo-047-m-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',63.50,75.50,30,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(188,1,47,42,'DEMO-047-M-BLACK','demo-047-m-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',63.50,75.50,31,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(189,1,48,30,'DEMO-048-M-INDIGO','demo-048-m-indigo','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',76.50,88.50,30,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(190,1,48,49,'DEMO-048-M-WASHED-BLACK','demo-048-m-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',76.50,88.50,31,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(191,1,48,30,'DEMO-048-L-INDIGO','demo-048-l-indigo','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',78.50,90.50,31,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(192,1,48,49,'DEMO-048-L-WASHED-BLACK','demo-048-l-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',78.50,90.50,32,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(193,1,49,37,'DEMO-049-M-BLACK','demo-049-m-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',83.50,95.50,31,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(194,1,49,56,'DEMO-049-M-ARMY-GREEN','demo-049-m-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',83.50,95.50,32,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(195,1,49,37,'DEMO-049-L-BLACK','demo-049-l-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',85.50,97.50,32,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(196,1,49,56,'DEMO-049-L-ARMY-GREEN','demo-049-l-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',85.50,97.50,33,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(197,1,50,44,'DEMO-050-S-CHARCOAL','demo-050-s-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',45.50,57.50,32,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(198,1,50,3,'DEMO-050-S-PLUM','demo-050-s-plum','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',45.50,57.50,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(199,1,50,44,'DEMO-050-M-CHARCOAL','demo-050-m-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',47.50,59.50,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(200,1,50,3,'DEMO-050-M-PLUM','demo-050-m-plum','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',47.50,59.50,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(201,1,51,51,'DEMO-051-M-BLACK','demo-051-m-black','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',39.50,51.50,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(202,1,51,10,'DEMO-051-M-COBALT','demo-051-m-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',39.50,51.50,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(203,1,51,51,'DEMO-051-L-BLACK','demo-051-l-black','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',41.50,53.50,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(204,1,51,10,'DEMO-051-L-COBALT','demo-051-l-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',41.50,53.50,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(205,1,52,58,'DEMO-052-4Y-RED','demo-052-4y-red','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',38.50,50.50,34,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(206,1,52,17,'DEMO-052-4Y-NAVY','demo-052-4y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',38.50,50.50,35,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(207,1,52,58,'DEMO-052-8Y-RED','demo-052-8y-red','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',40.50,52.50,35,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(208,1,52,17,'DEMO-052-8Y-NAVY','demo-052-8y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',40.50,52.50,36,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(209,1,53,5,'DEMO-053-6Y-WHITE','demo-053-6y-white','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',31.50,43.50,35,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(210,1,53,24,'DEMO-053-6Y-LIGHT-BLUE','demo-053-6y-light-blue','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',31.50,43.50,36,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(211,1,53,5,'DEMO-053-10Y-WHITE','demo-053-10y-white','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',33.50,45.50,36,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(212,1,53,24,'DEMO-053-10Y-LIGHT-BLUE','demo-053-10y-light-blue','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',33.50,45.50,37,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(213,1,54,12,'DEMO-054-M-NAVY','demo-054-m-navy','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',71.50,83.50,36,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(214,1,54,31,'DEMO-054-M-GRAPHITE','demo-054-m-graphite','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',71.50,83.50,37,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(215,1,54,12,'DEMO-054-L-NAVY','demo-054-l-navy','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',73.50,85.50,37,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(216,1,54,31,'DEMO-054-L-GRAPHITE','demo-054-l-graphite','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',73.50,85.50,38,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(217,1,55,19,'DEMO-055-M-OATMEAL','demo-055-m-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',55.50,67.50,37,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(218,1,55,38,'DEMO-055-M-BLACK','demo-055-m-black','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',55.50,67.50,38,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(219,1,55,19,'DEMO-055-L-OATMEAL','demo-055-l-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',57.50,69.50,38,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(220,1,55,38,'DEMO-055-L-BLACK','demo-055-l-black','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',57.50,69.50,39,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(221,1,56,26,'DEMO-056-S-CREAM','demo-056-s-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',64.50,76.50,38,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(222,1,56,45,'DEMO-056-S-DUSTY-ROSE','demo-056-s-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',64.50,76.50,39,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(223,1,56,26,'DEMO-056-M-CREAM','demo-056-m-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',66.50,78.50,39,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(224,1,56,45,'DEMO-056-M-DUSTY-ROSE','demo-056-m-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',66.50,78.50,40,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(225,1,57,33,'DEMO-057-S-STONE','demo-057-s-stone','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',105.50,117.50,39,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(226,1,57,52,'DEMO-057-S-CAMEL','demo-057-s-camel','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',105.50,117.50,40,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(227,1,57,33,'DEMO-057-M-STONE','demo-057-m-stone','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',107.50,119.50,40,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(228,1,57,52,'DEMO-057-M-CAMEL','demo-057-m-camel','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',107.50,119.50,12,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(229,1,58,40,'DEMO-058-M-CHARCOAL','demo-058-m-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',119.50,131.50,40,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(230,1,58,59,'DEMO-058-M-CAMEL','demo-058-m-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',119.50,131.50,12,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(231,1,58,40,'DEMO-058-L-CHARCOAL','demo-058-l-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',121.50,133.50,12,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(232,1,58,59,'DEMO-058-L-CAMEL','demo-058-l-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',121.50,133.50,13,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(233,1,59,47,'DEMO-059-S-IVORY','demo-059-s-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',49.50,61.50,12,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(234,1,59,6,'DEMO-059-S-POWDER-BLUE','demo-059-s-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',49.50,61.50,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(235,1,59,47,'DEMO-059-M-IVORY','demo-059-m-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',51.50,63.50,13,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(236,1,59,6,'DEMO-059-M-POWDER-BLUE','demo-059-m-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',51.50,63.50,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(237,1,60,54,'DEMO-060-XS-OLIVE','demo-060-xs-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',56.50,68.50,13,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(238,1,60,13,'DEMO-060-XS-BLACK','demo-060-xs-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',56.50,68.50,14,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(239,1,60,54,'DEMO-060-S-OLIVE','demo-060-s-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',58.50,70.50,14,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(240,1,60,13,'DEMO-060-S-BLACK','demo-060-s-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',58.50,70.50,15,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(241,1,61,1,'DEMO-061-S-WHITE','demo-061-s-white','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',44.00,56.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(242,1,61,20,'DEMO-061-S-SKY-BLUE','demo-061-s-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',44.00,56.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(243,1,61,1,'DEMO-061-M-WHITE','demo-061-m-white','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',46.00,58.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(244,1,61,20,'DEMO-061-M-SKY-BLUE','demo-061-m-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',46.00,58.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(245,1,62,8,'DEMO-062-S-NATURAL','demo-062-s-natural','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',49.00,61.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(246,1,62,27,'DEMO-062-S-SAGE','demo-062-s-sage','{\"fit\": \"Relaxed fit\", \"size\": \"S\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',49.00,61.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(247,1,62,8,'DEMO-062-M-NATURAL','demo-062-m-natural','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Natural\", \"material\": \"Garment-washed linen\"}',51.00,63.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(248,1,62,27,'DEMO-062-M-SAGE','demo-062-m-sage','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Sage\", \"material\": \"Garment-washed linen\"}',51.00,63.00,17,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(249,1,63,15,'DEMO-063-M-NAVY','demo-063-m-navy','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',39.00,51.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(250,1,63,34,'DEMO-063-M-HEATHER-GREY','demo-063-m-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',39.00,51.00,17,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(251,1,63,15,'DEMO-063-L-NAVY','demo-063-l-navy','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',41.00,53.00,17,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(252,1,63,34,'DEMO-063-L-HEATHER-GREY','demo-063-l-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',41.00,53.00,18,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(253,1,64,22,'DEMO-064-30-KHAKI','demo-064-30-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',54.00,66.00,17,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(254,1,64,41,'DEMO-064-30-OLIVE','demo-064-30-olive','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',54.00,66.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(255,1,64,22,'DEMO-064-32-KHAKI','demo-064-32-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',56.00,68.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(256,1,64,41,'DEMO-064-32-OLIVE','demo-064-32-olive','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',56.00,68.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(257,1,65,29,'DEMO-065-S-BLACK','demo-065-s-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',62.00,74.00,18,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(258,1,65,48,'DEMO-065-S-TAUPE','demo-065-s-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',62.00,74.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(259,1,65,29,'DEMO-065-M-BLACK','demo-065-m-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',64.00,76.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(260,1,65,48,'DEMO-065-M-TAUPE','demo-065-m-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',64.00,76.00,20,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(261,1,66,36,'DEMO-066-S-FLORAL-NAVY','demo-066-s-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',68.00,80.00,19,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(262,1,66,55,'DEMO-066-S-TERRACOTTA','demo-066-s-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',68.00,80.00,20,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(263,1,66,36,'DEMO-066-M-FLORAL-NAVY','demo-066-m-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',70.00,82.00,20,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(264,1,66,55,'DEMO-066-M-TERRACOTTA','demo-066-m-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',70.00,82.00,21,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(265,1,67,43,'DEMO-067-S-EMERALD','demo-067-s-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',64.00,76.00,20,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(266,1,67,2,'DEMO-067-S-BLACK','demo-067-s-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',64.00,76.00,21,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(267,1,67,43,'DEMO-067-M-EMERALD','demo-067-m-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',66.00,78.00,21,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(268,1,67,2,'DEMO-067-M-BLACK','demo-067-m-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',66.00,78.00,22,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(269,1,68,50,'DEMO-068-M-INDIGO','demo-068-m-indigo','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',79.00,91.00,21,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(270,1,68,9,'DEMO-068-M-WASHED-BLACK','demo-068-m-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',79.00,91.00,22,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(271,1,68,50,'DEMO-068-L-INDIGO','demo-068-l-indigo','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',81.00,93.00,22,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(272,1,68,9,'DEMO-068-L-WASHED-BLACK','demo-068-l-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',81.00,93.00,23,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(273,1,69,57,'DEMO-069-M-BLACK','demo-069-m-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',86.00,98.00,22,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(274,1,69,16,'DEMO-069-M-ARMY-GREEN','demo-069-m-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',86.00,98.00,23,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(275,1,69,57,'DEMO-069-L-BLACK','demo-069-l-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',88.00,100.00,23,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(276,1,69,16,'DEMO-069-L-ARMY-GREEN','demo-069-l-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',88.00,100.00,24,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(277,1,70,4,'DEMO-070-S-CHARCOAL','demo-070-s-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',48.00,60.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(278,1,70,23,'DEMO-070-S-PLUM','demo-070-s-plum','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',48.00,60.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(279,1,70,4,'DEMO-070-M-CHARCOAL','demo-070-m-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',50.00,62.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(280,1,70,23,'DEMO-070-M-PLUM','demo-070-m-plum','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',50.00,62.00,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(281,1,71,11,'DEMO-071-M-BLACK','demo-071-m-black','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',42.00,54.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(282,1,71,30,'DEMO-071-M-COBALT','demo-071-m-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',42.00,54.00,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(283,1,71,11,'DEMO-071-L-BLACK','demo-071-l-black','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',44.00,56.00,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(284,1,71,30,'DEMO-071-L-COBALT','demo-071-l-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',44.00,56.00,26,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:37','2026-07-17 09:06:37'),(285,1,72,18,'DEMO-072-4Y-RED','demo-072-4y-red','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',41.00,53.00,25,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(286,1,72,37,'DEMO-072-4Y-NAVY','demo-072-4y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',41.00,53.00,26,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(287,1,72,18,'DEMO-072-8Y-RED','demo-072-8y-red','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',43.00,55.00,26,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(288,1,72,37,'DEMO-072-8Y-NAVY','demo-072-8y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',43.00,55.00,27,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(289,1,73,25,'DEMO-073-6Y-WHITE','demo-073-6y-white','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',34.00,46.00,26,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(290,1,73,44,'DEMO-073-6Y-LIGHT-BLUE','demo-073-6y-light-blue','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',34.00,46.00,27,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(291,1,73,25,'DEMO-073-10Y-WHITE','demo-073-10y-white','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',36.00,48.00,27,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(292,1,73,44,'DEMO-073-10Y-LIGHT-BLUE','demo-073-10y-light-blue','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',36.00,48.00,28,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(293,1,74,32,'DEMO-074-M-NAVY','demo-074-m-navy','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',74.00,86.00,27,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(294,1,74,51,'DEMO-074-M-GRAPHITE','demo-074-m-graphite','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',74.00,86.00,28,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(295,1,74,32,'DEMO-074-L-NAVY','demo-074-l-navy','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',76.00,88.00,28,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(296,1,74,51,'DEMO-074-L-GRAPHITE','demo-074-l-graphite','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',76.00,88.00,29,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(297,1,75,39,'DEMO-075-M-OATMEAL','demo-075-m-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',58.00,70.00,28,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(298,1,75,58,'DEMO-075-M-BLACK','demo-075-m-black','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',58.00,70.00,29,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(299,1,75,39,'DEMO-075-L-OATMEAL','demo-075-l-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',60.00,72.00,29,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(300,1,75,58,'DEMO-075-L-BLACK','demo-075-l-black','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',60.00,72.00,30,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(301,1,76,46,'DEMO-076-S-CREAM','demo-076-s-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',67.00,79.00,29,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(302,1,76,5,'DEMO-076-S-DUSTY-ROSE','demo-076-s-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',67.00,79.00,30,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(303,1,76,46,'DEMO-076-M-CREAM','demo-076-m-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',69.00,81.00,30,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(304,1,76,5,'DEMO-076-M-DUSTY-ROSE','demo-076-m-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',69.00,81.00,31,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(305,1,77,53,'DEMO-077-S-STONE','demo-077-s-stone','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',108.00,120.00,30,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(306,1,77,12,'DEMO-077-S-CAMEL','demo-077-s-camel','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',108.00,120.00,31,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(307,1,77,53,'DEMO-077-M-STONE','demo-077-m-stone','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',110.00,122.00,31,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(308,1,77,12,'DEMO-077-M-CAMEL','demo-077-m-camel','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',110.00,122.00,32,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(309,1,78,60,'DEMO-078-M-CHARCOAL','demo-078-m-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',122.00,134.00,31,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(310,1,78,19,'DEMO-078-M-CAMEL','demo-078-m-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',122.00,134.00,32,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(311,1,78,60,'DEMO-078-L-CHARCOAL','demo-078-l-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',124.00,136.00,32,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(312,1,78,19,'DEMO-078-L-CAMEL','demo-078-l-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',124.00,136.00,33,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(313,1,79,7,'DEMO-079-S-IVORY','demo-079-s-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',52.00,64.00,32,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(314,1,79,26,'DEMO-079-S-POWDER-BLUE','demo-079-s-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',52.00,64.00,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(315,1,79,7,'DEMO-079-M-IVORY','demo-079-m-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',54.00,66.00,33,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(316,1,79,26,'DEMO-079-M-POWDER-BLUE','demo-079-m-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',54.00,66.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(317,1,80,14,'DEMO-080-XS-OLIVE','demo-080-xs-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',59.00,71.00,33,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(318,1,80,33,'DEMO-080-XS-BLACK','demo-080-xs-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',59.00,71.00,34,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(319,1,80,14,'DEMO-080-S-OLIVE','demo-080-s-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',61.00,73.00,34,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(320,1,80,33,'DEMO-080-S-BLACK','demo-080-s-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',61.00,73.00,35,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(321,1,81,21,'DEMO-081-S-WHITE','demo-081-s-white','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',48.00,60.00,34,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(322,1,81,40,'DEMO-081-S-SKY-BLUE','demo-081-s-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"S\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',48.00,60.00,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(323,1,81,21,'DEMO-081-M-WHITE','demo-081-m-white','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"White\", \"material\": \"100% cotton oxford\"}',50.00,62.00,35,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(324,1,81,40,'DEMO-081-M-SKY-BLUE','demo-081-m-sky-blue','{\"fit\": \"Regular fit\", \"size\": \"M\", \"color\": \"Sky Blue\", \"material\": \"100% cotton oxford\"}',50.00,62.00,36,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(329,1,83,35,'DEMO-083-M-NAVY','demo-083-m-navy','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',43.00,55.00,36,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(330,1,83,54,'DEMO-083-M-HEATHER-GREY','demo-083-m-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"M\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',43.00,55.00,37,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(331,1,83,35,'DEMO-083-L-NAVY','demo-083-l-navy','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Pique cotton\"}',45.00,57.00,37,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(332,1,83,54,'DEMO-083-L-HEATHER-GREY','demo-083-l-heather-grey','{\"fit\": \"Classic fit\", \"size\": \"L\", \"color\": \"Heather Grey\", \"material\": \"Pique cotton\"}',45.00,57.00,38,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(333,1,84,42,'DEMO-084-30-KHAKI','demo-084-30-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',58.00,70.00,37,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(334,1,84,1,'DEMO-084-30-OLIVE','demo-084-30-olive','{\"fit\": \"Slim straight fit\", \"size\": \"30\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',58.00,70.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(335,1,84,42,'DEMO-084-32-KHAKI','demo-084-32-khaki','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Khaki\", \"material\": \"Cotton twill with elastane\"}',60.00,72.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(336,1,84,1,'DEMO-084-32-OLIVE','demo-084-32-olive','{\"fit\": \"Slim straight fit\", \"size\": \"32\", \"color\": \"Olive\", \"material\": \"Cotton twill with elastane\"}',60.00,72.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(337,1,85,49,'DEMO-085-S-BLACK','demo-085-s-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',66.00,78.00,38,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(338,1,85,8,'DEMO-085-S-TAUPE','demo-085-s-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"S\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',66.00,78.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(339,1,85,49,'DEMO-085-M-BLACK','demo-085-m-black','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Viscose blend suiting\"}',68.00,80.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(340,1,85,8,'DEMO-085-M-TAUPE','demo-085-m-taupe','{\"fit\": \"Tailored wide-leg fit\", \"size\": \"M\", \"color\": \"Taupe\", \"material\": \"Viscose blend suiting\"}',68.00,80.00,40,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(341,1,86,56,'DEMO-086-S-FLORAL-NAVY','demo-086-s-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',72.00,84.00,39,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(342,1,86,15,'DEMO-086-S-TERRACOTTA','demo-086-s-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"S\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',72.00,84.00,40,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(343,1,86,56,'DEMO-086-M-FLORAL-NAVY','demo-086-m-floral-navy','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Floral Navy\", \"material\": \"Printed viscose challis\"}',74.00,86.00,40,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(344,1,86,15,'DEMO-086-M-TERRACOTTA','demo-086-m-terracotta','{\"fit\": \"Flowing fit\", \"size\": \"M\", \"color\": \"Terracotta\", \"material\": \"Printed viscose challis\"}',74.00,86.00,12,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(345,1,87,3,'DEMO-087-S-EMERALD','demo-087-s-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',68.00,80.00,40,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(346,1,87,22,'DEMO-087-S-BLACK','demo-087-s-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',68.00,80.00,12,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(347,1,87,3,'DEMO-087-M-EMERALD','demo-087-m-emerald','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Emerald\", \"material\": \"Soft crepe\"}',70.00,82.00,12,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(348,1,87,22,'DEMO-087-M-BLACK','demo-087-m-black','{\"fit\": \"Adjustable wrap fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Soft crepe\"}',70.00,82.00,13,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(349,1,88,10,'DEMO-088-M-INDIGO','demo-088-m-indigo','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',83.00,95.00,12,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(350,1,88,29,'DEMO-088-M-WASHED-BLACK','demo-088-m-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"M\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',83.00,95.00,13,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(351,1,88,10,'DEMO-088-L-INDIGO','demo-088-l-indigo','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Indigo\", \"material\": \"Midweight denim\"}',85.00,97.00,13,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(352,1,88,29,'DEMO-088-L-WASHED-BLACK','demo-088-l-washed-black','{\"fit\": \"Boxy fit\", \"size\": \"L\", \"color\": \"Washed Black\", \"material\": \"Midweight denim\"}',85.00,97.00,14,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(353,1,89,17,'DEMO-089-M-BLACK','demo-089-m-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',90.00,102.00,13,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(354,1,89,36,'DEMO-089-M-ARMY-GREEN','demo-089-m-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"M\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',90.00,102.00,14,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(355,1,89,17,'DEMO-089-L-BLACK','demo-089-l-black','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Recycled nylon shell\"}',92.00,104.00,14,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(356,1,89,36,'DEMO-089-L-ARMY-GREEN','demo-089-l-army-green','{\"fit\": \"Ribbed hem fit\", \"size\": \"L\", \"color\": \"Army Green\", \"material\": \"Recycled nylon shell\"}',92.00,104.00,15,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(357,1,90,24,'DEMO-090-S-CHARCOAL','demo-090-s-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',52.00,64.00,14,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(358,1,90,43,'DEMO-090-S-PLUM','demo-090-s-plum','{\"fit\": \"High-compression fit\", \"size\": \"S\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',52.00,64.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(359,1,90,24,'DEMO-090-M-CHARCOAL','demo-090-m-charcoal','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Stretch jersey\"}',54.00,66.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(360,1,90,43,'DEMO-090-M-PLUM','demo-090-m-plum','{\"fit\": \"High-compression fit\", \"size\": \"M\", \"color\": \"Plum\", \"material\": \"Stretch jersey\"}',54.00,66.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(361,1,91,31,'DEMO-091-M-BLACK','demo-091-m-black','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',46.00,58.00,15,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(362,1,91,50,'DEMO-091-M-COBALT','demo-091-m-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"M\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',46.00,58.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(363,1,91,31,'DEMO-091-L-BLACK','demo-091-l-black','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Quick-dry polyester\"}',48.00,60.00,16,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(364,1,91,50,'DEMO-091-L-COBALT','demo-091-l-cobalt','{\"fit\": \"Athletic fit\", \"size\": \"L\", \"color\": \"Cobalt\", \"material\": \"Quick-dry polyester\"}',48.00,60.00,17,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(365,1,92,38,'DEMO-092-4Y-RED','demo-092-4y-red','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',45.00,57.00,16,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(366,1,92,57,'DEMO-092-4Y-NAVY','demo-092-4y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"4Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',45.00,57.00,17,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(367,1,92,38,'DEMO-092-8Y-RED','demo-092-8y-red','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Red\", \"material\": \"Cotton fleece\"}',47.00,59.00,17,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(368,1,92,57,'DEMO-092-8Y-NAVY','demo-092-8y-navy','{\"fit\": \"Easy kids fit\", \"size\": \"8Y\", \"color\": \"Navy\", \"material\": \"Cotton fleece\"}',47.00,59.00,18,0.300,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(369,1,93,45,'DEMO-093-6Y-WHITE','demo-093-6y-white','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',38.00,50.00,17,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(370,1,93,4,'DEMO-093-6Y-LIGHT-BLUE','demo-093-6y-light-blue','{\"fit\": \"School fit\", \"size\": \"6Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',38.00,50.00,18,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(371,1,93,45,'DEMO-093-10Y-WHITE','demo-093-10y-white','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"White\", \"material\": \"Cotton poplin\"}',40.00,52.00,18,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(372,1,93,4,'DEMO-093-10Y-LIGHT-BLUE','demo-093-10y-light-blue','{\"fit\": \"School fit\", \"size\": \"10Y\", \"color\": \"Light Blue\", \"material\": \"Cotton poplin\"}',40.00,52.00,19,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(373,1,94,52,'DEMO-094-M-NAVY','demo-094-m-navy','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',78.00,90.00,18,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(374,1,94,11,'DEMO-094-M-GRAPHITE','demo-094-m-graphite','{\"fit\": \"Utility fit\", \"size\": \"M\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',78.00,90.00,19,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(375,1,94,52,'DEMO-094-L-NAVY','demo-094-l-navy','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Navy\", \"material\": \"Durable cotton twill\"}',80.00,92.00,19,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(376,1,94,11,'DEMO-094-L-GRAPHITE','demo-094-l-graphite','{\"fit\": \"Utility fit\", \"size\": \"L\", \"color\": \"Graphite\", \"material\": \"Durable cotton twill\"}',80.00,92.00,20,0.850,'{\"width_cm\": 36, \"height_cm\": 10, \"length_cm\": 48}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(377,1,95,59,'DEMO-095-M-OATMEAL','demo-095-m-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',62.00,74.00,19,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(378,1,95,18,'DEMO-095-M-BLACK','demo-095-m-black','{\"fit\": \"Relaxed fit\", \"size\": \"M\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',62.00,74.00,20,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(379,1,95,59,'DEMO-095-L-OATMEAL','demo-095-l-oatmeal','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Oatmeal\", \"material\": \"Brushed fleece\"}',64.00,76.00,20,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(380,1,95,18,'DEMO-095-L-BLACK','demo-095-l-black','{\"fit\": \"Relaxed fit\", \"size\": \"L\", \"color\": \"Black\", \"material\": \"Brushed fleece\"}',64.00,76.00,21,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(381,1,96,6,'DEMO-096-S-CREAM','demo-096-s-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',71.00,83.00,20,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(382,1,96,25,'DEMO-096-S-DUSTY-ROSE','demo-096-s-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"S\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',71.00,83.00,21,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(383,1,96,6,'DEMO-096-M-CREAM','demo-096-m-cream','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Cream\", \"material\": \"Cotton knit\"}',73.00,85.00,21,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(384,1,96,25,'DEMO-096-M-DUSTY-ROSE','demo-096-m-dusty-rose','{\"fit\": \"Soft relaxed fit\", \"size\": \"M\", \"color\": \"Dusty Rose\", \"material\": \"Cotton knit\"}',73.00,85.00,22,0.700,'{\"width_cm\": 32, \"height_cm\": 9, \"length_cm\": 42}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(385,1,97,13,'DEMO-097-S-STONE','demo-097-s-stone','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',112.00,124.00,21,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(386,1,97,32,'DEMO-097-S-CAMEL','demo-097-s-camel','{\"fit\": \"Belted fit\", \"size\": \"S\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',112.00,124.00,22,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(387,1,97,13,'DEMO-097-M-STONE','demo-097-m-stone','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Stone\", \"material\": \"Cotton gabardine\"}',114.00,126.00,22,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(388,1,97,32,'DEMO-097-M-CAMEL','demo-097-m-camel','{\"fit\": \"Belted fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Cotton gabardine\"}',114.00,126.00,23,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(389,1,98,20,'DEMO-098-M-CHARCOAL','demo-098-m-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',126.00,138.00,22,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(390,1,98,39,'DEMO-098-M-CAMEL','demo-098-m-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"M\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',126.00,138.00,23,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(391,1,98,20,'DEMO-098-L-CHARCOAL','demo-098-l-charcoal','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Charcoal\", \"material\": \"Wool blend\"}',128.00,140.00,23,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(392,1,98,39,'DEMO-098-L-CAMEL','demo-098-l-camel','{\"fit\": \"Tailored outerwear fit\", \"size\": \"L\", \"color\": \"Camel\", \"material\": \"Wool blend\"}',128.00,140.00,24,1.250,'{\"width_cm\": 42, \"height_cm\": 12, \"length_cm\": 55}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(393,1,99,27,'DEMO-099-S-IVORY','demo-099-s-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',56.00,68.00,23,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(394,1,99,46,'DEMO-099-S-POWDER-BLUE','demo-099-s-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"S\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',56.00,68.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(395,1,99,27,'DEMO-099-M-IVORY','demo-099-m-ivory','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Ivory\", \"material\": \"Cotton voile\"}',58.00,70.00,24,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(396,1,99,46,'DEMO-099-M-POWDER-BLUE','demo-099-m-powder-blue','{\"fit\": \"Soft drape fit\", \"size\": \"M\", \"color\": \"Powder Blue\", \"material\": \"Cotton voile\"}',58.00,70.00,25,0.420,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(397,1,100,34,'DEMO-100-XS-OLIVE','demo-100-xs-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',63.00,75.00,24,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(398,1,100,53,'DEMO-100-XS-BLACK','demo-100-xs-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"XS\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',63.00,75.00,25,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(399,1,100,34,'DEMO-100-S-OLIVE','demo-100-s-olive','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Olive\", \"material\": \"Ripstop cotton\"}',65.00,77.00,25,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38'),(400,1,100,53,'DEMO-100-S-BLACK','demo-100-s-black','{\"fit\": \"Relaxed cargo fit\", \"size\": \"S\", \"color\": \"Black\", \"material\": \"Ripstop cotton\"}',65.00,77.00,26,0.520,'{\"width_cm\": 28, \"height_cm\": 6, \"length_cm\": 35}','active','2026-07-17 09:06:38','2026-07-17 09:06:38');
/*!40000 ALTER TABLE `commerce_product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerce_products`
--

DROP TABLE IF EXISTS `commerce_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `audience_id` bigint unsigned DEFAULT NULL,
  `primary_media_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `care_information` text COLLATE utf8mb4_unicode_ci,
  `condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `audience` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_of_origin` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `wizard_step` tinyint unsigned NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_products_workspace_id_slug_unique` (`workspace_id`,`slug`),
  KEY `commerce_products_category_id_foreign` (`category_id`),
  KEY `commerce_products_brand_id_foreign` (`brand_id`),
  KEY `commerce_products_audience_id_foreign` (`audience_id`),
  KEY `commerce_products_primary_media_id_foreign` (`primary_media_id`),
  KEY `commerce_products_status_index` (`status`),
  CONSTRAINT `commerce_products_audience_id_foreign` FOREIGN KEY (`audience_id`) REFERENCES `commerce_audiences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `commerce_brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `commerce_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_primary_media_id_foreign` FOREIGN KEY (`primary_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerce_products`
--

LOCK TABLES `commerce_products` WRITE;
/*!40000 ALTER TABLE `commerce_products` DISABLE KEYS */;
INSERT INTO `commerce_products` VALUES (1,1,1,1,2,1,'Essential Oxford Button-Down Shirt','demo-essential-oxford-button-down-shirt','Dhaka Loom Studio','Essential Oxford Button-Down Shirt is a production-ready Regular fit garment made from 100% cotton oxford. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,1,1,2,3,8,'Essential Washed Linen Camp Shirt','demo-essential-washed-linen-camp-shirt','Bengal Threadworks','Essential Washed Linen Camp Shirt is a production-ready Relaxed fit garment made from Garment-washed linen. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,1,1,3,3,15,'Essential Premium Pique Polo','demo-essential-premium-pique-polo','River & Reed Apparel','Essential Premium Pique Polo is a production-ready Classic fit garment made from Pique cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,1,2,4,2,22,'Essential Stretch Chino Trouser','demo-essential-stretch-chino-trouser','Northstar Garments','Essential Stretch Chino Trouser is a production-ready Slim straight fit garment made from Cotton twill with elastane. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,1,2,5,1,29,'Essential High-Rise Tailored Trouser','demo-essential-high-rise-tailored-trouser','Urban Weave Co.','Essential High-Rise Tailored Trouser is a production-ready Tailored wide-leg fit garment made from Viscose blend suiting. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,1,3,6,1,36,'Essential Tiered Viscose Maxi Dress','demo-essential-tiered-viscose-maxi-dress','Cotton House BD','Essential Tiered Viscose Maxi Dress is a production-ready Flowing fit garment made from Printed viscose challis. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,1,3,7,1,43,'Essential Crepe Wrap Midi Dress','demo-essential-crepe-wrap-midi-dress','Aarong Lane Basics','Essential Crepe Wrap Midi Dress is a production-ready Adjustable wrap fit garment made from Soft crepe. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,1,4,8,3,50,'Essential Classic Denim Trucker Jacket','demo-essential-classic-denim-trucker-jacket','Summit Activewear','Essential Classic Denim Trucker Jacket is a production-ready Boxy fit garment made from Midweight denim. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,1,4,1,2,57,'Essential Recycled Nylon Bomber Jacket','demo-essential-recycled-nylon-bomber-jacket','Dhaka Loom Studio','Essential Recycled Nylon Bomber Jacket is a production-ready Ribbed hem fit garment made from Recycled nylon shell. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,1,5,2,1,4,'Essential Seamless Performance Legging','demo-essential-seamless-performance-legging','Bengal Threadworks','Essential Seamless Performance Legging is a production-ready High-compression fit garment made from Stretch jersey. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,1,5,3,3,11,'Essential Quick-Dry Training Short','demo-essential-quick-dry-training-short','River & Reed Apparel','Essential Quick-Dry Training Short is a production-ready Athletic fit garment made from Quick-dry polyester. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,1,6,4,4,18,'Essential Kids Everyday Zip Hoodie','demo-essential-kids-everyday-zip-hoodie','Northstar Garments','Essential Kids Everyday Zip Hoodie is a production-ready Easy kids fit garment made from Cotton fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,1,7,5,4,25,'Essential School Uniform Poplin Shirt','demo-essential-school-uniform-poplin-shirt','Urban Weave Co.','Essential School Uniform Poplin Shirt is a production-ready School fit garment made from Cotton poplin. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,1,7,6,3,32,'Essential Industrial Workwear Coverall','demo-essential-industrial-workwear-coverall','Cotton House BD','Essential Industrial Workwear Coverall is a production-ready Utility fit garment made from Durable cotton twill. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,1,8,7,3,39,'Essential Brushed Fleece Pullover Hoodie','demo-essential-brushed-fleece-pullover-hoodie','Aarong Lane Basics','Essential Brushed Fleece Pullover Hoodie is a production-ready Relaxed fit garment made from Brushed fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,1,8,8,1,46,'Essential Cable Knit Cotton Sweater','demo-essential-cable-knit-cotton-sweater','Summit Activewear','Essential Cable Knit Cotton Sweater is a production-ready Soft relaxed fit garment made from Cotton knit. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,1,9,1,1,53,'Essential Water-Repellent Trench Coat','demo-essential-water-repellent-trench-coat','Dhaka Loom Studio','Essential Water-Repellent Trench Coat is a production-ready Belted fit garment made from Cotton gabardine. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,1,9,2,2,60,'Essential Double-Face Wool Blend Coat','demo-essential-double-face-wool-blend-coat','Bengal Threadworks','Essential Double-Face Wool Blend Coat is a production-ready Tailored outerwear fit garment made from Wool blend. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,1,10,3,1,7,'Essential Pleated Cotton Voile Blouse','demo-essential-pleated-cotton-voile-blouse','River & Reed Apparel','Essential Pleated Cotton Voile Blouse is a production-ready Soft drape fit garment made from Cotton voile. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,1,2,4,5,14,'Essential Ripstop Cargo Pant','demo-essential-ripstop-cargo-pant','Northstar Garments','Essential Ripstop Cargo Pant is a production-ready Relaxed cargo fit garment made from Ripstop cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Teen','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,1,1,5,2,21,'Heritage Oxford Button-Down Shirt','demo-heritage-oxford-button-down-shirt','Urban Weave Co.','Heritage Oxford Button-Down Shirt is a production-ready Regular fit garment made from 100% cotton oxford. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,1,1,6,3,28,'Heritage Washed Linen Camp Shirt','demo-heritage-washed-linen-camp-shirt','Cotton House BD','Heritage Washed Linen Camp Shirt is a production-ready Relaxed fit garment made from Garment-washed linen. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,1,1,7,3,35,'Heritage Premium Pique Polo','demo-heritage-premium-pique-polo','Aarong Lane Basics','Heritage Premium Pique Polo is a production-ready Classic fit garment made from Pique cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,1,2,8,2,42,'Heritage Stretch Chino Trouser','demo-heritage-stretch-chino-trouser','Summit Activewear','Heritage Stretch Chino Trouser is a production-ready Slim straight fit garment made from Cotton twill with elastane. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,1,2,1,1,49,'Heritage High-Rise Tailored Trouser','demo-heritage-high-rise-tailored-trouser','Dhaka Loom Studio','Heritage High-Rise Tailored Trouser is a production-ready Tailored wide-leg fit garment made from Viscose blend suiting. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,1,3,2,1,56,'Heritage Tiered Viscose Maxi Dress','demo-heritage-tiered-viscose-maxi-dress','Bengal Threadworks','Heritage Tiered Viscose Maxi Dress is a production-ready Flowing fit garment made from Printed viscose challis. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,1,3,3,1,3,'Heritage Crepe Wrap Midi Dress','demo-heritage-crepe-wrap-midi-dress','River & Reed Apparel','Heritage Crepe Wrap Midi Dress is a production-ready Adjustable wrap fit garment made from Soft crepe. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,1,4,4,3,10,'Heritage Classic Denim Trucker Jacket','demo-heritage-classic-denim-trucker-jacket','Northstar Garments','Heritage Classic Denim Trucker Jacket is a production-ready Boxy fit garment made from Midweight denim. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,1,4,5,2,17,'Heritage Recycled Nylon Bomber Jacket','demo-heritage-recycled-nylon-bomber-jacket','Urban Weave Co.','Heritage Recycled Nylon Bomber Jacket is a production-ready Ribbed hem fit garment made from Recycled nylon shell. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,1,5,6,1,24,'Heritage Seamless Performance Legging','demo-heritage-seamless-performance-legging','Cotton House BD','Heritage Seamless Performance Legging is a production-ready High-compression fit garment made from Stretch jersey. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,1,5,7,3,31,'Heritage Quick-Dry Training Short','demo-heritage-quick-dry-training-short','Aarong Lane Basics','Heritage Quick-Dry Training Short is a production-ready Athletic fit garment made from Quick-dry polyester. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,1,6,8,4,38,'Heritage Kids Everyday Zip Hoodie','demo-heritage-kids-everyday-zip-hoodie','Summit Activewear','Heritage Kids Everyday Zip Hoodie is a production-ready Easy kids fit garment made from Cotton fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,1,7,1,4,45,'Heritage School Uniform Poplin Shirt','demo-heritage-school-uniform-poplin-shirt','Dhaka Loom Studio','Heritage School Uniform Poplin Shirt is a production-ready School fit garment made from Cotton poplin. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,1,7,2,3,52,'Heritage Industrial Workwear Coverall','demo-heritage-industrial-workwear-coverall','Bengal Threadworks','Heritage Industrial Workwear Coverall is a production-ready Utility fit garment made from Durable cotton twill. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,1,8,3,3,59,'Heritage Brushed Fleece Pullover Hoodie','demo-heritage-brushed-fleece-pullover-hoodie','River & Reed Apparel','Heritage Brushed Fleece Pullover Hoodie is a production-ready Relaxed fit garment made from Brushed fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,1,8,4,1,6,'Heritage Cable Knit Cotton Sweater','demo-heritage-cable-knit-cotton-sweater','Northstar Garments','Heritage Cable Knit Cotton Sweater is a production-ready Soft relaxed fit garment made from Cotton knit. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,1,9,5,1,13,'Heritage Water-Repellent Trench Coat','demo-heritage-water-repellent-trench-coat','Urban Weave Co.','Heritage Water-Repellent Trench Coat is a production-ready Belted fit garment made from Cotton gabardine. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,1,9,6,2,20,'Heritage Double-Face Wool Blend Coat','demo-heritage-double-face-wool-blend-coat','Cotton House BD','Heritage Double-Face Wool Blend Coat is a production-ready Tailored outerwear fit garment made from Wool blend. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,1,10,7,1,27,'Heritage Pleated Cotton Voile Blouse','demo-heritage-pleated-cotton-voile-blouse','Aarong Lane Basics','Heritage Pleated Cotton Voile Blouse is a production-ready Soft drape fit garment made from Cotton voile. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,1,2,8,5,34,'Heritage Ripstop Cargo Pant','demo-heritage-ripstop-cargo-pant','Summit Activewear','Heritage Ripstop Cargo Pant is a production-ready Relaxed cargo fit garment made from Ripstop cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Teen','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,1,1,1,2,41,'Urban Oxford Button-Down Shirt','demo-urban-oxford-button-down-shirt','Dhaka Loom Studio','Urban Oxford Button-Down Shirt is a production-ready Regular fit garment made from 100% cotton oxford. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,1,1,2,3,48,'Urban Washed Linen Camp Shirt','demo-urban-washed-linen-camp-shirt','Bengal Threadworks','Urban Washed Linen Camp Shirt is a production-ready Relaxed fit garment made from Garment-washed linen. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,1,1,3,3,55,'Urban Premium Pique Polo','demo-urban-premium-pique-polo','River & Reed Apparel','Urban Premium Pique Polo is a production-ready Classic fit garment made from Pique cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,1,2,4,2,2,'Urban Stretch Chino Trouser','demo-urban-stretch-chino-trouser','Northstar Garments','Urban Stretch Chino Trouser is a production-ready Slim straight fit garment made from Cotton twill with elastane. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,1,2,5,1,9,'Urban High-Rise Tailored Trouser','demo-urban-high-rise-tailored-trouser','Urban Weave Co.','Urban High-Rise Tailored Trouser is a production-ready Tailored wide-leg fit garment made from Viscose blend suiting. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,1,3,6,1,16,'Urban Tiered Viscose Maxi Dress','demo-urban-tiered-viscose-maxi-dress','Cotton House BD','Urban Tiered Viscose Maxi Dress is a production-ready Flowing fit garment made from Printed viscose challis. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,1,3,7,1,23,'Urban Crepe Wrap Midi Dress','demo-urban-crepe-wrap-midi-dress','Aarong Lane Basics','Urban Crepe Wrap Midi Dress is a production-ready Adjustable wrap fit garment made from Soft crepe. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,1,4,8,3,30,'Urban Classic Denim Trucker Jacket','demo-urban-classic-denim-trucker-jacket','Summit Activewear','Urban Classic Denim Trucker Jacket is a production-ready Boxy fit garment made from Midweight denim. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,1,4,1,2,37,'Urban Recycled Nylon Bomber Jacket','demo-urban-recycled-nylon-bomber-jacket','Dhaka Loom Studio','Urban Recycled Nylon Bomber Jacket is a production-ready Ribbed hem fit garment made from Recycled nylon shell. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,1,5,2,1,44,'Urban Seamless Performance Legging','demo-urban-seamless-performance-legging','Bengal Threadworks','Urban Seamless Performance Legging is a production-ready High-compression fit garment made from Stretch jersey. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,1,5,3,3,51,'Urban Quick-Dry Training Short','demo-urban-quick-dry-training-short','River & Reed Apparel','Urban Quick-Dry Training Short is a production-ready Athletic fit garment made from Quick-dry polyester. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,1,6,4,4,58,'Urban Kids Everyday Zip Hoodie','demo-urban-kids-everyday-zip-hoodie','Northstar Garments','Urban Kids Everyday Zip Hoodie is a production-ready Easy kids fit garment made from Cotton fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,1,7,5,4,5,'Urban School Uniform Poplin Shirt','demo-urban-school-uniform-poplin-shirt','Urban Weave Co.','Urban School Uniform Poplin Shirt is a production-ready School fit garment made from Cotton poplin. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,1,7,6,3,12,'Urban Industrial Workwear Coverall','demo-urban-industrial-workwear-coverall','Cotton House BD','Urban Industrial Workwear Coverall is a production-ready Utility fit garment made from Durable cotton twill. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,1,8,7,3,19,'Urban Brushed Fleece Pullover Hoodie','demo-urban-brushed-fleece-pullover-hoodie','Aarong Lane Basics','Urban Brushed Fleece Pullover Hoodie is a production-ready Relaxed fit garment made from Brushed fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,1,8,8,1,26,'Urban Cable Knit Cotton Sweater','demo-urban-cable-knit-cotton-sweater','Summit Activewear','Urban Cable Knit Cotton Sweater is a production-ready Soft relaxed fit garment made from Cotton knit. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,1,9,1,1,33,'Urban Water-Repellent Trench Coat','demo-urban-water-repellent-trench-coat','Dhaka Loom Studio','Urban Water-Repellent Trench Coat is a production-ready Belted fit garment made from Cotton gabardine. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,1,9,2,2,40,'Urban Double-Face Wool Blend Coat','demo-urban-double-face-wool-blend-coat','Bengal Threadworks','Urban Double-Face Wool Blend Coat is a production-ready Tailored outerwear fit garment made from Wool blend. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,1,10,3,1,47,'Urban Pleated Cotton Voile Blouse','demo-urban-pleated-cotton-voile-blouse','River & Reed Apparel','Urban Pleated Cotton Voile Blouse is a production-ready Soft drape fit garment made from Cotton voile. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,1,2,4,5,54,'Urban Ripstop Cargo Pant','demo-urban-ripstop-cargo-pant','Northstar Garments','Urban Ripstop Cargo Pant is a production-ready Relaxed cargo fit garment made from Ripstop cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Teen','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(61,1,1,5,2,1,'Studio Oxford Button-Down Shirt','demo-studio-oxford-button-down-shirt','Urban Weave Co.','Studio Oxford Button-Down Shirt is a production-ready Regular fit garment made from 100% cotton oxford. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(62,1,1,6,3,8,'Studio Washed Linen Camp Shirt','demo-studio-washed-linen-camp-shirt','Cotton House BD','Studio Washed Linen Camp Shirt is a production-ready Relaxed fit garment made from Garment-washed linen. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(63,1,1,7,3,15,'Studio Premium Pique Polo','demo-studio-premium-pique-polo','Aarong Lane Basics','Studio Premium Pique Polo is a production-ready Classic fit garment made from Pique cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(64,1,2,8,2,22,'Studio Stretch Chino Trouser','demo-studio-stretch-chino-trouser','Summit Activewear','Studio Stretch Chino Trouser is a production-ready Slim straight fit garment made from Cotton twill with elastane. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(65,1,2,1,1,29,'Studio High-Rise Tailored Trouser','demo-studio-high-rise-tailored-trouser','Dhaka Loom Studio','Studio High-Rise Tailored Trouser is a production-ready Tailored wide-leg fit garment made from Viscose blend suiting. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(66,1,3,2,1,36,'Studio Tiered Viscose Maxi Dress','demo-studio-tiered-viscose-maxi-dress','Bengal Threadworks','Studio Tiered Viscose Maxi Dress is a production-ready Flowing fit garment made from Printed viscose challis. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(67,1,3,3,1,43,'Studio Crepe Wrap Midi Dress','demo-studio-crepe-wrap-midi-dress','River & Reed Apparel','Studio Crepe Wrap Midi Dress is a production-ready Adjustable wrap fit garment made from Soft crepe. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(68,1,4,4,3,50,'Studio Classic Denim Trucker Jacket','demo-studio-classic-denim-trucker-jacket','Northstar Garments','Studio Classic Denim Trucker Jacket is a production-ready Boxy fit garment made from Midweight denim. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(69,1,4,5,2,57,'Studio Recycled Nylon Bomber Jacket','demo-studio-recycled-nylon-bomber-jacket','Urban Weave Co.','Studio Recycled Nylon Bomber Jacket is a production-ready Ribbed hem fit garment made from Recycled nylon shell. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(70,1,5,6,1,4,'Studio Seamless Performance Legging','demo-studio-seamless-performance-legging','Cotton House BD','Studio Seamless Performance Legging is a production-ready High-compression fit garment made from Stretch jersey. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(71,1,5,7,3,11,'Studio Quick-Dry Training Short','demo-studio-quick-dry-training-short','Aarong Lane Basics','Studio Quick-Dry Training Short is a production-ready Athletic fit garment made from Quick-dry polyester. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(72,1,6,8,4,18,'Studio Kids Everyday Zip Hoodie','demo-studio-kids-everyday-zip-hoodie','Summit Activewear','Studio Kids Everyday Zip Hoodie is a production-ready Easy kids fit garment made from Cotton fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:37','2026-07-17 09:06:37','2026-07-17 09:06:37'),(73,1,7,1,4,25,'Studio School Uniform Poplin Shirt','demo-studio-school-uniform-poplin-shirt','Dhaka Loom Studio','Studio School Uniform Poplin Shirt is a production-ready School fit garment made from Cotton poplin. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(74,1,7,2,3,32,'Studio Industrial Workwear Coverall','demo-studio-industrial-workwear-coverall','Bengal Threadworks','Studio Industrial Workwear Coverall is a production-ready Utility fit garment made from Durable cotton twill. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(75,1,8,3,3,39,'Studio Brushed Fleece Pullover Hoodie','demo-studio-brushed-fleece-pullover-hoodie','River & Reed Apparel','Studio Brushed Fleece Pullover Hoodie is a production-ready Relaxed fit garment made from Brushed fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(76,1,8,4,1,46,'Studio Cable Knit Cotton Sweater','demo-studio-cable-knit-cotton-sweater','Northstar Garments','Studio Cable Knit Cotton Sweater is a production-ready Soft relaxed fit garment made from Cotton knit. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(77,1,9,5,1,53,'Studio Water-Repellent Trench Coat','demo-studio-water-repellent-trench-coat','Urban Weave Co.','Studio Water-Repellent Trench Coat is a production-ready Belted fit garment made from Cotton gabardine. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(78,1,9,6,2,60,'Studio Double-Face Wool Blend Coat','demo-studio-double-face-wool-blend-coat','Cotton House BD','Studio Double-Face Wool Blend Coat is a production-ready Tailored outerwear fit garment made from Wool blend. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Men','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(79,1,10,7,1,7,'Studio Pleated Cotton Voile Blouse','demo-studio-pleated-cotton-voile-blouse','Aarong Lane Basics','Studio Pleated Cotton Voile Blouse is a production-ready Soft drape fit garment made from Cotton voile. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(80,1,2,8,5,14,'Studio Ripstop Cargo Pant','demo-studio-ripstop-cargo-pant','Summit Activewear','Studio Ripstop Cargo Pant is a production-ready Relaxed cargo fit garment made from Ripstop cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Teen','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(81,1,1,1,2,21,'Premium Oxford Button-Down Shirt','demo-premium-oxford-button-down-shirt','Dhaka Loom Studio','Premium Oxford Button-Down Shirt is a production-ready Regular fit garment made from 100% cotton oxford. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(83,1,1,3,3,35,'Premium Premium Pique Polo','demo-premium-premium-pique-polo','River & Reed Apparel','Premium Premium Pique Polo is a production-ready Classic fit garment made from Pique cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(84,1,2,4,2,42,'Premium Stretch Chino Trouser','demo-premium-stretch-chino-trouser','Northstar Garments','Premium Stretch Chino Trouser is a production-ready Slim straight fit garment made from Cotton twill with elastane. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(85,1,2,5,1,49,'Premium High-Rise Tailored Trouser','demo-premium-high-rise-tailored-trouser','Urban Weave Co.','Premium High-Rise Tailored Trouser is a production-ready Tailored wide-leg fit garment made from Viscose blend suiting. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(86,1,3,6,1,56,'Premium Tiered Viscose Maxi Dress','demo-premium-tiered-viscose-maxi-dress','Cotton House BD','Premium Tiered Viscose Maxi Dress is a production-ready Flowing fit garment made from Printed viscose challis. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(87,1,3,7,1,3,'Premium Crepe Wrap Midi Dress','demo-premium-crepe-wrap-midi-dress','Aarong Lane Basics','Premium Crepe Wrap Midi Dress is a production-ready Adjustable wrap fit garment made from Soft crepe. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(88,1,4,8,3,10,'Premium Classic Denim Trucker Jacket','demo-premium-classic-denim-trucker-jacket','Summit Activewear','Premium Classic Denim Trucker Jacket is a production-ready Boxy fit garment made from Midweight denim. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(89,1,4,1,2,17,'Premium Recycled Nylon Bomber Jacket','demo-premium-recycled-nylon-bomber-jacket','Dhaka Loom Studio','Premium Recycled Nylon Bomber Jacket is a production-ready Ribbed hem fit garment made from Recycled nylon shell. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Men','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(90,1,5,2,1,24,'Premium Seamless Performance Legging','demo-premium-seamless-performance-legging','Bengal Threadworks','Premium Seamless Performance Legging is a production-ready High-compression fit garment made from Stretch jersey. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(91,1,5,3,3,31,'Premium Quick-Dry Training Short','demo-premium-quick-dry-training-short','River & Reed Apparel','Premium Quick-Dry Training Short is a production-ready Athletic fit garment made from Quick-dry polyester. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(92,1,6,4,4,38,'Premium Kids Everyday Zip Hoodie','demo-premium-kids-everyday-zip-hoodie','Northstar Garments','Premium Kids Everyday Zip Hoodie is a production-ready Easy kids fit garment made from Cotton fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(93,1,7,5,4,45,'Premium School Uniform Poplin Shirt','demo-premium-school-uniform-poplin-shirt','Urban Weave Co.','Premium School Uniform Poplin Shirt is a production-ready School fit garment made from Cotton poplin. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Kids','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(94,1,7,6,3,52,'Premium Industrial Workwear Coverall','demo-premium-industrial-workwear-coverall','Cotton House BD','Premium Industrial Workwear Coverall is a production-ready Utility fit garment made from Durable cotton twill. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(95,1,8,7,3,59,'Premium Brushed Fleece Pullover Hoodie','demo-premium-brushed-fleece-pullover-hoodie','Aarong Lane Basics','Premium Brushed Fleece Pullover Hoodie is a production-ready Relaxed fit garment made from Brushed fleece. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Unisex','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(96,1,8,8,1,6,'Premium Cable Knit Cotton Sweater','demo-premium-cable-knit-cotton-sweater','Summit Activewear','Premium Cable Knit Cotton Sweater is a production-ready Soft relaxed fit garment made from Cotton knit. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(97,1,9,1,1,13,'Premium Water-Repellent Trench Coat','demo-premium-water-repellent-trench-coat','Dhaka Loom Studio','Premium Water-Repellent Trench Coat is a production-ready Belted fit garment made from Cotton gabardine. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(98,1,9,2,2,20,'Premium Double-Face Wool Blend Coat','demo-premium-double-face-wool-blend-coat','Bengal Threadworks','Premium Double-Face Wool Blend Coat is a production-ready Tailored outerwear fit garment made from Wool blend. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.','new','Men','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(99,1,10,3,1,27,'Premium Pleated Cotton Voile Blouse','demo-premium-pleated-cotton-voile-blouse','River & Reed Apparel','Premium Pleated Cotton Voile Blouse is a production-ready Soft drape fit garment made from Cotton voile. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Women','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38'),(100,1,2,4,5,34,'Premium Ripstop Cargo Pant','demo-premium-ripstop-cargo-pant','Northstar Garments','Premium Ripstop Cargo Pant is a production-ready Relaxed cargo fit garment made from Ripstop cotton. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.','Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.','new','Teen','BD','active',5,'2026-07-17 09:06:38','2026-07-17 09:06:38','2026-07-17 09:06:38');
/*!40000 ALTER TABLE `commerce_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_group_contact`
--

DROP TABLE IF EXISTS `contact_group_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_group_contact` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_group_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_group_contact_unique` (`contact_group_id`,`contact_id`),
  KEY `contact_group_contact_contact_id_foreign` (`contact_id`),
  CONSTRAINT `contact_group_contact_contact_group_id_foreign` FOREIGN KEY (`contact_group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_group_contact_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_group_contact`
--

LOCK TABLES `contact_group_contact` WRITE;
/*!40000 ALTER TABLE `contact_group_contact` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_group_contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_groups`
--

DROP TABLE IF EXISTS `contact_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'static',
  `rules` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_groups_workspace_id_name_unique` (`workspace_id`,`name`),
  UNIQUE KEY `contact_groups_workspace_id_slug_unique` (`workspace_id`,`slug`),
  CONSTRAINT `contact_groups_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_groups`
--

LOCK TABLES `contact_groups` WRITE;
/*!40000 ALTER TABLE `contact_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_imports`
--

DROP TABLE IF EXISTS `contact_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'import',
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `created_rows` int unsigned NOT NULL DEFAULT '0',
  `updated_rows` int unsigned NOT NULL DEFAULT '0',
  `imported_rows` int unsigned NOT NULL DEFAULT '0',
  `skipped_rows` int unsigned NOT NULL DEFAULT '0',
  `failed_rows` int unsigned NOT NULL DEFAULT '0',
  `column_mapping` json DEFAULT NULL,
  `options` json DEFAULT NULL,
  `errors` json DEFAULT NULL,
  `summary` json DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_imports_workspace_id_foreign` (`workspace_id`),
  KEY `contact_imports_user_id_foreign` (`user_id`),
  CONSTRAINT `contact_imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contact_imports_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_imports`
--

LOCK TABLES `contact_imports` WRITE;
/*!40000 ALTER TABLE `contact_imports` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_message_replies`
--

DROP TABLE IF EXISTS `contact_message_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_message_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_message_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `notification_log_id` bigint unsigned DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_variables` json DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_message_replies_admin_id_foreign` (`admin_id`),
  KEY `contact_message_replies_notification_log_id_foreign` (`notification_log_id`),
  KEY `contact_message_replies_contact_message_id_created_at_index` (`contact_message_id`,`created_at`),
  KEY `contact_message_replies_template_slug_index` (`template_slug`),
  CONSTRAINT `contact_message_replies_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contact_message_replies_contact_message_id_foreign` FOREIGN KEY (`contact_message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_message_replies_notification_log_id_foreign` FOREIGN KEY (`notification_log_id`) REFERENCES `notification_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_message_replies`
--

LOCK TABLES `contact_message_replies` WRITE;
/*!40000 ALTER TABLE `contact_message_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_message_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interest` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_messages_status_created_at_index` (`status`,`created_at`),
  KEY `contact_messages_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_provider_identities`
--

DROP TABLE IF EXISTS `contact_provider_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_provider_identities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_contact_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `identity_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'phone',
  `metadata` json DEFAULT NULL,
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_identity_unique` (`workspace_id`,`provider`,`provider_contact_id`),
  KEY `contact_provider_identities_contact_id_foreign` (`contact_id`),
  KEY `contact_provider_identities_channel_account_id_foreign` (`channel_account_id`),
  KEY `contact_provider_identities_provider_index` (`provider`),
  KEY `contact_provider_identities_provider_contact_id_index` (`provider_contact_id`),
  CONSTRAINT `contact_provider_identities_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contact_provider_identities_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_provider_identities_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_provider_identities`
--

LOCK TABLES `contact_provider_identities` WRITE;
/*!40000 ALTER TABLE `contact_provider_identities` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_provider_identities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_segment`
--

DROP TABLE IF EXISTS `contact_segment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_segment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `segment_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_segment_segment_id_contact_id_unique` (`segment_id`,`contact_id`),
  KEY `contact_segment_contact_id_foreign` (`contact_id`),
  CONSTRAINT `contact_segment_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_segment_segment_id_foreign` FOREIGN KEY (`segment_id`) REFERENCES `segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_segment`
--

LOCK TABLES `contact_segment` WRITE;
/*!40000 ALTER TABLE `contact_segment` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_segment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_tag_contact`
--

DROP TABLE IF EXISTS `contact_tag_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_tag_contact` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_tag_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_tag_contact_unique` (`contact_tag_id`,`contact_id`),
  KEY `contact_tag_contact_contact_id_foreign` (`contact_id`),
  CONSTRAINT `contact_tag_contact_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_tag_contact_contact_tag_id_foreign` FOREIGN KEY (`contact_tag_id`) REFERENCES `contact_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_tag_contact`
--

LOCK TABLES `contact_tag_contact` WRITE;
/*!40000 ALTER TABLE `contact_tag_contact` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_tag_contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_tags`
--

DROP TABLE IF EXISTS `contact_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_tags_workspace_id_name_unique` (`workspace_id`,`name`),
  UNIQUE KEY `contact_tags_workspace_id_slug_unique` (`workspace_id`,`slug`),
  CONSTRAINT `contact_tags_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_tags`
--

LOCK TABLES `contact_tags` WRITE;
/*!40000 ALTER TABLE `contact_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields` json DEFAULT NULL,
  `source` enum('website','form','import','manual','ai_generated') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opt_in_status` enum('unknown','subscribed','unsubscribed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `opt_in_at` timestamp NULL DEFAULT NULL,
  `opt_out_at` timestamp NULL DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL,
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_workspace_id_phone_unique` (`workspace_id`,`phone`),
  UNIQUE KEY `contacts_workspace_id_email_unique` (`workspace_id`,`email`),
  KEY `contacts_assigned_to_foreign` (`assigned_to`),
  KEY `contacts_workspace_id_assigned_to_index` (`workspace_id`,`assigned_to`),
  CONSTRAINT `contacts_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `provider_conversation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `session_expires_at` timestamp NULL DEFAULT NULL,
  `labels` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_workspace_id_foreign` (`workspace_id`),
  KEY `conversations_channel_account_id_foreign` (`channel_account_id`),
  KEY `conversations_contact_id_foreign` (`contact_id`),
  KEY `conversations_assigned_to_foreign` (`assigned_to`),
  KEY `conversations_provider_index` (`provider`),
  KEY `conversations_provider_conversation_id_index` (`provider_conversation_id`),
  CONSTRAINT `conversations_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_activities`
--

DROP TABLE IF EXISTS `crm_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_activities_lead_id_foreign` (`lead_id`),
  KEY `crm_activities_contact_id_foreign` (`contact_id`),
  KEY `crm_activities_conversation_id_foreign` (`conversation_id`),
  KEY `crm_activities_created_by_foreign` (`created_by`),
  KEY `crm_activities_workspace_id_lead_id_created_at_index` (`workspace_id`,`lead_id`,`created_at`),
  KEY `crm_activities_workspace_id_contact_id_created_at_index` (`workspace_id`,`contact_id`,`created_at`),
  KEY `crm_activities_type_index` (`type`),
  CONSTRAINT `crm_activities_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_activities_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_activities`
--

LOCK TABLES `crm_activities` WRITE;
/*!40000 ALTER TABLE `crm_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_leads`
--

DROP TABLE IF EXISTS `crm_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `pipeline_id` bigint unsigned NOT NULL,
  `stage_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(12,2) DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `next_follow_up_at` timestamp NULL DEFAULT NULL,
  `won_at` timestamp NULL DEFAULT NULL,
  `lost_at` timestamp NULL DEFAULT NULL,
  `lost_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_leads_contact_id_foreign` (`contact_id`),
  KEY `crm_leads_conversation_id_foreign` (`conversation_id`),
  KEY `crm_leads_campaign_id_foreign` (`campaign_id`),
  KEY `crm_leads_pipeline_id_foreign` (`pipeline_id`),
  KEY `crm_leads_stage_id_foreign` (`stage_id`),
  KEY `crm_leads_assigned_to_foreign` (`assigned_to`),
  KEY `crm_leads_board_index` (`workspace_id`,`pipeline_id`,`stage_id`,`status`),
  KEY `crm_leads_contact_index` (`workspace_id`,`contact_id`,`pipeline_id`,`status`),
  KEY `crm_leads_workspace_id_assigned_to_status_index` (`workspace_id`,`assigned_to`,`status`),
  CONSTRAINT `crm_leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_leads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `crm_pipelines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `crm_leads_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `crm_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `crm_leads_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_leads`
--

LOCK TABLES `crm_leads` WRITE;
/*!40000 ALTER TABLE `crm_leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_pipelines`
--

DROP TABLE IF EXISTS `crm_pipelines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_pipelines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_pipelines_workspace_id_name_unique` (`workspace_id`,`name`),
  KEY `crm_pipelines_workspace_id_is_default_index` (`workspace_id`,`is_default`),
  CONSTRAINT `crm_pipelines_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_pipelines`
--

LOCK TABLES `crm_pipelines` WRITE;
/*!40000 ALTER TABLE `crm_pipelines` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_pipelines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_stages`
--

DROP TABLE IF EXISTS `crm_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_stages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `pipeline_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_stages_pipeline_id_name_unique` (`pipeline_id`,`name`),
  KEY `crm_stages_workspace_id_pipeline_id_position_index` (`workspace_id`,`pipeline_id`,`position`),
  CONSTRAINT `crm_stages_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `crm_pipelines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_stages_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_stages`
--

LOCK TABLES `crm_stages` WRITE;
/*!40000 ALTER TABLE `crm_stages` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_tasks`
--

DROP TABLE IF EXISTS `crm_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `due_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reminded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_tasks_lead_id_foreign` (`lead_id`),
  KEY `crm_tasks_contact_id_foreign` (`contact_id`),
  KEY `crm_tasks_assigned_to_foreign` (`assigned_to`),
  KEY `crm_tasks_workspace_id_status_due_at_index` (`workspace_id`,`status`,`due_at`),
  KEY `crm_tasks_workspace_id_assigned_to_status_index` (`workspace_id`,`assigned_to`,`status`),
  CONSTRAINT `crm_tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_tasks_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_tasks_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_tasks_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_tasks`
--

LOCK TABLES `crm_tasks` WRITE;
/*!40000 ALTER TABLE `crm_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate` decimal(20,8) NOT NULL DEFAULT '1.00000000',
  `rate_synced_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`),
  KEY `currencies_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'AED','United Arab Emirates dirham','د.إ',0.00000000,NULL,1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'AFN','Afghan afghani','؋',0.00000000,NULL,1,2,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'ALL','Albanian lek','L',0.00000000,NULL,1,3,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'AMD','Armenian dram','AMD',0.00000000,NULL,1,4,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'ANG','Netherlands Antillean guilder','ƒ',0.00000000,NULL,1,5,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'AOA','Angolan kwanza','Kz',0.00000000,NULL,1,6,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'ARS','Argentine peso','$',0.00000000,NULL,1,7,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,'AUD','Australian dollar','$',0.00000000,NULL,1,11,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,'AWG','Aruban florin','Afl.',0.00000000,NULL,1,8,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,'AZN','Azerbaijani manat','AZN',0.00000000,NULL,1,9,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'BAM','Bosnia and Herzegovina convertible mark','KM',0.00000000,NULL,1,10,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(12,'BBD','Barbadian dollar','$',0.00000000,NULL,1,11,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(13,'BDT','Bangladeshi taka','৳',0.00000000,NULL,1,4,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(14,'BGN','Bulgarian lev','лв.',0.00000000,NULL,1,12,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(15,'BHD','Bahraini dinar','.د.ب',0.00000000,NULL,1,13,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(16,'BIF','Burundian franc','Fr',0.00000000,NULL,1,14,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(17,'BMD','Bermudian dollar','$',0.00000000,NULL,1,15,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(18,'BND','Brunei dollar','$',0.00000000,NULL,1,16,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(19,'BOB','Bolivian boliviano','Bs.',0.00000000,NULL,1,17,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(20,'BRL','Brazilian real','R$',0.00000000,NULL,1,18,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(21,'BSD','Bahamian dollar','$',0.00000000,NULL,1,19,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(22,'BTC','Bitcoin','฿',0.00000000,NULL,1,20,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(23,'ETH','Ethereum','Ξ',0.00000000,NULL,1,21,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(24,'USDT','Tether','$',0.00000000,NULL,1,22,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(25,'BTN','Bhutanese ngultrum','Nu.',0.00000000,NULL,1,23,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(26,'BWP','Botswana pula','P',0.00000000,NULL,1,24,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(27,'BYR','Belarusian ruble (old)','Br',0.00000000,NULL,1,25,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(28,'BYN','Belarusian ruble','Br',0.00000000,NULL,1,26,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(29,'BZD','Belize dollar','$',0.00000000,NULL,1,27,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(30,'CAD','Canadian dollar','$',0.00000000,NULL,1,10,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(31,'CDF','Congolese franc','Fr',0.00000000,NULL,1,28,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(32,'CHF','Swiss franc','CHF',0.00000000,NULL,1,29,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(33,'CLP','Chilean peso','$',0.00000000,NULL,1,30,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(34,'CNY','Chinese yuan','¥',0.00000000,NULL,1,13,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(35,'COP','Colombian peso','$',0.00000000,NULL,1,31,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(36,'CRC','Costa Rican colón','₡',0.00000000,NULL,1,32,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(37,'CUC','Cuban convertible peso','$',0.00000000,NULL,1,33,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(38,'CUP','Cuban peso','$',0.00000000,NULL,1,34,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(39,'CVE','Cape Verdean escudo','$',0.00000000,NULL,1,35,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(40,'CZK','Czech koruna','Kč',0.00000000,NULL,1,36,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(41,'DJF','Djiboutian franc','Fr',0.00000000,NULL,1,37,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(42,'DKK','Danish krone','DKK',0.00000000,NULL,1,38,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(43,'DOP','Dominican peso','RD$',0.00000000,NULL,1,39,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(44,'DZD','Algerian dinar','د.ج',0.00000000,NULL,1,40,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(45,'EGP','Egyptian pound','EGP',0.00000000,NULL,1,41,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(46,'ERN','Eritrean nakfa','Nfk',0.00000000,NULL,1,42,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(47,'ETB','Ethiopian birr','Br',0.00000000,NULL,1,43,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(48,'EUR','Euro','€',0.00000000,NULL,1,2,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(49,'FJD','Fijian dollar','$',0.00000000,NULL,1,44,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(50,'FKP','Falkland Islands pound','£',0.00000000,NULL,1,45,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(51,'GBP','Pound sterling','£',0.00000000,NULL,1,3,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(52,'GEL','Georgian lari','₾',0.00000000,NULL,1,46,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(53,'GGP','Guernsey pound','£',0.00000000,NULL,1,47,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(54,'GHS','Ghana cedi','₵',0.00000000,NULL,1,7,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(55,'GIP','Gibraltar pound','£',0.00000000,NULL,1,48,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(56,'GMD','Gambian dalasi','D',0.00000000,NULL,1,49,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(57,'GNF','Guinean franc','Fr',0.00000000,NULL,1,50,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(58,'GTQ','Guatemalan quetzal','Q',0.00000000,NULL,1,51,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(59,'GYD','Guyanese dollar','$',0.00000000,NULL,1,52,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(60,'HKD','Hong Kong dollar','$',0.00000000,NULL,1,53,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(61,'HNL','Honduran lempira','L',0.00000000,NULL,1,54,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(62,'HRK','Croatian kuna','kn',0.00000000,NULL,1,55,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(63,'HTG','Haitian gourde','G',0.00000000,NULL,1,56,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(64,'HUF','Hungarian forint','Ft',0.00000000,NULL,1,57,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(65,'IDR','Indonesian rupiah','Rp',0.00000000,NULL,1,58,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(66,'ILS','Israeli new shekel','₪',0.00000000,NULL,1,59,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(67,'IMP','Manx pound','£',0.00000000,NULL,1,60,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(68,'INR','Indian rupee','₹',0.00000000,NULL,1,5,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(69,'IQD','Iraqi dinar','ع.د',0.00000000,NULL,1,61,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(70,'IRR','Iranian rial','﷼',0.00000000,NULL,1,62,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(71,'IRT','Iranian toman','تومان',0.00000000,NULL,1,63,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(72,'ISK','Icelandic króna','kr.',0.00000000,NULL,1,64,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(73,'JEP','Jersey pound','£',0.00000000,NULL,1,65,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(74,'JMD','Jamaican dollar','$',0.00000000,NULL,1,66,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(75,'JOD','Jordanian dinar','د.ا',0.00000000,NULL,1,67,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(76,'JPY','Japanese yen','¥',0.00000000,NULL,1,12,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(77,'KES','Kenyan shilling','KSh',0.00000000,NULL,1,8,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(78,'KGS','Kyrgyzstani som','сом',0.00000000,NULL,1,68,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(79,'KHR','Cambodian riel','៛',0.00000000,NULL,1,69,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(80,'KMF','Comorian franc','Fr',0.00000000,NULL,1,70,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(81,'KPW','North Korean won','₩',0.00000000,NULL,1,71,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(82,'KRW','South Korean won','₩',0.00000000,NULL,1,72,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(83,'KWD','Kuwaiti dinar','د.ك',0.00000000,NULL,1,73,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(84,'KYD','Cayman Islands dollar','$',0.00000000,NULL,1,74,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(85,'KZT','Kazakhstani tenge','₸',0.00000000,NULL,1,75,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(86,'LAK','Lao kip','₭',0.00000000,NULL,1,76,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(87,'LBP','Lebanese pound','ل.ل',0.00000000,NULL,1,77,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(88,'LKR','Sri Lankan rupee','රු',0.00000000,NULL,1,78,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(89,'LRD','Liberian dollar','$',0.00000000,NULL,1,79,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(90,'LSL','Lesotho loti','L',0.00000000,NULL,1,80,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(91,'LYD','Libyan dinar','ل.د',0.00000000,NULL,1,81,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(92,'MAD','Moroccan dirham','د.م.',0.00000000,NULL,1,82,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(93,'MDL','Moldovan leu','MDL',0.00000000,NULL,1,83,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(94,'MGA','Malagasy ariary','Ar',0.00000000,NULL,1,84,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(95,'MKD','Macedonian denar','ден',0.00000000,NULL,1,85,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(96,'MMK','Burmese kyat','Ks',0.00000000,NULL,1,86,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(97,'MNT','Mongolian tögrög','₮',0.00000000,NULL,1,87,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(98,'MOP','Macanese pataca','P',0.00000000,NULL,1,88,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(99,'MRU','Mauritanian ouguiya','UM',0.00000000,NULL,1,89,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(100,'MUR','Mauritian rupee','₨',0.00000000,NULL,1,90,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(101,'MVR','Maldivian rufiyaa','.ރ',0.00000000,NULL,1,91,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(102,'MWK','Malawian kwacha','MK',0.00000000,NULL,1,92,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(103,'MXN','Mexican peso','$',0.00000000,NULL,1,93,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(104,'MYR','Malaysian ringgit','RM',0.00000000,NULL,1,14,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(105,'MZN','Mozambican metical','MT',0.00000000,NULL,1,94,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(106,'NAD','Namibian dollar','N$',0.00000000,NULL,1,95,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(107,'NGN','Nigerian naira','₦',0.00000000,NULL,1,6,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(108,'NIO','Nicaraguan córdoba','C$',0.00000000,NULL,1,96,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(109,'NOK','Norwegian krone','kr',0.00000000,NULL,1,97,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(110,'NPR','Nepalese rupee','₨',0.00000000,NULL,1,98,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(111,'NZD','New Zealand dollar','$',0.00000000,NULL,1,99,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(112,'OMR','Omani rial','ر.ع.',0.00000000,NULL,1,100,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(113,'PAB','Panamanian balboa','B/.',0.00000000,NULL,1,101,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(114,'PEN','Sol','S/',0.00000000,NULL,1,102,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(115,'PGK','Papua New Guinean kina','K',0.00000000,NULL,1,103,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(116,'PHP','Philippine peso','₱',0.00000000,NULL,1,104,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(117,'PKR','Pakistani rupee','₨',0.00000000,NULL,1,105,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(118,'PLN','Polish złoty','zł',0.00000000,NULL,1,106,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(119,'PRB','Transnistrian ruble','р.',0.00000000,NULL,1,107,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(120,'PYG','Paraguayan guaraní','₲',0.00000000,NULL,1,108,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(121,'QAR','Qatari riyal','ر.ق',0.00000000,NULL,1,109,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(122,'RON','Romanian leu','lei',0.00000000,NULL,1,110,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(123,'RSD','Serbian dinar','рсд',0.00000000,NULL,1,111,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(124,'RUB','Russian ruble','₽',0.00000000,NULL,1,112,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(125,'RWF','Rwandan franc','Fr',0.00000000,NULL,1,113,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(126,'SAR','Saudi riyal','ر.س',0.00000000,NULL,1,114,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(127,'SBD','Solomon Islands dollar','$',0.00000000,NULL,1,115,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(128,'SCR','Seychellois rupee','₨',0.00000000,NULL,1,116,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(129,'SDG','Sudanese pound','ج.س.',0.00000000,NULL,1,117,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(130,'SEK','Swedish krona','kr',0.00000000,NULL,1,118,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(131,'SGD','Singapore dollar','$',0.00000000,NULL,1,15,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(132,'SHP','Saint Helena pound','£',0.00000000,NULL,1,119,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(133,'SLL','Sierra Leonean leone','Le',0.00000000,NULL,1,120,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(134,'SOS','Somali shilling','Sh',0.00000000,NULL,1,121,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(135,'SRD','Surinamese dollar','$',0.00000000,NULL,1,122,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(136,'SSP','South Sudanese pound','£',0.00000000,NULL,1,123,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(137,'STN','São Tomé and Príncipe dobra','Db',0.00000000,NULL,1,124,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(138,'SYP','Syrian pound','ل.س',0.00000000,NULL,1,125,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(139,'SZL','Swazi lilangeni','L',0.00000000,NULL,1,126,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(140,'THB','Thai baht','฿',0.00000000,NULL,1,127,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(141,'TJS','Tajikistani somoni','ЅМ',0.00000000,NULL,1,128,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(142,'TMT','Turkmenistan manat','m',0.00000000,NULL,1,129,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(143,'TND','Tunisian dinar','د.ت',0.00000000,NULL,1,130,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(144,'TOP','Tongan paʻanga','T$',0.00000000,NULL,1,131,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(145,'TRY','Turkish lira','₺',0.00000000,NULL,1,132,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(146,'TTD','Trinidad and Tobago dollar','$',0.00000000,NULL,1,133,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(147,'TWD','New Taiwan dollar','NT$',0.00000000,NULL,1,134,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(148,'TZS','Tanzanian shilling','Sh',0.00000000,NULL,1,135,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(149,'UAH','Ukrainian hryvnia','₴',0.00000000,NULL,1,136,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(150,'UGX','Ugandan shilling','UGX',0.00000000,NULL,1,137,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(151,'USD','United States (US) dollar','$',1.00000000,NULL,1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(152,'UYU','Uruguayan peso','$',0.00000000,NULL,1,138,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(153,'UZS','Uzbekistani som','UZS',0.00000000,NULL,1,139,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(154,'VEF','Venezuelan bolívar','Bs F',0.00000000,NULL,1,140,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(155,'VES','Bolívar soberano','Bs.S',0.00000000,NULL,1,141,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(156,'VND','Vietnamese đồng','₫',0.00000000,NULL,1,142,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(157,'VUV','Vanuatu vatu','Vt',0.00000000,NULL,1,143,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(158,'WST','Samoan tālā','T',0.00000000,NULL,1,144,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(159,'XAF','Central African CFA franc','CFA',0.00000000,NULL,1,145,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(160,'XCD','East Caribbean dollar','$',0.00000000,NULL,1,146,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(161,'XOF','West African CFA franc','CFA',0.00000000,NULL,1,147,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(162,'XPF','CFP franc','Fr',0.00000000,NULL,1,148,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(163,'YER','Yemeni rial','﷼',0.00000000,NULL,1,149,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(164,'ZAR','South African rand','R',0.00000000,NULL,1,9,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(165,'ZMW','Zambian kwacha','ZK',0.00000000,NULL,1,150,'2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_tokens`
--

DROP TABLE IF EXISTS `device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_tokens`
--

LOCK TABLES `device_tokens` WRITE;
/*!40000 ALTER TABLE `device_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_tokens` ENABLE KEYS */;
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
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
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
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_status_active_index` (`status`,`active`),
  KEY `faqs_sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` VALUES (1,'How do you structure a new project kickoff?','We begin with a discovery sprint to align on goals, constraints, users, and delivery scope. That sprint ends with a prioritized roadmap, milestones, and a shared definition of done.',1,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'How often will we see progress during delivery?','Most engagements run in one or two week sprints with regular demos, written updates, and direct access to the people building the work so feedback stays fast and concrete.',2,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'Do you work on fixed scope or monthly retainers?','We support both. Fixed scope works well when deliverables are well defined, while retainers fit ongoing product, design, or growth work that benefits from continuous iteration.',3,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'Can you help us estimate budget before a full engagement?','Yes. We can start with a short scoping engagement or advisory workshop to clarify complexity, timeline, and likely budget ranges before committing to a larger build.',4,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'Who owns the code, designs, and deliverables after launch?','You do. Once the work is delivered and paid for, your team retains ownership of the agreed deliverables, including repositories, assets, and documentation.',5,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'Do you provide post-launch support?','Yes. We can provide a structured support window after launch and longer-term retainers for maintenance, improvements, analytics, and roadmap execution.',6,1,'published','2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frontend_menu_items`
--

DROP TABLE IF EXISTS `frontend_menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frontend_menu_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `frontend_menu_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `linkable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkable_id` bigint unsigned DEFAULT NULL,
  `url` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `frontend_menu_items_frontend_menu_id_foreign` (`frontend_menu_id`),
  KEY `frontend_menu_items_parent_id_foreign` (`parent_id`),
  KEY `frontend_menu_items_linkable_type_linkable_id_index` (`linkable_type`,`linkable_id`),
  CONSTRAINT `frontend_menu_items_frontend_menu_id_foreign` FOREIGN KEY (`frontend_menu_id`) REFERENCES `frontend_menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `frontend_menu_items_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `frontend_menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frontend_menu_items`
--

LOCK TABLES `frontend_menu_items` WRITE;
/*!40000 ALTER TABLE `frontend_menu_items` DISABLE KEYS */;
INSERT INTO `frontend_menu_items` VALUES (1,1,NULL,'internal','Home','App\\Modules\\Frontend\\Models\\Page',1,NULL,'_self',0,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,1,NULL,'internal','About','App\\Modules\\Frontend\\Models\\Page',4,NULL,'_self',1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,1,NULL,'external','Blog',NULL,NULL,'http://localhost:8000/blog','_self',2,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,1,NULL,'group','Resources',NULL,NULL,NULL,'_self',3,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,1,4,'external','Documentation',NULL,NULL,'https://example.com/docs','_blank',0,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,2,NULL,'internal','Home','App\\Modules\\Frontend\\Models\\Page',1,NULL,'_self',0,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,2,NULL,'internal','About','App\\Modules\\Frontend\\Models\\Page',4,NULL,'_self',1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,3,NULL,'internal','Home','App\\Modules\\Frontend\\Models\\Page',1,NULL,'_self',0,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,3,NULL,'internal','About','App\\Modules\\Frontend\\Models\\Page',4,NULL,'_self',1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,3,NULL,'external','Blog',NULL,NULL,'http://localhost:8000/blog','_self',2,1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,4,NULL,'external','Home',NULL,NULL,'http://localhost:8000','_self',0,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(12,4,NULL,'external','Features',NULL,NULL,'http://localhost:8000/features','_self',1,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(13,4,NULL,'external','FAQs',NULL,NULL,'http://localhost:8000/faqs','_self',2,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(14,4,NULL,'external','Blog',NULL,NULL,'http://localhost:8000/blog','_self',3,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(15,4,NULL,'external','Contact',NULL,NULL,'http://localhost:8000/contact','_self',4,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(16,5,NULL,'group','Product',NULL,NULL,NULL,'_self',0,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(17,5,16,'external','Features',NULL,NULL,'http://localhost:8000/features','_self',0,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(18,5,16,'external','FAQs',NULL,NULL,'http://localhost:8000/faqs','_self',1,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(19,5,16,'external','Get started',NULL,NULL,'http://localhost:8000/login','_self',2,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(20,5,NULL,'group','Company',NULL,NULL,NULL,'_self',1,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(21,5,20,'external','Contact',NULL,NULL,'http://localhost:8000/contact','_self',0,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(22,5,20,'external','Sign in',NULL,NULL,'http://localhost:8000/login','_self',1,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(23,5,20,'internal','Privacy Policy','App\\Modules\\Frontend\\Models\\Page',13,NULL,'_self',2,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(24,5,20,'internal','Terms & Conditions','App\\Modules\\Frontend\\Models\\Page',14,NULL,'_self',3,1,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(25,5,20,'internal','Cookie Policy','App\\Modules\\Frontend\\Models\\Page',17,NULL,'_self',4,1,'2026-07-17 09:06:04','2026-07-17 09:06:04');
/*!40000 ALTER TABLE `frontend_menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frontend_menus`
--

DROP TABLE IF EXISTS `frontend_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frontend_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `frontend_menus_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frontend_menus`
--

LOCK TABLES `frontend_menus` WRITE;
/*!40000 ALTER TABLE `frontend_menus` DISABLE KEYS */;
INSERT INTO `frontend_menus` VALUES (1,'Primary Navigation','primary-navigation','published','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'Footer Links','footer-links','published','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'Mobile Navigation','mobile-navigation','published','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'Header Menu','header-menu','published','2026-07-17 09:06:04','2026-07-17 09:06:04'),(5,'Footer Menu','footer-menu','published','2026-07-17 09:06:04','2026-07-17 09:06:04');
/*!40000 ALTER TABLE `frontend_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frontend_sections`
--

DROP TABLE IF EXISTS `frontend_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frontend_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `data` json NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `theme_overrides` json DEFAULT NULL,
  `preview_image_media_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `frontend_sections_slug_unique` (`slug`),
  KEY `frontend_sections_type_status_index` (`type`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frontend_sections`
--

LOCK TABLES `frontend_sections` WRITE;
/*!40000 ALTER TABLE `frontend_sections` DISABLE KEYS */;
INSERT INTO `frontend_sections` VALUES (1,'Homepage Hero','homepage-hero','home_hero','published','{\"logos\": [{\"alt\": \"Vercel\", \"fallback_src\": \"assets/logos/vercel.svg\", \"logo_media_id\": null}, {\"alt\": \"Stripe\", \"fallback_src\": \"assets/logos/stripe.svg\", \"logo_media_id\": null}, {\"alt\": \"Figma\", \"fallback_src\": \"assets/logos/figma.svg\", \"logo_media_id\": null}, {\"alt\": \"Linear\", \"fallback_src\": \"assets/logos/linear.svg\", \"logo_media_id\": null}, {\"alt\": \"Notion\", \"fallback_src\": \"assets/logos/notion.svg\", \"logo_media_id\": null}, {\"alt\": \"Shopify\", \"fallback_src\": \"assets/logos/shopify.svg\", \"logo_media_id\": null}, {\"alt\": \"Cloudflare\", \"fallback_src\": \"assets/logos/cloudflare.svg\", \"logo_media_id\": null}, {\"alt\": \"Airbnb\", \"fallback_src\": \"assets/logos/airbnb.svg\", \"logo_media_id\": null}, {\"alt\": \"Spotify\", \"fallback_src\": \"assets/logos/spotify.svg\", \"logo_media_id\": null}, {\"alt\": \"Discord\", \"fallback_src\": \"assets/logos/discord.svg\", \"logo_media_id\": null}, {\"alt\": \"Google Cloud\", \"fallback_src\": \"assets/logos/googlecloud.svg\", \"logo_media_id\": null}, {\"alt\": \"Docker\", \"fallback_src\": \"assets/logos/docker.svg\", \"logo_media_id\": null}, {\"alt\": \"Kubernetes\", \"fallback_src\": \"assets/logos/kubernetes.svg\", \"logo_media_id\": null}, {\"alt\": \"TypeScript\", \"fallback_src\": \"assets/logos/typescript.svg\", \"logo_media_id\": null}, {\"alt\": \"Next.js\", \"fallback_src\": \"assets/logos/nextdotjs.svg\", \"logo_media_id\": null}, {\"alt\": \"React\", \"fallback_src\": \"assets/logos/react.svg\", \"logo_media_id\": null}, {\"alt\": \"Tailwind CSS\", \"fallback_src\": \"assets/logos/tailwindcss.svg\", \"logo_media_id\": null}], \"subheading\": \"Classic is a senior product team for founders and operators. From discovery to launch — clean UI, scalable code, one timeline.\", \"stat_1_label\": \"Products shipped\", \"stat_1_value\": \"120+\", \"stat_2_label\": \"Average MVP\", \"stat_2_value\": \"8 wks\", \"stat_3_label\": \"Client rating\", \"stat_3_value\": \"4.9/5\", \"caption_words\": \"SaaS platforms\\nweb apps\\nmobile apps\\nMVPs\", \"caption_prefix\": \"We ship\", \"heading_accent\": \"SaaS, web and mobile\", \"eyebrow_message\": \"Now booking Q3 builds — 2 slots left\", \"marquee_eyebrow\": \"Trusted by teams that ship at\", \"heading_line_one\": \"We build scalable\", \"heading_line_two\": \"products.\", \"primary_cta_link\": \"#contact\", \"primary_cta_text\": \"Start a Project\", \"uptime_chip_text\": \"99.98% uptime\", \"eyebrow_badge_text\": \"Live\", \"secondary_cta_link\": \"#showreel\", \"secondary_cta_text\": \"Watch Showreel\", \"deploy_chip_version\": \"v2.4.1 · production\"}','Primary hero section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'Homepage About','homepage-about','home_about','published','{\"cta_link\": \"/about\", \"cta_text\": \"About Classic\", \"est_text\": \"EST · 2014 · SOFTIVUS\", \"intro_body\": \"Classic is a senior product team for founders and operators. From discovery to launch, we ship modern digital products with clean UI, scalable code, and a single delivery timeline.\", \"video_link\": \"https://www.youtube.com/watch?v=LXb3EKWsInQ\", \"video_year\": \"2024\", \"video_label\": \"Showreel · 2 min\", \"video_title\": \"How we ship products\", \"badge_number\": \"10+\", \"eyebrow_text\": \"About our company\", \"heading_accent\": \"SaaS, web, and mobile\", \"differentiators\": [{\"label\": \"Senior product team\"}, {\"label\": \"Engineering taste\"}, {\"label\": \"120+ products shipped\"}, {\"label\": \"Remote-first, global\"}], \"body_paragraph_1\": \"We focus on smart, efficient, high-performance digital products. Strong expertise across product strategy, UI/UX, web and mobile engineering, and platform integrations — combined into one team that owns delivery from kickoff to ship.\", \"body_paragraph_2\": \"Our work blends product strategy and engineering taste so the outcome looks the way it should and performs the way it must — for founders, operators, and growing companies.\", \"heading_line_one\": \"We build complete\", \"heading_line_two\": \"products — end to end.\", \"arch_caption_body\": \"Same senior engineers and designers from kickoff to launch — no junior handoffs.\", \"arch_caption_title\": \"A team that owns the outcome.\", \"arch_image_media_id\": null, \"differentiators_heading\": \"What makes us different\", \"video_card_image_media_id\": null}','About section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'Homepage Services','homepage-services','home_services','published','{\"cta_link\": \"#services\", \"cta_text\": \"Explore all services\", \"eyebrow_text\": \"Services\", \"heading_line_one\": \"Engineering teams\", \"heading_highlight\": \"that ship.\"}','Services section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'Homepage Stats','homepage-stats','home_stats_number','published','{\"card1_badge\": \"2x · YoY\", \"card1_label\": \"Annual success rate\", \"card2_badge\": \"1x+ · lifetime\", \"card2_label\": \"Projects completed\", \"card1_target\": \"98%\", \"card2_target\": \"23K\", \"background_image_media_id\": null}','Stats section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'Homepage Solutions','homepage-solutions','home_solutions','published','{\"subheading\": \"Whatever the model — SaaS, marketplace, mobile, fintech — we ship the right architecture for the way it actually has to grow.\", \"eyebrow_text\": \"Solutions\", \"heading_line_one\": \"One team,\", \"heading_highlight\": \"six product shapes.\"}','Solutions section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'Homepage Marquee','homepage-marquee','home_marquee','published','{\"phrases\": [{\"text\": \"Build with intent\"}, {\"text\": \"Ship like a senior team\"}, {\"text\": \"Design that survives engineering\"}, {\"text\": \"Engineered for the way it grows\"}, {\"text\": \"From discovery to launch\"}], \"badge_text\": \"SOFTIVUS · BUILD · SHIP · SOFTIVUS · BUILD · SHIP ·\"}','Marquee section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'Homepage Why Us','homepage-why-us','home_why_us','published','{\"pillars\": [{\"icon\": \"ph-users\", \"size\": \"hero\", \"color\": \"blue\", \"title\": \"A senior team, not an agency funnel.\", \"number\": \"01\", \"checklist\": \"No outsourcing layers\\n9+ years average experience\\nSame team start to finish\\n4–6 specialists per build\", \"stat_label\": \"\", \"stat_value\": \"\", \"description\": \"Same senior engineers and designers from kickoff to launch.\"}, {\"icon\": \"ph-lightning\", \"size\": \"std\", \"color\": \"green\", \"title\": \"Eight weeks, not eight quarters.\", \"number\": \"02\", \"checklist\": \"\", \"stat_label\": \"avg MVP timeline\", \"stat_value\": \"8 wks\", \"description\": \"Discovery to launch on one timeline.\"}, {\"icon\": \"ph-key\", \"size\": \"std\", \"color\": \"navy\", \"title\": \"You own the code and the keys.\", \"number\": \"03\", \"checklist\": \"\", \"stat_label\": \"repo + infra at handoff\", \"stat_value\": \"Yours\", \"description\": \"Your GitHub, your cloud, your secrets.\"}, {\"icon\": \"ph-arrows-clockwise\", \"size\": \"std\", \"color\": \"navy\", \"title\": \"Weekly demos, not quarterly reveals.\", \"number\": \"04\", \"checklist\": \"\", \"stat_label\": \"avg feedback loop\", \"stat_value\": \"7d\", \"description\": \"Working software every week.\"}, {\"icon\": \"ph-activity\", \"size\": \"std\", \"color\": \"blue\", \"title\": \"Observability built in, not bolted on.\", \"number\": \"05\", \"checklist\": \"\", \"stat_label\": \"telemetry live\", \"stat_value\": \"Day 1\", \"description\": \"Logs, traces, and analytics from day one.\"}, {\"icon\": \"ph-receipt\", \"size\": \"std\", \"color\": \"green\", \"title\": \"Honest pricing, no surprise invoices.\", \"number\": \"06\", \"checklist\": \"\", \"stat_label\": \"change-order quote\", \"stat_value\": \"24h\", \"description\": \"Fixed-scope sprints with a clear rate card.\"}, {\"icon\": \"ph-handshake\", \"size\": \"wide\", \"color\": \"blue\", \"title\": \"Built for the long arc, not just launch.\", \"number\": \"07\", \"checklist\": \"Same team for v2 and beyond\\nRetainer or pay-as-you-need\\nQuarterly roadmap reviews\\nDirect line to engineers\", \"stat_label\": \"\", \"stat_value\": \"\", \"description\": \"Same team for v2 and the year-two roadmap.\"}], \"subheading\": \"What separates Classic is what we don\'t do — no junior handoffs, no locked code, no quarter-long timelines, no surprise invoices.\", \"footer_text\": \"Sound like the team you\'d want to build with?\", \"eyebrow_text\": \"Why Classic\", \"footer_email\": \"support.com\", \"footer_cta_link\": \"#contact\", \"footer_cta_text\": \"Book a 30-min scope call\", \"heading_line_one\": \"Built like a senior product team.\", \"heading_line_two\": \"Priced like an honest one.\", \"footer_email_label\": \"24/7 Available\"}','Why choose us section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,'Homepage How Work','homepage-how-work','home_how_work','published','{\"phases\": [{\"icon\": \"ph-compass\", \"title\": \"Understand the why before the what.\", \"number\": \"01\", \"duration\": \"1 week\", \"description\": \"Stakeholder interviews, technical audit, and a written scope doc. We leave with a one-page brief and a milestone plan you can sign off on.\", \"phase_label\": \"Discovery\", \"deliverables\": \"Discovery brief\\nTechnical audit\\nMilestone plan\\nRisk register\"}, {\"icon\": \"ph-palette\", \"title\": \"Hi-fi designs ready to build, not pitch.\", \"number\": \"02\", \"duration\": \"2 weeks\", \"description\": \"Research-backed flows, design tokens, and motion specs in Figma. Every screen reviewed against the engineering plan so it survives the build.\", \"phase_label\": \"Design\", \"deliverables\": \"Hi-fi mockups\\nDesign system tokens\\nInteraction specs\\nAccessibility audit\"}, {\"icon\": \"ph-code\", \"title\": \"Working software every week, not every quarter.\", \"number\": \"03\", \"duration\": \"4–6 weeks\", \"description\": \"Two-week sprints with a public preview environment. Telemetry from sprint one. Demo every Friday with a clear changelog.\", \"phase_label\": \"Build\", \"deliverables\": \"Preview environment\\nWeekly demos\\nTelemetry pipeline\\nContinuous deploys\"}, {\"icon\": \"ph-rocket\", \"title\": \"Production-ready, observable, and yours.\", \"number\": \"04\", \"duration\": \"1 week\", \"description\": \"Deploy to your cloud, your domain, your repo. Runbook, on-call rotation, and a post-launch report on day three.\", \"phase_label\": \"Launch\", \"deliverables\": \"Production deploy\\nRunbook + alerts\\nDay-3 launch report\\nCode handover\"}, {\"icon\": \"ph-trend-up\", \"title\": \"Same team, year-two roadmap.\", \"number\": \"05\", \"duration\": \"Ongoing\", \"description\": \"Quarterly roadmap reviews, retainer or pay-as-you-need engineering, and direct-line access to the team that shipped v1.\", \"phase_label\": \"Scale\", \"deliverables\": \"Quarterly review\\nRetainer engineering\\nRoadmap planning\\nOn-call support\"}], \"subheading\": \"From discovery to year-two roadmap — the same engineers and designers from kickoff to scale, with a clear deliverable at every step.\", \"eyebrow_text\": \"How we work\", \"heading_line_one\": \"Five phases.\", \"heading_line_two\": \"One team. One timeline.\"}','Process section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,'Homepage Stack','homepage-stack','home_stack','published','{\"subheading\": \"Every technology earns its place. No bloat, no trend-chasing — just the stack that ships fastest and scales longest.\", \"eyebrow_text\": \"Technology stack\", \"heading_line_one\": \"The tools we ship with —\", \"heading_highlight\": \"every one, opinionated.\"}','Technology stack section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,'Homepage Testimonials','homepage-testimonials','home_testimonials','published','{\"subheading\": \"A decade of senior product engineering — measured in launches, retention, and the long arc of teams we still work with.\", \"eyebrow_text\": \"What teams say\", \"heading_line_one\": \"Trusted by founders\", \"heading_line_two\": \"who actually shipped.\"}','Testimonials section for the homepage.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'Homepage Blog','homepage-blog','home_blog','published','{\"cta_link\": \"/blog\", \"cta_text\": \"View all posts\", \"subheading\": \"Practical WhatsApp automation, CRM, broadcast, and chatbot ideas for teams that want better customer conversations.\", \"eyebrow_text\": \"From the Blog\", \"heading_line_one\": \"Insights &\", \"heading_highlight\": \"articles\"}','Homepage blog preview section.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(12,'Global FAQ','global-faq','global_faq','published','{\"subheading\": \"The questions founders ask in the first 30-minute scope call.\", \"call_card_body\": \"30-minute scope call with someone who would actually build it. Usually within 48 hours.\", \"call_card_title\": \"Talk to a senior engineer\", \"email_card_body\": \"Send a one-pager and we\'ll come back with a written response within two working days.\", \"email_card_email\": \"hello.com\", \"email_card_title\": \"Drop us a brief\", \"heading_line_one\": \"Common questions,\", \"heading_line_two\": \"clear answers.\", \"call_card_cta_link\": \"#book-call\", \"call_card_cta_text\": \"Book a call\", \"call_card_engineers_text\": \"8 senior engineers · all on call\"}','Reusable FAQ section across the frontend.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(13,'Blog Hero','blog-hero','blog_hero','published','{\"heading\": \"WhatsApp marketing insights for growing teams\", \"subheading\": \"Guides on automation, broadcasts, chatbots, CRM workflows, and customer messaging that feels personal at scale.\", \"eyebrow_text\": \"WaPro Blog\"}','Hero section for the blog page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(14,'Blog Featured','blog-featured','blog_featured','published','{\"heading\": \"Start with the latest playbook\", \"eyebrow_text\": \"Featured\"}','Featured articles section for the blog page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(15,'Blog Archive','blog-archive','blog_archive','published','{\"heading\": \"All articles\", \"subheading\": \"Browse practical guides for WhatsApp marketing, support automation, campaign operations, and customer growth.\", \"eyebrow_text\": \"Archive\"}','All articles grid section for the blog page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(16,'Blog Newsletter','blog-newsletter','blog_newsletter','published','{\"heading\": \"Get WhatsApp growth notes in your inbox\", \"subheading\": \"Practical automation, CRM, and campaign ideas for teams that use messaging every day.\", \"button_text\": \"Subscribe\", \"eyebrow_text\": \"Newsletter\"}','Newsletter signup section for the blog page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(17,'FAQ Hero','faq-hero','faq_hero','published','{\"eyebrow\": \"FAQ\", \"description\": \"Clear answers about process, pricing, collaboration, ownership, and support.\", \"heading_line\": \"Questions teams ask\", \"heading_highlight\": \"before they hire us.\"}','Hero section for the FAQ page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(18,'Service Category Hero','service-category-hero','service_category_hero','published','{\"heading\": \"\", \"badge_text\": \"\", \"subheading\": \"\", \"stat_1_label\": \"Products Shipped\", \"stat_1_value\": \"120+\", \"stat_2_label\": \"Average MVP\", \"stat_2_value\": \"8 wks\", \"stat_3_label\": \"Client Rating\", \"stat_3_value\": \"4.9/5\", \"stat_4_label\": \"Years Experience\", \"stat_4_value\": \"10+\", \"eyebrow_label\": \"\", \"eyebrow_suffix\": \"\", \"primary_cta_link\": \"#contact\", \"primary_cta_text\": \"Start a Project\", \"secondary_cta_link\": \"#services\", \"secondary_cta_text\": \"Our Services\", \"hero_image_media_id\": null}','Hero section for service category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(19,'Service Category Services','service-category-services','service_category_services','published','{\"cta_link\": \"#contact\", \"cta_text\": \"Get a Project Estimate\", \"section_heading\": \"\", \"section_subheading\": \"\"}','Services grid for service category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(20,'Service Category Why Us','service-category-why-us','service_category_why_us','published','{\"items\": [], \"stat_1_label\": \"Client rating\", \"stat_1_value\": \"4.9/5\", \"stat_2_label\": \"Retention rate\", \"stat_2_value\": \"98%\", \"section_heading\": \"\", \"section_subheading\": \"\", \"why_image_media_id\": null}','Why choose us section for service category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(21,'Service Category Process','service-category-process','service_category_process','published','{\"steps\": [], \"kicker_stat\": \"\", \"section_heading\": \"\", \"section_subheading\": \"\"}','Process steps section for service category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(22,'Project Category Hero','project-category-hero','project_category_hero','published','{\"heading\": \"\", \"subheading\": \"\", \"stat_1_label\": \"Projects shipped\", \"stat_1_value\": \"80+\", \"stat_2_label\": \"Avg. MVP\", \"stat_2_value\": \"8 wks\", \"stat_3_label\": \"Uptime SLA\", \"stat_3_value\": \"99.9%\", \"eyebrow_label\": \"\", \"primary_cta_link\": \"#contact\", \"primary_cta_text\": \"Start your project\", \"floating_tech_text\": \"\", \"secondary_cta_link\": \"#case-studies\", \"secondary_cta_text\": \"See case studies\", \"floating_stat_label\": \"\", \"floating_stat_value\": \"\", \"mosaic_primary_media_id\": null, \"mosaic_tertiary_media_id\": null, \"mosaic_secondary_media_id\": null}','Hero section for project category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(23,'Project Category Projects','project-category-projects','project_category_projects','published','{\"eyebrow_text\": \"Featured work\", \"section_heading\": \"\", \"section_subheading\": \"\"}','Project showcase section for project category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(24,'Project Category Stack','project-category-stack','project_category_stack','published','{\"items\": [], \"eyebrow_text\": \"Tech stack\", \"section_heading\": \"\"}','Stack section for project category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(25,'Project Category Process','project-category-process','project_category_process','published','{\"steps\": [], \"result_text\": \"\", \"eyebrow_text\": \"How we work\", \"result_title\": \"\", \"section_heading\": \"\", \"section_subheading\": \"\", \"process_image_media_id\": null}','Process section for project category detail pages.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(26,'Global Contact','global-contact','global_contact','published','{\"email\": \"hello.com\", \"cal_link\": \"pixelaxis/idea-sprint\", \"cal_namespace\": \"idea-sprint\", \"whatsapp_link\": \"https://wa.me/000000000\", \"section_heading\": \"Let\'s Talk About Your Project\", \"section_subheading\": \"Share your project idea and we\'ll get back to you within one business day with a free scoping estimate — no obligation.\"}','Shared contact section usable on any page across the frontend.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(27,'Team Hero','team-hero','team_hero','published','{\"eyebrow\": \"Our Team\", \"description\": \"A focused team of engineers, designers, and product thinkers united by a passion for building exceptional digital products that ship fast and last long.\", \"stat_rating\": \"4.9★\", \"heading_line_one\": \"The people who\", \"primary_cta_link\": \"/careers\", \"primary_cta_text\": \"Join the team\", \"heading_highlight\": \"make it happen.\", \"stat_rating_label\": \"Client rating\", \"secondary_cta_link\": \"/about\", \"secondary_cta_text\": \"About Classic\", \"stat_members_count\": \"42\", \"stat_members_label\": \"Team members\", \"stat_countries_count\": \"8\", \"stat_countries_label\": \"Countries\", \"stat_products_shipped\": \"120+\", \"mosaic_image_1_media_id\": null, \"mosaic_image_2_media_id\": null, \"mosaic_image_3_media_id\": null, \"stat_products_shipped_label\": \"Products shipped\"}','Hero section for the team page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(28,'Team Members','team-members','team_members','published','{\"eyebrow\": \"Our Team\", \"description\": \"Engineers, designers, product thinkers, and operators — every person here is a domain expert who cares deeply about craft.\", \"heading_line_one\": \"The people who\", \"heading_highlight\": \"make it happen.\"}','Grid showing team members.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(29,'Team Culture','team-culture','team_culture','published','{\"items\": [{\"icon\": \"ph-lightning\", \"color\": \"#2148ff\", \"title\": \"Ship Fast\", \"description\": \"We value working software over perfect plans. Iterate, learn, repeat.\"}, {\"icon\": \"ph-shield-check\", \"color\": \"#00c875\", \"title\": \"Deep Ownership\", \"description\": \"Everyone owns their work end-to-end — no hand-offs, no silos.\"}, {\"icon\": \"ph-chat\", \"color\": \"#8b42f5\", \"title\": \"Radical Candor\", \"description\": \"Honest feedback delivered with care. We grow through real conversations.\"}, {\"icon\": \"ph-globe\", \"color\": \"#ea6c28\", \"title\": \"Remote-First\", \"description\": \"Work from anywhere. We built the processes to make async the default.\"}], \"eyebrow\": \"Our culture\", \"stat_label\": \"Remote & async-first team\", \"stat_value\": \"100%\", \"description\": \"We\'re remote-first, async by default, and obsessively outcome-focused. No unnecessary meetings, no bloated processes — just deep work, fast feedback loops, and genuine ownership.\", \"heading_line_one\": \"How we work\", \"image_1_media_id\": null, \"image_2_media_id\": null, \"heading_highlight\": \"every day.\"}','Culture and values section for the team page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(30,'Team Presence','team-presence','team_presence','published','{\"items\": [{\"icon\": \"ph-users\", \"color\": \"#2148ff\", \"label\": \"Team members\", \"value\": \"42\"}, {\"icon\": \"ph-globe\", \"color\": \"#00c875\", \"label\": \"Countries\", \"value\": \"8\"}, {\"icon\": \"ph-star\", \"color\": \"#8b42f5\", \"label\": \"Client rating\", \"value\": \"4.9★\"}, {\"icon\": \"ph-briefcase\", \"color\": \"#ea6c28\", \"label\": \"Products shipped\", \"value\": \"120+\"}]}','Global presence stats section for the team page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(31,'Team Roles','team-roles','team_roles','published','{\"eyebrow\": \"We\'re hiring\", \"cta_link\": \"/careers\", \"cta_text\": \"View all careers\", \"heading_line_one\": \"Open positions\", \"heading_highlight\": \"right now.\"}','Open positions section for the team page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(32,'Careers Hero','careers-hero','careers_hero','published','{\"eyebrow\": \"We\'re hiring\", \"stat_label\": \"Products\\nshipped\", \"stat_value\": \"60+\", \"description\": \"Join a team that values your ideas, supports creativity, and empowers you to build meaningful products — celebrating every milestone along the way.\", \"avatar_count\": \"28+\", \"heading_line_one\": \"Shape your future with\", \"primary_cta_link\": \"#open-roles\", \"primary_cta_text\": \"View open positions\", \"heading_highlight\": \"growth & impact.\", \"mosaic_image_1_media_id\": null, \"mosaic_image_2_media_id\": null, \"mosaic_image_3_media_id\": null, \"mosaic_image_4_media_id\": null}','Hero section for the careers page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(33,'Careers Life','careers-life','careers_life','published','{\"items\": [{\"icon\": \"ph-lightning\", \"title\": \"Ships fast\", \"description\": \"We move in weeks, not quarters. No endless planning cycles.\"}, {\"icon\": \"ph-chat-slash\", \"title\": \"Async by default\", \"description\": \"No pointless standups. Decisions happen in writing, not meetings.\"}, {\"icon\": \"ph-shield-slash\", \"title\": \"No politics\", \"description\": \"Flat structure, direct feedback, and zero org-chart theatre.\"}, {\"icon\": \"ph-users\", \"title\": \"Real teamwork\", \"description\": \"Designers, engineers, and PMs work together from day one.\"}]}','Life at Classic section for the careers page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(34,'Careers Perks','careers-perks','careers_perks','published','{\"items\": [{\"icon\": \"ph-monitor\", \"color\": \"blue\", \"title\": \"Remote-first forever\", \"description\": \"Work from anywhere. Async-friendly culture with no mandatory standups and no time-zone penalties.\"}, {\"icon\": \"ph-trend-up\", \"color\": \"green\", \"title\": \"Real ownership\", \"description\": \"You lead features end-to-end. No ticket monkeys — every person here shapes how the product gets built.\"}, {\"icon\": \"ph-book-open\", \"color\": \"navy\", \"title\": \"Learning budget\", \"description\": \"$1,200/year for courses, books, and conferences. We pay for you to get better at your craft.\"}, {\"icon\": \"ph-clock\", \"color\": \"blue\", \"title\": \"Flexible hours\", \"description\": \"We care about output, not clock time. Build your schedule around your life, not the other way around.\"}, {\"icon\": \"ph-laptop\", \"color\": \"green\", \"title\": \"Equipment stipend\", \"description\": \"$2,000 setup budget on day one. Get the gear you need to do your best work.\"}, {\"icon\": \"ph-airplane\", \"color\": \"navy\", \"title\": \"Annual offsite\", \"description\": \"Once a year the whole team meets in person. Past locations: Lisbon, Tbilisi, Medellín.\"}], \"eyebrow\": \"Why join us\", \"heading_line_one\": \"What you get when\", \"heading_highlight\": \"you join the team.\"}','Benefits and perks section for the careers page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(35,'Careers Process','careers-process','careers_process','published','{\"steps\": [{\"title\": \"Submit Your Application\", \"number\": \"01\", \"description\": \"Share your resume and tell us why you\'d be a great fit. We read every single one.\"}, {\"title\": \"Initial Screening Call\", \"number\": \"02\", \"description\": \"A quick 30-min chat to understand your background, goals, and role alignment — no prep needed.\"}, {\"title\": \"Technical / Skill Interview\", \"number\": \"03\", \"description\": \"Showcase your craft. Any take-home work we ask for is compensated — your time is valuable.\"}, {\"title\": \"Culture Fit & Offer\", \"number\": \"04\", \"description\": \"We align on values, expectations, and compensation — then move fast. No ghosting, ever.\"}], \"eyebrow\": \"Our hiring process\", \"heading_line_one\": \"A simple, clear journey\", \"heading_highlight\": \"to your next role.\", \"process_image_media_id\": null}','Hiring process section for the careers page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(36,'Careers Roles','careers-roles','careers_roles','published','{\"roles\": [], \"eyebrow\": \"Open positions\", \"heading\": \"4 roles open right now\", \"subheading\": \"All roles are fully remote. Compensation is competitive and location-independent.\", \"hiring_team\": [], \"no_fit_title\": \"Don\'t see a match?\", \"no_fit_suffix\": \"— we keep great people on file.\", \"meet_team_title\": \"Meet the hiring team\", \"no_fit_link_url\": \"mailto:careers.com\", \"no_fit_link_text\": \"Send us your CV anyway\"}','Open positions listing section for the careers page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(37,'About Hero','about-hero','about_hero','published','{\"eyebrow\": \"About Our Company\", \"features\": [], \"description\": \"At Classic, we bring innovation, logic, and design together to build powerful digital solutions. As a product studio, we focus on creating results-driven software that matches real-world needs — from early planning all the way to launch.\", \"metric_title\": \"Faster time-to-market\", \"metric_value\": \"2.6x\", \"heading_line_one\": \"Your Trusted Digital Partner for Smarter\", \"primary_cta_link\": \"#contact\", \"primary_cta_text\": \"Start Your Project\", \"heading_highlight\": \"Software & Products\", \"metric_description\": \"Streamlined process, faster delivery, no quality trade-offs.\", \"secondary_cta_link\": \"#our-story\", \"secondary_cta_text\": \"Our story\", \"hero_image_media_id\": null}','Hero section for the about us page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(38,'About Story','about-story','about_story','published','{\"tags\": [{\"text\": \"Remote-first since day one\"}, {\"text\": \"No offshore hand-offs\"}, {\"text\": \"Fixed-scope, fixed-price\"}], \"eyebrow\": \"Who we are\", \"heading\": \"A studio obsessed with craft and clarity\", \"metrics\": [{\"icon\": \"ph-calendar\", \"color\": \"blue\", \"label\": \"Founded — fully bootstrapped\", \"value\": \"2018\"}, {\"icon\": \"ph-check\", \"color\": \"blue\", \"label\": \"Products shipped\", \"value\": \"60+\"}, {\"icon\": \"ph-users\", \"color\": \"blue\", \"label\": \"People on the team\", \"value\": \"28\"}, {\"icon\": \"ph-percent\", \"color\": \"navy\", \"label\": \"Client retention rate\", \"value\": \"98%\"}, {\"icon\": \"ph-star\", \"color\": \"brand\", \"label\": \"Average client rating\", \"value\": \"4.9/5\"}], \"body_paragraph_one\": \"Classic started as a two-person consultancy with one conviction: most software fails because teams rush to code before they understand the problem. We took the opposite bet — start slow, think hard, then build fast.\", \"body_paragraph_two\": \"Today we\'re a 28-person studio spanning product design, full-stack engineering, and DevOps. We\'ve shipped 60+ products across fintech, health-tech, logistics, and SaaS — always as embedded partners, never as body-shop contractors.\"}','Story section for the about us page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(39,'About Values','about-values','about_values','published','{\"items\": [{\"icon\": \"ph-eye\", \"color\": \"blue\", \"title\": \"Radical clarity\", \"description\": \"No jargon, no hand-waving. We write specs in plain language, explain trade-offs in plain English, and never hide behind complexity.\"}, {\"icon\": \"ph-lightning\", \"color\": \"green\", \"title\": \"Speed with substance\", \"description\": \"We move fast without cutting corners. Every sprint ends with shippable, tested code — not a pile of tech debt to clean up later.\"}, {\"icon\": \"ph-shield-check\", \"color\": \"navy\", \"title\": \"Full ownership\", \"description\": \"We act like founders, not vendors. That means flagging risk early, saying no when something won\'t work, and treating your roadmap like our own.\"}, {\"icon\": \"ph-chat-circle\", \"color\": \"blue\", \"title\": \"Open by default\", \"description\": \"Weekly demos, async Slack updates, and a Notion board you can always read. You never have to chase us for status.\"}, {\"icon\": \"ph-sparkle\", \"color\": \"green\", \"title\": \"Uncompromised quality\", \"description\": \"We QA everything. Every screen, every edge case, every integration. If it ships with our name on it, it works.\"}, {\"icon\": \"ph-handshake\", \"color\": \"navy\", \"title\": \"Long-term thinking\", \"description\": \"We\'re not optimizing for the invoice — we\'re optimizing for your next funding round, your next million users, your next decade.\"}], \"eyebrow\": \"What drives us\", \"subheading\": \"We don\'t hang our values on a wall — we bake them into how we scope, build, and ship.\", \"heading_line_one\": \"Values that shape\", \"heading_highlight\": \"every decision.\"}','Values section for the about us page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(40,'About Team','about-team','about_team','published','{\"eyebrow\": \"The team\", \"description\": \"A tight-knit group of designers, engineers, and strategists — distributed across 8 countries.\", \"hiring_title\": \"We\'re hiring — 4 open roles\", \"hiring_cta_link\": \"#careers\", \"hiring_cta_text\": \"View open roles\", \"heading_line_one\": \"Meet the people\", \"heading_highlight\": \"behind the products.\", \"hiring_description\": \"Join a team that ships real products for real people.\"}','Team section for the about us page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(41,'Support Hero','support-hero','support_hero','published','{\"eyebrow\": \"Support\", \"heading\": \"Welcome to Classic Support.\", \"hours_days\": \"Saturday to Thursday\", \"hours_time\": \"5:00AM – 2:00PM (GMT)\", \"access_note\": \"To keep our support system efficient and seamless and to keep your data safe and secure, we only keep this page accessible for registered users.\", \"badge_label\": \"Classic Help Center\", \"description\": \"We put special emphasis on customer support. Our dedicated support team is waiting to assist you. We always try to give you a better support experience.\", \"hours_holiday\": \"Friday is our weekly holiday!\", \"login_cta_text\": \"Login\", \"register_cta_text\": \"Register\"}','Hero section for the support page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(42,'Support Channels','support-channels','support_channels','published','{\"eyebrow\": \"How to Reach Us\", \"heading\": \"Multiple Ways to Get Help\", \"channels\": [{\"icon\": \"message-circle\", \"color\": \"blue\", \"title\": \"Live Chat\", \"cta_link\": \"#\", \"cta_text\": \"Start a Chat\", \"features\": \"Avg. response under 5 minutes\\nScreen sharing available\\nFile attachment supported\", \"description\": \"Chat with our support agents in real time. Fastest response for urgent issues and quick questions.\"}, {\"icon\": \"mail\", \"color\": \"green\", \"title\": \"Submit a Ticket\", \"cta_link\": \"#\", \"cta_text\": \"Open Ticket\", \"features\": \"Reply within 24 hours\\nFull issue tracking & history\\nPriority escalation available\", \"description\": \"Open a formal support ticket for detailed issues. We track every ticket until it\'s fully resolved.\"}, {\"icon\": \"book-open\", \"color\": \"navy\", \"title\": \"Knowledge Base\", \"cta_link\": \"#\", \"cta_text\": \"Browse Docs\", \"features\": \"200+ articles & tutorials\\nVideo walkthroughs included\\nUpdated weekly\", \"description\": \"Browse our comprehensive documentation. Find step-by-step guides, tutorials, and answers 24/7.\"}], \"description\": \"Choose the channel that works best for you. Our team is trained to resolve issues quickly and keep you informed every step of the way.\"}','Contact channel cards for the support page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(43,'Support Why','support-why','support_why','published','{\"rating\": \"4.9\", \"eyebrow\": \"Why Choose Us\", \"heading\": \"Support You Can Actually Rely On\", \"description\": \"We don\'t just answer tickets — we solve problems. Our team is trained, empowered, and committed to your success.\", \"rating_count\": \"Based on 2,800+ reviews\", \"rating_label\": \"Average Support Rating\", \"feature_cards\": [{\"icon\": \"zap\", \"color\": \"blue\", \"title\": \"Fast First Response\", \"position\": \"left\", \"description\": \"Live chat replies in under 5 minutes during business hours. Tickets acknowledged within 1 hour.\"}, {\"icon\": \"shield-check\", \"color\": \"green\", \"title\": \"Secure & Private\", \"position\": \"left\", \"description\": \"All support interactions are encrypted. Your project data is never shared or accessed without permission.\"}, {\"icon\": \"users\", \"color\": \"blue\", \"title\": \"Dedicated Account Support\", \"position\": \"right\", \"description\": \"Enterprise clients get a dedicated account manager who knows your project inside and out.\"}, {\"icon\": \"globe\", \"color\": \"green\", \"title\": \"Multi-Language Support\", \"position\": \"right\", \"description\": \"Our team speaks English, Arabic, and Bengali — serving clients across Asia, the Middle East, and beyond.\"}], \"happy_clients\": \"23K+\", \"stat_one_label\": \"Satisfaction rate\", \"stat_one_value\": \"98%\", \"stat_two_label\": \"Avg. first reply\", \"stat_two_value\": \"< 5 min\", \"tickets_resolved\": \"10K+\"}','Why our support section for the support page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(44,'Support CTA','support-cta','support_cta','published','{\"heading\": \"Still Have Questions? We\'re Here to Help.\", \"badge_label\": \"Support Team Ready\", \"description\": \"Log in to access the full support portal or create an account to get started in under a minute.\", \"login_cta_text\": \"Login to Support Portal\", \"register_cta_text\": \"Create Free Account\"}','Dark CTA banner for the support page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(45,'Contact Page Hero','contact-page-hero','contact_page_hero','published','{\"eyebrow\": \"Get in touch\", \"description\": \"Whether you have a fully scoped project or just an early idea — reach out. We respond within 48 hours and our first call is always free.\", \"heading_line\": \"Let\'s build something\", \"trust_signals\": [{\"icon\": \"ph-clock\", \"color\": \"green\", \"label\": \"Reply in 48 hrs\", \"detail\": \"— guaranteed\"}, {\"icon\": \"ph-calendar\", \"color\": \"blue\", \"label\": \"Free scope call\", \"detail\": \"— 30 minutes, no pitch\"}, {\"icon\": \"ph-shield-check\", \"color\": \"blue\", \"label\": \"NDA available\", \"detail\": \"— on request\"}], \"heading_highlight\": \"great together.\"}','Hero section for the contact page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(46,'Contact Page Form','contact-page-form','contact_page_form','published','{\"email\": \"hello.com\", \"phone\": \"+1 (415) 555-1234\", \"phone_href\": \"tel:+14155551234\", \"form_heading\": \"Start the conversation\", \"info_heading\": \"Contact information\", \"business_hours\": \"Mon – Fri, 9 am – 6 pm PST\\nAsync replies on weekends\", \"office_address\": \"340 Pine Street, Suite 800\\nSan Francisco, CA 94104, USA\", \"form_subheading\": \"We\'ll get back to you within 48 hours.\", \"info_description\": \"We\'re a distributed team — but never hard to reach. Pick the channel that works best for you.\"}','Contact information and enquiry form for the contact page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(47,'Contact Page Offices','contact-page-offices','contact_page_offices','published','{\"eyebrow\": \"Our offices\", \"heading\": \"We\'re distributed — but always nearby.\", \"offices\": [{\"icon\": \"building-2\", \"meta\": \"HQ · PST (UTC-8)\", \"name\": \"San Francisco, USA\", \"color\": \"blue\", \"address\": \"340 Pine Street, Suite 800\\nCA 94104\"}, {\"icon\": \"building-2\", \"meta\": \"EU Hub · GMT (UTC+0)\", \"name\": \"London, UK\", \"color\": \"blue\", \"address\": \"12 Finsbury Square\\nLondon EC2A 1AR\"}, {\"icon\": \"globe\", \"meta\": \"Always async-ready\", \"name\": \"Remote-first team\", \"color\": \"green\", \"address\": \"Engineers & designers spread across 12 countries.\"}]}','Office locations grid for the contact page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(48,'Home Hero','home-hero','marketing_hero','published','{\"images\": [{\"alt\": \"A team collaborating on customer messaging\", \"url\": \"http://localhost:8000/assets/wapro/images/hero-tedy-1.webp\"}, {\"alt\": \"Two colleagues reviewing campaign results\", \"url\": \"http://localhost:8000/assets/wapro/images/hero-tedy-2.webp\"}, {\"alt\": \"A happy customer support team\", \"url\": \"http://localhost:8000/assets/wapro/images/hero-tedy-3.webp\"}], \"eyebrow\": \"WhatsApp Marketing Platform\", \"subheading\": \"Bulk sends. Chatbots. Real results.\\nLive in minutes.\", \"heading_accent\": \"right.\", \"heading_line_1\": \"WhatsApp\", \"heading_line_2\": \"campaigns, done\", \"cta_primary_url\": \"http://localhost:8000/login\", \"cta_primary_text\": \"Start for free\", \"cta_secondary_url\": \"http://localhost:8000/features\", \"cta_secondary_text\": \"Watch a tour\"}','Hero section for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(49,'Brand Marquee','brand-marquee','marketing_marquee','published','{\"items\": [{\"text\": \"Bulk campaigns\", \"accent\": false}, {\"text\": \"Auto replies\", \"accent\": true}, {\"text\": \"Smart chatbots\", \"accent\": false}, {\"text\": \"Real results\", \"accent\": true}]}','Brand statement marquee for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(50,'Product Modules','product-modules','marketing_modules','published','{\"eyebrow\": \"Product modules\", \"heading\": \"Everything you need to run WhatsApp at scale\", \"modules\": [{\"label\": \"Bulk Campaigns\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Launch high-volume WhatsApp campaigns to segmented audiences with timing and delivery under control.\"}, {\"label\": \"Auto Reply\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Trigger automatic responses for inbound messages, off-hours support and lead capture by keyword.\"}, {\"label\": \"AI Smart Reply\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 2a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 0 1 0 4h-1v3a2 2 0 0 1-2 2h-3v1a2 2 0 0 1-4 0v-1H7a2 2 0 0 1-2-2v-3H4a2 2 0 0 1 0-4h1V7a2 2 0 0 1 2-2h3V4a2 2 0 0 1 2-2z\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Generate fast, context-aware replies so agents handle conversations with less manual effort.\"}, {\"label\": \"Chatbot\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 8V4m0 4a4 4 0 0 0-4 4v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a4 4 0 0 0-4-4zM9 14h.01M15 14h.01\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Build conversational flows that qualify leads and move contacts to the next step automatically.\"}, {\"label\": \"Contacts\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Manage lists, segments and campaign targets from one structured database built for WhatsApp.\"}, {\"label\": \"Export Participants\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 3v12m0 0 4-4m-4 4-4-4M4 21h16\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Extract participants from WhatsApp groups for outreach, qualification and audience building.\"}, {\"label\": \"Profile Info\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Review WhatsApp profile details quickly to enrich lead context and improve handoff quality.\"}, {\"label\": \"Reports\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 3v18h18M7 14l3-3 3 3 5-6\\\"/></svg>\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Track campaign output, reply activity and workflow performance with operational reporting.\"}], \"subheading\": \"Each module supports a real operational job — from outreach and automation to contacts and reporting.\"}','Product modules accordion for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(51,'Feature Spotlight','feature-spotlight','marketing_spotlight','published','{\"stats\": {\"ctr\": \"11.4%\", \"read\": \"86%\", \"sent\": \"104.7k\", \"change\": \"+78%\", \"failed\": \"0.04%\"}, \"steps\": [{\"title\": \"Track every recipient\", \"description\": \"Per-recipient delivery, read and reply tracking across every campaign — see exactly who engaged, who slipped through, and which contacts need a timely follow-up. Your team can review each send status in one place, spot delivery issues earlier, and avoid wasting time on manual checks after every broadcast. When a campaign underperforms, the activity trail makes it easier to separate message quality, audience fit, and delivery problems before the next send goes out.\"}, {\"title\": \"Know who engaged\", \"description\": \"A clear activity view turns raw sends into people: replies, reads and deliveries per contact, so sales and support teams can prioritize the conversations most likely to convert. Use those signals to segment warm leads, re-engage quiet contacts, and hand active conversations to the right teammate faster. Instead of treating every contact the same, teams can build follow-up lists from real behavior and keep customer conversations moving with better context.\"}, {\"title\": \"Test & auto-pick the winner\", \"description\": \"A/B test templates, compare reply rates, and let the winner promote itself automatically. Export the full report when the team needs proof for the next campaign plan, then reuse the strongest message style for future launches, reminders, and follow-up sequences. Over time, the reporting view becomes a playbook of what your audience responds to, helping every new campaign start from proven messaging instead of guesswork.\"}], \"cta_url\": \"http://localhost:8000/features\", \"eyebrow\": \"Visibility\", \"heading\": \"Visual insights for data-driven campaigns\", \"cta_text\": \"See all features\", \"recipients\": [{\"name\": \"Aisha Rahman\", \"status\": \"Replied\"}, {\"name\": \"Theo Sullivan\", \"status\": \"Read\"}, {\"name\": \"Nadia B.\", \"status\": \"Read\"}, {\"name\": \"Diego R.\", \"status\": \"Delivered\"}], \"subheading\": \"See delivery, read and reply rates across every campaign and device.\"}','Feature spotlight with sticky visuals for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(52,'How It Works','how-it-works','marketing_how_works','published','{\"steps\": [{\"title\": \"Import & segment\", \"number\": \"01\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 7h18M3 12h18M3 17h12\\\"/></svg>\", \"description\": \"Bring contacts in via CSV or sync, then group them into reusable audiences and tags.\"}, {\"title\": \"Automate & send\", \"number\": \"02\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M13 2 3 14h7l-1 8 10-12h-7l1-8z\\\"/></svg>\", \"description\": \"Launch bulk campaigns or let chatbots and auto-replies handle inbound conversations.\"}, {\"title\": \"Track & grow\", \"number\": \"03\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 3v18h18M7 14l3-3 3 3 5-6\\\"/></svg>\", \"description\": \"Measure delivery, read and reply outcomes, then export participants for new growth loops.\"}], \"eyebrow\": \"How it works\", \"heading\": \"Designed as one system, not a pile of tools\", \"subheading\": \"Contacts feed campaigns, bots reduce manual load, and reports show what\'s actually working.\"}','How it works steps for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(53,'Use Cases','use-cases','marketing_use_cases','published','{\"cases\": [{\"title\": \"Outbound Marketing\", \"bullets\": [\"Segmented lists & reusable audiences\", \"Bulk campaigns with delivery tracking\"], \"eyebrow\": \"Growth\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Build segmented lists, run bulk campaigns, track responses, and improve conversion using report data.\", \"mockup_data\": {\"stats\": [{\"label\": \"Sent\", \"value\": \"42.1k\"}, {\"label\": \"Read\", \"value\": \"86%\"}, {\"label\": \"Replies\", \"value\": \"11.4%\"}], \"status\": \"Sent\", \"campaign_name\": \"Campaign · Spring Sale\"}, \"layout_direction\": \"text_left\"}, {\"title\": \"Inbound Automation\", \"bullets\": [\"Keyword auto-replies, 24/7\", \"Chatbot flows that qualify leads\"], \"eyebrow\": \"Support\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Use chatbots and auto-replies to handle repetitive conversations without keeping agents on every message.\", \"mockup_data\": {\"status\": \"online\", \"bot_name\": \"Auto-reply bot\", \"messages\": [\"Hi! Is the House Blend back in stock? ☕\", \"Yes! Reply ORDER to grab a bag ✅\", \"Order placed — shipping today 🎉\"]}, \"layout_direction\": \"text_right\"}, {\"title\": \"Performance Visibility\", \"bullets\": [\"Exportable reports for the whole team\", \"Participant export for new growth loops\"], \"eyebrow\": \"Operations\", \"link_url\": \"http://localhost:8000/features\", \"link_text\": \"Learn more\", \"description\": \"Use profile lookups, reports and participant export to move from scattered chats to measurable workflows.\", \"mockup_data\": {\"change\": \"+78%\", \"delivered\": \"104.7k\"}, \"layout_direction\": \"text_left\"}], \"eyebrow\": \"Use cases\", \"heading\": \"For growth, support & daily operations\"}','Use cases section for the WaPro home page.','[]',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(54,'Home FAQ','home-faq','marketing_faq','published','{\"items\": [{\"answer\": \"You can connect an existing WABA, or we help you provision one via Meta\'s embedded signup. WaPro uses the official WhatsApp Cloud API only.\", \"question\": \"Do I need a WhatsApp Business API account to start?\"}, {\"answer\": \"Templates are submitted straight to Meta and typically approve within minutes. Our linter flags likely-rejection issues before you submit.\", \"question\": \"How fast are templates approved?\"}, {\"answer\": \"Yes — import via CSV with column mapping and de-duplication, then segment and tag for campaigns.\", \"question\": \"Can I import my existing contacts?\"}, {\"answer\": \"Yes. The Starter plan is free and includes 10,000 messages per month with auto-reply and chatbot.\", \"question\": \"Is there a free plan?\"}], \"eyebrow\": \"FAQ\", \"heading\": \"Frequently asked questions\"}','FAQ section for the WaPro home page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(55,'Home CTA','home-cta','marketing_cta','published','{\"eyebrow\": \"Limited-time onboarding\", \"heading\": \"Build your WhatsApp workflow on one system\", \"subheading\": \"Launch campaigns, automate replies, manage contacts, and track performance from one workspace.\", \"cta_primary_url\": \"http://localhost:8000/login\", \"background_image\": \"http://localhost:8000/assets/wapro/images/hero-tedy-1.webp\", \"cta_primary_text\": \"Create your workspace\", \"cta_secondary_url\": \"http://localhost:8000/pricing\", \"cta_secondary_text\": \"View pricing\"}','CTA parallax banner for the WaPro home page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(56,'Page Header - Features','page-header-features','marketing_page_header','published','{\"eyebrow\": \"Features\", \"heading\": \"One platform for everything you do on WhatsApp\", \"subheading\": \"Campaigns, automation, a shared inbox, contacts, and reporting — built for the way teams run WhatsApp at scale.\", \"cta_primary_url\": \"http://localhost:8000/login\", \"cta_primary_text\": \"Start for free\", \"cta_secondary_url\": \"http://localhost:8000/pricing\", \"cta_secondary_text\": \"See pricing\"}','Page header for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(57,'Broadcasting','broadcasting','marketing_broadcasting','published','{\"bullets\": [\"Audience segments and saved lists\", \"Schedule and throttle for safe sending\", \"Live delivered / read / replied tracking\"], \"cta_url\": \"http://localhost:8000/login\", \"eyebrow\": \"Broadcasting\", \"heading\": \"Send bulk campaigns that actually get delivered\", \"cta_text\": \"Launch a campaign\", \"subheading\": \"Reach segmented audiences with approved templates, controlled throttling, and scheduling — then watch delivery and replies land in real time.\", \"visual_cards\": [{\"type\": \"stats\", \"badge\": \"+78%\", \"stats\": [{\"label\": \"Read\", \"value\": \"86%\"}, {\"label\": \"CTR\", \"value\": \"11.4%\"}, {\"label\": \"Failed\", \"value\": \"0.04%\"}], \"value\": \"104.7k\", \"heading\": \"Messages delivered\", \"chart_bars\": [{\"accent\": false, \"height\": \"40%\"}, {\"accent\": false, \"height\": \"30%\"}, {\"accent\": true, \"height\": \"95%\"}, {\"accent\": false, \"height\": \"55%\"}, {\"accent\": false, \"height\": \"48%\"}, {\"accent\": true, \"height\": \"70%\"}]}, {\"rows\": [{\"label\": \"Spring promo · Segment A\", \"status\": \"Delivered\", \"status_type\": \"soft\"}, {\"label\": \"Re-engagement · Inactive\", \"status\": \"Scheduled\", \"status_type\": \"warning\"}, {\"label\": \"Order updates · All\", \"status\": \"Sending\", \"status_type\": \"info\"}], \"type\": \"rows\", \"heading\": \"Campaign status\"}]}','Broadcasting deep-dive section for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(58,'Automation & AI','automation-ai','marketing_automation','published','{\"bullets\": [\"Keyword auto-replies with match rules\", \"Visual chatbot flows that qualify leads\", \"AI smart replies from your knowledge base\"], \"cta_url\": \"http://localhost:8000/login\", \"eyebrow\": \"Automation & AI\", \"heading\": \"Automate replies and let AI handle the routine\", \"cta_text\": \"Build an automation\", \"subheading\": \"Set keyword rules, build chatbot flows, and add AI smart replies so common questions get answered instantly — day or night — while your team focuses on the conversations that matter.\", \"visual_cards\": [{\"type\": \"rule\", \"heading\": \"Auto-reply rule\", \"rule_body\": \"\\\"pricing\\\" · \\\"plans\\\" · \\\"cost\\\"\", \"reply_preview\": \"Here are our plans 👇 wapro.com/pricing — want a recommendation?\"}, {\"type\": \"progress\", \"badge\": \"Live\", \"heading\": \"Chatbot resolution rate\", \"progress_label\": \"Resolved before reaching an agent\", \"progress_value\": \"64%\", \"progress_percentage\": \"64%\"}]}','Automation deep-dive section for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(59,'All Modules Grid','all-modules-grid','marketing_modules_grid','published','{\"cards\": [{\"title\": \"Bulk Campaigns\", \"number\": \"01\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z\\\"/></svg>\", \"description\": \"Launch high-volume campaigns to segmented audiences with timing and delivery under control.\"}, {\"title\": \"Auto Reply\", \"number\": \"02\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z\\\"/></svg>\", \"description\": \"Trigger automatic responses for inbound messages, off-hours support, and lead capture by keyword.\"}, {\"title\": \"AI Smart Reply\", \"number\": \"03\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 2a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 0 1 0 4h-1v3a2 2 0 0 1-2 2h-3v1a2 2 0 0 1-4 0v-1H7a2 2 0 0 1-2-2v-3H4a2 2 0 0 1 0-4h1V7a2 2 0 0 1 2-2h3V4a2 2 0 0 1 2-2z\\\"/></svg>\", \"description\": \"Generate fast, context-aware replies so agents handle conversations with less manual effort.\"}, {\"title\": \"Chatbot\", \"number\": \"04\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 8V4m0 4a4 4 0 0 0-4 4v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a4 4 0 0 0-4-4zM9 14h.01M15 14h.01\\\"/></svg>\", \"description\": \"Build conversational flows that qualify leads and move contacts to the next step automatically.\"}, {\"title\": \"Contacts\", \"number\": \"05\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z\\\"/></svg>\", \"description\": \"Manage lists, segments, and campaign targets from one structured database built for WhatsApp.\"}, {\"title\": \"Export Participants\", \"number\": \"06\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 3v12m0 0 4-4m-4 4-4-4M4 21h16\\\"/></svg>\", \"description\": \"Extract participants from WhatsApp groups for outreach, qualification, and audience building.\"}, {\"title\": \"Templates\", \"number\": \"07\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V6zM4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9zM8 13h8M8 16h5\\\"/></svg>\", \"description\": \"Create, submit, and reuse approved message templates with variables, buttons, and live previews.\"}, {\"title\": \"Shared Inbox\", \"number\": \"08\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M4 13h4l2 3h4l2-3h4M4 6h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1z\\\"/></svg>\", \"description\": \"Handle every conversation as a team with labels, assignments, and quick replies in one place.\"}, {\"title\": \"Reports\", \"number\": \"09\", \"icon_svg\": \"<svg class=\\\"h-6 w-6\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 3v18h18M7 14l3-3 3 3 5-6\\\"/></svg>\", \"description\": \"Track campaign output, reply activity, and workflow performance with operational reporting.\"}], \"eyebrow\": \"Every module\", \"heading\": \"A complete WhatsApp toolkit\", \"subheading\": \"Each module supports a real operational job — pick what you need today and grow into the rest.\"}','Full modules grid for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(60,'Why WaPro','why-wapro','marketing_why_wapro','published','{\"eyebrow\": \"Why WaPro\", \"heading\": \"Built to run WhatsApp like a system\", \"reasons\": [{\"title\": \"Live in minutes\", \"number\": \"01\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M13 2 3 14h7l-1 8 10-12h-7l1-8z\\\"/></svg>\", \"description\": \"Connect your number, import contacts, and send your first campaign the same day.\"}, {\"title\": \"Safe by design\", \"number\": \"02\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\\\"/></svg>\", \"description\": \"Throttling and template compliance keep your number healthy as you scale sends.\"}, {\"title\": \"Decisions from data\", \"number\": \"03\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M3 3v18h18M7 14l3-3 3 3 5-6\\\"/></svg>\", \"description\": \"Delivery, read, and reply analytics turn raw sends into measurable outcomes.\"}, {\"title\": \"Built for teams\", \"number\": \"04\", \"icon_svg\": \"<svg class=\\\"h-5 w-5\\\" fill=\\\"none\\\" stroke=\\\"currentColor\\\" stroke-width=\\\"1.8\\\" viewBox=\\\"0 0 24 24\\\"><path stroke-linecap=\\\"round\\\" stroke-linejoin=\\\"round\\\" d=\\\"M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z\\\"/></svg>\", \"description\": \"Shared inbox, roles, and assignments keep every agent on the same page.\"}], \"subheading\": \"Every module shares the same contacts, automation, and reporting — so the whole operation moves together.\", \"center_label\": \"This month\", \"center_value\": \"104.7k\", \"center_subtitle\": \"messages delivered\", \"center_bottom_stats\": [{\"label\": \"Delivery\", \"value\": \"98%\"}, {\"label\": \"Read\", \"value\": \"86%\"}, {\"label\": \"Bot solved\", \"value\": \"64%\"}]}','Why choose WaPro section for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(61,'Features CTA','features-cta','marketing_cta','published','{\"eyebrow\": \"Ready when you are\", \"heading\": \"Put every WhatsApp module to work today\", \"subheading\": \"Start free, connect your number, and send your first campaign in minutes.\", \"cta_primary_url\": \"http://localhost:8000/login\", \"background_image\": \"http://localhost:8000/assets/wapro/images/hero-tedy-2.webp\", \"cta_primary_text\": \"Create your workspace\", \"cta_secondary_url\": \"http://localhost:8000/pricing\", \"cta_secondary_text\": \"View pricing\"}','CTA parallax banner for the Features page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(62,'Page Header - FAQs','page-header-faqs','marketing_page_header','published','{\"eyebrow\": \"Help center\", \"heading\": \"Frequently asked questions\", \"subheading\": \"Everything you need to know about WaPro — from getting set up to billing and the API. Can\'t find an answer? We\'re a message away.\"}','Page header for the FAQs page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(63,'FAQ Categories','faq-categories','marketing_faq_categories','published','{\"cta_title\": \"Still have a question?\", \"categories\": [{\"items\": [{\"answer\": \"Head to Channel Setup, connect your WhatsApp Business Account through Meta, verify your number, and sync your phone numbers — the whole flow takes a few minutes.\", \"question\": \"How do I connect my WhatsApp number?\"}, {\"answer\": \"Yes. WhatsApp\'s Business API requires a verified Meta Business account. We guide you through verification during setup if you don\'t have one yet.\", \"question\": \"Do I need a Meta Business account?\"}, {\"answer\": \"Absolutely. Upload a CSV from the Contacts page and map your columns — names, numbers and tags come across in one step.\", \"question\": \"Can I import my existing contacts?\"}], \"heading\": \"Getting started\"}, {\"items\": [{\"answer\": \"Yes — upgrade or downgrade anytime from the Subscription page. Changes are prorated and you keep all your data.\", \"question\": \"Can I change plans later?\"}, {\"answer\": \"All major cards via Stripe, plus PayPal. Annual plans can also be paid by bank transfer — contact us to arrange it.\", \"question\": \"Which payment methods do you accept?\"}, {\"answer\": \"Every paid plan includes a 14-day money-back guarantee. If it\'s not the right fit, reach out for a full refund.\", \"question\": \"Do you offer refunds?\"}], \"heading\": \"Billing & plans\"}, {\"items\": [{\"answer\": \"WhatsApp reviews message templates to prevent spam. Most are approved within minutes — we show the status live and explain any rejection so you can fix it fast.\", \"question\": \"Why do templates need approval?\"}, {\"answer\": \"Limits depend on your WhatsApp messaging tier and your plan\'s monthly allowance. Campaigns respect both automatically and queue the rest.\", \"question\": \"Is there a limit on bulk campaigns?\"}], \"heading\": \"Messaging\"}, {\"items\": [{\"answer\": \"Yes. Generate API tokens, send messages programmatically, and subscribe to webhook events for delivery and inbound updates. Full reference lives in our API docs.\", \"question\": \"Do you have a REST API and webhooks?\"}, {\"answer\": \"All traffic is encrypted in transit over HTTPS, tokens are scoped and revocable, and you can review every account action in the activity log.\", \"question\": \"How is my data secured?\"}], \"heading\": \"API & technical\"}], \"icon_class\": \"ph-headset\", \"cta_subtitle\": \"Our team is happy to help. Start a conversation and we\'ll get back to you within a few hours on business days.\", \"cta_primary_url\": \"http://localhost:8000/contact\", \"cta_primary_text\": \"Contact us\", \"cta_secondary_url\": \"http://localhost:8000/login\", \"cta_secondary_text\": \"Start free\"}','Grouped FAQ categories for the FAQs page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(64,'CTA Card - FAQs','cta-card-faqs','marketing_cta_card','published','{\"heading\": \"Still have a question?\", \"icon_class\": \"ph-headset\", \"subheading\": \"Our team is happy to help. Start a conversation and we\'ll get back to you within a few hours on business days.\", \"cta_primary_url\": \"http://localhost:8000/contact\", \"cta_primary_text\": \"Contact us\", \"cta_secondary_url\": \"http://localhost:8000/login\", \"cta_secondary_text\": \"Start free\"}','CTA card for the FAQs page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(65,'Page Header - Contact','page-header-contact','marketing_page_header','published','{\"eyebrow\": \"Contact\", \"heading\": \"Talk to the WaPro team\", \"subheading\": \"Questions about features, pricing, or getting set up? Send us a message and we\'ll get back within one business day.\"}','Page header for the Contact page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(66,'Contact Info','contact-info','marketing_contact_info','published','{\"fields\": [{\"name\": \"first_name\", \"type\": \"text\", \"label\": \"First name\", \"required\": true, \"placeholder\": \"Jane\"}, {\"name\": \"last_name\", \"type\": \"text\", \"label\": \"Last name\", \"required\": true, \"placeholder\": \"Doe\"}, {\"name\": \"email\", \"type\": \"email\", \"label\": \"Email\", \"required\": true, \"placeholder\": \"jane@company.com\"}, {\"name\": \"company\", \"type\": \"text\", \"label\": \"Company\", \"required\": true, \"placeholder\": \"Acme Inc.\"}, {\"name\": \"interest\", \"type\": \"select\", \"label\": \"How can we help?\", \"placeholder\": \"How can we help?\"}, {\"name\": \"message\", \"type\": \"textarea\", \"label\": \"Message\", \"required\": true, \"placeholder\": \"Tell us a bit about what you need...\"}], \"eyebrow\": \"Get in touch\", \"heading\": \"We\'re here to help\", \"channels\": [{\"label\": \"Email\", \"value\": \"hello@wapro.com\", \"link_url\": \"hello@wapro.com\", \"link_type\": \"email\", \"icon_class\": \"ph-envelope-simple\"}, {\"label\": \"Phone\", \"value\": \"+1 (406) 555-0120\", \"link_url\": \"+1 (406) 555-0120\", \"link_type\": \"phone\", \"icon_class\": \"ph-phone\"}, {\"label\": \"Address\", \"value\": \"123 Business Street, Suite 456\\nNew York, NY 10001, USA\", \"link_url\": \"\", \"link_type\": \"none\", \"icon_class\": \"ph-map-pin\"}], \"subheading\": \"Reach us by email or phone, or drop by the office. Prefer chat? Message us on WhatsApp.\", \"submit_text\": \"Send message\", \"form_heading\": \"Send us a message\", \"whatsapp_hours\": \"Mon–Fri, 9am–6pm ET. Average reply under 10 minutes.\", \"whatsapp_title\": \"Message us on WhatsApp\", \"success_message\": \"Thanks! Your message is on its way. We\'ll reply within one business day.\", \"interest_options\": [{\"label\": \"Sales & pricing\", \"value\": \"sales\"}, {\"label\": \"Technical support\", \"value\": \"support\"}, {\"label\": \"Partnership\", \"value\": \"partnership\"}, {\"label\": \"Something else\", \"value\": \"other\"}]}','Contact information section for the Contact page.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(67,'Legal - Privacy Policy','legal-privacy-policy','legal_content','published','{\"eyebrow\": \"Legal\", \"heading\": \"Privacy Policy\", \"subheading\": \"How WaPro collects, uses, and protects personal information when you use our website and services.\", \"content_blocks\": [{\"body\": \"We may collect account details, contact information, workspace activity, billing details, support messages, and technical data needed to operate WaPro. We only ask for information that helps us provide, secure, improve, or support the service.\", \"heading\": \"Information we collect\"}, {\"body\": \"We use information to create and manage accounts, deliver product features, process payments, respond to support requests, prevent abuse, improve performance, and communicate service updates. We do not sell personal information.\", \"heading\": \"How we use information\"}, {\"body\": \"We may share information with trusted service providers that help us host, secure, analyze, bill, or support WaPro. These providers may only use the information to perform services for us and must protect it appropriately.\", \"heading\": \"Data sharing\"}, {\"body\": \"You may request access, correction, export, or deletion of personal information where applicable. Some data may be retained when required for security, legal, billing, or legitimate business records.\", \"heading\": \"Your choices\"}], \"effective_date\": \"July 12, 2026\"}','Editable privacy policy content.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(68,'Legal - Terms & Conditions','legal-terms-and-conditions','legal_content','published','{\"eyebrow\": \"Legal\", \"heading\": \"Terms & Conditions\", \"subheading\": \"The basic terms that govern access to and use of WaPro, including accounts, acceptable use, billing, and service availability.\", \"content_blocks\": [{\"body\": \"You are responsible for maintaining accurate account information, protecting login credentials, and ensuring your use of WaPro complies with applicable laws, platform policies, and messaging consent requirements.\", \"heading\": \"Using WaPro\"}, {\"body\": \"You may not use WaPro to send unlawful, harmful, misleading, abusive, or unsolicited communications. We may suspend access when usage creates risk for users, recipients, the platform, or our infrastructure.\", \"heading\": \"Acceptable use\"}, {\"body\": \"Paid plans renew according to the selected billing cycle unless canceled. Plan limits, pricing, and included features may vary by subscription and will be shown before purchase or renewal.\", \"heading\": \"Subscriptions and billing\"}, {\"body\": \"We may update features, integrations, policies, and these terms as the service evolves. Continued use of WaPro after changes take effect means you accept the updated terms.\", \"heading\": \"Service changes\"}], \"effective_date\": \"July 12, 2026\"}','Editable terms and conditions content.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(69,'Legal - Confidentiality & Privacy','legal-confidentiality-privacy','legal_content','published','{\"eyebrow\": \"Legal\", \"heading\": \"Confidentiality & Privacy\", \"subheading\": \"How WaPro treats customer workspace information, message data, and confidential operational details.\", \"content_blocks\": [{\"body\": \"Customer lists, campaign details, conversations, templates, account settings, and business records are treated as confidential customer information. We use this information only to provide and support the service.\", \"heading\": \"Confidential information\"}, {\"body\": \"Access to customer information is limited to authorized personnel and systems that need it for operations, security, support, or compliance. Administrative access is reviewed and restricted based on job responsibilities.\", \"heading\": \"Access controls\"}, {\"body\": \"Message and contact data belongs to the customer workspace. Customers are responsible for collecting required consent and honoring recipient preferences, while WaPro provides tools to manage campaigns and communication workflows.\", \"heading\": \"Message and contact privacy\"}, {\"body\": \"We use reasonable technical and organizational safeguards to protect confidential information, including encrypted transport, scoped access, monitoring, and operational controls designed to reduce unauthorized access.\", \"heading\": \"Security practices\"}], \"effective_date\": \"July 12, 2026\"}','Editable confidentiality and privacy content.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(70,'Legal - Legal Information','legal-information','legal_content','published','{\"eyebrow\": \"Legal\", \"heading\": \"Legal Information\", \"subheading\": \"General company, compliance, and legal contact information for WaPro customers and website visitors.\", \"content_blocks\": [{\"body\": \"WaPro provides software for WhatsApp marketing, automation, customer messaging, and reporting. Company registration, tax, and billing details may be provided in invoices, account records, or direct legal correspondence.\", \"heading\": \"Company information\"}, {\"body\": \"WaPro uses official provider integrations where applicable. Product names, trademarks, and platform policies remain the property and responsibility of their respective owners.\", \"heading\": \"Platform relationship\"}, {\"body\": \"Formal legal notices should include the account owner name, workspace identifier where relevant, a clear description of the request, and contact details for follow-up.\", \"heading\": \"Legal notices\"}, {\"body\": \"For legal, privacy, or compliance questions, contact hello@wapro.com. We will route your request to the appropriate team and respond as soon as reasonably possible.\", \"heading\": \"Contact\"}], \"effective_date\": \"July 12, 2026\"}','Editable legal information content.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04'),(71,'Legal - Cookie Policy','legal-cookie-policy','legal_content','published','{\"eyebrow\": \"Legal\", \"heading\": \"Cookie Policy\", \"subheading\": \"How WaPro uses cookies and similar technologies on our website and services.\", \"content_blocks\": [{\"body\": \"Cookies are small text files stored on your device when you visit a website. They help remember preferences, support secure sessions, and improve how pages work.\", \"heading\": \"What cookies are\"}, {\"body\": \"We use necessary cookies to operate the website and may use analytics or preference cookies to understand usage, improve performance, and personalize your experience.\", \"heading\": \"How we use cookies\"}, {\"body\": \"You can accept our cookie notice or control cookies through your browser settings. Blocking some cookies may affect website functionality or saved preferences.\", \"heading\": \"Managing cookies\"}, {\"body\": \"We may update this Cookie Policy when our website, services, or legal requirements change. The effective date on this page reflects the latest version.\", \"heading\": \"Updates to this policy\"}], \"effective_date\": \"July 12, 2026\"}','Editable cookie policy content.','[]',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04');
/*!40000 ALTER TABLE `frontend_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frontend_theme_settings`
--

DROP TABLE IF EXISTS `frontend_theme_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frontend_theme_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `frontend_theme_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frontend_theme_settings`
--

LOCK TABLES `frontend_theme_settings` WRITE;
/*!40000 ALTER TABLE `frontend_theme_settings` DISABLE KEYS */;
INSERT INTO `frontend_theme_settings` VALUES (1,'active_theme','classic','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'theme.classic.enabled','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'theme.classic.logo_text','WaPro','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'theme.classic.primary_color','#25D366','2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'theme.classic.accent_color','#F59E0B','2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'theme.classic.show_hero_kicker','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'theme.classic.footer_link_cookies','/cookie-policy','2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,'theme.classic.menu.header','4','2026-07-17 09:06:03','2026-07-17 09:06:04'),(9,'theme.classic.menu.footer','5','2026-07-17 09:06:03','2026-07-17 09:06:04'),(10,'theme.classic.menu.mobile','3','2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'theme.classic.footer_email','hello@wapro.com','2026-07-17 09:06:03','2026-07-17 09:06:03'),(12,'theme.classic.footer_phone','+1 (406) 555-0120','2026-07-17 09:06:03','2026-07-17 09:06:03'),(13,'theme.classic.footer_address','123 Business Street, Suite 456, New York, NY 10001, USA','2026-07-17 09:06:03','2026-07-17 09:06:03'),(14,'theme.classic.footer_newsletter_heading','Newsletter','2026-07-17 09:06:03','2026-07-17 09:06:03'),(15,'theme.classic.footer_newsletter_subheading','Subscribe to our newsletter','2026-07-17 09:06:03','2026-07-17 09:06:03'),(16,'theme.classic.footer_copyright','2026 WaPro. All rights reserved.','2026-07-17 09:06:03','2026-07-17 09:06:03'),(17,'theme.classic.footer_link_terms','/legal-information','2026-07-17 09:06:03','2026-07-17 09:06:03'),(18,'theme.classic.footer_link_privacy','/confidentiality-privacy','2026-07-17 09:06:03','2026-07-17 09:06:03'),(19,'theme.classic.footer_social_facebook','#','2026-07-17 09:06:03','2026-07-17 09:06:03'),(20,'theme.classic.footer_social_x','#','2026-07-17 09:06:03','2026-07-17 09:06:03'),(21,'theme.classic.footer_social_instagram','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(22,'theme.classic.show_auth_links','true','2026-07-17 09:06:03','2026-07-17 09:06:03'),(23,'theme.classic.sign_in_text','Sign in','2026-07-17 09:06:03','2026-07-17 09:06:03'),(24,'theme.classic.sign_up_text','Sign up','2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `frontend_theme_settings` ENABLE KEYS */;
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
  `attempts` tinyint unsigned NOT NULL,
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
-- Table structure for table `knowledge_base_chunks`
--

DROP TABLE IF EXISTS `knowledge_base_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `knowledge_base_id` bigint unsigned NOT NULL,
  `source_id` bigint unsigned NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `embedding` json DEFAULT NULL,
  `vector_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_count` int unsigned NOT NULL DEFAULT '0',
  `position` int unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `score` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_base_chunks_source_id_foreign` (`source_id`),
  KEY `knowledge_base_chunks_knowledge_base_id_source_id_index` (`knowledge_base_id`,`source_id`),
  KEY `knowledge_base_chunks_vector_id_index` (`vector_id`),
  FULLTEXT KEY `knowledge_base_chunks_content_fulltext` (`content`),
  CONSTRAINT `knowledge_base_chunks_knowledge_base_id_foreign` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_bases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `knowledge_base_chunks_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `knowledge_base_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_base_chunks`
--

LOCK TABLES `knowledge_base_chunks` WRITE;
/*!40000 ALTER TABLE `knowledge_base_chunks` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_base_chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `knowledge_base_sources`
--

DROP TABLE IF EXISTS `knowledge_base_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `knowledge_base_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `token_count` int unsigned NOT NULL DEFAULT '0',
  `chunks_count` int unsigned NOT NULL DEFAULT '0',
  `checksum` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vector_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `vector_error` text COLLATE utf8mb4_unicode_ci,
  `error` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `last_indexed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_base_sources_knowledge_base_id_status_index` (`knowledge_base_id`,`status`),
  KEY `knowledge_base_sources_type_index` (`type`),
  KEY `knowledge_base_sources_status_index` (`status`),
  KEY `knowledge_base_sources_checksum_index` (`checksum`),
  KEY `knowledge_base_sources_vector_status_index` (`vector_status`),
  CONSTRAINT `knowledge_base_sources_knowledge_base_id_foreign` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_bases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_base_sources`
--

LOCK TABLES `knowledge_base_sources` WRITE;
/*!40000 ALTER TABLE `knowledge_base_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_base_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `knowledge_bases`
--

DROP TABLE IF EXISTS `knowledge_bases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_bases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `visibility` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'workspace',
  `settings` json DEFAULT NULL,
  `sources_count` int unsigned NOT NULL DEFAULT '0',
  `chunks_count` int unsigned NOT NULL DEFAULT '0',
  `last_indexed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_bases_workspace_id_foreign` (`workspace_id`),
  KEY `knowledge_bases_status_index` (`status`),
  CONSTRAINT `knowledge_bases_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_bases`
--

LOCK TABLES `knowledge_bases` WRITE;
/*!40000 ALTER TABLE `knowledge_bases` DISABLE KEYS */;
/*!40000 ALTER TABLE `knowledge_bases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('ltr','rtl') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'en','English','English','ltr',1,1,1,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL),(2,'bn','Bengali','বাংলা','ltr',1,0,2,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL),(3,'ar','Arabic','العربية','rtl',1,0,3,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` tinyint unsigned DEFAULT NULL,
  `contact_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `verification_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `ai_prompt` text COLLATE utf8mb4_unicode_ci,
  `criteria` json DEFAULT NULL,
  `value` decimal(12,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_workspace_id_external_source_external_id_unique` (`workspace_id`,`external_source`,`external_id`),
  KEY `leads_contact_id_foreign` (`contact_id`),
  CONSTRAINT `leads_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_activities`
--

DROP TABLE IF EXISTS `login_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_activities_user_type_user_id_index` (`user_type`,`user_id`),
  KEY `login_activities_event_index` (`event`),
  KEY `login_activities_created_at_index` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_activities`
--

LOCK TABLES `login_activities` WRITE;
/*!40000 ALTER TABLE `login_activities` DISABLE KEYS */;
INSERT INTO `login_activities` VALUES (1,'App\\Models\\User',NULL,'failed','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','Desktop','Chrome','macOS','{\"email\": \"user@mail.com\"}','2026-07-17 09:06:13'),(2,'App\\Models\\User',1,'login','172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','Desktop','Chrome','macOS',NULL,'2026-07-17 09:06:17');
/*!40000 ALTER TABLE `login_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint unsigned NOT NULL,
  `disk` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_created_at_index` (`created_at`),
  KEY `media_type_index` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media`
--

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
INSERT INTO `media` VALUES (1,'Commerce product image 01','commerce-demo-01.jpg','commerce-demo-01.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,oxford-shirt?lock=4101','Oxford shirt on a studio model',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(2,'Commerce product image 02','commerce-demo-02.jpg','commerce-demo-02.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,linen-shirt?lock=4102','Linen shirt product lifestyle photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(3,'Commerce product image 03','commerce-demo-03.jpg','commerce-demo-03.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,polo-shirt?lock=4103','Polo shirt retail product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(4,'Commerce product image 04','commerce-demo-04.jpg','commerce-demo-04.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,chino-trousers?lock=4104','Chino trousers on model',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(5,'Commerce product image 05','commerce-demo-05.jpg','commerce-demo-05.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,tailored-trousers?lock=4105','Tailored trousers studio product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(6,'Commerce product image 06','commerce-demo-06.jpg','commerce-demo-06.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,maxi-dress?lock=4106','Maxi dress lifestyle product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(7,'Commerce product image 07','commerce-demo-07.jpg','commerce-demo-07.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,wrap-dress?lock=4107','Wrap dress on model',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(8,'Commerce product image 08','commerce-demo-08.jpg','commerce-demo-08.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,denim-jacket?lock=4108','Denim jacket product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(9,'Commerce product image 09','commerce-demo-09.jpg','commerce-demo-09.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,bomber-jacket?lock=4109','Bomber jacket streetwear product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(10,'Commerce product image 10','commerce-demo-10.jpg','commerce-demo-10.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,leggings?lock=4110','Performance leggings activewear image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(11,'Commerce product image 11','commerce-demo-11.jpg','commerce-demo-11.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,training-shorts?lock=4111','Training shorts activewear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(12,'Commerce product image 12','commerce-demo-12.jpg','commerce-demo-12.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,kids-hoodie?lock=4112','Kids zip hoodie product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(13,'Commerce product image 13','commerce-demo-13.jpg','commerce-demo-13.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,school-uniform?lock=4113','School uniform shirt product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(14,'Commerce product image 14','commerce-demo-14.jpg','commerce-demo-14.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,workwear-coverall?lock=4114','Workwear coverall garment photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(15,'Commerce product image 15','commerce-demo-15.jpg','commerce-demo-15.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,fleece-hoodie?lock=4115','Fleece hoodie product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(16,'Commerce product image 16','commerce-demo-16.jpg','commerce-demo-16.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,knit-sweater?lock=4116','Cable knit sweater product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(17,'Commerce product image 17','commerce-demo-17.jpg','commerce-demo-17.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,trench-coat?lock=4117','Classic trench coat fashion image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(18,'Commerce product image 18','commerce-demo-18.jpg','commerce-demo-18.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,wool-coat?lock=4118','Wool blend coat product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(19,'Commerce product image 19','commerce-demo-19.jpg','commerce-demo-19.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,cotton-blouse?lock=4119','Cotton blouse on model',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(20,'Commerce product image 20','commerce-demo-20.jpg','commerce-demo-20.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,cargo-pants?lock=4120','Cargo pants product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(21,'Commerce product image 21','commerce-demo-21.jpg','commerce-demo-21.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,button-shirt?lock=4121','Button shirt ecommerce image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(22,'Commerce product image 22','commerce-demo-22.jpg','commerce-demo-22.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,summer-shirt?lock=4122','Summer shirt catalog photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(23,'Commerce product image 23','commerce-demo-23.jpg','commerce-demo-23.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,knit-polo?lock=4123','Knit polo shirt product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(24,'Commerce product image 24','commerce-demo-24.jpg','commerce-demo-24.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,pleated-trousers?lock=4124','Pleated trousers product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(25,'Commerce product image 25','commerce-demo-25.jpg','commerce-demo-25.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,wide-leg-trousers?lock=4125','Wide leg trousers catalog photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(26,'Commerce product image 26','commerce-demo-26.jpg','commerce-demo-26.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,shirt-dress?lock=4126','Shirt dress lifestyle photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(27,'Commerce product image 27','commerce-demo-27.jpg','commerce-demo-27.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,midi-dress?lock=4127','Midi dress studio product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(28,'Commerce product image 28','commerce-demo-28.jpg','commerce-demo-28.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,overshirt?lock=4128','Overshirt jacket product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(29,'Commerce product image 29','commerce-demo-29.jpg','commerce-demo-29.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,utility-jacket?lock=4129','Utility jacket catalog photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(30,'Commerce product image 30','commerce-demo-30.jpg','commerce-demo-30.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,sports-bra?lock=4130','Activewear top product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(31,'Commerce product image 31','commerce-demo-31.jpg','commerce-demo-31.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,running-shorts?lock=4131','Running shorts on model',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(32,'Commerce product image 32','commerce-demo-32.jpg','commerce-demo-32.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,kids-cardigan?lock=4132','Kids cardigan product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(33,'Commerce product image 33','commerce-demo-33.jpg','commerce-demo-33.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,uniform-polo?lock=4133','Uniform polo product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(34,'Commerce product image 34','commerce-demo-34.jpg','commerce-demo-34.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,chef-jacket?lock=4134','Chef jacket workwear photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(35,'Commerce product image 35','commerce-demo-35.jpg','commerce-demo-35.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,pullover-hoodie?lock=4135','Pullover hoodie product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(36,'Commerce product image 36','commerce-demo-36.jpg','commerce-demo-36.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,crewneck-sweater?lock=4136','Crewneck sweater product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(37,'Commerce product image 37','commerce-demo-37.jpg','commerce-demo-37.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,parka-coat?lock=4137','Parka coat fashion product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(38,'Commerce product image 38','commerce-demo-38.jpg','commerce-demo-38.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,overcoat?lock=4138','Overcoat catalog image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(39,'Commerce product image 39','commerce-demo-39.jpg','commerce-demo-39.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,silk-blouse?lock=4139','Silk blouse product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(40,'Commerce product image 40','commerce-demo-40.jpg','commerce-demo-40.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,drawstring-pants?lock=4140','Drawstring pants catalog image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(41,'Commerce product image 41','commerce-demo-41.jpg','commerce-demo-41.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,apparel-flatlay?lock=4141','Apparel flat lay product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(42,'Commerce product image 42','commerce-demo-42.jpg','commerce-demo-42.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,clothing-rack?lock=4142','Retail clothing rack product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(43,'Commerce product image 43','commerce-demo-43.jpg','commerce-demo-43.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,garment-detail?lock=4143','Garment fabric detail image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(44,'Commerce product image 44','commerce-demo-44.jpg','commerce-demo-44.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,studio-model?lock=4144','Studio model wearing apparel',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(45,'Commerce product image 45','commerce-demo-45.jpg','commerce-demo-45.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,ecommerce-clothing?lock=4145','Ecommerce apparel product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(46,'Commerce product image 46','commerce-demo-46.jpg','commerce-demo-46.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,retail-shirt?lock=4146','Retail shirt product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(47,'Commerce product image 47','commerce-demo-47.jpg','commerce-demo-47.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,retail-dress?lock=4147','Retail dress product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(48,'Commerce product image 48','commerce-demo-48.jpg','commerce-demo-48.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,retail-jacket?lock=4148','Retail jacket product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(49,'Commerce product image 49','commerce-demo-49.jpg','commerce-demo-49.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,retail-trousers?lock=4149','Retail trousers product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(50,'Commerce product image 50','commerce-demo-50.jpg','commerce-demo-50.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,retail-activewear?lock=4150','Retail activewear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(51,'Commerce product image 51','commerce-demo-51.jpg','commerce-demo-51.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,casualwear?lock=4151','Casualwear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(52,'Commerce product image 52','commerce-demo-52.jpg','commerce-demo-52.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,business-casual?lock=4152','Business casual garment photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(53,'Commerce product image 53','commerce-demo-53.jpg','commerce-demo-53.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,streetwear?lock=4153','Streetwear product photo',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(54,'Commerce product image 54','commerce-demo-54.jpg','commerce-demo-54.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,minimal-clothing?lock=4154','Minimal clothing catalog image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(55,'Commerce product image 55','commerce-demo-55.jpg','commerce-demo-55.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,outerwear?lock=4155','Outerwear fashion product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(56,'Commerce product image 56','commerce-demo-56.jpg','commerce-demo-56.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,womenswear?lock=4156','Womenswear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(57,'Commerce product image 57','commerce-demo-57.jpg','commerce-demo-57.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,menswear?lock=4157','Menswear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(58,'Commerce product image 58','commerce-demo-58.jpg','commerce-demo-58.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,kidswear?lock=4158','Kidswear product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(59,'Commerce product image 59','commerce-demo-59.jpg','commerce-demo-59.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,uniforms?lock=4159','Uniform apparel product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37'),(60,'Commerce product image 60','commerce-demo-60.jpg','commerce-demo-60.jpg','image/jpeg','jpg','image',350000,'public','https://loremflickr.com/960/1200/fashion,wholesale-clothing?lock=4160','Wholesale apparel product image',1,'2026-07-17 09:06:37','2026-07-17 09:06:37');
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_template_submissions`
--

DROP TABLE IF EXISTS `message_template_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_template_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `message_template_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `provider_account_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whatsapp_template_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','submitted','pending','approved','rejected','paused','disabled','failed','in_appeal','pending_deletion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `submission_payload` json DEFAULT NULL,
  `meta_response` json DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_submission_waba_unique` (`workspace_id`,`message_template_id`,`provider_account_id`),
  KEY `message_template_submissions_message_template_id_foreign` (`message_template_id`),
  KEY `message_template_submissions_channel_account_id_foreign` (`channel_account_id`),
  KEY `message_template_submissions_provider_index` (`provider`),
  KEY `message_template_submissions_provider_account_id_index` (`provider_account_id`),
  KEY `message_template_submissions_status_index` (`status`),
  CONSTRAINT `message_template_submissions_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `message_template_submissions_message_template_id_foreign` FOREIGN KEY (`message_template_id`) REFERENCES `message_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_template_submissions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_template_submissions`
--

LOCK TABLES `message_template_submissions` WRITE;
/*!40000 ALTER TABLE `message_template_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_template_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_templates`
--

DROP TABLE IF EXISTS `message_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_US',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'marketing',
  `status` enum('draft','submitted','pending','approved','rejected','paused','disabled','failed','in_appeal','pending_deletion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `components` json DEFAULT NULL,
  `buttons` json DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `provider_template_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_templates_workspace_provider_name_language_unique` (`workspace_id`,`provider`,`name`,`language`),
  KEY `message_templates_provider_index` (`provider`),
  CONSTRAINT `message_templates_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_templates`
--

LOCK TABLES `message_templates` WRITE;
/*!40000 ALTER TABLE `message_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `conversation_id` bigint unsigned DEFAULT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `body` text COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL,
  `status` enum('received','queued','sending','sent','delivered','read','replied','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `provider_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `whatsapp_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_workspace_id_foreign` (`workspace_id`),
  KEY `messages_channel_account_id_foreign` (`channel_account_id`),
  KEY `messages_conversation_id_foreign` (`conversation_id`),
  KEY `messages_contact_id_foreign` (`contact_id`),
  KEY `messages_campaign_id_foreign` (`campaign_id`),
  KEY `messages_provider_index` (`provider`),
  KEY `messages_provider_message_id_index` (`provider_message_id`),
  KEY `messages_whatsapp_message_id_index` (`whatsapp_message_id`),
  CONSTRAINT `messages_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_02_09_000001_create_audit_logs_table',1),(5,'2026_02_09_055051_create_permission_tables',1),(6,'2026_02_09_120000_create_settings_table',1),(7,'2026_02_16_000001_create_admins_table',1),(8,'2026_02_16_000001_create_languages_table',1),(9,'2026_02_23_120000_create_media_table',1),(10,'2026_03_29_000001_create_system_notifications_table',1),(11,'2026_03_29_065957_create_login_activities_table',1),(12,'2026_03_30_000001_create_notification_templates_table',1),(13,'2026_03_30_000002_create_notification_logs_table',1),(14,'2026_03_30_000003_create_device_tokens_table',1),(15,'2026_03_30_000004_drop_old_notifications_table',1),(16,'2026_03_30_062644_create_push_subscriptions_table',1),(17,'2026_04_01_000001_create_currencies_table',1),(18,'2026_04_04_100000_create_ai_settings_table',1),(19,'2026_04_04_120826_create_agent_conversations_table',1),(20,'2026_04_05_100000_create_pages_table',1),(21,'2026_04_05_100001_create_frontend_sections_table',1),(22,'2026_04_05_100002_create_page_sections_table',1),(23,'2026_04_05_100003_create_frontend_theme_settings_table',1),(24,'2026_04_06_100000_create_frontend_menus_table',1),(25,'2026_04_06_100001_create_frontend_menu_items_table',1),(26,'2026_04_22_091530_create_personal_access_tokens_table',1),(27,'2026_04_22_100000_create_social_accounts_table',1),(28,'2026_06_17_113414_create_faqs_table',1),(29,'2026_06_22_150700_create_subscribers_table',1),(30,'2026_06_29_091331_create_support_tickets_table',1),(31,'2026_06_29_091332_create_support_ticket_replies_table',1),(32,'2026_07_02_000001_create_workspaces_table',1),(33,'2026_07_02_000021_create_marketing_channel_tables',1),(34,'2026_07_02_000030_create_contacts_table',1),(35,'2026_07_02_000031_create_segments_table',1),(36,'2026_07_02_000032_create_contact_provider_identities_table',1),(37,'2026_07_02_000040_create_message_templates_table',1),(38,'2026_07_02_000041_create_campaigns_table',1),(39,'2026_07_02_000041_create_message_template_submissions_table',1),(40,'2026_07_02_000050_create_inbox_tables',1),(41,'2026_07_02_000051_create_auto_reply_rules_table',1),(42,'2026_07_02_000060_create_automations_table',1),(43,'2026_07_02_000061_create_knowledge_bases_table',1),(44,'2026_07_02_000062_create_chatbots_table',1),(45,'2026_07_02_000063_create_leads_table',1),(46,'2026_07_04_000041_create_contact_groups_table',1),(47,'2026_07_04_000042_create_contact_group_contact_table',1),(48,'2026_07_04_000043_create_contact_tags_table',1),(49,'2026_07_04_000044_create_contact_tag_contact_table',1),(50,'2026_07_04_000045_create_contact_imports_table',1),(51,'2026_07_04_000047_create_contact_segment_table',1),(52,'2026_07_05_000001_add_assigned_admin_id_to_support_tickets_table',1),(53,'2026_07_05_000002_create_support_ticket_attachments_table',1),(54,'2026_07_05_000003_add_admin_id_to_support_ticket_replies_table',1),(55,'2026_07_05_000004_extend_message_templates_table',1),(56,'2026_07_05_000005_extend_channel_webhook_events_table',1),(57,'2026_07_06_000001_add_workspace_ai_providers_to_chatbots',1),(58,'2026_07_06_000001_create_automation_run_tables',1),(59,'2026_07_06_000001_create_telegram_opt_in_tokens_table',1),(60,'2026_07_06_000002_complete_knowledge_bases_module',1),(61,'2026_07_06_090417_add_setup_fields_to_auto_reply_rules_table',1),(62,'2026_07_07_000003_create_chatbot_widgets_table',1),(63,'2026_07_08_073806_create_blog_posts_table',1),(64,'2026_07_11_000001_create_place_api_settings_table',1),(65,'2026_07_12_000001_create_ai_usage_logs_table',1),(66,'2026_07_12_000001_create_scheduler_entries_table',1),(67,'2026_07_13_000000_create_contact_messages_table',1),(68,'2026_07_13_000001_create_contact_message_replies_table',1),(69,'2026_07_14_000001_create_crm_tables',1),(70,'2026_07_16_152726_create_commerce_tables',1),(71,'2026_07_16_170000_upgrade_commerce_store',1),(72,'2026_07_16_180000_create_commerce_brand_audience_tables',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\Admin',1),(2,'App\\Models\\User',1),(3,'App\\Models\\User',1);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `metadata` json DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_logs_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notification_logs_status_index` (`status`),
  KEY `notification_logs_created_at_index` (`created_at`),
  KEY `notification_logs_template_slug_index` (`template_slug`),
  KEY `notification_logs_channel_index` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `email_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_body` longtext COLLATE utf8mb4_unicode_ci,
  `sms_body` text COLLATE utf8mb4_unicode_ci,
  `in_app_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_app_body` text COLLATE utf8mb4_unicode_ci,
  `push_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `push_body` text COLLATE utf8mb4_unicode_ci,
  `channels` json DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_slug_unique` (`slug`),
  KEY `notification_templates_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_templates`
--

LOCK TABLES `notification_templates` WRITE;
/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
INSERT INTO `notification_templates` VALUES (1,'welcome','Welcome','Sent to new users after registration','Welcome to {{site_name}}, {{user_name}}!','<p>Hello {{user_name}},</p><p>Welcome to {{site_name}}! Your account has been created successfully.</p><p>Click the button below to get started.</p>',NULL,'Welcome, {{user_name}}!','Your account has been created successfully. Start exploring the platform.',NULL,NULL,'[\"email\", \"in_app\"]','{\"login_url\": \"URL to the login page\", \"user_name\": \"The registered user\'s name\"}',1,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL),(2,'password-changed','Password Changed','Sent when a user changes their password','Your password has been changed','<p>Hi {{user_name}},</p><p>Your password was successfully changed on {{changed_at}}.</p><p>If you did not make this change, please contact support immediately.</p>',NULL,NULL,NULL,NULL,NULL,'[\"email\"]','{\"user_name\": \"The user\'s name\", \"changed_at\": \"Date and time of the change\"}',1,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL),(3,'subscription-expiring-soon','Subscription Expiring Soon','Sent to workspace owners one day before their subscription expires','Your {{plan_name}} plan expires soon','<p>Hello {{user_name}},</p><p>Your {{plan_name}} plan for {{workspace_name}} expires on {{expires_at}}.</p><p>Please renew your subscription to keep your services running.</p>','{{site_name}}: Your {{plan_name}} plan expires on {{expires_at}}. Renew: {{renew_url}}','Your plan expires soon','{{plan_name}} for {{workspace_name}} expires on {{expires_at}}.','Your plan expires soon','{{plan_name}} expires on {{expires_at}}.','[\"email\", \"sms\", \"in_app\", \"web_push\", \"mobile_push\"]','{\"plan_name\": \"The current plan name\", \"renew_url\": \"The subscription renewal URL\", \"user_name\": \"The workspace owner name\", \"expires_at\": \"The subscription expiry date\", \"days_remaining\": \"Days remaining before expiry\", \"workspace_name\": \"The workspace name\"}',1,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL),(4,'subscription-expired','Subscription Expired','Sent to workspace owners when their subscription is marked expired','Your {{plan_name}} plan has expired','<p>Hello {{user_name}},</p><p>Your {{plan_name}} plan for {{workspace_name}} expired on {{expires_at}}.</p><p>Your workspace is now read-only until you renew.</p>','{{site_name}}: Your {{plan_name}} plan has expired. Renew: {{renew_url}}','Your plan has expired','{{workspace_name}} is read-only until you renew {{plan_name}}.','Your plan has expired','{{workspace_name}} is read-only until renewal.','[\"email\", \"sms\", \"in_app\", \"web_push\", \"mobile_push\"]','{\"plan_name\": \"The expired plan name\", \"renew_url\": \"The subscription renewal URL\", \"user_name\": \"The workspace owner name\", \"expires_at\": \"The subscription expiry date\", \"days_remaining\": \"Days remaining before expiry\", \"workspace_name\": \"The workspace name\"}',1,'2026-07-17 09:06:03','2026-07-17 09:06:03',NULL);
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_sections`
--

DROP TABLE IF EXISTS `page_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_id` bigint unsigned NOT NULL,
  `frontend_section_id` bigint unsigned NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `visibility_rules` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_sections_page_id_frontend_section_id_unique` (`page_id`,`frontend_section_id`),
  KEY `page_sections_frontend_section_id_foreign` (`frontend_section_id`),
  CONSTRAINT `page_sections_frontend_section_id_foreign` FOREIGN KEY (`frontend_section_id`) REFERENCES `frontend_sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_sections_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_sections`
--

LOCK TABLES `page_sections` WRITE;
/*!40000 ALTER TABLE `page_sections` DISABLE KEYS */;
INSERT INTO `page_sections` VALUES (14,2,27,0,NULL,NULL,NULL),(15,2,28,1,NULL,NULL,NULL),(16,2,29,2,NULL,NULL,NULL),(17,2,30,3,NULL,NULL,NULL),(18,2,36,4,NULL,NULL,NULL),(19,3,32,0,NULL,NULL,NULL),(20,3,33,1,NULL,NULL,NULL),(21,3,34,2,NULL,NULL,NULL),(22,3,35,3,NULL,NULL,NULL),(23,3,36,4,NULL,NULL,NULL),(24,4,37,0,NULL,NULL,NULL),(25,4,38,1,NULL,NULL,NULL),(26,4,39,2,NULL,NULL,NULL),(27,4,40,3,NULL,NULL,NULL),(28,5,18,0,NULL,NULL,NULL),(29,5,19,1,NULL,NULL,NULL),(30,5,20,2,NULL,NULL,NULL),(31,5,21,3,NULL,NULL,NULL),(32,6,22,0,NULL,NULL,NULL),(33,6,23,1,NULL,NULL,NULL),(34,6,24,2,NULL,NULL,NULL),(35,6,25,3,NULL,NULL,NULL),(36,6,26,4,NULL,NULL,NULL),(41,8,17,0,NULL,NULL,NULL),(42,8,12,1,NULL,NULL,NULL),(43,9,41,0,NULL,NULL,NULL),(44,9,42,1,NULL,NULL,NULL),(45,9,43,2,NULL,NULL,NULL),(46,9,12,3,NULL,NULL,NULL),(47,9,44,4,NULL,NULL,NULL),(48,10,13,0,NULL,NULL,NULL),(49,10,14,1,NULL,NULL,NULL),(50,10,15,2,NULL,NULL,NULL),(51,1,48,0,NULL,NULL,NULL),(52,1,49,1,NULL,NULL,NULL),(53,1,50,2,NULL,NULL,NULL),(54,1,51,3,NULL,NULL,NULL),(55,1,52,4,NULL,NULL,NULL),(56,1,53,5,NULL,NULL,NULL),(57,1,54,6,NULL,NULL,NULL),(58,1,55,7,NULL,NULL,NULL),(59,11,56,0,NULL,NULL,NULL),(60,11,57,1,NULL,NULL,NULL),(61,11,58,2,NULL,NULL,NULL),(62,11,59,3,NULL,NULL,NULL),(63,11,60,4,NULL,NULL,NULL),(64,11,61,5,NULL,NULL,NULL),(65,12,62,0,NULL,NULL,NULL),(66,12,63,1,NULL,NULL,NULL),(67,7,65,0,NULL,NULL,NULL),(68,7,66,1,NULL,NULL,NULL),(69,13,67,0,NULL,NULL,NULL),(70,14,68,0,NULL,NULL,NULL),(71,15,69,0,NULL,NULL,NULL),(72,16,70,0,NULL,NULL,NULL),(73,17,71,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `page_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `default_layout` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `theme_overrides` json DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_home` tinyint(1) NOT NULL DEFAULT '0',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_image_media_id` bigint unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`),
  KEY `pages_status_slug_index` (`status`,`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'Home','home','published','WaPro — WhatsApp marketing, automation, and CRM platform.','landing','[]',1,1,'WhatsApp Marketing, Automation & CRM - WaPro','Run WhatsApp Cloud API campaigns, smart replies, chatbots, contacts, automations, and reports from one SaaS workspace.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:03','2026-07-17 09:06:04'),(2,'Team','team','published','Meet the team behind Classic.','default','[]',1,0,'Team — Classic','Meet the engineers, designers, and product builders behind Classic. A focused team building world-class digital products.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'Careers','careers','published','Join a tight-knit team building world-class digital products. See open roles at Classic.','default','[]',1,0,'Careers — Classic','Join a tight-knit team building world-class digital products. See open roles at Classic.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'About Us','about','published','Learn who we are, what drives us, and how we\'ve been building world-class digital products since 2018.','default','[]',1,0,'About Us — Classic','Learn who we are, what drives us, and how we\'ve been building world-class digital products since 2018.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'Service Category Template','service-category-template','published','Template page that defines the sections shown on all service category detail pages.','service_category','[]',1,0,'Service Category Template',NULL,NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'Project Category Template','project-category-template','published','Template page that defines the sections shown on all project category detail pages.','project_category','[]',1,0,'Project Category Template',NULL,NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'Contact','contact','published','Talk to the WaPro team. Questions about features, pricing, or getting set up.','default','[]',1,0,'Contact - WaPro','Questions about features, pricing, or getting set up? Send us a message and we\'ll get back within one business day.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:03','2026-07-17 09:06:04'),(8,'FAQ','faq','published','Answers to the most common questions about Classic — process, pricing, ownership, and support.','default','[]',1,0,'FAQ — Classic','Answers to the most common questions about Classic — process, pricing, ownership, and support.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,'Support','support','published','Get help from the Classic support team. Browse FAQs, submit a ticket, or reach out via live chat.','default','[]',1,0,'Support — Classic','Get help from the Classic support team. Browse FAQs, submit a ticket, or reach out via live chat. We\'re here Saturday to Thursday, 5AM–2PM GMT.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,'Blog','blog','published','WhatsApp marketing, automation, chatbot, and CRM insights from WaPro.','default','[]',1,0,'WhatsApp Marketing Blog - WaPro','Read WaPro guides on WhatsApp automation, broadcast campaigns, chatbots, CRM workflows, and customer messaging.',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'Features','features','published','Explore every WaPro module — broadcasting, automation, chatbot, contacts, and more.','default','[]',1,0,'Features - WaPro','Campaigns, automation, a shared inbox, contacts, and reporting — built for the way teams run WhatsApp at scale.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(12,'FAQs','faqs','published','Everything you need to know about WaPro — from getting set up to billing and the API.','default','[]',1,0,'FAQs - WaPro','Answers to the most common questions about WaPro — setup, billing, messaging, and API.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(13,'Privacy Policy','privacy-policy','published','How WaPro collects, uses, and protects personal information.','default','[]',1,0,'Privacy Policy - WaPro','Learn how WaPro collects, uses, shares, and protects personal information.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(14,'Terms & Conditions','terms-and-conditions','published','The terms that govern access to and use of WaPro.','default','[]',1,0,'Terms & Conditions - WaPro','Review the terms and conditions that apply when using WaPro.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(15,'Confidentiality & Privacy','confidentiality-privacy','published','How WaPro treats customer workspace information and confidential data.','default','[]',1,0,'Confidentiality & Privacy - WaPro','Learn how WaPro handles customer workspace information, message data, and confidential operational details.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(16,'Legal Information','legal-information','published','General company, compliance, and legal contact information for WaPro.','default','[]',1,0,'Legal Information - WaPro','Find general company, compliance, and legal contact information for WaPro.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04'),(17,'Cookie Policy','cookie-policy','published','How WaPro uses cookies and similar technologies.','default','[]',1,0,'Cookie Policy - WaPro','Learn how WaPro uses cookies and similar technologies on its website and services.',NULL,'2026-07-17 09:06:04','2026-07-17 09:06:04','2026-07-17 09:06:04');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
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
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'staffs.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(2,'staffs.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(3,'staffs.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(4,'staffs.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(5,'roles.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(6,'roles.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(7,'roles.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(8,'roles.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(9,'marketing-channels.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(10,'marketing-channels.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(11,'workspaces.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(12,'workspaces.manage','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(13,'workspace.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(14,'workspace.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(15,'workspace.edit','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(16,'team.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(17,'team.manage.staff_only','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(18,'subscription.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(19,'billing.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(20,'channels.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(21,'contacts.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(22,'contacts.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(23,'contacts.assigned_only','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(24,'leads.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(25,'leads.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(26,'campaigns.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(27,'campaigns.create','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(28,'campaigns.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(29,'templates.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(30,'inbox.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(31,'inbox.assigned_only','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(32,'inbox.reply','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(33,'inbox.assign','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(34,'reports.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(35,'automations.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(36,'chatbots.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(37,'settings.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(38,'settings.edit','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(39,'whatsapp-cloud.settings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(40,'whatsapp-cloud.settings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(41,'whatsapp-cloud.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(42,'meta-social.settings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(43,'meta-social.settings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(44,'meta-social.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(45,'telegram.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(46,'settings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(47,'settings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(48,'email.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(49,'threads.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(50,'sms.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(51,'media.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(52,'media.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(53,'media.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(54,'commerce.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(55,'commerce.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(56,'notification-templates.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(57,'notification-templates.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(58,'notification-logs.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(59,'system-notifications.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(60,'system-notifications.send','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(61,'ai-settings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(62,'ai-settings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(63,'ai-usage.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(64,'crm.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(65,'crm.manage','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(66,'support-tickets.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(67,'support-tickets.reply','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(68,'support-tickets.manage','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(69,'support-tickets.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(70,'currencies.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(71,'currencies.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(72,'currencies.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(73,'currencies.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(74,'blogs.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(75,'blogs.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(76,'blogs.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(77,'blogs.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(78,'blog-categories.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(79,'blog-categories.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(80,'blog-categories.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(81,'blog-categories.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(82,'faqs.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(83,'faqs.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(84,'faqs.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(85,'faqs.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(86,'languages.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(87,'languages.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(88,'languages.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(89,'languages.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(90,'newsletter.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(91,'newsletter.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(92,'newsletter.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(93,'newsletter.send','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(94,'contact-messages.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(95,'contact-messages.manage','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(96,'contact-messages.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(97,'audit-logs.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(98,'login-activity.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(99,'place-api-settings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(100,'place-api-settings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(101,'scheduler-queues.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(102,'scheduler-queues.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(103,'scheduler-queues.run','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(104,'scheduler-queues.manage','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(105,'frontend-themes.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(106,'frontend-themes.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(107,'frontend-menus.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(108,'frontend-menus.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(109,'frontend-menus.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(110,'frontend-menus.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(111,'frontend-menus.publish','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(112,'frontend-sections.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(113,'frontend-sections.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(114,'frontend-sections.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(115,'frontend-sections.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(116,'frontend-pages.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(117,'frontend-pages.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(118,'frontend-pages.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(119,'frontend-pages.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(120,'frontend-pages.publish','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(121,'users.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(122,'users.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(123,'users.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(124,'users.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(125,'dashboard.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(126,'dashboard.view','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(127,'profile.edit','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(128,'job-postings.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(129,'job-postings.create','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(130,'job-postings.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(131,'job-postings.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(132,'job-applications.view','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(133,'job-applications.edit','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(134,'job-applications.delete','admin','2026-07-17 09:06:02','2026-07-17 09:06:02');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
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
-- Table structure for table `place_api_settings`
--

DROP TABLE IF EXISTS `place_api_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `place_api_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `place_api_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `place_api_settings`
--

LOCK TABLES `place_api_settings` WRITE;
/*!40000 ALTER TABLE `place_api_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `place_api_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscribable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscribable_id` bigint unsigned NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_encoding` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_subscribable_morph_idx` (`subscribable_type`,`subscribable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_subscriptions`
--

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (10,2),(13,2),(14,2),(15,2),(16,2),(17,2),(18,2),(19,2),(20,2),(21,2),(22,2),(23,2),(24,2),(25,2),(26,2),(27,2),(28,2),(29,2),(30,2),(31,2),(32,2),(33,2),(34,2),(35,2),(36,2),(37,2),(38,2),(41,2),(44,2),(45,2),(48,2),(49,2),(50,2),(54,2),(55,2),(64,2),(65,2),(126,2),(127,2);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
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
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super-admin','admin','2026-07-17 09:06:02','2026-07-17 09:06:02'),(2,'user','web','2026-07-17 09:06:02','2026-07-17 09:06:02'),(3,'workspace-owner','web','2026-07-17 09:06:17','2026-07-17 09:06:17');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheduler_entries`
--

DROP TABLE IF EXISTS `scheduler_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scheduler_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hourly',
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_at` timestamp NULL DEFAULT NULL,
  `last_finished_at` timestamp NULL DEFAULT NULL,
  `last_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_message` text COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scheduler_entries_key_unique` (`key`),
  KEY `scheduler_entries_enabled_frequency_index` (`enabled`,`frequency`),
  KEY `scheduler_entries_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduler_entries`
--

LOCK TABLES `scheduler_entries` WRITE;
/*!40000 ALTER TABLE `scheduler_entries` DISABLE KEYS */;
INSERT INTO `scheduler_entries` VALUES (1,'crm-task-reminders','CRM Task Reminders','job','App\\Modules\\Crm\\Jobs\\SendCrmTaskRemindersJob','every_minute','default',1,NULL,NULL,NULL,NULL,'[]','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'subscription-expiry-reminders','Subscription Expiry Reminders','job','App\\Modules\\PlansSubscriptions\\Jobs\\SendSubscriptionExpiryReminderJob','hourly','default',1,NULL,NULL,NULL,NULL,'[]','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'subscription-expiry-processing','Subscription Expiry Processing','job','App\\Modules\\PlansSubscriptions\\Jobs\\ExpireSubscriptionsJob','hourly','default',1,NULL,NULL,NULL,NULL,'[]','2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `scheduler_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `segments`
--

DROP TABLE IF EXISTS `segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `segments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dynamic',
  `rules` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `segments_workspace_id_name_unique` (`workspace_id`,`name`),
  CONSTRAINT `segments_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segments`
--

LOCK TABLES `segments` WRITE;
/*!40000 ALTER TABLE `segments` DISABLE KEYS */;
/*!40000 ALTER TABLE `segments` ENABLE KEYS */;
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
INSERT INTO `sessions` VALUES ('y7QxYm9DxxF4Oiy5tp1P7xTFyVms3eN8zUpZbCbv',1,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiNTZ4WTk0R0g4cXUyc0ZRTDJIY1RMNndaN0xsVzRrS0VjVE5LdzY0RyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQvY29tbWVyY2UiO3M6NToicm91dGUiO3M6Mjg6InVzZXIuY29tbWVyY2UucHJvZHVjdHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=',1784288939);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','Admin Panel','2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'site_description','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'contact_email','admin@example.com','2026-07-17 09:06:03','2026-07-17 09:06:03'),(4,'default_timezone','UTC','2026-07-17 09:06:03','2026-07-17 09:06:03'),(5,'date_format','d M, Y','2026-07-17 09:06:03','2026-07-17 09:06:03'),(6,'items_per_page','15','2026-07-17 09:06:03','2026-07-17 09:06:03'),(7,'site_logo',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(8,'site_favicon',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(9,'primary_color','#1fb254','2026-07-17 09:06:03','2026-07-17 09:06:03'),(10,'secondary_color','#215ebf','2026-07-17 09:06:03','2026-07-17 09:06:03'),(11,'mail_mailer','log','2026-07-17 09:06:03','2026-07-17 09:06:03'),(12,'mail_from_name','Laravel','2026-07-17 09:06:03','2026-07-17 09:06:03'),(13,'mail_from_address','hello@example.com','2026-07-17 09:06:03','2026-07-17 09:06:03'),(14,'mail_host','127.0.0.1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(15,'mail_port','2525','2026-07-17 09:06:03','2026-07-17 09:06:03'),(16,'mail_encryption','none','2026-07-17 09:06:03','2026-07-17 09:06:03'),(17,'mail_username',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(18,'mail_password',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(19,'mailgun_domain','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(20,'mailgun_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(21,'mailgun_endpoint','api.mailgun.net','2026-07-17 09:06:03','2026-07-17 09:06:03'),(22,'mailgun_scheme','https','2026-07-17 09:06:03','2026-07-17 09:06:03'),(23,'enable_registration','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(24,'enable_api','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(25,'maintenance_mode','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(26,'require_2fa_for_admins','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(27,'enable_2fa_for_users','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(28,'require_2fa_for_users','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(29,'cookie_popup_enabled','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(30,'cookie_popup_title','We use cookies','2026-07-17 09:06:03','2026-07-17 09:06:03'),(31,'cookie_popup_message','We use cookies to improve your browsing experience, analyze site traffic, and personalize content. By clicking accept, you consent to our use of cookies.','2026-07-17 09:06:03','2026-07-17 09:06:03'),(32,'cookie_popup_accept_label','Accept','2026-07-17 09:06:03','2026-07-17 09:06:03'),(33,'cookie_popup_policy_label','Cookie Policy','2026-07-17 09:06:03','2026-07-17 09:06:03'),(34,'cookie_popup_policy_url','/cookie-policy','2026-07-17 09:06:03','2026-07-17 09:06:03'),(35,'cookie_popup_lifetime_days','365','2026-07-17 09:06:03','2026-07-17 09:06:03'),(36,'enable_email_notifications','1','2026-07-17 09:06:03','2026-07-17 09:06:03'),(37,'enable_sms_notifications','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(38,'enable_push_notifications','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(39,'enable_mobile_push_notifications','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(40,'sms_provider','log','2026-07-17 09:06:03','2026-07-17 09:06:03'),(41,'sms_from_number','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(42,'vonage_api_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(43,'vonage_api_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(44,'twilio_sid','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(45,'twilio_auth_token','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(46,'vapid_public_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(47,'vapid_private_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(48,'firebase_credentials_json','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(49,'storage_provider','local','2026-07-17 09:06:03','2026-07-17 09:06:03'),(50,'storage_s3_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(51,'storage_s3_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(52,'storage_s3_region','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(53,'storage_s3_bucket','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(54,'storage_s3_endpoint','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(55,'storage_s3_url','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(56,'plugin_ga4_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(57,'plugin_ga4_measurement_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(58,'plugin_tawk_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(59,'plugin_tawk_property_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(60,'plugin_tawk_widget_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(61,'plugin_turnstile_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(62,'plugin_turnstile_site_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(63,'plugin_turnstile_secret_key','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(64,'social_google_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(65,'social_google_client_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(66,'social_google_client_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(67,'social_google_callback_url',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(68,'social_facebook_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(69,'social_facebook_client_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(70,'social_facebook_client_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(71,'social_facebook_callback_url',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(72,'social_github_enabled','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(73,'social_github_client_id','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(74,'social_github_client_secret','','2026-07-17 09:06:03','2026-07-17 09:06:03'),(75,'social_github_callback_url',NULL,'2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_accounts`
--

DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_avatar` text COLLATE utf8mb4_unicode_ci,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_accounts_provider_provider_user_id_unique` (`provider`,`provider_user_id`),
  UNIQUE KEY `social_accounts_user_id_provider_unique` (`user_id`,`provider`),
  CONSTRAINT `social_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_accounts`
--

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscribers`
--

DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscribers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscribers_email_unique` (`email`),
  KEY `subscribers_active_index` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscribers`
--

LOCK TABLES `subscribers` WRITE;
/*!40000 ALTER TABLE `subscribers` DISABLE KEYS */;
INSERT INTO `subscribers` VALUES (1,'john.doe@example.com',1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(2,'jane.smith@example.com',1,'2026-07-17 09:06:03','2026-07-17 09:06:03'),(3,'admin.com',1,'2026-07-17 09:06:03','2026-07-17 09:06:03');
/*!40000 ALTER TABLE `subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_attachments`
--

DROP TABLE IF EXISTS `support_ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `reply_id` bigint unsigned DEFAULT NULL,
  `uploaded_by_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by_id` bigint unsigned NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_attachments_ticket_id_foreign` (`ticket_id`),
  KEY `support_ticket_attachments_reply_id_foreign` (`reply_id`),
  KEY `support_ticket_attachments_uploaded_by_type_uploaded_by_id_index` (`uploaded_by_type`,`uploaded_by_id`),
  CONSTRAINT `support_ticket_attachments_reply_id_foreign` FOREIGN KEY (`reply_id`) REFERENCES `support_ticket_replies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_attachments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_attachments`
--

LOCK TABLES `support_ticket_attachments` WRITE;
/*!40000 ALTER TABLE `support_ticket_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_replies`
--

DROP TABLE IF EXISTS `support_ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_replies_ticket_id_foreign` (`ticket_id`),
  KEY `support_ticket_replies_user_id_foreign` (`user_id`),
  KEY `support_ticket_replies_admin_id_foreign` (`admin_id`),
  CONSTRAINT `support_ticket_replies_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_replies_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_replies`
--

LOCK TABLES `support_ticket_replies` WRITE;
/*!40000 ALTER TABLE `support_ticket_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `last_replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_user_id_foreign` (`user_id`),
  KEY `support_tickets_assigned_admin_id_foreign` (`assigned_admin_id`),
  CONSTRAINT `support_tickets_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_notifications`
--

DROP TABLE IF EXISTS `system_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `system_notifications_read_at_index` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_notifications`
--

LOCK TABLES `system_notifications` WRITE;
/*!40000 ALTER TABLE `system_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_opt_in_tokens`
--

DROP TABLE IF EXISTS `telegram_opt_in_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_opt_in_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `channel_account_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_opt_in_tokens_token_unique` (`token`),
  KEY `telegram_opt_in_tokens_contact_id_foreign` (`contact_id`),
  KEY `telegram_opt_in_tokens_channel_account_id_foreign` (`channel_account_id`),
  KEY `telegram_opt_in_lookup_idx` (`workspace_id`,`contact_id`,`channel_account_id`),
  CONSTRAINT `telegram_opt_in_tokens_channel_account_id_foreign` FOREIGN KEY (`channel_account_id`) REFERENCES `channel_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `telegram_opt_in_tokens_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `telegram_opt_in_tokens_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_opt_in_tokens`
--

LOCK TABLES `telegram_opt_in_tokens` WRITE;
/*!40000 ALTER TABLE `telegram_opt_in_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_opt_in_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `locale` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `phone_verification_code` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `otp_two_factor_channel` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Application','Owner',NULL,'user@mail.com','$2y$12$mHksdPnt3YSZfm7ShuWRyOLuMul4i7K6.2XwLytwpP12O477dx8uy',1,'2026-07-17 09:06:03','2026-07-17 09:06:17','172.18.0.1',NULL,NULL,NULL,'UTC','en',NULL,NULL,0,NULL,NULL,'2026-07-17 09:06:03','2026-07-17 09:06:17',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspace_invitations`
--

DROP TABLE IF EXISTS `workspace_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invited_by` bigint unsigned NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_invitations_token_unique` (`token`),
  KEY `workspace_invitations_invited_by_foreign` (`invited_by`),
  KEY `workspace_invitations_workspace_id_email_index` (`workspace_id`,`email`),
  KEY `workspace_invitations_token_index` (`token`),
  CONSTRAINT `workspace_invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workspace_invitations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspace_invitations`
--

LOCK TABLES `workspace_invitations` WRITE;
/*!40000 ALTER TABLE `workspace_invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `workspace_invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspace_members`
--

DROP TABLE IF EXISTS `workspace_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `status` enum('active','invited','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_members_workspace_id_user_id_unique` (`workspace_id`,`user_id`),
  KEY `workspace_members_user_id_foreign` (`user_id`),
  CONSTRAINT `workspace_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workspace_members_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspace_members`
--

LOCK TABLES `workspace_members` WRITE;
/*!40000 ALTER TABLE `workspace_members` DISABLE KEYS */;
INSERT INTO `workspace_members` VALUES (1,1,1,'administrator','active','2026-07-17 09:06:17','2026-07-17 09:06:17');
/*!40000 ALTER TABLE `workspace_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspaces`
--

DROP TABLE IF EXISTS `workspaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','suspended','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspaces_slug_unique` (`slug`),
  KEY `workspaces_owner_id_foreign` (`owner_id`),
  CONSTRAINT `workspaces_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspaces`
--

LOCK TABLES `workspaces` WRITE;
/*!40000 ALTER TABLE `workspaces` DISABLE KEYS */;
INSERT INTO `workspaces` VALUES (1,1,'Application Owner\'s Workspace','application-owner-1','active','UTC',NULL,'2026-07-17 09:06:17','2026-07-17 09:06:17');
/*!40000 ALTER TABLE `workspaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'marketing_app'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-17 14:51:01
