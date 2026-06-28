<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Shared helpers for Punchout return endpoints.
 */

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutimporter.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutsecurity.class.php';

/**
 * Store a returned basket and import it immediately.
 *
 * @param LmdbWurthPunchoutSession $session    Session
 * @param string                   $protocol   Expected protocol
 * @param string                   $rawPayload Raw payload
 * @param array<int,array<string,mixed>> $lines Normalized lines
 * @param array<string,mixed>|null $basketPayload Structured basket metadata
 * @return array<string,mixed>
 */
function lmdbwurthpunchoutStoreAndImportReturn($session, $protocol, $rawPayload, $lines, $basketPayload = null)
{
	global $db, $langs;

	if ($session->protocol !== $protocol) {
		accessforbidden($langs->trans('LmdbWurthPunchoutProtocolMismatch'));
	}
	if ($session->isExpired()) {
		$session->setStatus(LmdbWurthPunchoutSession::STATUS_EXPIRED);
		accessforbidden($langs->trans('LmdbWurthPunchoutSessionExpired'));
	}
	if (!in_array($session->status, array(LmdbWurthPunchoutSession::STATUS_CREATED, LmdbWurthPunchoutSession::STATUS_SENT), true)) {
		accessforbidden($langs->trans('LmdbWurthPunchoutSessionAlreadyUsed'));
	}
	if (empty($lines)) {
		throw new RuntimeException($langs->trans('LmdbWurthPunchoutNoLineReturned'));
	}
	if ($session->storeReturn($rawPayload, $lines, $basketPayload) < 0) {
		throw new RuntimeException($session->error);
	}

	$importUser = lmdbwurthpunchoutLoadSessionUser($session);
	$importer = new LmdbWurthPunchoutImporter($db);

	try {
		return $importer->importStoredSession($session, $importUser);
	} catch (LmdbWurthPunchoutRepRulesRequiredException $exception) {
		if ($session->markImportBlocked($exception->getSummary(), $exception->getMessage()) < 0) {
			throw new RuntimeException($session->error ?: $exception->getMessage());
		}
		lmdbwurthpunchoutRenderRepRulesRequired($session, $exception);
	}
}

/**
 * Load the user that initiated the Punchout session.
 *
 * @param LmdbWurthPunchoutSession $session Session
 * @return User
 */
function lmdbwurthpunchoutLoadSessionUser($session)
{
	global $db, $langs;

	$importUser = new User($db);
	if ($importUser->fetch((int) $session->fk_user) <= 0) {
		throw new RuntimeException($langs->trans('LmdbWurthPunchoutImportUserNotFound'));
	}
	if (method_exists($importUser, 'getrights')) {
		$importUser->getrights();
	}
	if (!LmdbWurthPunchoutSecurity::canUsePunchout($importUser)) {
		throw new RuntimeException($langs->trans('LmdbWurthPunchoutImportUserNoRight'));
	}

	return $importUser;
}

/**
 * Render final return page and redirect the user back to the supplier order.
 *
 * @param LmdbWurthPunchoutSession $session Session
 * @param array<string,mixed>      $summary Import summary
 * @return void
 */
function lmdbwurthpunchoutRenderImportDone($session, $summary)
{
	global $langs;

	$orderUrl = DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn;
	$message = $langs->trans('LmdbWurthPunchoutImportSuccess', (int) $summary['lines_added'], (int) $summary['products_created'], (int) $summary['supplier_prices_updated']);
	if (!empty($summary['warnings']) && is_array($summary['warnings'])) {
		$message .= '<br>'.dol_escape_htmltag(implode(', ', $summary['warnings']));
	}

	llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
	print '<div class="ok">'.$message.'</div>';
	print '<p><a class="button button-save" href="'.$orderUrl.'">'.$langs->trans('BackToSupplierOrder').'</a></p>';
	print '<script>';
	print 'var u='.json_encode($orderUrl).';';
	print 'if (window.parent && window.parent !== window && typeof window.parent.lmdbWurthPunchoutCloseModal === "function") { window.parent.lmdbWurthPunchoutCloseModal(u); }';
	print 'else if (window.opener && !window.opener.closed) { window.opener.location.href = u; window.close(); }';
	print 'else if (window.top && window.top !== window.self) { window.top.location.href = u; }';
	print 'else { window.setTimeout(function(){ window.location.href = u; }, 800); }';
	print '</script>';
	llxFooter();
	exit;
}

/**
 * Render a cXML REP rules completion page.
 *
 * @param LmdbWurthPunchoutSession                  $session   Session
 * @param LmdbWurthPunchoutRepRulesRequiredException $exception Exception
 * @return void
 */
function lmdbwurthpunchoutRenderRepRulesRequired($session, $exception)
{
	global $langs;

	$setupUrl = dol_buildpath('/lmdbwurthpunchout/admin/repmap.php', 1).'?retry_session='.((int) $session->id);
	$importUrl = dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'?id='.(int) $session->id;
	$orderUrl = DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn;

	llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
	print '<div class="warning">'.$langs->trans('LmdbWurthPunchoutRepRulesRequiredIntro').'</div>';
	print '<p>'.dol_escape_htmltag($exception->getMessage()).'</p>';
	print '<p>';
	print '<a class="button button-edit" href="'.$setupUrl.'">'.$langs->trans('LmdbWurthPunchoutCompleteRepRules').'</a> ';
	print '<a class="button button-save" href="'.$importUrl.'">'.$langs->trans('LmdbWurthPunchoutRetryImport').'</a> ';
	print '<a class="button" href="'.$orderUrl.'">'.$langs->trans('BackToSupplierOrder').'</a>';
	print '</p>';
	llxFooter();
	exit;
}

/**
 * Render a return/import error.
 *
 * @param Exception $exception Exception
 * @return void
 */
function lmdbwurthpunchoutRenderReturnError($exception)
{
	global $langs;

	llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
	print '<div class="error">'.$langs->trans('LmdbWurthPunchoutReturnFailed').' '.dol_escape_htmltag($exception->getMessage()).'</div>';
	llxFooter();
	exit;
}
