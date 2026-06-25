<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * WURTH France supplier helper.
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Helper used to create or reuse the WURTH France third party.
 */
class LmdbWurthPunchoutSupplier
{
	/**
	 * Return the reference data used to create the WURTH France third party.
	 *
	 * @param DoliDB $db Database handler
	 * @return array<string,mixed>
	 */
	public static function getWurthFranceData($db)
	{
		$countryId = self::getCountryId($db);

		return array(
			'name' => 'WURTH FRANCE',
			'name_alias' => 'Wurth France SAS',
			'address' => "Z.I. Ouest\nRue Georges Besse\nBP 40013",
			'zip' => '67158',
			'town' => 'Erstein Cedex',
			'country_id' => $countryId,
			'country_code' => 'FR',
			'state_id' => self::getDepartmentId($db),
			'phone' => '+33 3 88 64 53 00',
			'fax' => '+33 3 88 64 62 00',
			'email' => 'info.client@wurth.fr',
			'url' => 'https://www.wurth.fr/',
			'idprof1' => '668502966',
			'idprof2' => '66850296600041',
			'idprof3' => '4674A',
			'tva_intra' => 'FR57668502966',
			'capital' => '6000000',
			'forme_juridique_code' => self::getLegalFormCode($db, $countryId),
			'default_lang' => 'fr_FR',
			'code_fournisseur' => self::getDefaultSupplierCode(),
			'code_compta_fournisseur' => '401668502966',
		);
	}

	/**
	 * Create or complete the WURTH France third party in the current entity scope.
	 *
	 * @param DoliDB $db   Database handler
	 * @param User   $user User
	 * @return array<string,mixed>
	 */
	public static function createOrUpdateWurthFrance($db, $user)
	{
		global $conf;

		$result = array(
			'result' => -1,
			'id' => 0,
			'created' => 0,
			'updated' => 0,
			'error' => '',
			'errors' => array(),
		);

		$socid = self::findExistingWurthFrance($db);
		$societe = new Societe($db);

		if ($socid > 0) {
			$fetchResult = $societe->fetch($socid);
			if ($fetchResult <= 0) {
				$result['error'] = $societe->error ? $societe->error : 'LmdbWurthPunchoutWurthFranceSupplierFetchFailed';
				$result['errors'] = $societe->errors;
				return $result;
			}

			$before = self::getManagedSnapshot($societe);
			self::applyWurthFranceData($db, $societe, 0);
			$after = self::getManagedSnapshot($societe);

			if ($before !== $after) {
				$updateResult = $societe->update($societe->id, $user, 1, 1, 1);
				if ($updateResult < 0) {
					$result['error'] = $societe->error;
					$result['errors'] = $societe->errors;
					return $result;
				}
				$result['updated'] = 1;
			}

			$result['result'] = 1;
			$result['id'] = (int) $societe->id;
			return $result;
		}

		$societe->entity = (int) $conf->entity;
		self::applyWurthFranceData($db, $societe, 1);

		$createResult = $societe->create($user);
		if ($createResult <= 0) {
			$result['error'] = $societe->error;
			$result['errors'] = $societe->errors;
			return $result;
		}

		$result['result'] = 1;
		$result['id'] = (int) $createResult;
		$result['created'] = 1;

		return $result;
	}

	/**
	 * Find an existing WURTH France third party in the visible entity scope.
	 *
	 * @param DoliDB $db Database handler
	 * @return int
	 */
	private static function findExistingWurthFrance($db)
	{
		global $conf;

		$entitySql = self::getThirdpartyEntitySql();

		$normalizedTva = "UPPER(REPLACE(REPLACE(REPLACE(s.tva_intra, ' ', ''), '.', ''), '-', ''))";
		$normalizedSiret = "REPLACE(REPLACE(REPLACE(s.siret, ' ', ''), '.', ''), '-', '')";
		$normalizedSiren = "REPLACE(REPLACE(REPLACE(s.siren, ' ', ''), '.', ''), '-', '')";

		$sql = 'SELECT s.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe AS s';
		$sql .= ' WHERE s.entity IN ('.$entitySql.')';
		$sql .= ' AND (';
		$sql .= $normalizedTva." = 'FR57668502966'";
		$sql .= ' OR '.$normalizedSiret." = '66850296600041'";
		$sql .= ' OR '.$normalizedSiren." = '668502966'";
		$sql .= " OR UPPER(s.nom) IN ('WURTH FRANCE', 'WURTH FRANCE SAS')";
		$sql .= " OR UPPER(s.nom) LIKE 'W%RTH FRANCE%'";
		$sql .= ')';
		$sql .= ' ORDER BY CASE WHEN s.entity = '.((int) $conf->entity).' THEN 0 ELSE 1 END, CASE WHEN s.fournisseur = 1 THEN 0 ELSE 1 END, s.rowid ASC';
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			return (int) $obj->rowid;
		}

		return 0;
	}

	/**
	 * Apply WURTH France data on a Societe object.
	 *
	 * @param DoliDB  $db       Database handler
	 * @param Societe $societe  Third party
	 * @param int     $isNew    1 for creation, 0 for completion
	 * @return void
	 */
	private static function applyWurthFranceData($db, $societe, $isNew)
	{
		$data = self::getWurthFranceData($db);

		$societe->name = $data['name'];
		$societe->nom = $data['name'];
		$societe->name_alias = $data['name_alias'];
		$societe->address = $data['address'];
		$societe->zip = $data['zip'];
		$societe->town = $data['town'];
		$societe->country_id = (int) $data['country_id'];
		$societe->country_code = $data['country_code'];
		$societe->state_id = (int) $data['state_id'];
		$societe->phone = $data['phone'];
		$societe->fax = $data['fax'];
		$societe->email = $data['email'];
		$societe->url = $data['url'];
		$societe->idprof1 = $data['idprof1'];
		$societe->idprof2 = $data['idprof2'];
		$societe->idprof3 = $data['idprof3'];
		$societe->tva_intra = $data['tva_intra'];
		$societe->capital = $data['capital'];
		$societe->tva_assuj = 1;
		$societe->fournisseur = 1;
		$societe->status = 1;

		if (!empty($data['forme_juridique_code'])) {
			$societe->forme_juridique_code = $data['forme_juridique_code'];
		}
		if (empty($societe->default_lang)) {
			$societe->default_lang = $data['default_lang'];
		}
		if ($isNew) {
			$societe->client = 0;
		}
		if (empty($societe->code_fournisseur) || $societe->code_fournisseur === '-1') {
			$societe->code_fournisseur = $data['code_fournisseur'];
		}
		if (empty($societe->code_compta_fournisseur)) {
			$societe->code_compta_fournisseur = $data['code_compta_fournisseur'];
		}
	}

	/**
	 * Return a snapshot of fields managed by this helper.
	 *
	 * @param Societe $societe Third party
	 * @return array<string,string>
	 */
	private static function getManagedSnapshot($societe)
	{
		return array(
			'name' => (string) $societe->name,
			'name_alias' => (string) $societe->name_alias,
			'address' => (string) $societe->address,
			'zip' => (string) $societe->zip,
			'town' => (string) $societe->town,
			'country_id' => (string) $societe->country_id,
			'state_id' => (string) $societe->state_id,
			'phone' => self::normalizePhoneForCompare($societe->phone),
			'fax' => self::normalizePhoneForCompare($societe->fax),
			'email' => (string) $societe->email,
			'url' => (string) $societe->url,
			'idprof1' => (string) $societe->idprof1,
			'idprof2' => (string) $societe->idprof2,
			'idprof3' => (string) $societe->idprof3,
			'tva_intra' => (string) $societe->tva_intra,
			'capital' => self::normalizeNumberForCompare($societe->capital),
			'fournisseur' => (string) $societe->fournisseur,
			'status' => (string) $societe->status,
			'default_lang' => (string) $societe->default_lang,
			'code_fournisseur' => (string) $societe->code_fournisseur,
			'code_compta_fournisseur' => (string) $societe->code_compta_fournisseur,
		);
	}

	/**
	 * Normalize a phone or fax value like Societe::update().
	 *
	 * @param string $value Phone or fax value
	 * @return string
	 */
	private static function normalizePhoneForCompare($value)
	{
		$value = preg_replace('/\s/', '', (string) $value);
		return preg_replace('/\./', '', (string) $value);
	}

	/**
	 * Normalize numeric values before comparing a fetched object with reference data.
	 *
	 * @param mixed $value Numeric value
	 * @return string
	 */
	private static function normalizeNumberForCompare($value)
	{
		if ($value === '' || $value === null) {
			return '';
		}

		if (is_numeric($value)) {
			return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
		}

		return (string) $value;
	}

	/**
	 * Return the entity list allowed for third-party lookup.
	 *
	 * @return string
	 */
	private static function getThirdpartyEntitySql()
	{
		global $conf;

		$entities = array((int) $conf->entity);
		if (function_exists('getEntity')) {
			$entityString = (string) getEntity('societe');
			$parts = explode(',', $entityString);
			foreach ($parts as $part) {
				$entity = (int) trim($part);
				if ($entity > 0) {
					$entities[] = $entity;
				}
			}
		}

		$entities = array_values(array_unique($entities));
		return implode(',', $entities);
	}

	/**
	 * Return France country rowid.
	 *
	 * @param DoliDB $db Database handler
	 * @return int
	 */
	private static function getCountryId($db)
	{
		if (function_exists('dol_getIdFromCode')) {
			return (int) dol_getIdFromCode($db, 'FR', 'c_country', 'code', 'rowid');
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code = 'FR' LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			return (int) $obj->rowid;
		}

		return 0;
	}

	/**
	 * Return Bas-Rhin department rowid when available.
	 *
	 * @param DoliDB $db Database handler
	 * @return int
	 */
	private static function getDepartmentId($db)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_departements WHERE code_departement = '67' LIMIT 1";

		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			return (int) $obj->rowid;
		}

		return 0;
	}

	/**
	 * Return SAS legal form code when available.
	 *
	 * @param DoliDB $db        Database handler
	 * @param int    $countryId France country rowid
	 * @return string
	 */
	private static function getLegalFormCode($db, $countryId)
	{
		$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_forme_juridique';
		$sql .= ' WHERE active = 1';
		if ($countryId > 0) {
			$sql .= ' AND fk_pays = '.((int) $countryId);
		}
		$sql .= " AND (code = '5710' OR UPPER(libelle) LIKE '%SAS%' OR UPPER(libelle) LIKE '%SOCIETE PAR ACTIONS SIMPLIFIEE%')";
		$sql .= " ORDER BY CASE WHEN code = '5710' THEN 0 ELSE 1 END";
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			return (string) $obj->code;
		}

		return '';
	}

	/**
	 * Return the supplier code to use for a new third party.
	 *
	 * @return string
	 */
	private static function getDefaultSupplierCode()
	{
		if (function_exists('getDolGlobalString') && getDolGlobalString('SOCIETE_CODECLIENT_ADDON')) {
			return 'auto';
		}

		return 'WURTHFRANCE';
	}
}
