-- Copyright (C) 2026 DIAMANT INDUSTRIE

ALTER TABLE llx_diamantassistant_message ADD INDEX idx_da_msg_conv (fk_conversation);
ALTER TABLE llx_diamantassistant_message ADD INDEX idx_da_msg_date (date_creation);
ALTER TABLE llx_diamantassistant_message ADD CONSTRAINT fk_da_msg_conv FOREIGN KEY (fk_conversation) REFERENCES llx_diamantassistant_conversation (rowid) ON DELETE CASCADE;
