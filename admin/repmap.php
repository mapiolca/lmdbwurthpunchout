<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * WURTH REP rules setup page.
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
require_once __DIR__.'/../class/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';

$langs->loadLangs(array('admin', 'lmdbwurthpunchout@lmdbwurthpunchout'));

if (!$user->admin && !LmdbWurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$rowid = GETPOSTINT('rowid');
$repmapUrl = dol_buildpath('/lmdbwurthpunchout/admin/repmap.php', 1);

if ($action === 'save_repmap') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}

	$vendorRef = trim(GETPOST('vendor_ref', 'restricthtml'));
	$amountHt = str_replace(',', '.', trim(GETPOST('amount_ht', 'restricthtml')));
	$label = GETPOST('label', 'restricthtml');

	if ($vendorRef === '') {
		setEventMessages($langs->trans('LmdbWurthPunchoutRepVendorRefRequired'), null, 'errors');
	} elseif ($amountHt === '' || !is_numeric($amountHt) || (float) $amountHt < 0) {
		setEventMessages($langs->trans('LmdbWurthPunchoutRepAmountRequired'), null, 'errors');
	} elseif ($rowid > 0) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap SET';
		$sql .= " vendor_ref = '".$db->escape($vendorRef)."'";
		$sql .= ', amount_ht = '.((float) $amountHt);
		$sql .= ", label = '".$db->escape($label)."'";
		$sql .= ", status = 'active'";
		$sql .= ' WHERE rowid = '.((int) $rowid).' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		}
	} else {
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap (entity, vendor_ref, amount_ht, label, status)';
		$sql .= ' VALUES ('.((int) $conf->entity).", '".$db->escape($vendorRef)."', ".((float) $amountHt).", '".$db->escape($label)."', 'active')";
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		}
	}
	header('Location: '.$repmapUrl);
	exit;
}

if ($action === 'delete_repmap') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}
	if ($rowid > 0) {
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap WHERE rowid = '.((int) $rowid).' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		}
	}
	header('Location: '.$repmapUrl);
	exit;
}

llxHeader('', $langs->trans('LmdbWurthPunchoutRepMapping'));
lmdbwurthpunchoutPrintAdminHeader('repmap');

print load_fiche_titre($langs->trans('LmdbWurthPunchoutRepMapping'), '', '');
renderRepMapTable($db, $repmapUrl);

print dol_get_fiche_end();
llxFooter();

/**
 * Render REP amount mapping table.
 *
 * @param DoliDB $db      Database handler
 * @param string $pageUrl Current page URL
 * @return void
 */
function renderRepMapTable($db, $pageUrl)
{
	global $conf, $langs;

	$currency = LmdbWurthPunchoutConfig::getExpectedCurrency();
	$sql = 'SELECT rowid, vendor_ref, amount_ht, label, status FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap';
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	$sql .= " ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END, vendor_ref ASC";
	$resql = $db->query($sql);
	$rows = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rows[] = $obj;
		}
	}

	foreach ($rows as $obj) {
		$formId = 'repmap-'.((int) $obj->rowid);
		print '<form id="'.$formId.'" method="POST" action="'.$pageUrl.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_repmap">';
		print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
		print '</form>';
	}
	print '<form id="repmap-new" method="POST" action="'.$pageUrl.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save_repmap">';
	print '</form>';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('LmdbWurthPunchoutRepVendorRef').'</th><th>'.$langs->trans('LmdbWurthPunchoutRepAmountPerUnit').'</th><th>'.$langs->trans('Label').'</th><th></th></tr>';
	if (!empty($rows)) {
		foreach ($rows as $obj) {
			$formId = 'repmap-'.((int) $obj->rowid);
			$status = (string) ($obj->status ?: 'active');
			$statusLabel = $status === 'pending' ? $langs->trans('LmdbWurthPunchoutRepStatusPending') : $langs->trans('LmdbWurthPunchoutRepStatusActive');
			$statusClass = $status === 'pending' ? 'badge-status3' : 'badge-status4';
			print '<tr class="oddeven">';
			print '<td><span class="badge '.$statusClass.'">'.$statusLabel.'</span></td>';
			print '<td><input form="'.$formId.'" class="flat minwidth200" name="vendor_ref" value="'.dol_escape_htmltag($obj->vendor_ref).'"></td>';
			print '<td><input form="'.$formId.'" class="flat width75" name="amount_ht" value="'.dol_escape_htmltag(formatRepAmount((float) $obj->amount_ht)).'"> '.dol_escape_htmltag($currency).'</td>';
			print '<td><input form="'.$formId.'" class="flat minwidth300" name="label" value="'.dol_escape_htmltag($obj->label).'"></td>';
			print '<td class="right"><input form="'.$formId.'" class="button button-save small" type="submit" value="'.$langs->trans('Save').'"> ';
			print '<a class="button button-delete small" href="'.$pageUrl.'?action=delete_repmap&rowid='.((int) $obj->rowid).'&token='.newToken().'">'.$langs->trans('Delete').'</a></td>';
			print '</tr>';
		}
	} elseif (!$resql) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$db->lasterror().'</span></td></tr>';
	} else {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '<tr class="liste_titre"><td colspan="5">'.$langs->trans('Add').'</td></tr>';
	print '<tr class="oddeven">';
	print '<td><span class="badge badge-status4">'.$langs->trans('LmdbWurthPunchoutRepStatusActive').'</span></td>';
	print '<td><input form="repmap-new" class="flat minwidth200" name="vendor_ref" value=""></td>';
	print '<td><input form="repmap-new" class="flat width75" name="amount_ht" value=""> '.dol_escape_htmltag($currency).'</td>';
	print '<td><input form="repmap-new" class="flat minwidth300" name="label" value=""></td>';
	print '<td class="right"><input form="repmap-new" class="button button-add small" type="submit" value="'.$langs->trans('Add').'"></td>';
	print '</tr>';
	print '</table>';

	print '<div class="opacitymedium">'.$langs->trans('LmdbWurthPunchoutRepMappingHelp').'</div>';
}

/**
 * Format REP amount for editable inputs.
 *
 * @param float $amount Amount
 * @return string
 */
function formatRepAmount($amount)
{
	$value = rtrim(rtrim(sprintf('%.8F', $amount), '0'), '.');
	return $value !== '' ? $value : '0';
}
