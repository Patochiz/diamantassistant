-- Copyright (C) 2026 DIAMANT INDUSTRIE

CREATE TABLE llx_diamantassistant_conversation (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user         INTEGER NOT NULL,
    date_creation   DATETIME NOT NULL,
    context_page    VARCHAR(255) DEFAULT NULL,
    title           VARCHAR(255) DEFAULT NULL
) ENGINE=innodb;
