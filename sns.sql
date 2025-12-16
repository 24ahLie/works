-- ...existing code...
CREATE DATABASE IF NOT EXISTS `sns` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sns`;

CREATE TABLE IF NOT EXISTS `Characters` (
  `Character_id` INT NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(255) NOT NULL,
  `Title` VARCHAR(255),
  `BirthMonth` TINYINT,
  `BirthDay` TINYINT,
  `Character_img` VARCHAR(1024),
  `Gender` VARCHAR(50),
  `Age` VARCHAR(50),
  PRIMARY KEY (`Character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Users` (
  `User_id` INT NOT NULL AUTO_INCREMENT,
  `User_name` VARCHAR(255) NOT NULL,
  `PassDig` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Character_id` INT,
  PRIMARY KEY (`User_id`),
  KEY `idx_users_character_id` (`Character_id`),
  CONSTRAINT `fk_users_character` FOREIGN KEY (`Character_id`)
    REFERENCES `Characters` (`Character_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Posts` (
  `Post_id` INT NOT NULL AUTO_INCREMENT,
  `User_id` INT NULL,
  `Post_Content` TEXT,
  `Post_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Post_id`),
  KEY `idx_posts_user` (`User_id`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`User_id`)
    REFERENCES `Users` (`User_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `Comments` (
  `Comment_id` INT NOT NULL AUTO_INCREMENT,
  `Post_id` INT NOT NULL,
  `User_id` INT NULL,
  `Comment_content` TEXT,
  `Comment_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Comment_id`),
  KEY `idx_comments_post` (`Post_id`),
  KEY `idx_comments_user` (`User_id`),
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`Post_id`)
    REFERENCES `Posts` (`Post_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`User_id`)
    REFERENCES `Users` (`User_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ...existing code...