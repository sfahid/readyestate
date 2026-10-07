CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS companies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'PKR',
 address TEXT, owner_id BIGINT UNSIGNED NOT NULL, next_journal INT NOT NULL DEFAULT 1, next_invoice INT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS memberships (
 company_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, role VARCHAR(20) NOT NULL,
 PRIMARY KEY(company_id,user_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id),
 CHECK(role IN ('Owner','Accountant','Viewer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(12) NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL, system_key VARCHAR(30),
 UNIQUE(company_id,code), UNIQUE(company_id,system_key), UNIQUE(company_id,id), FOREIGN KEY(company_id) REFERENCES companies(id),
 CHECK(type IN ('Asset','Cash','Bank','Liability','Equity','Revenue','Expense'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS journals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, number VARCHAR(30) NOT NULL,
 entry_date DATE NOT NULL, memo VARCHAR(250) NOT NULL, source VARCHAR(25) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, reversal_of BIGINT UNSIGNED NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,number), UNIQUE(company_id,id), INDEX(company_id,entry_date),
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(reversal_of) REFERENCES journals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS journal_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, journal_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL, debit BIGINT NOT NULL DEFAULT 0, credit BIGINT NOT NULL DEFAULT 0,
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id),
 FOREIGN KEY(company_id,account_id) REFERENCES accounts(company_id,id), INDEX(company_id,account_id),
 CHECK((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, number VARCHAR(30) NOT NULL,
 kind VARCHAR(15) NOT NULL, party VARCHAR(150) NOT NULL, invoice_date DATE NOT NULL, due_date DATE NOT NULL,
 notes TEXT, total BIGINT NOT NULL, paid BIGINT NOT NULL DEFAULT 0, journal_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(15) NOT NULL DEFAULT 'posted', created_by BIGINT UNSIGNED NOT NULL,
 UNIQUE(company_id,number), UNIQUE(company_id,id), INDEX(company_id,kind,due_date),
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id), FOREIGN KEY(created_by) REFERENCES users(id),
 CHECK(total > 0 AND paid >= 0 AND paid <= total), CHECK(kind IN ('sale','purchase'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_id BIGINT UNSIGNED NOT NULL, description VARCHAR(250) NOT NULL,
 quantity_units BIGINT NOT NULL, unit_price BIGINT NOT NULL, total BIGINT NOT NULL,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, invoice_id BIGINT UNSIGNED NOT NULL,
 journal_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, payment_date DATE NOT NULL, amount BIGINT NOT NULL,
 FOREIGN KEY(company_id,invoice_id) REFERENCES invoices(company_id,id),
 FOREIGN KEY(company_id,journal_id) REFERENCES journals(company_id,id), FOREIGN KEY(company_id,account_id) REFERENCES accounts(company_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(60) NOT NULL, detail VARCHAR(250) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(company_id,id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS requests (
 company_id BIGINT UNSIGNED NOT NULL, request_key VARCHAR(64) NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 response TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(company_id,request_key),
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(64) NOT NULL, email_hash CHAR(64) NOT NULL,
 attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(ip_hash,attempted_at), INDEX(email_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
