ALTER TABLE llx_dolimcp_token ADD UNIQUE INDEX uk_dolimcp_token_user_entity (fk_user, entity);
ALTER TABLE llx_dolimcp_token ADD INDEX idx_dolimcp_token_entity (entity);
