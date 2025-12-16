-- Add anilist_id column and unique key to Characters table
ALTER TABLE `Characters`
  ADD COLUMN `anilist_id` INT NULL AFTER `Character_id`;

-- Add unique index so ON DUPLICATE KEY works on anilist_id
ALTER TABLE `Characters`
  ADD UNIQUE KEY `unq_anilist_id` (`anilist_id`);

-- If you prefer to keep anilist_id NOT NULL, run this after populating values:
-- ALTER TABLE `Characters` MODIFY COLUMN `anilist_id` INT NOT NULL;
