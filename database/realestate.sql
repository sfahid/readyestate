-- Additive real-estate module. Existing accounting tables are unchanged.
CREATE TABLE IF NOT EXISTS re_properties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(40) NOT NULL, title VARCHAR(150) NOT NULL,
 property_type VARCHAR(20) NOT NULL, location VARCHAR(250) NOT NULL,
 area VARCHAR(80) NOT NULL DEFAULT '', owner_name VARCHAR(150) NOT NULL,
 owner_phone VARCHAR(40) NOT NULL DEFAULT '', asking_price BIGINT NOT NULL DEFAULT 0,
 notes VARCHAR(2000) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'available',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,reference), UNIQUE(company_id,id), FOREIGN KEY(company_id) REFERENCES companies(id),
 CHECK(asking_price >= 0), CHECK(status IN ('available','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_deals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 property_id BIGINT UNSIGNED NOT NULL, reference VARCHAR(40) NOT NULL,
 kind VARCHAR(20) NOT NULL, buyer_name VARCHAR(150) NOT NULL, buyer_phone VARCHAR(40) NOT NULL DEFAULT '',
 seller_name VARCHAR(150) NOT NULL, seller_phone VARCHAR(40) NOT NULL DEFAULT '',
 deal_date DATE NOT NULL, due_date DATE NOT NULL, deal_value BIGINT NOT NULL,
 commission BIGINT NOT NULL DEFAULT 0, commission_party VARCHAR(150) NOT NULL,
 notes VARCHAR(2000) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,reference), UNIQUE(company_id,id), FOREIGN KEY(company_id,property_id) REFERENCES re_properties(company_id,id),
 FOREIGN KEY(created_by) REFERENCES users(id), CHECK(kind IN ('sale','rental')),
 CHECK(deal_value>0 AND commission>=0), CHECK(status IN ('active','completed','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 deal_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(20) NOT NULL, amount BIGINT NOT NULL,
 entry_date DATE NOT NULL, note VARCHAR(150) NOT NULL, journal_id BIGINT UNSIGNED NOT NULL,
 UNIQUE(company_id,journal_id), FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id),
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id),
 CHECK(kind IN ('token','biana','receipt','seller_payment','refund')), CHECK(amount>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_deal_invoices (
 company_id BIGINT UNSIGNED NOT NULL, deal_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(company_id,invoice_id),
 FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id),
 FOREIGN KEY(company_id,invoice_id) REFERENCES invoices(company_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
