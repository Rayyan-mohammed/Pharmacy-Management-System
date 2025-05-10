ALTER TABLE `medicines` 
ADD COLUMN `expiration_date` DATE DEFAULT NULL AFTER `prescription_needed`; 