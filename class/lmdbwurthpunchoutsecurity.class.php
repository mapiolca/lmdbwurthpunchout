<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Security helper for WURTH Punchout.
 */
class LmdbWurthPunchoutSecurity
{
	/**
	 * Check a Dolibarr CSRF token.
	 *
	 * @return bool
	 */
	public static function checkToken()
	{
		$token = GETPOST('token', 'alphanohtml');
		if ($token === '') {
			return false;
		}

		if (function_exists('currentToken')) {
			return hash_equals((string) currentToken(), (string) $token);
		}

		if (!empty($_SESSION['newtoken'])) {
			return hash_equals((string) $_SESSION['newtoken'], (string) $token);
		}

		return false;
	}

	/**
	 * Generate random public token.
	 *
	 * @return string
	 */
	public static function generateToken()
	{
		return bin2hex(random_bytes(32));
	}

	/**
	 * Hash public token.
	 *
	 * @param string $token Public token
	 * @return string
	 */
	public static function hashToken($token)
	{
		return hash('sha256', $token);
	}

	/**
	 * Normalize WURTH supplier reference into a Dolibarr product ref suffix.
	 *
	 * @param string $reference Supplier reference
	 * @return string
	 */
	public static function normalizeSupplierReference($reference)
	{
		$reference = preg_replace('/[^A-Za-z0-9._-]+/', '', $reference);
		return strtoupper((string) $reference);
	}
}
