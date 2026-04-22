-- ════════════════════════════════════════════════════════════════════════════
-- Farha App — Full Database Schema
-- Run this on your school server to create/reset all tables.
-- Safe to re-run: drops and recreates everything from scratch.
-- ════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ── Drop tables in reverse dependency order ───────────────────────────────────
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS wishlist;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS measurements;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS auth_attempts;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS tailors;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════════════════
-- CORE USER TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE users (
    id            VARCHAR(36)                         NOT NULL,
    email         VARCHAR(255)                        NOT NULL,
    phone         VARCHAR(20)                         NULL,
    password_hash VARCHAR(255)                        NOT NULL,
    user_type     ENUM('customer','tailor')           NOT NULL,
    first_name    VARCHAR(75)                         NOT NULL,
    last_name     VARCHAR(75)                         NOT NULL,
    language      ENUM('en','fr')    DEFAULT 'en'     NOT NULL,
    profile_photo VARCHAR(255)                        NULL,
    fcm_token     TEXT                                NULL,
    is_verified   TINYINT(1)         DEFAULT 0        NOT NULL,
    is_active     TINYINT(1)         DEFAULT 1        NOT NULL,
    last_login    DATETIME                            NULL,
    created_at    DATETIME           DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_phone (phone),
    INDEX idx_users_type (user_type),
    INDEX idx_users_active (is_active, is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE customers (
    id         VARCHAR(36) NOT NULL,
    user_id    VARCHAR(36) NOT NULL,
    gender     ENUM('male','female','other','prefer_not_to_say') NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE tailors (
    id                 VARCHAR(36)                                          NOT NULL,
    user_id            VARCHAR(36)                                          NOT NULL,
    shop_name          VARCHAR(200)                                         NOT NULL,
    gender             ENUM('male','female','other','prefer_not_to_say')    NULL,
    bio                TEXT                                                 NULL,
    shop_location      VARCHAR(500)                                         NULL,
    latitude           DECIMAL(10,8)                                        NULL,
    longitude          DECIMAL(11,8)                                        NULL,
    years_experience   INT                DEFAULT 0                         NOT NULL,
    experience_level   ENUM('apprentice','intermediate','master','grandmaster')
                                          DEFAULT 'apprentice'              NOT NULL,
    is_available       TINYINT(1)         DEFAULT 1                         NOT NULL,
    is_verified_tailor TINYINT(1)         DEFAULT 0                         NOT NULL,
    rating             DECIMAL(3,2)       DEFAULT 0.00                      NOT NULL,
    total_reviews      INT                DEFAULT 0                         NOT NULL,
    total_orders       INT                DEFAULT 0                         NOT NULL,
    created_at         DATETIME           DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tailors_user (user_id),
    INDEX idx_tailors_rating (rating),
    INDEX idx_tailors_available (is_available),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- AUTH / SESSION TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE email_verifications (
    id         VARCHAR(36) NOT NULL,
    user_id    VARCHAR(36) NOT NULL,
    token      VARCHAR(64) NOT NULL,
    expires_at DATETIME    NOT NULL,
    used_at    DATETIME    NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ev_token (token),
    INDEX idx_ev_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE password_resets (
    id          INT          AUTO_INCREMENT NOT NULL,
    user_id     VARCHAR(36)                NOT NULL,
    otp_code    VARCHAR(6)                 NOT NULL,
    reset_token VARCHAR(64)                NULL,
    attempts    INT          DEFAULT 0     NOT NULL,
    expires_at  DATETIME                   NOT NULL,
    used_at     DATETIME                   NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pr_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE refresh_tokens (
    id         INT          AUTO_INCREMENT NOT NULL,
    user_id    VARCHAR(36)                NOT NULL,
    token_hash VARCHAR(64)                NOT NULL,
    revoked    TINYINT(1)   DEFAULT 0     NOT NULL,
    expires_at DATETIME                   NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rt_hash (token_hash),
    INDEX idx_rt_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE auth_attempts (
    id           INT         AUTO_INCREMENT NOT NULL,
    ip_address   VARCHAR(45)                NOT NULL,
    endpoint     VARCHAR(100)               NOT NULL,
    attempted_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_aa_ip_ep (ip_address, endpoint, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- CATALOGUE TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE categories (
    id         VARCHAR(36)  NOT NULL,
    name_en    VARCHAR(100) NOT NULL,
    name_fr    VARCHAR(100) NOT NULL,
    icon       VARCHAR(100) NULL,
    sort_order INT          DEFAULT 0 NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default categories (fixed UUIDs so seed data can reference them)
INSERT INTO categories (id, name_en, name_fr, icon, sort_order) VALUES
    ('cat00000-0000-0000-0000-000000000001', 'Boubou',          'Boubou',            'boubou',      1),
    ('cat00000-0000-0000-0000-000000000002', 'Kaftan',           'Kaftan',            'kaftan',      2),
    ('cat00000-0000-0000-0000-000000000003', 'Agbada',           'Agbada',            'agbada',      3),
    ('cat00000-0000-0000-0000-000000000004', 'Dress',            'Robe',              'dress',       4),
    ('cat00000-0000-0000-0000-000000000005', 'Suit',             'Costume',           'suit',        5),
    ('cat00000-0000-0000-0000-000000000006', 'Children\'s Wear', 'Vêtements enfants', 'children',    6),
    ('cat00000-0000-0000-0000-000000000007', 'Wedding Attire',   'Tenue de mariage',  'wedding',     7),
    ('cat00000-0000-0000-0000-000000000008', 'Accessories',      'Accessoires',       'accessories', 8);


CREATE TABLE products (
    id             VARCHAR(36)    NOT NULL,
    tailor_id      VARCHAR(36)    NOT NULL,
    category_id    VARCHAR(36)    NOT NULL,
    name           VARCHAR(255)   NOT NULL,
    description    TEXT           NULL,
    base_price     DECIMAL(10,2)  NOT NULL,
    currency       VARCHAR(10)    DEFAULT 'CFA'  NOT NULL,
    stock_quantity INT            DEFAULT 0      NOT NULL,
    allows_custom  TINYINT(1)     DEFAULT 0      NOT NULL,
    is_available   TINYINT(1)     DEFAULT 1      NOT NULL,
    is_draft       TINYINT(1)     DEFAULT 0      NOT NULL,
    available_sizes JSON          NULL,
    rating         DECIMAL(3,2)   DEFAULT 0.00   NOT NULL,
    total_reviews  INT            DEFAULT 0      NOT NULL,
    total_sales    INT            DEFAULT 0      NOT NULL,
    created_at     DATETIME       DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_products_tailor (tailor_id),
    INDEX idx_products_category (category_id),
    INDEX idx_products_available (is_available, is_draft),
    INDEX idx_products_rating (rating),
    FOREIGN KEY (tailor_id)   REFERENCES tailors(id)     ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE product_images (
    id         VARCHAR(36)  NOT NULL,
    product_id VARCHAR(36)  NOT NULL,
    image_url  VARCHAR(500) NOT NULL,
    is_main    TINYINT(1)   DEFAULT 0  NOT NULL,
    sort_order INT          DEFAULT 0  NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pi_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- ORDER & PAYMENT TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE measurements (
    id              VARCHAR(36)  NOT NULL,
    customer_id     VARCHAR(36)  NOT NULL,
    profile_name    VARCHAR(100) DEFAULT 'My Measurements' NOT NULL,
    garment_type    VARCHAR(50)  DEFAULT 'general'         NOT NULL,
    chest           DECIMAL(5,2) NULL,
    waist           DECIMAL(5,2) NULL,
    hips            DECIMAL(5,2) NULL,
    shoulder_width  DECIMAL(5,2) NULL,
    sleeve_length   DECIMAL(5,2) NULL,
    total_length    DECIMAL(5,2) NULL,
    neck            DECIMAL(5,2) NULL,
    armhole         DECIMAL(5,2) NULL,
    unit            ENUM('cm','inches') DEFAULT 'cm'       NOT NULL,
    notes           TEXT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_m_customer (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE orders (
    id                   VARCHAR(36)    NOT NULL,
    reference_number     VARCHAR(20)    NOT NULL,
    customer_id          VARCHAR(36)    NOT NULL,
    tailor_id            VARCHAR(36)    NOT NULL,
    product_id           VARCHAR(36)    NULL,
    order_type           ENUM('ready_made','custom') NOT NULL,
    status               ENUM('pending','confirmed','cutting','sewing','ready','delivered','cancelled')
                                        DEFAULT 'pending' NOT NULL,
    size                 VARCHAR(20)    NULL,
    quantity             INT            DEFAULT 1 NOT NULL,
    total_amount         DECIMAL(10,2)  NOT NULL,
    deposit_amount       DECIMAL(10,2)  DEFAULT 0.00 NOT NULL,
    paid_amount          DECIMAL(10,2)  DEFAULT 0.00 NOT NULL,
    currency             VARCHAR(10)    DEFAULT 'CFA' NOT NULL,
    special_instructions TEXT           NULL,
    design_reference_url VARCHAR(500)   NULL,
    cancel_reason        TEXT           NULL,
    estimated_completion DATE           NULL,
    created_at           DATETIME       DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_ref (reference_number),
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_tailor   (tailor_id),
    INDEX idx_orders_status   (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (tailor_id)   REFERENCES tailors(id),
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE payments (
    id             VARCHAR(36)   NOT NULL,
    order_id       VARCHAR(36)   NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    currency       VARCHAR(10)   DEFAULT 'CFA' NOT NULL,
    payment_method VARCHAR(50)   NULL,
    transaction_id VARCHAR(100)  NULL,
    status         ENUM('pending','completed','failed','refunded') DEFAULT 'pending' NOT NULL,
    created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_payments_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- SHOPPING TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE cart_items (
    id          VARCHAR(36) NOT NULL,
    customer_id VARCHAR(36) NOT NULL,
    product_id  VARCHAR(36) NOT NULL,
    quantity    INT         DEFAULT 1 NOT NULL,
    size        VARCHAR(20) NULL,
    added_at    DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart (customer_id, product_id, size),
    INDEX idx_cart_customer (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE wishlist (
    id          VARCHAR(36) NOT NULL,
    customer_id VARCHAR(36) NOT NULL,
    product_id  VARCHAR(36) NOT NULL,
    added_at    DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wishlist (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- MESSAGING & NOTIFICATIONS
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE conversations (
    id              VARCHAR(36) NOT NULL,
    customer_id     VARCHAR(36) NOT NULL,
    tailor_id       VARCHAR(36) NOT NULL,
    order_id        VARCHAR(36) NULL,
    last_message_at DATETIME    NULL,
    created_at      DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conv (customer_id, tailor_id),
    INDEX idx_conv_customer (customer_id),
    INDEX idx_conv_tailor   (tailor_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tailor_id)   REFERENCES tailors(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE messages (
    id              VARCHAR(36) NOT NULL,
    conversation_id VARCHAR(36) NOT NULL,
    sender_id       VARCHAR(36) NOT NULL,
    message_text    TEXT        NOT NULL,
    is_read         TINYINT(1)  DEFAULT 0 NOT NULL,
    created_at      DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_msg_conv   (conversation_id),
    INDEX idx_msg_sender (sender_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE notifications (
    id           VARCHAR(36)  NOT NULL,
    user_id      VARCHAR(36)  NOT NULL,
    title        VARCHAR(255) NOT NULL,
    body         TEXT         NOT NULL,
    type         VARCHAR(50)  NULL,
    reference_id VARCHAR(36)  NULL,
    is_read      TINYINT(1)   DEFAULT 0 NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_notif_user (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- REVIEWS
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE reviews (
    id          VARCHAR(36) NOT NULL,
    order_id    VARCHAR(36) NOT NULL,
    customer_id VARCHAR(36) NOT NULL,
    tailor_id   VARCHAR(36) NOT NULL,
    product_id  VARCHAR(36) NULL,
    rating      TINYINT     NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT        NULL,
    created_at  DATETIME    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review (order_id, customer_id),
    INDEX idx_review_tailor  (tailor_id),
    INDEX idx_review_product (product_id),
    FOREIGN KEY (order_id)    REFERENCES orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (tailor_id)   REFERENCES tailors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- Done! All tables created successfully.
-- Connect using: host=169.239.251.102 db=mobileapps_2026B_mukaila_shittu
-- ════════════════════════════════════════════════════════════════════════════
