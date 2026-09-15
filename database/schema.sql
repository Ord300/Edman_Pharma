-- Boyambi Pharmacy - Schema complet
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS activity_logs, notifications, payments, invoice_items, invoices, sale_items, sales, stock_movements, medicines, categories, suppliers, customers, users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','pharmacien','caissier','gestionnaire_stock') NOT NULL DEFAULT 'pharmacien',
  avatar VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  company VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  products_supplied TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('M','F','Autre') DEFAULT 'M',
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  description TEXT NULL,
  dosage VARCHAR(100) NULL,
  form VARCHAR(80) NULL,
  laboratory VARCHAR(120) NULL,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantity INT NOT NULL DEFAULT 0,
  alert_threshold INT NOT NULL DEFAULT 10,
  manufacture_date DATE NULL,
  expiry_date DATE NULL,
  location VARCHAR(100) NULL,
  supplier_id INT NULL,
  status ENUM('available','low','out','expired','archived') DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  INDEX idx_med_name (name),
  INDEX idx_med_code (code),
  INDEX idx_med_expiry (expiry_date),
  INDEX idx_med_qty (quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT NOT NULL,
  type ENUM('entree','sortie','ajustement','retour') NOT NULL,
  quantity INT NOT NULL,
  old_stock INT NOT NULL,
  new_stock INT NOT NULL,
  user_id INT NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_mov_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT NULL,
  user_id INT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('especes','mobile_money','carte','credit') DEFAULT 'especes',
  status ENUM('completed','cancelled') DEFAULT 'completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_sales_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  medicine_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(30) NOT NULL UNIQUE,
  sale_id INT NOT NULL,
  customer_id INT NULL,
  total DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  medicine_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('especes','mobile_money','carte','credit') DEFAULT 'especes',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_log_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

-- Seed Categories
INSERT INTO categories (name, description) VALUES
('Antalgiques','Douleurs et fièvre'),
('Antibiotiques','Infections bactériennes'),
('Antipaludéens','Traitement paludisme'),
('Vitamines','Compléments'),
('Antiseptiques','Désinfection'),
('Cardiologie','Cœur et tension'),
('Pédiatrie','Enfants'),
('Gastro-entérologie','Digestion');

-- Seed Suppliers
INSERT INTO suppliers (name, company, phone, email, address, products_supplied) VALUES
('Dr. Mukendi','Tedis Pharma RDC','+243 81 000 0001','contact@tedis.cd','Kinshasa, Gombe','Antalgiques, Antibiotiques'),
('Mme Kabeya','Copharmed','+243 82 111 2222','info@copharmed.cd','Lubumbashi','Vitamines, Antiseptiques'),
('Labo Boyambi','Boyambi Labs','+243 99 333 4444','labo@boyambi.cd','Kisangani','Antipaludéens, Pédiatrie');

-- Seed Customers
INSERT INTO customers (full_name, gender, phone, address) VALUES
('Jean Kabasele','M','+243 81 234 5678','Commune Makiso, Kisangani'),
('Marie Amina','F','+243 82 345 6789','Commune Mangobo'),
('Patient Anonyme','Autre','+243 90 000 0000','Hôpital Boyambi');

-- Seed Users (password = password123)
INSERT INTO users (name, email, password, role) VALUES
('Admin Boyambi','admin@boyambi.cd','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('Dr. Pharmacien','pharmacien@boyambi.cd','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','pharmacien'),
('Caissier Principal','caisse@boyambi.cd','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','caissier'),
('Gestion Stock','stock@boyambi.cd','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','gestionnaire_stock');

-- Seed Medicines
INSERT INTO medicines (code, name, category_id, description, dosage, form, laboratory, purchase_price, sale_price, quantity, alert_threshold, manufacture_date, expiry_date, location, supplier_id, status) VALUES
('MED-001','Paracétamol 500mg',1,'Antalgique et antipyrétique','500mg','Comprimé','Sanofi',800,1200,8,10,'2024-01-10','2026-08-15','A1-R1',1,'low'),
('MED-002','Amoxicilline 500mg',2,'Antibiotique pénicilline','500mg','Gélule','GSK',1500,2500,120,15,'2024-06-01','2027-06-01','A1-R2',1,'available'),
('MED-003','Artemether 80mg',3,'Antipaludéen','80mg','Comprimé','Boyambi Labs',2000,3500,0,10,'2023-01-01','2025-02-01','B1-R1',3,'out'),
('MED-004','Vitamine C 1000mg',4,'Complément immunitaire','1000mg','Comprimé effervescent','Bayer',1200,1800,45,10,'2024-03-15','2026-12-30','A2-R1',2,'available'),
('MED-005','Chlorhexidine 5%',5,'Antiseptique','5%','Solution','Copharmed',2500,4000,22,5,'2024-02-01','2026-09-10','C1-R1',2,'available'),
('MED-006','Amlodipine 5mg',6,'Antihypertenseur','5mg','Comprimé','Pfizer',1800,3000,5,10,'2024-01-20','2026-10-20','A1-R3',1,'low'),
('MED-007','Sirop Pédiatrique Ambroxol',7,'Expectorant enfants','15mg/5ml','Sirop','GSK',2200,3500,30,8,'2024-05-01','2027-01-01','B2-R1',3,'available'),
('MED-008','Omeprazole 20mg',8,'Anti-acide','20mg','Gélule','AstraZeneca',1300,2100,60,12,'2024-04-10','2026-04-10','A3-R1',1,'available'),
('MED-009','Ibuprofène 400mg',1,'Anti-inflammatoire','400mg','Comprimé','Sanofi',900,1400,95,15,'2024-03-01','2027-03-01','A1-R4',1,'available'),
('MED-010','Quinine 500mg',3,'Antipaludéen','500mg','Comprimé','Tedis Pharma',1700,2800,3,10,'2023-06-01','2025-12-15','B1-R2',1,'low'),
('MED-011','Metformine 500mg',6,'Antidiabétique','500mg','Comprimé','Merck',1600,2600,40,10,'2024-02-15','2026-11-01','A2-R2',1,'available'),
('MED-012','Cotrimoxazole 480mg',2,'Antibiotique','480mg','Comprimé','Copharmed',700,1100,2,10,'2023-03-01','2025-09-01','A1-R5',2,'low');

-- Seed Sales (last 7 days)
INSERT INTO sales (invoice_number, customer_id, user_id, subtotal, discount, total, payment_method) VALUES
('FAC-20250101-0001',1,2,5600,0,5600,'especes'),
('FAC-20250102-0002',2,3,7500,500,7000,'mobile_money'),
('FAC-20250103-0003',1,3,12000,0,12000,'especes');

INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, discount, subtotal) VALUES
(1,2,2,2500,0,5000),
(1,9,1,1400,0,1400),
(2,4,2,1800,0,3600),
(2,8,1,2100,500,1600),
(3,2,4,2500,0,10000);

INSERT INTO invoices (invoice_number, sale_id, customer_id, total) VALUES
('FAC-20250101-0001',1,1,5600),
('FAC-20250102-0002',2,2,7000),
('FAC-20250103-0003',3,1,12000);

INSERT INTO invoice_items (invoice_id, medicine_name, quantity, unit_price, subtotal) VALUES
(1,'Amoxicilline 500mg',2,2500,5000),
(1,'Ibuprofène 400mg',1,1400,1400),
(2,'Vitamine C 1000mg',2,1800,3600),
(2,'Omeprazole 20mg',1,2100,1600),
(3,'Amoxicilline 500mg',4,2500,10000);

INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
(1,'connexion','Connexion admin','127.0.0.1'),
(2,'ajout_medicament','Ajout Paracétamol','127.0.0.1'),
(3,'vente','Vente FAC-20250101-0001','127.0.0.1');
