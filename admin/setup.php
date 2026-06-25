<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * WURTH Punchout setup page.
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/wurthpunchout.lib.php';
require_once __DIR__.'/../class/wurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/wurthpunchoutsecurity.class.php';

$langs->loadLangs(array('admin', 'companies', 'products', 'wurthpunchout@wurthpunchout'));

if (!$user->admin && !WurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$rowid = GETPOSTINT('rowid');

if ($action === 'save_settings') {
	if (!WurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}

	$settings = array(
		'PROTOCOL' => strtoupper(GETPOST('WURTHPUNCHOUT_PROTOCOL', 'alpha')),
		'FK_SOC' => (string) GETPOSTINT('WURTHPUNCHOUT_FK_SOC'),
		'OCI_URL' => GETPOST('WURTHPUNCHOUT_OCI_URL', 'restricthtml'),
		'OCI_ORGANIZATION' => GETPOST('WURTHPUNCHOUT_OCI_ORGANIZATION', 'restricthtml'),
		'OCI_NAME' => GETPOST('WURTHPUNCHOUT_OCI_NAME', 'restricthtml'),
		'OCI_METHOD' => strtoupper(GETPOST('WURTHPUNCHOUT_OCI_METHOD', 'alpha')),
		'CXML_URL' => GETPOST('WURTHPUNCHOUT_CXML_URL', 'restricthtml'),
		'CXML_CUSTOMER_DOMAIN' => GETPOST('WURTHPUNCHOUT_CXML_CUSTOMER_DOMAIN', 'restricthtml'),
		'CXML_CUSTOMER_IDENTITY' => GETPOST('WURTHPUNCHOUT_CXML_CUSTOMER_IDENTITY', 'restricthtml'),
		'CXML_SUPPLIER_DOMAIN' => GETPOST('WURTHPUNCHOUT_CXML_SUPPLIER_DOMAIN', 'restricthtml'),
		'CXML_SUPPLIER_IDENTITY' => GETPOST('WURTHPUNCHOUT_CXML_SUPPLIER_IDENTITY', 'restricthtml'),
		'CXML_MODE' => strtolower(GETPOST('WURTHPUNCHOUT_CXML_MODE', 'alpha')),
		'OPEN_MODE' => strtolower(GETPOST('WURTHPUNCHOUT_OPEN_MODE', 'alpha')),
		'CURRENCY' => strtoupper(GETPOST('WURTHPUNCHOUT_CURRENCY', 'alpha')),
		'DEFAULT_VAT' => GETPOST('WURTHPUNCHOUT_DEFAULT_VAT', 'alphanohtml'),
		'PRODUCT_REF_PREFIX' => GETPOST('WURTHPUNCHOUT_PRODUCT_REF_PREFIX', 'alphanohtml'),
		'PRICEUNIT_MODE' => GETPOST('WURTHPUNCHOUT_PRICEUNIT_MODE', 'alpha'),
		'TOKEN_TTL' => (string) max(1, GETPOSTINT('WURTHPUNCHOUT_TOKEN_TTL')),
		'RETENTION_DAYS' => (string) max(1, GETPOSTINT('WURTHPUNCHOUT_RETENTION_DAYS')),
	);

	if (!in_array($settings['PROTOCOL'], array('OCI', 'CXML'), true)) {
		$settings['PROTOCOL'] = 'OCI';
	}
	if (!in_array($settings['OCI_METHOD'], array('GET', 'POST'), true)) {
		$settings['OCI_METHOD'] = 'GET';
	}
	if (!in_array($settings['CXML_MODE'], array('test', 'production'), true)) {
		$settings['CXML_MODE'] = 'production';
	}
	if (!in_array($settings['OPEN_MODE'], array('iframe', 'popup', 'newtab'), true)) {
		$settings['OPEN_MODE'] = 'popup';
	}
	if (!in_array($settings['PRICEUNIT_MODE'], array('divide', 'ignore'), true)) {
		$settings['PRICEUNIT_MODE'] = 'divide';
	}
	if (!preg_match('/^[A-Z]{3}$/', $settings['CURRENCY'])) {
		$settings['CURRENCY'] = 'EUR';
	}

	foreach ($settings as $key => $value) {
		WurthPunchoutConfig::set($db, $key, $value);
	}

	$ociPassword = GETPOST('WURTHPUNCHOUT_OCI_PASSWORD', 'restricthtml');
	if ($ociPassword !== '') {
		WurthPunchoutConfig::setSecret($db, 'OCI_PASSWORD', $ociPassword);
	}
	$cxmlSecret = GETPOST('WURTHPUNCHOUT_CXML_SHARED_SECRET', 'restricthtml');
	if ($cxmlSecret !== '') {
		WurthPunchoutConfig::setSecret($db, 'CXML_SHARED_SECRET', $cxmlSecret);
	}

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'save_unitmap') {
	if (!WurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}

	$wurthUnit = strtoupper(trim(GETPOST('wurth_unit', 'alphanohtml')));
	$fkUnit = GETPOSTINT('fk_unit');
	$label = GETPOST('label', 'restricthtml');
	if ($wurthUnit === '') {
		setEventMessages($langs->trans('WurthPunchoutUnitCodeRequired'), null, 'errors');
	} elseif ($rowid > 0) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'wurthpunchout_unitmap SET';
		$sql .= " wurth_unit = '".$db->escape($wurthUnit)."'";
		$sql .= ', fk_unit = '.($fkUnit > 0 ? (int) $fkUnit : 'NULL');
		$sql .= ", label = '".$db->escape($label)."'";
		$sql .= ' WHERE rowid = '.((int) $rowid).' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		}
	} else {
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label)';
		$sql .= ' VALUES ('.((int) $conf->entity).", '".$db->escape($wurthUnit)."', ".($fkUnit > 0 ? (int) $fkUnit : 'NULL').", '".$db->escape($label)."')";
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'delete_unitmap') {
	if (!WurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}
	if ($rowid > 0) {
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'wurthpunchout_unitmap WHERE rowid = '.((int) $rowid).' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('WurthPunchoutSetup'));
wurthpunchoutPrintAdminHeader('settings');

$protocolOptions = array('OCI' => 'OCI', 'CXML' => 'cXML');
$methodOptions = array('GET' => 'GET', 'POST' => 'POST');
$openModeOptions = array('popup' => $langs->trans('WurthPunchoutOpenPopup'), 'newtab' => $langs->trans('WurthPunchoutOpenNewTab'), 'iframe' => $langs->trans('WurthPunchoutOpenIframe'));
$priceUnitOptions = array('divide' => $langs->trans('WurthPunchoutPriceUnitDivide'), 'ignore' => $langs->trans('WurthPunchoutPriceUnitIgnore'));
$cxmlModeOptions = array('production' => $langs->trans('Production'), 'test' => $langs->trans('Test'));

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_settings">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('WurthPunchoutGeneralSettings').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Protocol').'</td><td>'.$form->selectarray('WURTHPUNCHOUT_PROTOCOL', $protocolOptions, WurthPunchoutConfig::getProtocol(), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutSupplier').'</td><td>';
if (method_exists($form, 'select_company')) {
	print $form->select_company(WurthPunchoutConfig::getInt('FK_SOC'), 'WURTHPUNCHOUT_FK_SOC', 's.fournisseur = 1', 1, 0, 0, array(), 0, 'minwidth300');
} else {
	print '<input class="flat maxwidth100" type="text" name="WURTHPUNCHOUT_FK_SOC" value="'.WurthPunchoutConfig::getInt('FK_SOC').'">';
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutOpenMode').'</td><td>'.$form->selectarray('WURTHPUNCHOUT_OPEN_MODE', $openModeOptions, WurthPunchoutConfig::getOpenMode(), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Currency').'</td><td><input class="flat width50" name="WURTHPUNCHOUT_CURRENCY" value="'.dol_escape_htmltag(WurthPunchoutConfig::getExpectedCurrency()).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DefaultVATRate').'</td><td><input class="flat width50" name="WURTHPUNCHOUT_DEFAULT_VAT" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('DEFAULT_VAT', '20')).'"> %</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCreateProducts').'</td><td>'.(function_exists('ajax_constantonoff') ? ajax_constantonoff('WURTHPUNCHOUT_CREATE_PRODUCTS') : $langs->trans(WurthPunchoutConfig::getInt('CREATE_PRODUCTS', 1) ? 'Yes' : 'No')).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutAllowZeroPrice').'</td><td>'.(function_exists('ajax_constantonoff') ? ajax_constantonoff('WURTHPUNCHOUT_ALLOW_ZERO_PRICE') : $langs->trans(WurthPunchoutConfig::getInt('ALLOW_ZERO_PRICE', 0) ? 'Yes' : 'No')).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutProductRefPrefix').'</td><td><input class="flat minwidth100" name="WURTHPUNCHOUT_PRODUCT_REF_PREFIX" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('PRODUCT_REF_PREFIX', 'WURTH-')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutPriceUnitMode').'</td><td>'.$form->selectarray('WURTHPUNCHOUT_PRICEUNIT_MODE', $priceUnitOptions, WurthPunchoutConfig::getString('PRICEUNIT_MODE', 'divide'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutTokenTtl').'</td><td><input class="flat width50" name="WURTHPUNCHOUT_TOKEN_TTL" value="'.WurthPunchoutConfig::getInt('TOKEN_TTL', 30).'"> '.$langs->trans('Minutes').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutRetentionDays').'</td><td><input class="flat width50" name="WURTHPUNCHOUT_RETENTION_DAYS" value="'.WurthPunchoutConfig::getInt('RETENTION_DAYS', 30).'"> '.$langs->trans('days').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('WurthPunchoutOciSettings').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutOciUrl').'</td><td><input class="flat minwidth500" name="WURTHPUNCHOUT_OCI_URL" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('OCI_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutOciOrganization').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_OCI_ORGANIZATION" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('OCI_ORGANIZATION')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutOciName').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_OCI_NAME" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('OCI_NAME')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Password').'</td><td><input class="flat minwidth200" type="password" autocomplete="new-password" name="WURTHPUNCHOUT_OCI_PASSWORD" value="" placeholder="'.(WurthPunchoutConfig::getSecret('OCI_PASSWORD') !== '' ? dol_escape_htmltag($langs->trans('WurthPunchoutSecretAlreadySaved')) : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Method').'</td><td>'.$form->selectarray('WURTHPUNCHOUT_OCI_METHOD', $methodOptions, WurthPunchoutConfig::getString('OCI_METHOD', 'GET'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('WurthPunchoutCxmlSettings').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlUrl').'</td><td><input class="flat minwidth500" name="WURTHPUNCHOUT_CXML_URL" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('CXML_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlSharedSecret').'</td><td><input class="flat minwidth200" type="password" autocomplete="new-password" name="WURTHPUNCHOUT_CXML_SHARED_SECRET" value="" placeholder="'.(WurthPunchoutConfig::getSecret('CXML_SHARED_SECRET') !== '' ? dol_escape_htmltag($langs->trans('WurthPunchoutSecretAlreadySaved')) : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlCustomerDomain').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_CXML_CUSTOMER_DOMAIN" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('CXML_CUSTOMER_DOMAIN')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlCustomerIdentity').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_CXML_CUSTOMER_IDENTITY" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('CXML_CUSTOMER_IDENTITY')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlSupplierDomain').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_CXML_SUPPLIER_DOMAIN" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('CXML_SUPPLIER_DOMAIN')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('WurthPunchoutCxmlSupplierIdentity').'</td><td><input class="flat minwidth200" name="WURTHPUNCHOUT_CXML_SUPPLIER_IDENTITY" value="'.dol_escape_htmltag(WurthPunchoutConfig::getString('CXML_SUPPLIER_IDENTITY')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Mode').'</td><td>'.$form->selectarray('WURTHPUNCHOUT_CXML_MODE', $cxmlModeOptions, WurthPunchoutConfig::getString('CXML_MODE', 'production'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td></tr>';
print '</table>';

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

if (function_exists('ajax_combobox')) {
	foreach (array('WURTHPUNCHOUT_PROTOCOL', 'WURTHPUNCHOUT_FK_SOC', 'WURTHPUNCHOUT_OPEN_MODE', 'WURTHPUNCHOUT_PRICEUNIT_MODE', 'WURTHPUNCHOUT_OCI_METHOD', 'WURTHPUNCHOUT_CXML_MODE') as $htmlname) {
		ajax_combobox($htmlname);
	}
}

print '<br>';
print load_fiche_titre($langs->trans('WurthPunchoutUnitMapping'), '', '');
renderUnitMapTable($db, $form);

print dol_get_fiche_end();
llxFooter();

/**
 * Render unit mapping table.
 *
 * @param DoliDB $db   Database handler
 * @param Form   $form Form helper
 * @return void
 */
function renderUnitMapTable($db, $form)
{
	global $conf, $langs;

	$unitOptions = getUnitOptions($db);
	$sql = 'SELECT rowid, wurth_unit, fk_unit, label FROM '.MAIN_DB_PREFIX.'wurthpunchout_unitmap';
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	$sql .= ' ORDER BY wurth_unit ASC';
	$resql = $db->query($sql);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('WurthPunchoutUnitCode').'</th><th>'.$langs->trans('Unit').'</th><th>'.$langs->trans('Label').'</th><th></th></tr>';
	if ($resql && $db->num_rows($resql) > 0) {
		while ($obj = $db->fetch_object($resql)) {
			print '<tr class="oddeven">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="save_unitmap">';
			print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
			print '<td><input class="flat maxwidth100" name="wurth_unit" value="'.dol_escape_htmltag($obj->wurth_unit).'"></td>';
			print '<td>'.$form->selectarray('fk_unit', $unitOptions, (int) $obj->fk_unit, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
			print '<td><input class="flat minwidth300" name="label" value="'.dol_escape_htmltag($obj->label).'"></td>';
			print '<td class="right"><input class="button button-save small" type="submit" value="'.$langs->trans('Save').'"> ';
			print '<a class="button button-delete small" href="'.$_SERVER['PHP_SELF'].'?action=delete_unitmap&rowid='.((int) $obj->rowid).'&token='.newToken().'">'.$langs->trans('Delete').'</a></td>';
			print '</form>';
			print '</tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '<tr class="liste_titre"><td colspan="4">'.$langs->trans('Add').'</td></tr>';
	print '<tr class="oddeven">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save_unitmap">';
	print '<td><input class="flat maxwidth100" name="wurth_unit" value=""></td>';
	print '<td>'.$form->selectarray('fk_unit', $unitOptions, 0, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
	print '<td><input class="flat minwidth300" name="label" value=""></td>';
	print '<td class="right"><input class="button button-add small" type="submit" value="'.$langs->trans('Add').'"></td>';
	print '</form>';
	print '</tr>';
	print '</table>';

	if (function_exists('ajax_combobox')) {
		ajax_combobox('fk_unit');
	}
}

/**
 * Load Dolibarr units.
 *
 * @param DoliDB $db Database handler
 * @return array<int,string>
 */
function getUnitOptions($db)
{
	global $langs;

	$options = array();
	$sql = 'SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_units WHERE active = 1 ORDER BY sortorder, label';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$options[(int) $obj->rowid] = $obj->code.' - '.$langs->trans($obj->label);
		}
	}

	return $options;
}
