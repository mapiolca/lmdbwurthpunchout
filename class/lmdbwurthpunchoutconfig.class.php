<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Configuration helper for WURTH Punchout.
 */
class LmdbWurthPunchoutConfig
{
	/**
	 * Return selected protocol.
	 *
	 * @return string
	 */
	public static function getProtocol()
	{
		$protocol = strtoupper(getDolGlobalString('LMDBWURTHPUNCHOUT_PROTOCOL', 'OCI'));
		return in_array($protocol, array('OCI', 'CXML'), true) ? $protocol : 'OCI';
	}

	/**
	 * Return opening mode.
	 *
	 * @return string
	 */
	public static function getOpenMode()
	{
		$mode = strtolower(getDolGlobalString('LMDBWURTHPUNCHOUT_OPEN_MODE', 'popup'));
		return in_array($mode, array('iframe', 'popup', 'newtab'), true) ? $mode : 'popup';
	}

	/**
	 * Get encrypted secret value.
	 *
	 * @param string $name Constant name without prefix
	 * @return string
	 */
	public static function getSecret($name)
	{
		$value = getDolGlobalString('LMDBWURTHPUNCHOUT_'.$name, '');
		if ($value !== '' && function_exists('dolDecrypt')) {
			$decrypted = dolDecrypt($value);
			if ($decrypted !== false && $decrypted !== '') {
				return (string) $decrypted;
			}
		}

		return $value;
	}

	/**
	 * Save a regular constant in current entity.
	 *
	 * @param DoliDB $db    Database handler
	 * @param string $name  Constant name without prefix
	 * @param string $value Value
	 * @param string $type  Constant type
	 * @return int
	 */
	public static function set($db, $name, $value, $type = 'chaine')
	{
		global $conf;

		return dolibarr_set_const($db, 'LMDBWURTHPUNCHOUT_'.$name, $value, $type, 0, '', (int) $conf->entity);
	}

	/**
	 * Save a secret in current entity.
	 *
	 * @param DoliDB $db    Database handler
	 * @param string $name  Constant name without prefix
	 * @param string $value Plain value
	 * @return int
	 */
	public static function setSecret($db, $name, $value)
	{
		if ($value !== '' && function_exists('dolEncrypt')) {
			$value = dolEncrypt($value);
		}

		return self::set($db, $name, $value);
	}

	/**
	 * Build return URL for WURTH.
	 *
	 * @param string $protocol Protocol
	 * @param string $token    Public one-time token
	 * @param int    $entity   Entity
	 * @return string
	 */
	public static function getReturnUrl($protocol, $token, $entity)
	{
		$file = strtoupper($protocol) === 'CXML' ? 'return_cxml.php' : 'return_oci.php';
		return dol_buildpath('/lmdbwurthpunchout/public/'.$file, 2).'?entity='.(int) $entity.'&token='.urlencode($token);
	}

	/**
	 * Return expected currency.
	 *
	 * @return string
	 */
	public static function getExpectedCurrency()
	{
		$currency = strtoupper(getDolGlobalString('LMDBWURTHPUNCHOUT_CURRENCY', 'EUR'));
		return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
	}

	/**
	 * Get missing settings for a protocol.
	 *
	 * @param string $protocol Protocol
	 * @return array<int,string>
	 */
	public static function getMissingSettings($protocol = '')
	{
		$protocol = $protocol !== '' ? strtoupper($protocol) : self::getProtocol();
		$missing = array();

		if (getDolGlobalInt('LMDBWURTHPUNCHOUT_FK_SOC') <= 0) {
			$missing[] = 'LMDBWURTHPUNCHOUT_FK_SOC';
		}

		if ($protocol === 'OCI') {
			foreach (array('OCI_URL', 'OCI_ORGANIZATION', 'OCI_NAME', 'OCI_PASSWORD') as $key) {
				if ($key === 'OCI_PASSWORD') {
					if (self::getSecret($key) === '') {
						$missing[] = 'LMDBWURTHPUNCHOUT_'.$key;
					}
				} elseif (getDolGlobalString('LMDBWURTHPUNCHOUT_'.$key) === '') {
					$missing[] = 'LMDBWURTHPUNCHOUT_'.$key;
				}
			}
		} elseif ($protocol === 'CXML') {
			foreach (array('CXML_URL', 'CXML_SHARED_SECRET', 'CXML_CUSTOMER_DOMAIN', 'CXML_CUSTOMER_IDENTITY', 'CXML_SUPPLIER_DOMAIN', 'CXML_SUPPLIER_IDENTITY') as $key) {
				if ($key === 'CXML_SHARED_SECRET') {
					if (self::getSecret($key) === '') {
						$missing[] = 'LMDBWURTHPUNCHOUT_'.$key;
					}
				} elseif (getDolGlobalString('LMDBWURTHPUNCHOUT_'.$key) === '') {
					$missing[] = 'LMDBWURTHPUNCHOUT_'.$key;
				}
			}
		}

		return $missing;
	}

	/**
	 * Check if configuration is complete.
	 *
	 * @param string $protocol Protocol
	 * @return bool
	 */
	public static function isComplete($protocol = '')
	{
		return count(self::getMissingSettings($protocol)) === 0;
	}
}
