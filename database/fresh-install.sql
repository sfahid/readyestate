
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `code` varchar(12) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(20) NOT NULL,
  `system_key` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`code`),
  UNIQUE KEY `company_id_3` (`company_id`,`id`),
  UNIQUE KEY `company_id_2` (`company_id`,`system_key`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `accounts_chk_1` CHECK ((`type` in (_utf8mb4'Asset',_utf8mb4'Cash',_utf8mb4'Bank',_utf8mb4'Liability',_utf8mb4'Equity',_utf8mb4'Revenue',_utf8mb4'Expense')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(60) NOT NULL,
  `detail` varchar(250) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_events_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `audit_events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PKR',
  `address` text,
  `owner_id` bigint unsigned NOT NULL,
  `next_journal` int NOT NULL DEFAULT '1',
  `next_invoice` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `description` varchar(250) NOT NULL,
  `quantity_units` bigint NOT NULL,
  `unit_price` bigint NOT NULL,
  `total` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `number` varchar(30) NOT NULL,
  `kind` varchar(15) NOT NULL,
  `party` varchar(150) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `notes` text,
  `total` bigint NOT NULL,
  `paid` bigint NOT NULL DEFAULT '0',
  `journal_id` bigint unsigned NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'posted',
  `created_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`number`),
  UNIQUE KEY `company_id_2` (`company_id`,`id`),
  KEY `company_id_3` (`company_id`,`kind`,`due_date`),
  KEY `company_id_4` (`company_id`,`journal_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `invoices_chk_1` CHECK (((`total` > 0) and (`paid` >= 0) and (`paid` <= `total`))),
  CONSTRAINT `invoices_chk_2` CHECK ((`kind` in (_utf8mb4'sale',_utf8mb4'purchase')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `debit` bigint NOT NULL DEFAULT '0',
  `credit` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`journal_id`),
  KEY `company_id_2` (`company_id`,`account_id`),
  CONSTRAINT `journal_lines_ibfk_1` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `journal_lines_ibfk_2` FOREIGN KEY (`company_id`, `account_id`) REFERENCES `accounts` (`company_id`, `id`),
  CONSTRAINT `journal_lines_chk_1` CHECK ((((`debit` > 0) and (`credit` = 0)) or ((`credit` > 0) and (`debit` = 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `number` varchar(30) NOT NULL,
  `entry_date` date NOT NULL,
  `memo` varchar(250) NOT NULL,
  `source` varchar(25) NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `reversal_of` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`number`),
  UNIQUE KEY `company_id_2` (`company_id`,`id`),
  UNIQUE KEY `reversal_of` (`reversal_of`),
  KEY `company_id_3` (`company_id`,`entry_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `journals_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `journals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `journals_ibfk_3` FOREIGN KEY (`reversal_of`) REFERENCES `journals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_hash` char(64) NOT NULL,
  `email_hash` char(64) NOT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip_hash` (`ip_hash`,`attempted_at`),
  KEY `email_hash` (`email_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `memberships` (
  `company_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(20) NOT NULL,
  PRIMARY KEY (`company_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `memberships_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `memberships_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `memberships_chk_1` CHECK ((`role` in (_utf8mb4'Owner',_utf8mb4'Accountant',_utf8mb4'Viewer')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`invoice_id`),
  KEY `company_id_2` (`company_id`,`journal_id`),
  KEY `company_id_3` (`company_id`,`account_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`company_id`, `invoice_id`) REFERENCES `invoices` (`company_id`, `id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`company_id`, `account_id`) REFERENCES `accounts` (`company_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_asset_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `entry_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`journal_id`),
  KEY `company_id_2` (`company_id`,`asset_id`),
  CONSTRAINT `re_asset_events_ibfk_1` FOREIGN KEY (`company_id`, `asset_id`) REFERENCES `re_company_assets` (`company_id`, `id`),
  CONSTRAINT `re_asset_events_ibfk_2` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `re_asset_events_chk_1` CHECK ((`kind` in (_utf8mb4'purchase',_utf8mb4'sale',_utf8mb4'reversal')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `body` varchar(4000) NOT NULL,
  `purpose` varchar(15) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `re_campaigns_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `re_campaigns_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_campaigns_chk_1` CHECK ((`purpose` in (_utf8mb4'service',_utf8mb4'marketing')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_company_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned NOT NULL,
  `acquired_date` date NOT NULL,
  `cost` bigint NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `cash_account_id` bigint unsigned NOT NULL,
  `seller_party_id` bigint unsigned DEFAULT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'owned',
  `sold_date` date DEFAULT NULL,
  `sale_proceeds` bigint DEFAULT NULL,
  `sale_journal_id` bigint unsigned DEFAULT NULL,
  `buyer_party_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`id`),
  UNIQUE KEY `company_id_2` (`company_id`,`journal_id`),
  UNIQUE KEY `company_id_3` (`company_id`,`sale_journal_id`),
  KEY `company_id_4` (`company_id`,`property_id`),
  KEY `company_id_5` (`company_id`,`cash_account_id`),
  KEY `seller_party_id` (`seller_party_id`),
  KEY `buyer_party_id` (`buyer_party_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `re_company_assets_ibfk_1` FOREIGN KEY (`company_id`, `property_id`) REFERENCES `re_properties` (`company_id`, `id`),
  CONSTRAINT `re_company_assets_ibfk_2` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `re_company_assets_ibfk_3` FOREIGN KEY (`company_id`, `cash_account_id`) REFERENCES `accounts` (`company_id`, `id`),
  CONSTRAINT `re_company_assets_ibfk_4` FOREIGN KEY (`company_id`, `sale_journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `re_company_assets_ibfk_5` FOREIGN KEY (`seller_party_id`) REFERENCES `re_parties` (`id`),
  CONSTRAINT `re_company_assets_ibfk_6` FOREIGN KEY (`buyer_party_id`) REFERENCES `re_parties` (`id`),
  CONSTRAINT `re_company_assets_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_company_assets_chk_1` CHECK (((`cost` > 0) and (`status` in (_utf8mb4'owned',_utf8mb4'sold',_utf8mb4'void')))),
  CONSTRAINT `re_company_assets_chk_2` CHECK (((`sale_proceeds` is null) or (`sale_proceeds` > 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `category` varchar(20) NOT NULL,
  `service_consent` tinyint NOT NULL DEFAULT '0',
  `marketing_consent` tinyint NOT NULL DEFAULT '0',
  `opted_out` tinyint NOT NULL DEFAULT '0',
  `consent_note` varchar(500) NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`phone`),
  UNIQUE KEY `company_id_2` (`company_id`,`id`),
  CONSTRAINT `re_contacts_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_deal_invoices` (
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`company_id`,`invoice_id`),
  KEY `company_id` (`company_id`,`deal_id`),
  CONSTRAINT `re_deal_invoices_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_deal_invoices_ibfk_2` FOREIGN KEY (`company_id`, `invoice_id`) REFERENCES `invoices` (`company_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_deals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned NOT NULL,
  `reference` varchar(40) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `buyer_name` varchar(150) NOT NULL,
  `buyer_phone` varchar(40) NOT NULL DEFAULT '',
  `seller_name` varchar(150) NOT NULL,
  `seller_phone` varchar(40) NOT NULL DEFAULT '',
  `deal_date` date NOT NULL,
  `due_date` date NOT NULL,
  `deal_value` bigint NOT NULL,
  `commission` bigint NOT NULL DEFAULT '0',
  `commission_party` varchar(150) NOT NULL,
  `notes` varchar(2000) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `buyer_party_id` bigint unsigned DEFAULT NULL,
  `seller_party_id` bigint unsigned DEFAULT NULL,
  `commission_payer_party_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`reference`),
  UNIQUE KEY `company_id_2` (`company_id`,`id`),
  KEY `company_id_3` (`company_id`,`property_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `re_deals_ibfk_1` FOREIGN KEY (`company_id`, `property_id`) REFERENCES `re_properties` (`company_id`, `id`),
  CONSTRAINT `re_deals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_deals_chk_1` CHECK ((`kind` in (_utf8mb4'sale',_utf8mb4'rental'))),
  CONSTRAINT `re_deals_chk_2` CHECK (((`deal_value` > 0) and (`commission` >= 0))),
  CONSTRAINT `re_deals_chk_3` CHECK ((`status` in (_utf8mb4'active',_utf8mb4'completed',_utf8mb4'cancelled')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_direct_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `amount` bigint NOT NULL,
  `settled_date` date NOT NULL,
  `reference` varchar(150) NOT NULL,
  `note` varchar(500) NOT NULL DEFAULT '',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reversed_at` datetime DEFAULT NULL,
  `reversal_reason` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `company_id` (`company_id`,`deal_id`,`settled_date`),
  CONSTRAINT `re_direct_settlements_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_direct_settlements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_direct_settlements_chk_1` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `filename` varchar(180) NOT NULL,
  `mime` varchar(80) NOT NULL,
  `file_size` int unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `content` mediumblob NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`deal_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `re_documents_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_documents_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `kind` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `detail` text NOT NULL,
  `event_date` date NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `company_id` (`company_id`,`deal_id`,`event_date`),
  CONSTRAINT `re_events_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned DEFAULT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `task_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `body` varchar(4000) NOT NULL,
  `purpose` varchar(15) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'prepared',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `opened_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`campaign_id`,`phone`),
  KEY `company_id_2` (`company_id`,`deal_id`),
  KEY `company_id_3` (`company_id`,`contact_id`),
  KEY `company_id_4` (`company_id`,`task_id`),
  KEY `created_by` (`created_by`),
  KEY `company_id_5` (`company_id`,`status`,`scheduled_at`),
  CONSTRAINT `re_messages_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_messages_ibfk_2` FOREIGN KEY (`company_id`, `contact_id`) REFERENCES `re_contacts` (`company_id`, `id`),
  CONSTRAINT `re_messages_ibfk_3` FOREIGN KEY (`company_id`, `task_id`) REFERENCES `re_tasks` (`company_id`, `id`),
  CONSTRAINT `re_messages_ibfk_4` FOREIGN KEY (`company_id`, `campaign_id`) REFERENCES `re_campaigns` (`company_id`, `id`),
  CONSTRAINT `re_messages_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_messages_chk_1` CHECK ((`purpose` in (_utf8mb4'service',_utf8mb4'marketing'))),
  CONSTRAINT `re_messages_chk_2` CHECK ((`status` in (_utf8mb4'prepared',_utf8mb4'opened',_utf8mb4'sent_manual',_utf8mb4'cancelled')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_messaging_settings` (
  `company_id` bigint unsigned NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `signature` varchar(500) NOT NULL DEFAULT '',
  `updated_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`company_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `re_messaging_settings_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `re_messaging_settings_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `amount` bigint NOT NULL,
  `entry_date` date NOT NULL,
  `note` varchar(150) NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`journal_id`),
  KEY `company_id_2` (`company_id`,`deal_id`),
  CONSTRAINT `re_movements_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_movements_ibfk_2` FOREIGN KEY (`company_id`, `journal_id`) REFERENCES `journals` (`company_id`, `id`),
  CONSTRAINT `re_movements_chk_1` CHECK ((`kind` in (_utf8mb4'token',_utf8mb4'biana',_utf8mb4'receipt',_utf8mb4'seller_payment',_utf8mb4'refund'))),
  CONSTRAINT `re_movements_chk_2` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_parties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `address` varchar(500) NOT NULL DEFAULT '',
  `notes` varchar(2000) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_party_access` (
  `company_id` bigint unsigned NOT NULL,
  `party_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`company_id`,`party_id`),
  KEY `party_id` (`party_id`),
  CONSTRAINT `re_party_access_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `re_party_access_ibfk_2` FOREIGN KEY (`party_id`) REFERENCES `re_parties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `reference` varchar(40) NOT NULL,
  `title` varchar(150) NOT NULL,
  `property_type` varchar(20) NOT NULL,
  `location` varchar(250) NOT NULL,
  `area` varchar(80) NOT NULL DEFAULT '',
  `owner_name` varchar(150) NOT NULL,
  `owner_phone` varchar(40) NOT NULL DEFAULT '',
  `asking_price` bigint NOT NULL DEFAULT '0',
  `notes` varchar(2000) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `owner_party_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`reference`),
  UNIQUE KEY `company_id_2` (`company_id`,`id`),
  CONSTRAINT `re_properties_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `re_properties_chk_1` CHECK ((`asking_price` >= 0)),
  CONSTRAINT `re_properties_chk_2` CHECK ((`status` in (_utf8mb4'available',_utf8mb4'inactive')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `re_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `deal_id` bigint unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `detail` varchar(2000) NOT NULL DEFAULT '',
  `assigned_to` bigint unsigned NOT NULL,
  `due_at` datetime NOT NULL,
  `remind_at` datetime NOT NULL,
  `priority` varchar(10) NOT NULL DEFAULT 'normal',
  `status` varchar(15) NOT NULL DEFAULT 'pending',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`id`),
  KEY `company_id_2` (`company_id`,`deal_id`),
  KEY `created_by` (`created_by`),
  KEY `assigned_to` (`assigned_to`),
  KEY `company_id_3` (`company_id`,`status`,`remind_at`),
  CONSTRAINT `re_tasks_ibfk_1` FOREIGN KEY (`company_id`, `deal_id`) REFERENCES `re_deals` (`company_id`, `id`),
  CONSTRAINT `re_tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `re_tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `re_tasks_chk_1` CHECK ((`status` in (_utf8mb4'pending',_utf8mb4'done',_utf8mb4'cancelled'))),
  CONSTRAINT `re_tasks_chk_2` CHECK ((`priority` in (_utf8mb4'normal',_utf8mb4'high')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requests` (
  `company_id` bigint unsigned NOT NULL,
  `request_key` varchar(64) NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`,`request_key`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

