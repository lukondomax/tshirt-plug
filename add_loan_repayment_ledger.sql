CREATE TABLE `loan_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loan_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `loan_repayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loan_repayments_customer_date` (`customer_id`, `payment_date`, `id`),
  KEY `idx_loan_repayments_recorder` (`recorded_by_user_id`),
  CONSTRAINT `fk_loan_repayments_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `loan_customers` (`id`),
  CONSTRAINT `fk_loan_repayments_recorder`
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `loan_repayment_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repayment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repayment_loan` (`repayment_id`, `loan_id`),
  KEY `idx_loan_allocations_loan` (`loan_id`),
  CONSTRAINT `fk_loan_allocations_repayment`
    FOREIGN KEY (`repayment_id`) REFERENCES `loan_repayments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loan_allocations_loan`
    FOREIGN KEY (`loan_id`) REFERENCES `long_term_loans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `loan_writeoffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text NOT NULL,
  `recorded_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loan_writeoffs_loan` (`loan_id`),
  KEY `idx_loan_writeoffs_recorder` (`recorded_by_user_id`),
  CONSTRAINT `fk_loan_writeoffs_loan`
    FOREIGN KEY (`loan_id`) REFERENCES `long_term_loans` (`id`),
  CONSTRAINT `fk_loan_writeoffs_recorder`
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `long_term_loans`
  ADD COLUMN `customer_id` int(11) DEFAULT NULL,
  ADD COLUMN `legacy_closed` tinyint(1) NOT NULL DEFAULT 0;

ALTER TABLE `long_term_loans`
  ADD KEY `idx_long_term_loans_customer_status` (`customer_id`, `clearance`, `legacy_closed`);

ALTER TABLE `loan_customers`
  ADD COLUMN `legacy_loan_id` int(11) DEFAULT NULL,
  ADD UNIQUE KEY `uq_loan_customers_legacy_loan` (`legacy_loan_id`);

INSERT INTO `loan_customers` (`name`, `legacy_loan_id`)
SELECT `customer_name`, `id`
FROM `long_term_loans`;

UPDATE `long_term_loans` AS l
JOIN `loan_customers` AS c ON c.`legacy_loan_id` = l.`id`
SET l.`customer_id` = c.`id`;

UPDATE `long_term_loans`
SET `legacy_closed` = 1
WHERE `clearance` = 'Cleared';

ALTER TABLE `loan_customers`
  DROP COLUMN `legacy_loan_id`;

ALTER TABLE `long_term_loans`
  ADD CONSTRAINT `fk_long_term_loans_customer`
  FOREIGN KEY (`customer_id`) REFERENCES `loan_customers` (`id`);
