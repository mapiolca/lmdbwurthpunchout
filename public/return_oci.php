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

require_once __DIR__.'/../class/wurthpunchoutsession.class.php';
require_once __DIR__.'/../class/wurthpunchoutparser.class.php';

$langs->loadLangs(array('wurthpunchout@wurthpunchout', 'errors'));

$token = GETPOST('token', 'alphanohtml');
$entity = GETPOSTINT('entity');

$session = new WurthPunchoutSession($db);
if ($token === '' || $entity <= 0 || $session->fetchByToken($token, $entity) <= 0) {
	accessforbidden($langs->trans('WurthPunchoutInvalidToken'));
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
	$rawPayload = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
	if (!is_array($rawPayload)) {
		$rawPayload = array();
	}

	$payload = array();
	foreach ($rawPayload as $key => $value) {
		if (!preg_match('/^NEW_ITEM-[A-Z0-9_:-]+\[\d+\]$/', (string) $key)) {
			continue;
		}
		if (is_array($value)) {
			$value = reset($value);
		}
		if (!is_scalar($value)) {
			continue;
		}
		$payload[(string) $key] = dol_string_nohtmltag((string) $value);
	}

	return $payload;
}

/**
 * Store OCI return.
 *
 * @param WurthPunchoutSession $session Session
 * @param string               $protocol Protocol
 * @param string               $rawPayload Raw payload
 * @param array<string,mixed>  $payload Array payload
 * @return void
 */
function handlePunchoutReturn($session, $protocol, $rawPayload, $payload)
{
	global $langs;

	if ($session->protocol !== $protocol) {
		accessforbidden($langs->trans('WurthPunchoutProtocolMismatch'));
	}
	if ($session->isExpired()) {
		$session->setStatus(WurthPunchoutSession::STATUS_EXPIRED);
		accessforbidden($langs->trans('WurthPunchoutSessionExpired'));
	}
	if (!in_array($session->status, array(WurthPunchoutSession::STATUS_CREATED, WurthPunchoutSession::STATUS_SENT), true)) {
		accessforbidden($langs->trans('WurthPunchoutSessionAlreadyUsed'));
	}

	try {
		$parser = new WurthPunchoutParser();
		$lines = $parser->parseOci($payload);
		if (empty($lines)) {
			throw new RuntimeException($langs->trans('WurthPunchoutNoLineReturned'));
		}
		if ($session->storeReturn($rawPayload, $lines) < 0) {
			throw new RuntimeException($session->error);
		}
		renderImportRedirect($session);
	} catch (Exception $e) {
		$session->setStatus(WurthPunchoutSession::STATUS_ERROR, $e->getMessage());
		llxHeader('', $langs->trans('WurthPunchoutReturnTitle'));
		print load_fiche_titre($langs->trans('WurthPunchoutReturnTitle'), '', 'technic');
		print '<div class="error">'.$langs->trans('WurthPunchoutReturnFailed').' '.dol_escape_htmltag($e->getMessage()).'</div>';
		llxFooter();
		exit;
	}
}

/**
 * Render import continuation form.
 *
 * @param WurthPunchoutSession $session Session
 * @return void
 */
function renderImportRedirect($session)
{
	global $langs;

	llxHeader('', $langs->trans('WurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('WurthPunchoutReturnTitle'), '', 'technic');
	print '<p>'.$langs->trans('WurthPunchoutBasketReceived').'</p>';
	print '<form method="POST" action="'.dol_buildpath('/wurthpunchout/public/import.php', 1).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="import">';
	print '<input type="hidden" name="id" value="'.((int) $session->id).'">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('WurthPunchoutImportBasket').'">';
	print '</form>';
	llxFooter();
	exit;
}
