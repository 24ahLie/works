ALTER TABLE `Characters` ADD COLUMN `anilist_id` INT NULL AFTER `Character_id`;
ALTER TABLE `Characters` ADD UNIQUE KEY `unq_anilist_id` (`anilist_id`);
