-- Copyright (C) 2026 DIAMANT INDUSTRIE

CREATE TABLE IF NOT EXISTS llx_diamantassistant_tool (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100) NOT NULL,
    label             VARCHAR(255) NOT NULL,
    description       TEXT NOT NULL,
    sql_query         TEXT NOT NULL,
    parameters        TEXT NOT NULL DEFAULT '[]',
    active            TINYINT DEFAULT 1 NOT NULL,
    date_creation     DATETIME NOT NULL,
    date_modification DATETIME DEFAULT NULL,
    fk_user_creat     INTEGER DEFAULT NULL
) ENGINE=innodb;
