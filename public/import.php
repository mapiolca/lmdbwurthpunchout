<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Authenticated Punchout import page.
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

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsession.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutimporter.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';

$langs->loadLangs(array('lmdbwurthpunchout@lmdbwurthpunchout', 'errors', 'orders'));

if (!isModEnabled('lmdbwurthpunchout')) {
	accessforbidden();
}
if (!LmdbWurthPunchoutSecurity::canUsePunchout($user)) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$session = new LmdbWurthPunchoutSession($db);
if ($id <= 0 || $session->fetch($id) <= 0) {
	accessforbidden($langs->trans('LmdbWurthPunchoutSessionNotFound'));
}

if ((int) $session->entity !== (int) $conf->entity) {
	accessforbidden($langs->trans('LmdbWurthPunchoutWrongEntity'));
}
if ((int) $session->fk_user !== (int) $user->id && empty($user->admin)) {
	accessforbidden($langs->trans('LmdbWurthPunchoutSessionUserMismatch'));
}

if ($action === 'import') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}
	if ($session->status !== LmdbWurthPunchoutSession::STATUS_RETURNED) {
		accessforbidden($langs->trans('LmdbWurthPunchoutSessionAlreadyUsed'));
	}

	try {
		$importer = new LmdbWurthPunchoutImporter($db);
		$summary = $importer->importStoredSession($session, $user);

		$message = $langs->trans('LmdbWurthPunchoutImportSuccess', (int) $summary['lines_added'], (int) $summary['products_created'], (int) $summary['supplier_prices_updated']);
		if (!empty($summary['warnings'])) {
			$message .= '<br>'.dol_escape_htmltag(implode(', ', $summary['warnings']));
		}
		setEventMessages($message, null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn);
		exit;
	} catch (LmdbWurthPunchoutRepRulesRequiredException $e) {
		if ($session->markImportBlocked($e->getSummary(), $e->getMessage()) < 0) {
			setEventMessages($session->error ?: $e->getMessage(), null, 'errors');
			header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn);
			exit;
		}
		setEventMessages($e->getMessage(), null, 'warnings');
		header('Location: '.dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'?id='.(int) $session->id);
		exit;
	} catch (Exception $e) {
		$session->setStatus(LmdbWurthPunchoutSession::STATUS_ERROR, $e->getMessage());
		setEventMessages($langs->trans('LmdbWurthPunchoutImportFailed').' '.$e->getMessage(), null, 'errors');
		header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn);
		exit;
	}
}

llxHeader('', $langs->trans('LmdbWurthPunchoutImportBasket'));
print load_fiche_titre($langs->trans('LmdbWurthPunchoutImportBasket'), '', 'technic');

if ($session->status !== LmdbWurthPunchoutSession::STATUS_RETURNED) {
	print '<div class="warning">'.$langs->trans('LmdbWurthPunchoutSessionAlreadyUsed').'</div>';
} else {
	$lines = $session->fetchLines();
	$importLog = array();
	if (!empty($session->import_log)) {
		$decodedImportLog = json_decode((string) $session->import_log, true);
		$importLog = is_array($decodedImportLog) ? $decodedImportLog : array();
	}
	if (!empty($importLog['rep_pending_refs']) && is_array($importLog['rep_pending_refs'])) {
		print '<div class="warning">'.$langs->trans('LmdbWurthPunchoutRepRulesRequiredIntro').'</div>';
		print '<p>'.dol_escape_htmltag($langs->trans('LmdbWurthPunchoutRepPendingRefs', implode(', ', $importLog['rep_pending_refs']))).'</p>';
		print '<p><a class="button button-edit" href="'.dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1).'#repmap">'.$langs->trans('LmdbWurthPunchoutCompleteRepRules').'</a></p>';
	}
	print '<form method="POST" action="'.dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="import">';
	print '<input type="hidden" name="id" value="'.((int) $session->id).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('SupplierRef').'</th>';
	print '<th>'.$langs->trans('Label').'</th>';
	print '<th class="right">'.$langs->trans('Qty').'</th>';
	print '<th>'.$langs->trans('Unit').'</th>';
	print '<th class="right">'.$langs->trans('PriceUHT').'</th>';
	print '<th>'.$langs->trans('Currency').'</th>';
	print '</tr>';
	foreach ($lines as $line) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($line['vendor_ref']).'</td>';
		print '<td>'.dol_escape_htmltag($line['label']).'</td>';
		print '<td class="right">'.price($line['qty']).'</td>';
		print '<td>'.dol_escape_htmltag($line['unit_code']).'</td>';
		print '<td class="right">'.price($line['unit_price_ht']).'</td>';
		print '<td>'.dol_escape_htmltag($line['currency']).'</td>';
		print '</tr>';
	}
	if (empty($lines)) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('LmdbWurthPunchoutImportBasket').'"></div>';
	print '</form>';
}

print '<p><a class="button" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn.'">'.$langs->trans('BackToSupplierOrder').'</a></p>';

llxFooter();
