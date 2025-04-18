CREATE TABLE IF NOT EXISTS `hipaaichat_sessions` (
    `chat_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT NOT NULL,
    `title` VARCHAR(255) DEFAULT 'New Chat',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`chat_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `hipaaichat_messages` (
    `message_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `chat_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT NOT NULL,
    `sender` ENUM('user', 'ai') NOT NULL,
    `content` TEXT NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`),
    INDEX `idx_chat_id_ts` (`chat_id`, `timestamp`),
    FOREIGN KEY (`chat_id`) REFERENCES `hipaaichat_sessions`(`chat_id`) ON DELETE CASCADE
);