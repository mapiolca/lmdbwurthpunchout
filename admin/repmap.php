<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * WURTH REP rules setup tab.
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/lmdbwurthpunchout.lib.php';
require_once __DIR__.'/../lib/lmdbwurthpunchout_repmap.lib.php';

$langs->loadLangs(array('admin', 'lmdbwurthpunchout@lmdbwurthpunchout'));

if (!$user->admin && !LmdbWurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$retrySessionId = GETPOSTINT('retry_session');
$repmapBaseUrl = dol_buildpath('/lmdbwurthpunchout/admin/repmap.php', 1);

lmdbwurthpunchoutHandleRepMapActions($db, $repmapBaseUrl, $retrySessionId);

llxHeader('', $langs->trans('LmdbWurthPunchoutRepMapping'));
lmdbwurthpunchoutPrintAdminHeader('repmap');

print load_fiche_titre($langs->trans('LmdbWurthPunchoutRepMapping'), '', '');
lmdbwurthpunchoutRenderRetryImportBlock($db, $retrySessionId);
lmdbwurthpunchoutRenderRepMapTable($db, $repmapBaseUrl, $retrySessionId);

print dol_get_fiche_end();
llxFooter();
