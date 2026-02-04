CREATE TABLE advertisers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME DEFAULT NULL
);

CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    landing_url VARCHAR(2048) NOT NULL,
    ad_type VARCHAR(16) NOT NULL DEFAULT 'context',
    teaser_text VARCHAR(255) DEFAULT NULL,
    image_url VARCHAR(2048) DEFAULT NULL,
    cpc DECIMAL(10,2) NOT NULL,
    region_code VARCHAR(8) DEFAULT NULL,
    daily_limit DECIMAL(12,2) DEFAULT NULL,
    total_limit DECIMAL(12,2) DEFAULT NULL,
    spent_today DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    spent_today_date DATE DEFAULT NULL,
    spent_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    moderation_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id)
);

CREATE TABLE keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    keyword VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    INDEX idx_keyword (keyword)
);

CREATE TABLE publishers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    site_domain VARCHAR(255) NOT NULL,
    site_url VARCHAR(2048) NOT NULL DEFAULT '',
    category VARCHAR(128) NOT NULL DEFAULT '',
    region_code VARCHAR(8) DEFAULT NULL,
    moderation_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    verification_status VARCHAR(32) NOT NULL DEFAULT 'unverified',
    verified_at DATETIME DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE campaign_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    publisher_id INT NOT NULL,
    list_type VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (publisher_id) REFERENCES publishers(id),
    INDEX idx_campaign_list (campaign_id, list_type)
);

CREATE TABLE impressions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    keyword_id INT NOT NULL,
    publisher_id INT NOT NULL,
    region_code VARCHAR(8) NOT NULL,
    page_url VARCHAR(2048) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_impressions_campaign (campaign_id, created_at),
    INDEX idx_impressions_publisher (publisher_id, created_at),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (keyword_id) REFERENCES keywords(id),
    FOREIGN KEY (publisher_id) REFERENCES publishers(id)
);

CREATE TABLE clicks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    keyword_id INT NOT NULL,
    publisher_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512) NOT NULL,
    referrer VARCHAR(2048) NOT NULL DEFAULT '',
    fingerprint VARCHAR(128) NOT NULL DEFAULT '',
    time_on_page_ms INT NOT NULL DEFAULT 0,
    region_code VARCHAR(8) NOT NULL,
    page_url VARCHAR(2048) NOT NULL,
    is_fraud TINYINT(1) NOT NULL DEFAULT 0,
    fraud_reason VARCHAR(128) NOT NULL DEFAULT '',
    counted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_clicks_campaign (campaign_id, created_at),
    INDEX idx_clicks_ip (ip, created_at),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (keyword_id) REFERENCES keywords(id),
    FOREIGN KEY (publisher_id) REFERENCES publishers(id)
);

CREATE TABLE transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    advertiser_id INT DEFAULT NULL,
    publisher_id INT DEFAULT NULL,
    campaign_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type VARCHAR(32) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transactions_advertiser (advertiser_id, created_at),
    INDEX idx_transactions_publisher (publisher_id, created_at),
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id),
    FOREIGN KEY (publisher_id) REFERENCES publishers(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
);

CREATE TABLE payout_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    publisher_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (publisher_id) REFERENCES publishers(id),
    INDEX idx_payout_requests (status, created_at)
);

CREATE TABLE settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL
);
