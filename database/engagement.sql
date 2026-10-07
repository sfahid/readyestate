-- Additive deal timeline, follow-up and document storage.
CREATE TABLE IF NOT EXISTS re_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 deal_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(30) NOT NULL, title VARCHAR(150) NOT NULL,
 detail TEXT NOT NULL, event_date DATE NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id), FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX(company_id,deal_id,event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 deal_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, detail VARCHAR(2000) NOT NULL DEFAULT '',
 assigned_to BIGINT UNSIGNED NOT NULL, due_at DATETIME NOT NULL, remind_at DATETIME NOT NULL,
 priority VARCHAR(10) NOT NULL DEFAULT 'normal', status VARCHAR(15) NOT NULL DEFAULT 'pending',
 created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL, UNIQUE(company_id,id),
 FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id), FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(assigned_to) REFERENCES users(id), CHECK(status IN ('pending','done','cancelled')),
 CHECK(priority IN ('normal','high')), INDEX(company_id,status,remind_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS re_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 deal_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, filename VARCHAR(180) NOT NULL,
 mime VARCHAR(80) NOT NULL, file_size INT UNSIGNED NOT NULL, sha256 CHAR(64) NOT NULL,
 content MEDIUMBLOB NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id,deal_id) REFERENCES re_deals(company_id,id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
