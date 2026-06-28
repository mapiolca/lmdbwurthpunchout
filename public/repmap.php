<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Contextual WURTH REP rules completion page.
 */

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

require_once __DIR__.'/../lib/lmdbwurthpunchout.lib.php';
require_once __DIR__.'/../lib/lmdbwurthpunchout_repmap.lib.php';

$langs->loadLangs(array('admin', 'lmdbwurthpunchout@lmdbwurthpunchout'));

if (!isModEnabled('lmdbwurthpunchout')) {
	accessforbidden();
}
if (!$user->admin && !LmdbWurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$retrySessionId = GETPOSTINT('retry_session');
$session = lmdbwurthpunchoutFetchRetryImportSession($db, $retrySessionId);
if (!is_object($session)) {
	accessforbidden($langs->trans('LmdbWurthPunchoutSessionNotFound'));
}

$pageUrl = dol_buildpath('/lmdbwurthpunchout/public/repmap.php', 1);
lmdbwurthpunchoutHandleRepMapActions($db, $pageUrl, $retrySessionId);

$orderUrl = DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn;
$importLog = array();
if (!empty($session->import_log)) {
	$decodedImportLog = json_decode((string) $session->import_log, true);
	$importLog = is_array($decodedImportLog) ? $decodedImportLog : array();
}

llxHeader('', $langs->trans('LmdbWurthPunchoutRepCompletionTitle'));

print load_fiche_titre($langs->trans('LmdbWurthPunchoutRepCompletionTitle'), '', 'technic');
if (!empty($importLog['rep_pending_refs']) && is_array($importLog['rep_pending_refs'])) {
	print '<div class="warning">'.$langs->trans('LmdbWurthPunchoutRepRulesRequiredIntro').'</div>';
	print '<p>'.dol_escape_htmltag($langs->trans('LmdbWurthPunchoutRepPendingRefs', implode(', ', $importLog['rep_pending_refs']))).'</p>';
}

lmdbwurthpunchoutRenderRetryImportBlock($db, $retrySessionId);
lmdbwurthpunchoutRenderRepMapTable($db, $pageUrl, $retrySessionId);

lmdbwurthpunchoutPrintReturnToSupplierOrderJavascript();
print '<p>'.lmdbwurthpunchoutGetReturnToSupplierOrderButton($orderUrl, 'button').'</p>';

llxFooter();
