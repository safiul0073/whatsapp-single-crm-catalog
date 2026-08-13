-- Commerce product import schema and loader for MySQL.
-- phpMyAdmin/shared-hosting safe: select your database first, then import this file.
--
-- Usage:
--   mysql -u username -p database_name < database/exports/commerce_product_import.sql
--
-- For an existing migrated Laravel database, keep the CREATE TABLE sections as a
-- reference and use only the staging/import section at the bottom.

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `workspaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspaces_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
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
  KEY `media_type_index` (`type`),
  KEY `media_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_categories_workspace_id_slug_unique` (`workspace_id`, `slug`),
  KEY `commerce_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `commerce_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `commerce_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_categories_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_brands_workspace_id_slug_unique` (`workspace_id`, `slug`),
  CONSTRAINT `commerce_brands_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_audiences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_audiences_workspace_id_slug_unique` (`workspace_id`, `slug`),
  CONSTRAINT `commerce_audiences_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_products` (
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
  `wizard_step` tinyint unsigned NOT NULL DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_products_workspace_id_slug_unique` (`workspace_id`, `slug`),
  KEY `commerce_products_status_index` (`status`),
  KEY `commerce_products_category_id_foreign` (`category_id`),
  KEY `commerce_products_brand_id_foreign` (`brand_id`),
  KEY `commerce_products_audience_id_foreign` (`audience_id`),
  KEY `commerce_products_primary_media_id_foreign` (`primary_media_id`),
  CONSTRAINT `commerce_products_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `commerce_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `commerce_brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_audience_id_foreign` FOREIGN KEY (`audience_id`) REFERENCES `commerce_audiences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commerce_products_primary_media_id_foreign` FOREIGN KEY (`primary_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_product_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `media_id` bigint unsigned NOT NULL,
  `media_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gallery',
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_media_product_id_media_id_unique` (`product_id`, `media_id`),
  KEY `commerce_product_media_product_id_position_index` (`product_id`, `position`),
  KEY `commerce_product_media_workspace_id_foreign` (`workspace_id`),
  KEY `commerce_product_media_media_id_foreign` (`media_id`),
  CONSTRAINT `commerce_product_media_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_media_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_media_media_id_foreign` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_product_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_options_product_id_code_unique` (`product_id`, `code`),
  KEY `commerce_product_options_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `commerce_product_options_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_options_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_product_option_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `option_id` bigint unsigned NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_option_values_option_id_value_unique` (`option_id`, `value`),
  KEY `commerce_product_option_values_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `commerce_product_option_values_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_option_values_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `commerce_product_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_retailer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attributes` json DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `stock_quantity` int unsigned NOT NULL DEFAULT 0,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `package_dimensions` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerce_product_variants_workspace_id_sku_unique` (`workspace_id`, `sku`),
  UNIQUE KEY `commerce_product_variants_workspace_id_meta_retailer_id_unique` (`workspace_id`, `meta_retailer_id`),
  KEY `commerce_product_variants_status_index` (`status`),
  KEY `commerce_product_variants_product_id_foreign` (`product_id`),
  KEY `commerce_product_variants_media_id_foreign` (`media_id`),
  CONSTRAINT `commerce_product_variants_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerce_product_variants_media_id_foreign` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

DROP TABLE IF EXISTS `commerce_product_import_rows`;

CREATE TABLE `commerce_product_import_rows` (
  `workspace_id` bigint unsigned NOT NULL,
  `category_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `care_information` text COLLATE utf8mb4_unicode_ci,
  `product_condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `country_of_origin` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BD',
  `product_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `option_1_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_1_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_1_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_2_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_2_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_2_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_retailer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `stock_quantity` int unsigned NOT NULL DEFAULT 0,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `primary_media_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `commerce_product_import_rows_workspace_product_index` (`workspace_id`, `product_slug`),
  KEY `commerce_product_import_rows_workspace_sku_index` (`workspace_id`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example row. Replace this with LOAD DATA INFILE or CSV-generated inserts.
-- INSERT INTO `commerce_product_import_rows`
-- (`workspace_id`, `category_slug`, `category_name`, `brand_slug`, `brand_name`, `audience_slug`, `audience_name`, `product_slug`, `product_name`, `description`, `care_information`, `product_condition`, `country_of_origin`, `product_status`, `option_1_name`, `option_1_code`, `option_1_value`, `option_2_name`, `option_2_code`, `option_2_value`, `sku`, `meta_retailer_id`, `price`, `compare_at_price`, `stock_quantity`, `weight_kg`, `media_id`, `primary_media_id`)
-- VALUES
-- (1, 'jackets', 'Jackets', 'dhaka-apparel', 'Dhaka Apparel', 'women', 'Women', 'premium-wool-coat', 'Premium Wool Coat', 'Warm wool coat for catalog selling.', 'Dry clean only.', 'new', 'BD', 'active', 'Color', 'color', 'Black', 'Size', 'size', 'M', 'PWC-BLK-M', 'PWC-BLK-M', 129.00, 159.00, 20, 0.800, NULL, NULL);

START TRANSACTION;

INSERT INTO `commerce_categories` (`workspace_id`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT DISTINCT `workspace_id`, `category_name`, `category_slug`, 1, NOW(), NOW()
FROM `commerce_product_import_rows`
WHERE `category_slug` IS NOT NULL
  AND `category_name` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_active` = 1,
  `updated_at` = NOW();

INSERT INTO `commerce_brands` (`workspace_id`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT DISTINCT `workspace_id`, `brand_name`, `brand_slug`, 1, NOW(), NOW()
FROM `commerce_product_import_rows`
WHERE `brand_slug` IS NOT NULL
  AND `brand_name` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_active` = 1,
  `updated_at` = NOW();

INSERT INTO `commerce_audiences` (`workspace_id`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT DISTINCT `workspace_id`, `audience_name`, `audience_slug`, 1, NOW(), NOW()
FROM `commerce_product_import_rows`
WHERE `audience_slug` IS NOT NULL
  AND `audience_name` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_active` = 1,
  `updated_at` = NOW();

INSERT INTO `commerce_products`
(`workspace_id`, `category_id`, `brand_id`, `audience_id`, `primary_media_id`, `name`, `slug`, `brand`, `description`, `care_information`, `condition`, `audience`, `country_of_origin`, `status`, `wizard_step`, `published_at`, `created_at`, `updated_at`)
SELECT
  import_rows.`workspace_id`,
  categories.`id`,
  brands.`id`,
  audiences.`id`,
  MAX(import_rows.`primary_media_id`),
  import_rows.`product_name`,
  import_rows.`product_slug`,
  brands.`name`,
  MAX(import_rows.`description`),
  MAX(import_rows.`care_information`),
  MAX(import_rows.`product_condition`),
  audiences.`name`,
  MAX(import_rows.`country_of_origin`),
  MAX(import_rows.`product_status`),
  5,
  CASE WHEN MAX(import_rows.`product_status`) = 'active' THEN NOW() ELSE NULL END,
  NOW(),
  NOW()
FROM `commerce_product_import_rows` import_rows
LEFT JOIN `commerce_categories` categories
  ON categories.`workspace_id` = import_rows.`workspace_id`
  AND categories.`slug` = import_rows.`category_slug`
LEFT JOIN `commerce_brands` brands
  ON brands.`workspace_id` = import_rows.`workspace_id`
  AND brands.`slug` = import_rows.`brand_slug`
LEFT JOIN `commerce_audiences` audiences
  ON audiences.`workspace_id` = import_rows.`workspace_id`
  AND audiences.`slug` = import_rows.`audience_slug`
GROUP BY import_rows.`workspace_id`, import_rows.`product_slug`, import_rows.`product_name`, categories.`id`, brands.`id`, audiences.`id`, brands.`name`, audiences.`name`
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `brand_id` = VALUES(`brand_id`),
  `audience_id` = VALUES(`audience_id`),
  `primary_media_id` = VALUES(`primary_media_id`),
  `name` = VALUES(`name`),
  `brand` = VALUES(`brand`),
  `description` = VALUES(`description`),
  `care_information` = VALUES(`care_information`),
  `condition` = VALUES(`condition`),
  `audience` = VALUES(`audience`),
  `country_of_origin` = VALUES(`country_of_origin`),
  `status` = VALUES(`status`),
  `wizard_step` = VALUES(`wizard_step`),
  `published_at` = COALESCE(`commerce_products`.`published_at`, VALUES(`published_at`)),
  `updated_at` = NOW();

INSERT INTO `commerce_product_options` (`workspace_id`, `product_id`, `name`, `code`, `position`, `created_at`, `updated_at`)
SELECT DISTINCT import_rows.`workspace_id`, products.`id`, import_rows.`option_1_name`, import_rows.`option_1_code`, 0, NOW(), NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
WHERE import_rows.`option_1_name` IS NOT NULL
  AND import_rows.`option_1_code` IS NOT NULL
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `position` = VALUES(`position`), `updated_at` = NOW();

INSERT INTO `commerce_product_options` (`workspace_id`, `product_id`, `name`, `code`, `position`, `created_at`, `updated_at`)
SELECT DISTINCT import_rows.`workspace_id`, products.`id`, import_rows.`option_2_name`, import_rows.`option_2_code`, 1, NOW(), NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
WHERE import_rows.`option_2_name` IS NOT NULL
  AND import_rows.`option_2_code` IS NOT NULL
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `position` = VALUES(`position`), `updated_at` = NOW();

INSERT INTO `commerce_product_option_values` (`workspace_id`, `option_id`, `value`, `position`, `created_at`, `updated_at`)
SELECT DISTINCT import_rows.`workspace_id`, options.`id`, import_rows.`option_1_value`, 0, NOW(), NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
INNER JOIN `commerce_product_options` options
  ON options.`product_id` = products.`id`
  AND options.`code` = import_rows.`option_1_code`
WHERE import_rows.`option_1_value` IS NOT NULL
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `commerce_product_option_values` (`workspace_id`, `option_id`, `value`, `position`, `created_at`, `updated_at`)
SELECT DISTINCT import_rows.`workspace_id`, options.`id`, import_rows.`option_2_value`, 1, NOW(), NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
INNER JOIN `commerce_product_options` options
  ON options.`product_id` = products.`id`
  AND options.`code` = import_rows.`option_2_code`
WHERE import_rows.`option_2_value` IS NOT NULL
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `commerce_product_variants`
(`workspace_id`, `product_id`, `media_id`, `sku`, `meta_retailer_id`, `attributes`, `price`, `compare_at_price`, `stock_quantity`, `weight_kg`, `package_dimensions`, `status`, `created_at`, `updated_at`)
SELECT
  import_rows.`workspace_id`,
  products.`id`,
  import_rows.`media_id`,
  import_rows.`sku`,
  COALESCE(import_rows.`meta_retailer_id`, import_rows.`sku`),
  JSON_REMOVE(JSON_OBJECT(
    COALESCE(import_rows.`option_1_code`, '_empty_1'), import_rows.`option_1_value`,
    COALESCE(import_rows.`option_2_code`, '_empty_2'), import_rows.`option_2_value`
  ), '$._empty_1', '$._empty_2'),
  import_rows.`price`,
  import_rows.`compare_at_price`,
  import_rows.`stock_quantity`,
  import_rows.`weight_kg`,
  NULL,
  'active',
  NOW(),
  NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
ON DUPLICATE KEY UPDATE
  `product_id` = VALUES(`product_id`),
  `media_id` = VALUES(`media_id`),
  `meta_retailer_id` = VALUES(`meta_retailer_id`),
  `attributes` = VALUES(`attributes`),
  `price` = VALUES(`price`),
  `compare_at_price` = VALUES(`compare_at_price`),
  `stock_quantity` = VALUES(`stock_quantity`),
  `weight_kg` = VALUES(`weight_kg`),
  `status` = VALUES(`status`),
  `updated_at` = NOW();

INSERT INTO `commerce_product_media`
(`workspace_id`, `product_id`, `media_id`, `media_type`, `role`, `alt_text`, `position`, `is_primary`, `created_at`, `updated_at`)
SELECT DISTINCT import_rows.`workspace_id`, products.`id`, media.`id`, media.`type`, IF(import_rows.`primary_media_id` = media.`id`, 'primary', 'gallery'), products.`name`, 0, IF(import_rows.`primary_media_id` = media.`id`, 1, 0), NOW(), NOW()
FROM `commerce_product_import_rows` import_rows
INNER JOIN `commerce_products` products
  ON products.`workspace_id` = import_rows.`workspace_id`
  AND products.`slug` = import_rows.`product_slug`
INNER JOIN `media`
  ON media.`id` = import_rows.`media_id`
WHERE import_rows.`media_id` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `media_type` = VALUES(`media_type`),
  `role` = VALUES(`role`),
  `alt_text` = VALUES(`alt_text`),
  `is_primary` = VALUES(`is_primary`),
  `updated_at` = NOW();

COMMIT;
