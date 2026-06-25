ALTER TABLE llx_wurthpunchout_session ADD UNIQUE INDEX uk_wurthpunchout_session_token (token_hash);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_entity (entity);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_order (fk_commandefourn);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_soc (fk_soc);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_user (fk_user);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_status (entity, status);
ALTER TABLE llx_wurthpunchout_session ADD INDEX idx_wurthpunchout_session_validity (entity, date_validity);
