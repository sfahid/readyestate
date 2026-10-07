-- Independent parties and company-owned property. No existing table is dropped.
CREATE TABLE IF NOT EXISTS re_parties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL, phone VARCHAR(40) NOT NULL DEFAULT '',
 address VARCHAR(500) NOT NULL DEFAULT '', notes VARCHAR(2000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_party_access (
 company_id BIGINT UNSIGNED NOT NULL, party_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(company_id,party_id), FOREIGN KEY(company_id) REFERENCES companies(id),
 FOREIGN KEY(party_id) REFERENCES re_parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_company_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL, property_id BIGINT UNSIGNED NOT NULL,
 acquired_date DATE NOT NULL, cost BIGINT NOT NULL, journal_id BIGINT UNSIGNED NOT NULL,
 cash_account_id BIGINT UNSIGNED NOT NULL, seller_party_id BIGINT UNSIGNED NULL,
 status VARCHAR(15) NOT NULL DEFAULT 'owned', sold_date DATE NULL,
 sale_proceeds BIGINT NULL, sale_journal_id BIGINT UNSIGNED NULL, buyer_party_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,id), UNIQUE(company_id,journal_id), UNIQUE(company_id,sale_journal_id),
 FOREIGN KEY(company_id,property_id) REFERENCES re_properties(company_id,id),
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id),
 FOREIGN KEY(company_id,cash_account_id) REFERENCES accounts(company_id,id),
 FOREIGN KEY(company_id,sale_journal_id) REFERENCES journals(company_id,id),
 FOREIGN KEY(seller_party_id) REFERENCES re_parties(id), FOREIGN KEY(buyer_party_id) REFERENCES re_parties(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 CHECK(cost>0 AND status IN ('owned','sold','void')), CHECK(sale_proceeds IS NULL OR sale_proceeds>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_asset_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(20) NOT NULL,
 journal_id BIGINT UNSIGNED NOT NULL, entry_date DATE NOT NULL,
 UNIQUE(company_id,journal_id), FOREIGN KEY(company_id,asset_id) REFERENCES re_company_assets(company_id,id),
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id),
 CHECK(kind IN ('purchase','sale','reversal'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_direct_settlements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL, deal_id BIGINT UNSIGNED NOT NULL,
 amount BIGINT NOT NULL, settled_date DATE NOT NULL, reference VARCHAR(150) NOT NULL,
 note VARCHAR(500) NOT NULL DEFAULT '', created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 reversed_at DATETIME NULL, reversal_reason VARCHAR(150) NULL,
 FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 CHECK(amount>0), INDEX(company_id,deal_id,settled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Four party columns are added by the checked installer using
-- information_schema so this file remains safely repeatable on test databases.
