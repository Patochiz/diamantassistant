-- Copyright (C) 2026 DIAMANT INDUSTRIE

ALTER TABLE llx_diamantassistant_conversation ADD INDEX idx_da_conv_user (fk_user);
ALTER TABLE llx_diamantassistant_conversation ADD INDEX idx_da_conv_date (date_creation);
