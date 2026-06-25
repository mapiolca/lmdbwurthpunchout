<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Start WURTH Punchout session.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once __DIR__.'/../class/wurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/wurthpunchoutsecurity.class.php';
require_once __DIR__.'/../class/wurthpunchoutsession.class.php';
require_once __DIR__.'/../class/wurthpunchoutcxmlclient.class.php';

$langs->loadLangs(array('wurthpunchout@wurthpunchout', 'errors', 'orders'));

if (!isModEnabled('wurthpunchout')) {
	accessforbidden();
}
if (!WurthPunchoutSecurity::checkToken()) {
	accessforbidden('Bad token');
}
if (!WurthPunchoutSecurity::canUsePunchout($user)) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$order = new CommandeFournisseur($db);
if ($id <= 0 || $order->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$order->fetch_thirdparty();

if ((int) $order->entity !== (int) $conf->entity) {
	accessforbidden($langs->trans('WurthPunchoutWrongEntity'));
}
if ((int) $order->socid !== WurthPunchoutConfig::getInt('FK_SOC')) {
	accessforbidden($langs->trans('WurthPunchoutWrongSupplier'));
}
if ((int) $order->statut !== CommandeFournisseur::STATUS_DRAFT) {
	accessforbidden($langs->trans('WurthPunchoutOrderMustBeDraft'));
}

$protocol = WurthPunchoutConfig::getProtocol();
if (!WurthPunchoutConfig::isComplete($protocol)) {
	accessforbidden($langs->trans('WurthPunchoutIncompleteConfiguration'));
}

$rawToken = WurthPunchoutSecurity::generateToken();
$session = new WurthPunchoutSession($db);
if ($session->createFromOrder($order, $user, $protocol, $rawToken) <= 0) {
	setEventMessages($session->error, $session->errors, 'errors');
	header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $order->id);
	exit;
}

$returnUrl = WurthPunchoutConfig::getReturnUrl($protocol, $rawToken, (int) $conf->entity);
$session->setStatus(WurthPunchoutSession::STATUS_SENT);

try {
	if ($protocol === 'CXML') {
		$client = new WurthPunchoutCxmlClient();
		$targetUrl = $client->getStartPageUrl($returnUrl, $rawToken);
		renderLaunchPage($targetUrl, 'GET', array(), $order->id);
	} else {
		$params = array(
			'ORGANIZATION' => WurthPunchoutConfig::getString('OCI_ORGANIZATION'),
			'NAME' => WurthPunchoutConfig::getString('OCI_NAME'),
			'PASSWORD' => WurthPunchoutConfig::getSecret('OCI_PASSWORD'),
			'HOOK_URL' => $returnUrl,
		);
		$method = strtoupper(WurthPunchoutConfig::getString('OCI_METHOD', 'GET'));
		$targetUrl = WurthPunchoutConfig::getString('OCI_URL');
		renderLaunchPage($targetUrl, $method === 'POST' ? 'POST' : 'GET', $params, $order->id);
	}
} catch (Exception $e) {
	$session->setStatus(WurthPunchoutSession::STATUS_ERROR, $e->getMessage());
	setEventMessages($langs->trans('WurthPunchoutLaunchFailed').' '.$e->getMessage(), null, 'errors');
	header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $order->id);
	exit;
}

/**
 * Render launch page.
 *
 * @param string              $targetUrl Target URL
 * @param string              $method    GET or POST
 * @param array<string,string> $params   Parameters
 * @param int                 $orderId   Supplier order id
 * @return void
 */
function renderLaunchPage($targetUrl, $method, $params, $orderId)
{
	global $langs;

	$mode = WurthPunchoutConfig::getOpenMode();

	if ($method === 'GET' && $mode !== 'iframe') {
		$query = http_build_query($params);
		$url = $query !== '' ? $targetUrl.(strpos($targetUrl, '?') === false ? '?' : '&').$query : $targetUrl;
		header('Location: '.$url);
		exit;
	}

	llxHeader('', $langs->trans('WurthPunchoutButton'));
	print load_fiche_titre($langs->trans('WurthPunchoutButton'), '', 'technic');

	if ($mode === 'iframe' && $method === 'GET') {
		$query = http_build_query($params);
		$url = $query !== '' ? $targetUrl.(strpos($targetUrl, '?') === false ? '?' : '&').$query : $targetUrl;
		print '<iframe src="'.dol_escape_htmltag($url).'" class="centpercent" style="height:75vh;border:1px solid #ddd;"></iframe>';
		print '<div class="opacitymedium">'.$langs->trans('WurthPunchoutIframeFallback').'</div>';
		print '<p><a class="button" target="_blank" rel="noopener" href="'.dol_escape_htmltag($url).'">'.$langs->trans('OpenInNewWindow').'</a></p>';
		llxFooter();
		exit;
	}

	print '<form id="wurthpunchout_launch" method="'.dol_escape_htmltag($method).'" action="'.dol_escape_htmltag($targetUrl).'">';
	foreach ($params as $key => $value) {
		print '<input type="hidden" name="'.dol_escape_htmltag($key).'" value="'.dol_escape_htmltag($value).'">';
	}
	print '<noscript><input class="button" type="submit" value="'.$langs->trans('Continue').'"></noscript>';
	print '</form>';
	print '<script>document.getElementById("wurthpunchout_launch").submit();</script>';
	print '<p><a class="button" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $orderId.'">'.$langs->trans('BackToSupplierOrder').'</a></p>';

	llxFooter();
	exit;
}
