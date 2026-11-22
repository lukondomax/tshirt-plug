ALTER TABLE `long_term_loans`
ADD COLUMN `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash' AFTER `amount`;
