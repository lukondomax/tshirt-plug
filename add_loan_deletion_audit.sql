ALTER TABLE `long_term_loans`
  ADD COLUMN `deleted_at` datetime DEFAULT NULL,
  ADD COLUMN `deleted_by_user_id` int(11) DEFAULT NULL,
  ADD KEY `idx_long_term_loans_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_long_term_loans_deleted_by`
    FOREIGN KEY (`deleted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `loan_deletion_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `borrower_name` varchar(150) NOT NULL,
  `borrower_phone` varchar(32) DEFAULT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `deleted_by_user_id` int(11) DEFAULT NULL,
  `deleted_by_username` varchar(100) NOT NULL,
  `deleted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loan_deletion_logs_loan` (`loan_id`),
  KEY `idx_loan_deletion_logs_deleted_at` (`deleted_at`),
  KEY `idx_loan_deletion_logs_recorder` (`deleted_by_user_id`),
  CONSTRAINT `fk_loan_deletion_logs_recorder`
    FOREIGN KEY (`deleted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
