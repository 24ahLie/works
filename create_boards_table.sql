-- create_boards_table.sql
-- 掲示板関連のテーブルを作成するSQL
-- Boards: 掲示板の基本情報（掲示板ID、名前、概要）
-- BoardComments: 掲示板への書き込み・コメント（コメントID、掲示板ID、投稿者、コメント内容）

-- ========================================
-- 1. Boards テーブル（掲示板基本情報）
-- ========================================
CREATE TABLE IF NOT EXISTS `Boards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT '掲示板名',
  `slug` VARCHAR(255) NOT NULL COMMENT 'URL用スラッグ',
  `description` TEXT DEFAULT NULL COMMENT '掲示板の概要・説明',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boards_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='掲示板マスタ';

-- ========================================
-- 2. BoardComments テーブル（書き込み・コメント）
-- ========================================
CREATE TABLE IF NOT EXISTS `BoardComments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'コメント番号',
  `board_id` INT UNSIGNED NOT NULL COMMENT '掲示板ID（外部キー）',
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT '投稿者ID（外部キー、任意）',
  `content` TEXT NOT NULL COMMENT 'コメント内容',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`id`),
  KEY `idx_board_comments_board_id` (`board_id`),
  KEY `idx_board_comments_user_id` (`user_id`),
  KEY `idx_board_comments_created_at` (`created_at`),
  CONSTRAINT `fk_board_comments_board_id` FOREIGN KEY (`board_id`) REFERENCES `Boards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='掲示板への書き込みコメント';

-- 外部キー: user_id -> Users.id （Users テーブルが存在する場合は以下をコメント解除）
-- ALTER TABLE `BoardComments`
--   ADD CONSTRAINT `fk_board_comments_user_id` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
