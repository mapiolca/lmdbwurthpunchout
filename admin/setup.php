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
require_once __DIR__.'/../lib/lmdbwurthpunchout.lib.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';

$langs->loadLangs(array('admin', 'companies', 'products', 'lmdbwurthpunchout@lmdbwurthpunchout'));

if (!$user->admin && !LmdbWurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$rowid = GETPOSTINT('rowid');
$setupUrl = dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1);

if ($action === 'save_settings') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}

	$settings = array(
		'PROTOCOL' => strtoupper(GETPOST('LMDBWURTHPUNCHOUT_PROTOCOL', 'alpha')),
		'FK_SOC' => (string) GETPOSTINT('LMDBWURTHPUNCHOUT_FK_SOC'),
		'OCI_URL' => GETPOST('LMDBWURTHPUNCHOUT_OCI_URL', 'restricthtml'),
		'OCI_ORGANIZATION' => GETPOST('LMDBWURTHPUNCHOUT_OCI_ORGANIZATION', 'restricthtml'),
		'OCI_NAME' => GETPOST('LMDBWURTHPUNCHOUT_OCI_NAME', 'restricthtml'),
		'OCI_METHOD' => strtoupper(GETPOST('LMDBWURTHPUNCHOUT_OCI_METHOD', 'alpha')),
		'CXML_URL' => GETPOST('LMDBWURTHPUNCHOUT_CXML_URL', 'restricthtml'),
		'CXML_CUSTOMER_DOMAIN' => GETPOST('LMDBWURTHPUNCHOUT_CXML_CUSTOMER_DOMAIN', 'restricthtml'),
		'CXML_CUSTOMER_IDENTITY' => GETPOST('LMDBWURTHPUNCHOUT_CXML_CUSTOMER_IDENTITY', 'restricthtml'),
		'CXML_SUPPLIER_DOMAIN' => GETPOST('LMDBWURTHPUNCHOUT_CXML_SUPPLIER_DOMAIN', 'restricthtml'),
		'CXML_SUPPLIER_IDENTITY' => GETPOST('LMDBWURTHPUNCHOUT_CXML_SUPPLIER_IDENTITY', 'restricthtml'),
		'CXML_MODE' => strtolower(GETPOST('LMDBWURTHPUNCHOUT_CXML_MODE', 'alpha')),
		'OPEN_MODE' => strtolower(GETPOST('LMDBWURTHPUNCHOUT_OPEN_MODE', 'alpha')),
		'CURRENCY' => strtoupper(GETPOST('LMDBWURTHPUNCHOUT_CURRENCY', 'alpha')),
		'DEFAULT_VAT' => GETPOST('LMDBWURTHPUNCHOUT_DEFAULT_VAT', 'alphanohtml'),
		'PRODUCT_REF_PREFIX' => GETPOST('LMDBWURTHPUNCHOUT_PRODUCT_REF_PREFIX', 'alphanohtml'),
		'PRICEUNIT_MODE' => GETPOST('LMDBWURTHPUNCHOUT_PRICEUNIT_MODE', 'alpha'),
		'TOKEN_TTL' => (string) max(1, GETPOSTINT('LMDBWURTHPUNCHOUT_TOKEN_TTL')),
		'RETENTION_DAYS' => (string) max(1, GETPOSTINT('LMDBWURTHPUNCHOUT_RETENTION_DAYS')),
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
		LmdbWurthPunchoutConfig::set($db, $key, $value);
	}

	$ociPassword = GETPOST('LMDBWURTHPUNCHOUT_OCI_PASSWORD', 'restricthtml');
	if ($ociPassword !== '') {
		LmdbWurthPunchoutConfig::setSecret($db, 'OCI_PASSWORD', $ociPassword);
	}
	$cxmlSecret = GETPOST('LMDBWURTHPUNCHOUT_CXML_SHARED_SECRET', 'restricthtml');
	if ($cxmlSecret !== '') {
		LmdbWurthPunchoutConfig::setSecret($db, 'CXML_SHARED_SECRET', $cxmlSecret);
	}

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$setupUrl);
	exit;
}

if ($action === 'save_unitmap') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}

	$wurthUnit = strtoupper(trim(GETPOST('wurth_unit', 'alphanohtml')));
	$fkUnit = GETPOSTINT('fk_unit');
	$label = GETPOST('label', 'restricthtml');
	if ($wurthUnit === '') {
		setEventMessages($langs->trans('LmdbWurthPunchoutUnitCodeRequired'), null, 'errors');
	} elseif ($rowid > 0) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap SET';
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
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap (entity, wurth_unit, fk_unit, label)';
		$sql .= ' VALUES ('.((int) $conf->entity).", '".$db->escape($wurthUnit)."', ".($fkUnit > 0 ? (int) $fkUnit : 'NULL').", '".$db->escape($label)."')";
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		}
	}
	header('Location: '.$setupUrl);
	exit;
}

if ($action === 'delete_unitmap') {
	if (!LmdbWurthPunchoutSecurity::checkToken()) {
		accessforbidden('Bad token');
	}
	if ($rowid > 0) {
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap WHERE rowid = '.((int) $rowid).' AND entity = '.((int) $conf->entity);
		$resql = $db->query($sql);
		if (!$resql) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		}
	}
	header('Location: '.$setupUrl);
	exit;
}

llxHeader('', $langs->trans('LmdbWurthPunchoutSetup'));
lmdbwurthpunchoutPrintAdminHeader('settings');

$protocolOptions = array('OCI' => 'OCI', 'CXML' => 'cXML');
$methodOptions = array('GET' => 'GET', 'POST' => 'POST');
$openModeOptions = array('popup' => $langs->trans('LmdbWurthPunchoutOpenPopup'), 'newtab' => $langs->trans('LmdbWurthPunchoutOpenNewTab'), 'iframe' => $langs->trans('LmdbWurthPunchoutOpenIframe'));
$priceUnitOptions = array('divide' => $langs->trans('LmdbWurthPunchoutPriceUnitDivide'), 'ignore' => $langs->trans('LmdbWurthPunchoutPriceUnitIgnore'));
$cxmlModeOptions = array('production' => $langs->trans('Production'), 'test' => $langs->trans('Test'));

print '<form method="POST" action="'.$setupUrl.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_settings">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbWurthPunchoutGeneralSettings').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Protocol').'</td><td>'.$form->selectarray('LMDBWURTHPUNCHOUT_PROTOCOL', $protocolOptions, LmdbWurthPunchoutConfig::getProtocol(), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutSupplier').'</td><td>';
if (method_exists($form, 'select_company')) {
	print $form->select_company(LmdbWurthPunchoutConfig::getInt('FK_SOC'), 'LMDBWURTHPUNCHOUT_FK_SOC', 's.fournisseur = 1', 1, 0, 0, array(), 0, 'minwidth300');
} else {
	print '<input class="flat maxwidth100" type="text" name="LMDBWURTHPUNCHOUT_FK_SOC" value="'.LmdbWurthPunchoutConfig::getInt('FK_SOC').'">';
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutOpenMode').'</td><td>'.$form->selectarray('LMDBWURTHPUNCHOUT_OPEN_MODE', $openModeOptions, LmdbWurthPunchoutConfig::getOpenMode(), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Currency').'</td><td><input class="flat width50" name="LMDBWURTHPUNCHOUT_CURRENCY" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getExpectedCurrency()).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DefaultVATRate').'</td><td><input class="flat width50" name="LMDBWURTHPUNCHOUT_DEFAULT_VAT" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('DEFAULT_VAT', '20')).'"> %</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCreateProducts').'</td><td>'.(function_exists('ajax_constantonoff') ? ajax_constantonoff('LMDBWURTHPUNCHOUT_CREATE_PRODUCTS') : $langs->trans(LmdbWurthPunchoutConfig::getInt('CREATE_PRODUCTS', 1) ? 'Yes' : 'No')).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutAllowZeroPrice').'</td><td>'.(function_exists('ajax_constantonoff') ? ajax_constantonoff('LMDBWURTHPUNCHOUT_ALLOW_ZERO_PRICE') : $langs->trans(LmdbWurthPunchoutConfig::getInt('ALLOW_ZERO_PRICE', 0) ? 'Yes' : 'No')).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutProductRefPrefix').'</td><td><input class="flat minwidth100" name="LMDBWURTHPUNCHOUT_PRODUCT_REF_PREFIX" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('PRODUCT_REF_PREFIX', 'WURTH-')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutPriceUnitMode').'</td><td>'.$form->selectarray('LMDBWURTHPUNCHOUT_PRICEUNIT_MODE', $priceUnitOptions, LmdbWurthPunchoutConfig::getString('PRICEUNIT_MODE', 'divide'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutTokenTtl').'</td><td><input class="flat width50" name="LMDBWURTHPUNCHOUT_TOKEN_TTL" value="'.LmdbWurthPunchoutConfig::getInt('TOKEN_TTL', 30).'"> '.$langs->trans('Minutes').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutRetentionDays').'</td><td><input class="flat width50" name="LMDBWURTHPUNCHOUT_RETENTION_DAYS" value="'.LmdbWurthPunchoutConfig::getInt('RETENTION_DAYS', 30).'"> '.$langs->trans('days').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbWurthPunchoutOciSettings').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutOciUrl').'</td><td><input class="flat minwidth500" name="LMDBWURTHPUNCHOUT_OCI_URL" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('OCI_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutOciOrganization').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_OCI_ORGANIZATION" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('OCI_ORGANIZATION')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutOciName').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_OCI_NAME" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('OCI_NAME')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Password').'</td><td><input class="flat minwidth200" type="password" autocomplete="new-password" name="LMDBWURTHPUNCHOUT_OCI_PASSWORD" value="" placeholder="'.(LmdbWurthPunchoutConfig::getSecret('OCI_PASSWORD') !== '' ? dol_escape_htmltag($langs->trans('LmdbWurthPunchoutSecretAlreadySaved')) : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Method').'</td><td>'.$form->selectarray('LMDBWURTHPUNCHOUT_OCI_METHOD', $methodOptions, LmdbWurthPunchoutConfig::getString('OCI_METHOD', 'GET'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth100').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LmdbWurthPunchoutCxmlSettings').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlUrl').'</td><td><input class="flat minwidth500" name="LMDBWURTHPUNCHOUT_CXML_URL" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('CXML_URL')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlSharedSecret').'</td><td><input class="flat minwidth200" type="password" autocomplete="new-password" name="LMDBWURTHPUNCHOUT_CXML_SHARED_SECRET" value="" placeholder="'.(LmdbWurthPunchoutConfig::getSecret('CXML_SHARED_SECRET') !== '' ? dol_escape_htmltag($langs->trans('LmdbWurthPunchoutSecretAlreadySaved')) : '').'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlCustomerDomain').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_CXML_CUSTOMER_DOMAIN" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_DOMAIN')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlCustomerIdentity').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_CXML_CUSTOMER_IDENTITY" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_IDENTITY')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlSupplierDomain').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_CXML_SUPPLIER_DOMAIN" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('CXML_SUPPLIER_DOMAIN')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbWurthPunchoutCxmlSupplierIdentity').'</td><td><input class="flat minwidth200" name="LMDBWURTHPUNCHOUT_CXML_SUPPLIER_IDENTITY" value="'.dol_escape_htmltag(LmdbWurthPunchoutConfig::getString('CXML_SUPPLIER_IDENTITY')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Mode').'</td><td>'.$form->selectarray('LMDBWURTHPUNCHOUT_CXML_MODE', $cxmlModeOptions, LmdbWurthPunchoutConfig::getString('CXML_MODE', 'production'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td></tr>';
print '</table>';

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

if (function_exists('ajax_combobox')) {
	foreach (array('LMDBWURTHPUNCHOUT_PROTOCOL', 'LMDBWURTHPUNCHOUT_FK_SOC', 'LMDBWURTHPUNCHOUT_OPEN_MODE', 'LMDBWURTHPUNCHOUT_PRICEUNIT_MODE', 'LMDBWURTHPUNCHOUT_OCI_METHOD', 'LMDBWURTHPUNCHOUT_CXML_MODE') as $htmlname) {
		ajax_combobox($htmlname);
	}
}

print '<br>';
print load_fiche_titre($langs->trans('LmdbWurthPunchoutUnitMapping'), '', '');
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
	$sql = 'SELECT rowid, wurth_unit, fk_unit, label FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap';
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	$sql .= ' ORDER BY wurth_unit ASC';
	$resql = $db->query($sql);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('LmdbWurthPunchoutUnitCode').'</th><th>'.$langs->trans('Unit').'</th><th>'.$langs->trans('Label').'</th><th></th></tr>';
	if ($resql && $db->num_rows($resql) > 0) {
		while ($obj = $db->fetch_object($resql)) {
			print '<tr class="oddeven">';
			print '<form method="POST" action="'.dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1).'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="save_unitmap">';
			print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
			print '<td><input class="flat maxwidth100" name="wurth_unit" value="'.dol_escape_htmltag($obj->wurth_unit).'"></td>';
			print '<td>'.$form->selectarray('fk_unit', $unitOptions, (int) $obj->fk_unit, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
			print '<td><input class="flat minwidth300" name="label" value="'.dol_escape_htmltag($obj->label).'"></td>';
			print '<td class="right"><input class="button button-save small" type="submit" value="'.$langs->trans('Save').'"> ';
			print '<a class="button button-delete small" href="'.dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1).'?action=delete_unitmap&rowid='.((int) $obj->rowid).'&token='.newToken().'">'.$langs->trans('Delete').'</a></td>';
			print '</form>';
			print '</tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '<tr class="liste_titre"><td colspan="4">'.$langs->trans('Add').'</td></tr>';
	print '<tr class="oddeven">';
	print '<form method="POST" action="'.dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1).'">';
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
