<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Public cXML return endpoint.
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
$rawPayload = GETPOST('cXML-urlencoded', 'none');
if ($rawPayload === '') {
	$rawPayload = GETPOST('cxml-urlencoded', 'none');
}
if ($rawPayload === '') {
	$rawPayload = (string) file_get_contents('php://input');
}

$session = new WurthPunchoutSession($db);
if ($token === '' || $entity <= 0 || $session->fetchByToken($token, $entity) <= 0) {
	accessforbidden($langs->trans('WurthPunchoutInvalidToken'));
}
if ($session->protocol !== 'CXML') {
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
	$lines = $parser->parseCxml($rawPayload);
	if (empty($lines)) {
		throw new RuntimeException($langs->trans('WurthPunchoutNoLineReturned'));
	}
	if ($session->storeReturn($rawPayload, $lines) < 0) {
		throw new RuntimeException($session->error);
	}
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
} catch (Exception $e) {
	$session->setStatus(WurthPunchoutSession::STATUS_ERROR, $e->getMessage());
	llxHeader('', $langs->trans('WurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('WurthPunchoutReturnTitle'), '', 'technic');
	print '<div class="error">'.$langs->trans('WurthPunchoutReturnFailed').' '.dol_escape_htmltag($e->getMessage()).'</div>';
	llxFooter();
	exit;
}
