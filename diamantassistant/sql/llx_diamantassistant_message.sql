-- Copyright (C) 2026 DIAMANT INDUSTRIE

CREATE TABLE llx_diamantassistant_message (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_conversation   INTEGER NOT NULL,
    role              VARCHAR(20) NOT NULL,
    content           MEDIUMTEXT NOT NULL,
    tokens_used       INTEGER DEFAULT 0,
    provider_used     VARCHAR(100) DEFAULT NULL,
    date_creation     DATETIME NOT NULL
) ENGINE=innodb;
