-- DoliMCP per-user token (separate from official REST API keys)
CREATE TABLE llx_dolimcp_token (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_user integer NOT NULL,
	token varchar(255) NOT NULL,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
