CREATE TABLE llx_lmdbwurthpunchout_unitmap (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	wurth_unit varchar(32) NOT NULL,
	fk_unit integer NULL,
	label varchar(255) NULL,
	date_creation datetime NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
