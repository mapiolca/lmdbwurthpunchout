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
require_once __DIR__.'/../class/wurthpunchoutsession.class.php';
require_once __DIR__.'/../class/wurthpunchoutimporter.class.php';
require_once __DIR__.'/../class/wurthpunchoutsecurity.class.php';

$langs->loadLangs(array('wurthpunchout@wurthpunchout', 'errors', 'orders'));

if (!isModEnabled('wurthpunchout')) {
	accessforbidden();
}
if (!WurthPunchoutSecurity::canUsePunchout($user)) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$session = new WurthPunchoutSession($db);
if ($id <= 0 || $session->fetch($id) <= 0) {
	accessforbidden($langs->trans('WurthPunchoutSessionNotFound'));
}

if ((int) $session->entity !== (int) $conf->entity) {
	accessforbidden($langs->trans('WurthPunchoutWrongEntity'));
}
if ((int) $session->fk_user !== (int) $user->id && empty($user->admin)) {
	accessforbidden($langs->trans('WurthPunchoutSessionUserMismatch'));
}

if ($action === 'import') {
	if (!WurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}
	if ($session->status !== WurthPunchoutSession::STATUS_RETURNED) {
		accessforbidden($langs->trans('WurthPunchoutSessionAlreadyUsed'));
	}

	$db->begin();
	try {
		$importer = new WurthPunchoutImporter($db);
		$summary = $importer->importSession($session, $user);
		if ($session->markImported($summary) < 0) {
			throw new RuntimeException($session->error);
		}
		$db->commit();

		$message = $langs->trans('WurthPunchoutImportSuccess', (int) $summary['lines_added'], (int) $summary['products_created'], (int) $summary['supplier_prices_updated']);
		if (!empty($summary['warnings'])) {
			$message .= '<br>'.dol_escape_htmltag(implode(', ', $summary['warnings']));
		}
		setEventMessages($message, null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn);
		exit;
	} catch (Exception $e) {
		$db->rollback();
		$session->setStatus(WurthPunchoutSession::STATUS_ERROR, $e->getMessage());
		setEventMessages($langs->trans('WurthPunchoutImportFailed').' '.$e->getMessage(), null, 'errors');
		header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn);
		exit;
	}
}

llxHeader('', $langs->trans('WurthPunchoutImportBasket'));
print load_fiche_titre($langs->trans('WurthPunchoutImportBasket'), '', 'technic');

if ($session->status !== WurthPunchoutSession::STATUS_RETURNED) {
	print '<div class="warning">'.$langs->trans('WurthPunchoutSessionAlreadyUsed').'</div>';
} else {
	$lines = $session->fetchLines();
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
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
	print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('WurthPunchoutImportBasket').'"></div>';
	print '</form>';
}

print '<p><a class="button" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn.'">'.$langs->trans('BackToSupplierOrder').'</a></p>';

llxFooter();
