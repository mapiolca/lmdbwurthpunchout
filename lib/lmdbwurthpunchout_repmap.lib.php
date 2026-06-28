<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Shared helpers for WURTH REP rules.
 */

require_once __DIR__.'/lmdbwurthpunchout.lib.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsession.class.php';

/**
 * Append a query parameter to an URL.
 *
 * @param string $url   URL
 * @param string $key   Parameter name
 * @param string $value Parameter value
 * @return string
 */
function lmdbwurthpunchoutAppendUrlParam($url, $key, $value)
{
	return $url.(strpos($url, '?') === false ? '?' : '&').urlencode($key).'='.urlencode($value);
}

/**
 * Build a REP mapping URL with optional retry session context.
 *
 * @param string $baseUrl        Page URL
 * @param int    $retrySessionId Retry session id
 * @return string
 */
function lmdbwurthpunchoutRepMapUrl($baseUrl, $retrySessionId)
{
	return $retrySessionId > 0 ? lmdbwurthpunchoutAppendUrlParam($baseUrl, 'retry_session', (string) ((int) $retrySessionId)) : $baseUrl;
}

/**
 * Handle REP mapping save/delete actions.
 *
 * @param DoliDB $db             Database handler
 * @param string $pageUrl        Page URL
 * @param int    $retrySessionId Retry session id
 * @return void
 */
function lmdbwurthpunchoutHandleRepMapActions($db, $pageUrl, $retrySessionId)
{
	global $conf, $langs;

	$action = GETPOST('action', 'aZ09');
	$rowid = GETPOSTINT('rowid');
	$redirectUrl = lmdbwurthpunchoutRepMapUrl($pageUrl, $retrySessionId);

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
		header('Location: '.$redirectUrl);
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
		header('Location: '.$redirectUrl);
		exit;
	}
}

/**
 * Fetch a retryable Punchout session for the current entity and user.
 *
 * @param DoliDB $db             Database handler
 * @param int    $retrySessionId Retry session id
 * @return LmdbWurthPunchoutSession|null
 */
function lmdbwurthpunchoutFetchRetryImportSession($db, $retrySessionId)
{
	global $conf, $user;

	if ($retrySessionId <= 0) {
		return null;
	}

	$session = new LmdbWurthPunchoutSession($db);
	if ($session->fetch($retrySessionId) <= 0) {
		return null;
	}
	if ((int) $session->entity !== (int) $conf->entity || $session->status !== LmdbWurthPunchoutSession::STATUS_RETURNED) {
		return null;
	}
	if ((int) $session->fk_user !== (int) $user->id && empty($user->admin)) {
		return null;
	}

	return $session;
}

/**
 * Render retry import action for a blocked stored basket.
 *
 * @param DoliDB $db             Database handler
 * @param int    $retrySessionId Retry session id
 * @param bool   $showBackButton Show supplier order return button
 * @return void
 */
function lmdbwurthpunchoutRenderRetryImportBlock($db, $retrySessionId, $showBackButton = false)
{
	global $langs, $user;

	$session = lmdbwurthpunchoutFetchRetryImportSession($db, $retrySessionId);
	if (!is_object($session)) {
		return;
	}

	$orderUrl = DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn;
	lmdbwurthpunchoutPrintReturnToSupplierOrderJavascript();

	print '<div class="info">';
	print '<span>'.$langs->trans('LmdbWurthPunchoutRetryStoredImportHelp').'</span> ';
	if (LmdbWurthPunchoutSecurity::canUsePunchout($user)) {
		$importUrl = dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'?id='.((int) $session->id);
		print '<a class="button button-save" href="'.dol_escape_htmltag($importUrl).'">'.$langs->trans('LmdbWurthPunchoutRetryStoredImport').'</a> ';
	}
	if ($showBackButton) {
		print lmdbwurthpunchoutGetReturnToSupplierOrderButton($orderUrl, 'button');
	}
	print '</div><br>';
}

/**
 * Render REP amount mapping table.
 *
 * @param DoliDB $db             Database handler
 * @param string $pageUrl        Current page URL
 * @param int    $retrySessionId Retry session id
 * @return void
 */
function lmdbwurthpunchoutRenderRepMapTable($db, $pageUrl, $retrySessionId)
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
		print '<form id="'.$formId.'" method="POST" action="'.dol_escape_htmltag($pageUrl).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_repmap">';
		print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
		if ($retrySessionId > 0) {
			print '<input type="hidden" name="retry_session" value="'.((int) $retrySessionId).'">';
		}
		print '</form>';
	}
	print '<form id="repmap-new" method="POST" action="'.dol_escape_htmltag($pageUrl).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save_repmap">';
	if ($retrySessionId > 0) {
		print '<input type="hidden" name="retry_session" value="'.((int) $retrySessionId).'">';
	}
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
			print '<td><input form="'.$formId.'" class="flat width75" name="amount_ht" value="'.dol_escape_htmltag(lmdbwurthpunchoutFormatRepAmount((float) $obj->amount_ht)).'"> '.dol_escape_htmltag($currency).'</td>';
			print '<td><input form="'.$formId.'" class="flat minwidth300" name="label" value="'.dol_escape_htmltag($obj->label).'"></td>';
			print '<td class="right"><input form="'.$formId.'" class="button button-save small" type="submit" value="'.$langs->trans('Save').'"> ';
			$deleteUrl = lmdbwurthpunchoutAppendUrlParam($pageUrl, 'action', 'delete_repmap');
			$deleteUrl = lmdbwurthpunchoutAppendUrlParam($deleteUrl, 'rowid', (string) ((int) $obj->rowid));
			$deleteUrl = lmdbwurthpunchoutAppendUrlParam($deleteUrl, 'token', newToken());
			if ($retrySessionId > 0) {
				$deleteUrl = lmdbwurthpunchoutAppendUrlParam($deleteUrl, 'retry_session', (string) ((int) $retrySessionId));
			}
			print '<a class="button button-delete small" href="'.dol_escape_htmltag($deleteUrl).'">'.$langs->trans('Delete').'</a></td>';
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
function lmdbwurthpunchoutFormatRepAmount($amount)
{
	$value = rtrim(rtrim(sprintf('%.8F', $amount), '0'), '.');
	return $value !== '' ? $value : '0';
}
