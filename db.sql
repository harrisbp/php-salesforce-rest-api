CREATE TABLE IF NOT EXISTS `salesforce_cache` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`sfid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
	`type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
	`data` text COLLATE utf8mb4_unicode_ci NOT NULL,
	`sf_created_at` datetime NULL,
	`sf_updated_at` datetime NULL,
	`created_at` datetime NOT NULL,
	`updated_at` datetime NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `idx_sfid_type` (`sfid`,`type`),
	KEY `type` (`type`),
	KEY `sfid` (`sfid`),
	FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci