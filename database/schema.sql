CREATE TABLE advertisers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    landing_url VARCHAR(2048) NOT NULL,
    cpc DECIMAL(10,2) NOT NULL,
    region_code VARCHAR(8) DEFAULT NULL,
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
    name VARCHAR(255) NOT NULL,
    site_domain VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clicks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    keyword_id INT NOT NULL,
    publisher_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512) NOT NULL,
    region_code VARCHAR(8) NOT NULL,
    page_url VARCHAR(2048) NOT NULL,
    is_fraud TINYINT(1) NOT NULL DEFAULT 0,
    fraud_reason VARCHAR(128) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_clicks_campaign (campaign_id, created_at),
    INDEX idx_clicks_ip (ip, created_at),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (keyword_id) REFERENCES keywords(id),
    FOREIGN KEY (publisher_id) REFERENCES publishers(id)
);
