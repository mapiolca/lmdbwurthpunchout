<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Punchout sessions list.
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

require_once __DIR__.'/../lib/wurthpunchout.lib.php';
require_once __DIR__.'/../class/wurthpunchoutsecurity.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('admin', 'orders', 'companies', 'wurthpunchout@wurthpunchout'));

if (!$user->admin && !WurthPunchoutSecurity::canReadSessions($user)) {
	accessforbidden();
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$offset = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) {
	$sortfield = 't.datec';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}
$search_status = GETPOST('search_status', 'alphanohtml');
$search_protocol = GETPOST('search_protocol', 'alpha');
$form = new Form($db);

$param = '';
if ($search_status !== '') {
	$param .= '&search_status='.urlencode($search_status);
}
if ($search_protocol !== '') {
	$param .= '&search_protocol='.urlencode($search_protocol);
}

$sql = 'SELECT t.rowid, t.protocol, t.status, t.datec, t.date_return, t.date_import, t.import_count, t.error_message, c.ref AS order_ref, s.nom AS thirdparty_name, u.login AS user_login';
$sql .= ' FROM '.MAIN_DB_PREFIX.'wurthpunchout_session AS t';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur AS c ON c.rowid = t.fk_commandefourn';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = t.fk_soc';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = t.fk_user';
$sql .= ' WHERE t.entity = '.((int) $conf->entity);
if ($search_status !== '') {
	$sql .= " AND t.status = '".$db->escape($search_status)."'";
}
if ($search_protocol !== '') {
	$sql .= " AND t.protocol = '".$db->escape(strtoupper($search_protocol))."'";
}
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

llxHeader('', $langs->trans('WurthPunchoutSessions'));
wurthpunchoutPrintAdminHeader('sessions');

print_barre_liste($langs->trans('WurthPunchoutSessions'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, -1, 'technic', 0, '', '', $limit);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td></td>';
$protocolOptions = array('OCI' => 'OCI', 'CXML' => 'cXML');
$statusOptions = array(
	'created' => $langs->trans('WurthPunchoutStatusCreated'),
	'sent' => $langs->trans('WurthPunchoutStatusSent'),
	'returned' => $langs->trans('WurthPunchoutStatusReturned'),
	'imported' => $langs->trans('WurthPunchoutStatusImported'),
	'expired' => $langs->trans('WurthPunchoutStatusExpired'),
	'error' => $langs->trans('WurthPunchoutStatusError'),
);
print '<td>'.$form->selectarray('search_protocol', $protocolOptions, strtoupper($search_protocol), 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td>'.$form->selectarray('search_status', $statusOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td colspan="6" class="right"><input class="button small" type="submit" value="'.$langs->trans('Search').'"> <a class="button small" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Reset').'</a></td>';
print '</tr>';
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'c.ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Protocol', $_SERVER['PHP_SELF'], 't.protocol', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Company', $_SERVER['PHP_SELF'], 's.nom', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('User', $_SERVER['PHP_SELF'], 'u.login', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER['PHP_SELF'], 't.datec', $param, '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre('WurthPunchoutReturnedAt', $_SERVER['PHP_SELF'], 't.date_return', $param, '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre('WurthPunchoutImportedAt', $_SERVER['PHP_SELF'], 't.date_import', $param, '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre('Lines', $_SERVER['PHP_SELF'], 't.import_count', $param, '', '', $sortfield, $sortorder, 'right');
print '</tr>';

$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($obj->order_ref).'</td>';
	print '<td>'.dol_escape_htmltag($obj->protocol).'</td>';
	print '<td>'.wurthpunchoutStatusBadge($obj->status).'</td>';
	print '<td>'.dol_escape_htmltag($obj->thirdparty_name).'</td>';
	print '<td>'.dol_escape_htmltag($obj->user_login).'</td>';
	print '<td class="center">'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
	print '<td class="center">'.($obj->date_return ? dol_print_date($db->jdate($obj->date_return), 'dayhour') : '').'</td>';
	print '<td class="center">'.($obj->date_import ? dol_print_date($db->jdate($obj->date_import), 'dayhour') : '').'</td>';
	print '<td class="right">'.((int) $obj->import_count).'</td>';
	print '</tr>';
	$i++;
}
if ($num == 0) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';
print '</form>';

if (function_exists('ajax_combobox')) {
	ajax_combobox('search_protocol');
	ajax_combobox('search_status');
}

print dol_get_fiche_end();
llxFooter();

/**
 * Render status badge.
 *
 * @param string $status Status
 * @return string
 */
function wurthpunchoutStatusBadge($status)
{
	global $langs;

	$classes = array(
		'created' => 'badge-status0',
		'sent' => 'badge-status1',
		'returned' => 'badge-status3',
		'imported' => 'badge-status4',
		'expired' => 'badge-status8',
		'error' => 'badge-status8',
	);
	$class = $classes[$status] ?? 'badge-status0';
	return '<span class="badge '.$class.'">'.$langs->trans('WurthPunchoutStatus'.ucfirst($status)).'</span>';
}
