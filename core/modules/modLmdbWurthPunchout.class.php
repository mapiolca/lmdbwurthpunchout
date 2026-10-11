<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        core/modules/modLmdbWurthPunchout.class.php
 * \ingroup     lmdbwurthpunchout
 * \brief       Descriptor for WURTH Punchout module.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor.
 */
class modLmdbWurthPunchout extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->numero = 450015;
		$this->rights_class = 'lmdbwurthpunchout';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'LmdbWurthPunchoutModuleDescription';
		$this->descriptionlong = 'LmdbWurthPunchoutModuleDescriptionLong';
		$this->version = '1.0.2';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'lmdbwurthpunchout.png@lmdbwurthpunchout';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';

		$this->module_parts = array(
			'hooks' => array(
				'ordersuppliercard',
				'globalcard',
			),
		);

		$this->dirs = array('/lmdbwurthpunchout/temp');

		$this->config_page_url = array(
			'setup.php@lmdbwurthpunchout',
		);

		$this->hidden = false;
		$this->depends = array('modFournisseur', 'modProduct');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('lmdbwurthpunchout@lmdbwurthpunchout');

		$this->const = array(
			1 => array('LMDBWURTHPUNCHOUT_PROTOCOL', 'chaine', 'OCI', 'Default punchout protocol', 0, 'current', 1),
			2 => array('LMDBWURTHPUNCHOUT_OPEN_MODE', 'chaine', 'popup', 'Default opening mode', 0, 'current', 1),
			3 => array('LMDBWURTHPUNCHOUT_CURRENCY', 'chaine', 'EUR', 'Expected currency', 0, 'current', 1),
			4 => array('LMDBWURTHPUNCHOUT_DEFAULT_VAT', 'chaine', '20', 'Default VAT rate', 0, 'current', 1),
			5 => array('LMDBWURTHPUNCHOUT_CREATE_PRODUCTS', 'chaine', '1', 'Create missing products', 0, 'current', 1),
			6 => array('LMDBWURTHPUNCHOUT_ALLOW_ZERO_PRICE', 'chaine', '0', 'Allow zero prices', 0, 'current', 1),
			7 => array('LMDBWURTHPUNCHOUT_PRODUCT_REF_PREFIX', 'chaine', 'WURTH-', 'Product reference prefix', 0, 'current', 1),
			8 => array('LMDBWURTHPUNCHOUT_PRICEUNIT_MODE', 'chaine', 'divide', 'How OCI PRICEUNIT is used', 0, 'current', 1),
			9 => array('LMDBWURTHPUNCHOUT_TOKEN_TTL', 'chaine', '30', 'Punchout token duration in minutes', 0, 'current', 1),
			10 => array('LMDBWURTHPUNCHOUT_RETENTION_DAYS', 'chaine', '30', 'Session retention duration in days', 0, 'current', 1),
			11 => array('LMDBWURTHPUNCHOUT_OCI_METHOD', 'chaine', 'GET', 'OCI call method', 0, 'current', 1),
			12 => array('LMDBWURTHPUNCHOUT_CXML_IMPORT_SHIPPING', 'chaine', '1', 'Import cXML shipping fees', 0, 'current', 1),
			13 => array('LMDBWURTHPUNCHOUT_CXML_SHIPPING_FK_PRODUCT', 'chaine', '0', 'Optional product/service for cXML shipping fees', 0, 'current', 1),
			14 => array('LMDBWURTHPUNCHOUT_CXML_SHIPPING_VAT_RATE', 'chaine', '', 'Optional VAT rate for cXML shipping fees', 0, 'current', 1),
			15 => array('LMDBWURTHPUNCHOUT_CXML_INFER_SHIPPING_FROM_TAX_DELTA', 'chaine', '1', 'Infer missing cXML ancillary fees from header tax delta', 0, 'current', 1),
			16 => array('LMDBWURTHPUNCHOUT_CXML_IMPORT_REP', 'chaine', '1', 'Import cXML REP fees', 0, 'current', 1),
			18 => array('LMDBWURTHPUNCHOUT_CXML_REP_FK_PRODUCT', 'chaine', '0', 'Optional product/service for cXML REP fees', 0, 'current', 1),
			19 => array('LMDBWURTHPUNCHOUT_CXML_REP_VAT_RATE', 'chaine', '', 'Optional VAT rate for cXML REP fees', 0, 'current', 1),
		);

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array(
			0 => array(
				'label' => 'LmdbWurthPunchoutCronCleanupLabel',
				'jobtype' => 'method',
				'class' => '/lmdbwurthpunchout/class/lmdbwurthpunchoutcron.class.php',
				'objectname' => 'LmdbWurthPunchoutCron',
				'method' => 'runCleanup',
				'parameters' => '',
				'comment' => 'LmdbWurthPunchoutCronCleanupComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("lmdbwurthpunchout")',
				'priority' => 50,
			),
		);

		$r = 0;
		// Existing offsets 1 and 2 remain stable; legacy offset 0 is reserved.
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbWurthPunchoutRightReadSessions';
		$this->rights[$r][4] = 'session';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbWurthPunchoutRightConfigure';
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'LmdbWurthPunchoutRightUse';
		$this->rights[$r][4] = 'punchout';
		$this->rights[$r][5] = 'use';

		$this->menu = array();
	}

	/**
	 * Initialize module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/lmdbwurthpunchout/sql/');
		if ($result < 0) {
			return -1;
		}
		if ($this->upgradeSchema() < 0) {
			return -1;
		}
		if ($this->migratePunchoutPermission() < 0) {
			return -1;
		}
		$this->initDefaultUnitMap();

		return $this->_init($sql, $options);
	}

	/**
	 * Move legacy use permission to offset 3 while preserving user/group grants.
	 * Offsets 1 and 2 are unchanged. The retired offset 0 is never reused.
	 *
	 * @return int
	 */
	protected function migratePunchoutPermission()
	{
		$oldId = $this->numero * 100;
		$newId = $oldId + 3;
		$sql = 'SELECT id, module, perms, subperms FROM '.MAIN_DB_PREFIX.'rights_def';
		$sql .= ' WHERE id IN ('.$oldId.', '.$newId.')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while (is_object($right = $this->db->fetch_object($resql))) {
			if ($right->module !== $this->rights_class || $right->perms !== 'punchout' || $right->subperms !== 'use') {
				$this->error = 'Punchout permission identifier collision';
				return -1;
			}
		}
		if (!$this->db->begin()) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		foreach (array('user_rights' => 'fk_user', 'usergroup_rights' => 'fk_usergroup') as $table => $owner) {
			$sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.$table.' (entity, '.$owner.', fk_id)';
			$sql .= ' SELECT entity, '.$owner.', '.$newId.' FROM '.MAIN_DB_PREFIX.$table.' WHERE fk_id = '.$oldId;
			if (!$this->db->query($sql) || !$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE fk_id = '.$oldId)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}
		if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'rights_def WHERE id = '.$oldId." AND module = '".$this->db->escape($this->rights_class)."'")) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		if (!$this->db->commit()) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		return 1;
	}

	/**
	 * Remove module.
	 *
	 * Configuration constants are intentionally preserved.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$sql = array();
		$declaredConstants = $this->const;
		$this->const = array();
		$result = $this->_remove($sql, $options);
		$this->const = $declaredConstants;

		return $result;
	}

	/**
	 * Insert default WURTH unit mappings for the current entity.
	 *
	 * @return void
	 */
	private function initDefaultUnitMap()
	{
		global $conf;

		$units = array(
			'PCE' => 'Pièce',
			'EA' => 'Pièce',
			'BOX' => 'Boîte',
			'M' => 'Mètre',
			'L' => 'Litre',
		);

		foreach ($units as $code => $label) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)';
			$sql .= ' SELECT '.((int) $conf->entity).", '".$this->db->escape($code)."', NULL, '".$this->db->escape($label)."', '".$this->db->idate(dol_now())."'";
			$sql .= ' WHERE NOT EXISTS (';
			$sql .= 'SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap';
			$sql .= ' WHERE entity = '.((int) $conf->entity)." AND wurth_unit = '".$this->db->escape($code)."'";
			$sql .= ')';
			$this->db->query($sql);
		}
	}

	/**
	 * Upgrade existing module tables with columns added after initial release.
	 *
	 * @return int
	 */
	private function upgradeSchema()
	{
		$columnsByTable = array(
			'lmdbwurthpunchout_session' => array(
				'basket_payload' => 'mediumtext NULL',
			),
			'lmdbwurthpunchout_session_line' => array(
				'source_line_number' => 'varchar(64) NULL',
				'supplier_part_auxiliary_id' => 'varchar(255) NULL',
				'classification_domain' => 'varchar(64) NULL',
				'classification' => 'varchar(255) NULL',
				'tax_amount' => 'double(24,8) DEFAULT 0 NOT NULL',
				'tax_currency' => 'varchar(3) NULL',
			),
			'lmdbwurthpunchout_repmap' => array(
				'status' => "varchar(16) DEFAULT 'active' NOT NULL",
			),
		);

		foreach ($columnsByTable as $table => $columns) {
			foreach ($columns as $column => $definition) {
				if ($this->addColumnIfMissing($table, $column, $definition) < 0) {
					return -1;
				}
			}
		}

		return 1;
	}

	/**
	 * Add a column if it does not already exist.
	 *
	 * @param string $table      Table name without prefix
	 * @param string $column     Column name
	 * @param string $definition SQL column definition
	 * @return int
	 */
	private function addColumnIfMissing($table, $column, $definition)
	{
		$tableName = MAIN_DB_PREFIX.$table;
		$sql = 'SHOW COLUMNS FROM '.$tableName." LIKE '".$this->db->escape($column)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			return 1;
		}

		$sql = 'ALTER TABLE '.$tableName.' ADD COLUMN '.$column.' '.$definition;
		return $this->db->query($sql) ? 1 : -1;
	}
}
