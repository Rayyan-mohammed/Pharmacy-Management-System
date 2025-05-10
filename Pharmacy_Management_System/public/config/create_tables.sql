CREATE TABLE IF NOT EXISTS `inventory_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `medicine_id` int(11) NOT NULL,
    `type` enum('in','out') NOT NULL,
    `quantity` int(11) NOT NULL,
    `reason` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `medicine_id` (`medicine_id`),
    CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 