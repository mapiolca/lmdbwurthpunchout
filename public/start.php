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
require_once __DIR__.'/../class/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsession.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutcxmlclient.class.php';

$langs->loadLangs(array('lmdbwurthpunchout@lmdbwurthpunchout', 'errors', 'orders'));

if (!isModEnabled('lmdbwurthpunchout')) {
	accessforbidden();
}
if (!LmdbWurthPunchoutSecurity::checkToken()) {
	accessforbidden('Bad token');
}
if (!LmdbWurthPunchoutSecurity::canUsePunchout($user)) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$embed = GETPOSTINT('embed');
$external = GETPOSTINT('external');
$order = new CommandeFournisseur($db);
if ($id <= 0 || $order->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$order->fetch_thirdparty();

if ((int) $order->entity !== (int) $conf->entity) {
	accessforbidden($langs->trans('LmdbWurthPunchoutWrongEntity'));
}
if ((int) $order->socid !== LmdbWurthPunchoutConfig::getInt('FK_SOC')) {
	accessforbidden($langs->trans('LmdbWurthPunchoutWrongSupplier'));
}
if ((int) $order->statut !== CommandeFournisseur::STATUS_DRAFT) {
	accessforbidden($langs->trans('LmdbWurthPunchoutOrderMustBeDraft'));
}

$protocol = LmdbWurthPunchoutConfig::getProtocol();
if (!LmdbWurthPunchoutConfig::isComplete($protocol)) {
	accessforbidden($langs->trans('LmdbWurthPunchoutIncompleteConfiguration'));
}

$rawToken = LmdbWurthPunchoutSecurity::generateToken();
$session = new LmdbWurthPunchoutSession($db);
if ($session->createFromOrder($order, $user, $protocol, $rawToken) <= 0) {
	setEventMessages($session->error, $session->errors, 'errors');
	header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $order->id);
	exit;
}

$returnUrl = LmdbWurthPunchoutConfig::getReturnUrl($protocol, $rawToken, (int) $conf->entity);
$session->setStatus(LmdbWurthPunchoutSession::STATUS_SENT);

try {
	if ($protocol === 'CXML') {
		$client = new LmdbWurthPunchoutCxmlClient();
		$targetUrl = $client->getStartPageUrl($returnUrl, $rawToken);
		renderLaunchPage($targetUrl, 'GET', array(), $order->id, $embed, $external);
	} else {
		$params = array(
			'ORGANIZATION' => LmdbWurthPunchoutConfig::getString('OCI_ORGANIZATION'),
			'NAME' => LmdbWurthPunchoutConfig::getString('OCI_NAME'),
			'PASSWORD' => LmdbWurthPunchoutConfig::getSecret('OCI_PASSWORD'),
			'HOOK_URL' => $returnUrl,
		);
		$method = strtoupper(LmdbWurthPunchoutConfig::getString('OCI_METHOD', 'GET'));
		$targetUrl = LmdbWurthPunchoutConfig::getString('OCI_URL');
		renderLaunchPage($targetUrl, $method === 'POST' ? 'POST' : 'GET', $params, $order->id, $embed, $external);
	}
} catch (Exception $e) {
	$session->setStatus(LmdbWurthPunchoutSession::STATUS_ERROR, $e->getMessage());
	setEventMessages($langs->trans('LmdbWurthPunchoutLaunchFailed').' '.$e->getMessage(), null, 'errors');
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
 * @param int                 $embed     1 when launched in the order modal iframe
 * @param int                 $external  1 to force external opening
 * @return void
 */
function renderLaunchPage($targetUrl, $method, $params, $orderId, $embed = 0, $external = 0)
{
	global $langs;

	$mode = $external ? 'popup' : LmdbWurthPunchoutConfig::getOpenMode();

	if ($method === 'GET' && ($mode !== 'iframe' || $embed)) {
		$query = http_build_query($params);
		$url = $query !== '' ? $targetUrl.(strpos($targetUrl, '?') === false ? '?' : '&').$query : $targetUrl;
		header('Location: '.$url);
		exit;
	}

	llxHeader('', $langs->trans('LmdbWurthPunchoutButton'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutButton'), '', 'technic');

	if ($mode === 'iframe' && $method === 'GET') {
		$query = http_build_query($params);
		$url = $query !== '' ? $targetUrl.(strpos($targetUrl, '?') === false ? '?' : '&').$query : $targetUrl;
		print '<iframe src="'.dol_escape_htmltag($url).'" class="centpercent" style="height:75vh;border:1px solid #ddd;"></iframe>';
		print '<div class="opacitymedium">'.$langs->trans('LmdbWurthPunchoutIframeFallback').'</div>';
		print '<p><a class="button" target="_blank" rel="noopener" href="'.dol_escape_htmltag($url).'">'.$langs->trans('LmdbWurthPunchoutOpenExternal').'</a></p>';
		llxFooter();
		exit;
	}

	print '<form id="lmdbwurthpunchout_launch" method="'.dol_escape_htmltag($method).'" action="'.dol_escape_htmltag($targetUrl).'">';
	foreach ($params as $key => $value) {
		print '<input type="hidden" name="'.dol_escape_htmltag($key).'" value="'.dol_escape_htmltag($value).'">';
	}
	print '<noscript><input class="button" type="submit" value="'.$langs->trans('Continue').'"></noscript>';
	print '</form>';
	print '<script>document.getElementById("lmdbwurthpunchout_launch").submit();</script>';
	print '<p><a class="button" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $orderId.'">'.$langs->trans('BackToSupplierOrder').'</a></p>';

	llxFooter();
	exit;
}
