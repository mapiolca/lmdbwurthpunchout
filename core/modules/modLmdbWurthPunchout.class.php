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
		$this->rights_class = 'Les Métiers du Bâtiment';
		$this->family = 'interface';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'LmdbWurthPunchoutModuleDescription';
		$this->descriptionlong = 'LmdbWurthPunchoutModuleDescriptionLong';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'object_lmdbwurthpunchout.png@lmdbwurthpunchout';
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
		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Use WURTH Punchout';
		$this->rights[$r][4] = 'punchout';
		$this->rights[$r][5] = 'use';
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = 'Read WURTH Punchout sessions';
		$this->rights[$r][4] = 'session';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero + 3;
		$this->rights[$r][1] = 'Configure WURTH Punchout';
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = 'write';
		$r++;

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
		$this->initDefaultUnitMap();

		return $this->_init($sql, $options);
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
}
