CREATE TABLE llx_lmdbwurthpunchout_repmap (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	vendor_ref varchar(128) NOT NULL,
	amount_ht double(24,8) DEFAULT 0 NOT NULL,
	label varchar(255) NULL,
	date_creation datetime NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
