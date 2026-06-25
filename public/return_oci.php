<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Public OCI return endpoint.
 *
 * This endpoint accepts a third-party browser postback. It verifies the
 * Punchout one-time token, stores the payload, and leaves the actual import
 * to the authenticated import page.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}

$preloadEntity = filter_input(INPUT_GET, 'entity', FILTER_VALIDATE_INT);
if (!$preloadEntity) {
	$preloadEntity = filter_input(INPUT_POST, 'entity', FILTER_VALIDATE_INT);
}
if ($preloadEntity > 0 && !defined('DOLENTITY')) {
	define('DOLENTITY', (int) $preloadEntity);
}

$res = 0;
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once __DIR__.'/../class/lmdbwurthpunchoutsession.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutparser.class.php';

$langs->loadLangs(array('lmdbwurthpunchout@lmdbwurthpunchout', 'errors'));

$token = GETPOST('token', 'alphanohtml');
$entity = GETPOSTINT('entity');

$session = new LmdbWurthPunchoutSession($db);
if ($token === '' || $entity <= 0 || $session->fetchByToken($token, $entity) <= 0) {
	accessforbidden($langs->trans('LmdbWurthPunchoutInvalidToken'));
}

$payload = getOciPayload();
handlePunchoutReturn($session, 'OCI', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $payload);

/**
 * Return whitelisted OCI payload fields.
 *
 * @return array<string,string>
 */
function getOciPayload()
{
	// OCI fields such as NEW_ITEM-QUANTITY[1] are parsed by PHP as nested arrays.
	$rawGet = is_array($_GET) ? $_GET : array();
	$rawPost = is_array($_POST) ? $_POST : array();
	$rawPayload = array_merge($rawGet, $rawPost);

	$payload = array();
	foreach ($rawPayload as $key => $value) {
		$key = (string) $key;
		if (preg_match('/^NEW_ITEM-[A-Z0-9_:-]+\[\d+\]$/i', $key)) {
			if (is_array($value)) {
				$value = reset($value);
			}
			if (!is_scalar($value)) {
				continue;
			}
			$payload[$key] = dol_string_nohtmltag((string) $value);
			continue;
		}

		if (!preg_match('/^NEW_ITEM-[A-Z0-9_:-]+$/i', $key) || !is_array($value)) {
			continue;
		}

		foreach ($value as $index => $indexedValue) {
			if (!preg_match('/^\d+$/', (string) $index)) {
				continue;
			}
			if (is_array($indexedValue)) {
				$indexedValue = reset($indexedValue);
			}
			if (!is_scalar($indexedValue)) {
				continue;
			}
			$payload[$key.'['.((int) $index).']'] = dol_string_nohtmltag((string) $indexedValue);
		}
	}

	return $payload;
}

/**
 * Store OCI return.
 *
 * @param LmdbWurthPunchoutSession $session Session
 * @param string               $protocol Protocol
 * @param string               $rawPayload Raw payload
 * @param array<string,mixed>  $payload Array payload
 * @return void
 */
function handlePunchoutReturn($session, $protocol, $rawPayload, $payload)
{
	global $langs;

	if ($session->protocol !== $protocol) {
		accessforbidden($langs->trans('LmdbWurthPunchoutProtocolMismatch'));
	}
	if ($session->isExpired()) {
		$session->setStatus(LmdbWurthPunchoutSession::STATUS_EXPIRED);
		accessforbidden($langs->trans('LmdbWurthPunchoutSessionExpired'));
	}
	if (!in_array($session->status, array(LmdbWurthPunchoutSession::STATUS_CREATED, LmdbWurthPunchoutSession::STATUS_SENT), true)) {
		accessforbidden($langs->trans('LmdbWurthPunchoutSessionAlreadyUsed'));
	}

	try {
		$parser = new LmdbWurthPunchoutParser();
		$lines = $parser->parseOci($payload);
		if (empty($lines)) {
			throw new RuntimeException($langs->trans('LmdbWurthPunchoutNoLineReturned'));
		}
		if ($session->storeReturn($rawPayload, $lines) < 0) {
			throw new RuntimeException($session->error);
		}
		renderImportRedirect($session);
	} catch (Exception $e) {
		$session->setStatus(LmdbWurthPunchoutSession::STATUS_ERROR, $e->getMessage());
		llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
		print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
		print '<div class="error">'.$langs->trans('LmdbWurthPunchoutReturnFailed').' '.dol_escape_htmltag($e->getMessage()).'</div>';
		llxFooter();
		exit;
	}
}

/**
 * Render import continuation form.
 *
 * @param LmdbWurthPunchoutSession $session Session
 * @return void
 */
function renderImportRedirect($session)
{
	global $langs;

	llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
	print '<p>'.$langs->trans('LmdbWurthPunchoutBasketReceived').'</p>';
	print '<p><a class="button button-save" href="'.dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'?id='.((int) $session->id).'">'.$langs->trans('LmdbWurthPunchoutImportBasket').'</a></p>';
	llxFooter();
	exit;
}
