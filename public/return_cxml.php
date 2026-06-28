<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Public cXML return endpoint.
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', 1);
}
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
require_once __DIR__.'/../class/lmdbwurthpunchoutcxmlpayload.class.php';
require_once __DIR__.'/return_common.php';

$langs->loadLangs(array('lmdbwurthpunchout@lmdbwurthpunchout', 'errors'));

$token = GETPOST('token', 'alphanohtml');
$entity = GETPOSTINT('entity');

$session = new LmdbWurthPunchoutSession($db);
if ($token === '' || $entity <= 0 || $session->fetchByToken($token, $entity) <= 0) {
	accessforbidden($langs->trans('LmdbWurthPunchoutInvalidToken'));
}
if ($session->protocol !== 'CXML') {
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
	$rawPayload = LmdbWurthPunchoutCxmlPayload::extract($_POST, (string) file_get_contents('php://input'));
	$parser = new LmdbWurthPunchoutParser();
	$basket = $parser->parseCxmlBasket($rawPayload);
	$summary = lmdbwurthpunchoutStoreAndImportReturn($session, 'CXML', $rawPayload, $basket['lines'], $basket);
	lmdbwurthpunchoutRenderImportDone($session, $summary);
} catch (Exception $e) {
	$session->setStatus(LmdbWurthPunchoutSession::STATUS_ERROR, $e->getMessage());
	lmdbwurthpunchoutRenderReturnError($e);
}
