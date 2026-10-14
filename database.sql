CREATE DATABASE IF NOT EXISTS florist_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE florist_db;

CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    flower_name VARCHAR(100) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price BIGINT UNSIGNED NOT NULL,
    sale_date DATE NOT NULL,
    payment_method ENUM('Tunai', 'Transfer', 'QRIS', 'Kartu') NOT NULL DEFAULT 'Tunai',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sales_date (sale_date),
    INDEX idx_sales_customer (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sales (customer_name, flower_name, quantity, unit_price, sale_date, payment_method, notes)
SELECT sample.customer_name, sample.flower_name, sample.quantity, sample.unit_price,
       sample.sale_date, sample.payment_method, sample.notes
FROM (
    SELECT 'Alya Putri' AS customer_name, 'Mawar Merah' AS flower_name, 2 AS quantity, 185000 AS unit_price,
           CURDATE() AS sale_date, 'QRIS' AS payment_method, 'Kartu ucapan ulang tahun' AS notes
    UNION ALL SELECT 'Bima Pratama', 'Tulip', 1, 250000, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Transfer', 'Warna pastel'
    UNION ALL SELECT 'Citra Maharani', 'Buket Peony', 3, 320000, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Tunai', NULL
    UNION ALL SELECT 'Dinda Safitri', 'Matahari', 2, 145000, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Kartu', 'Untuk wisuda'
    UNION ALL SELECT 'Farhan Akbar', 'Anggrek', 1, 275000, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Transfer', NULL
) AS sample
WHERE NOT EXISTS (SELECT 1 FROM sales LIMIT 1);
