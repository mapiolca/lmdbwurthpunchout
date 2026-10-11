<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Shared helpers for Punchout return endpoints.
 */

require_once __DIR__.'/../lib/lmdbwurthpunchout.lib.php';

/**
 * Store a returned basket without business import.
 *
 * @param LmdbWurthPunchoutSession $session    Session
 * @param string                   $protocol   Expected protocol
 * @param string                   $rawPayload Raw payload
 * @param array<int,array<string,mixed>> $lines Normalized lines
 * @param array<string,mixed>|null $basketPayload Structured basket metadata
 * @return void
 */
function lmdbwurthpunchoutStoreReturn($session, $protocol, $rawPayload, $lines, $basketPayload = null)
{
	global $langs;

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
}

/**
 * Render a link to the authenticated import confirmation.
 *
 * @param LmdbWurthPunchoutSession $session Session
 * @return void
 */
function lmdbwurthpunchoutRenderBasketStored($session)
{
	global $langs;

	$orderUrl = DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $session->fk_commandefourn;
	$message = $langs->trans('LmdbWurthPunchoutBasketStored');
	$importUrl = dol_buildpath('/lmdbwurthpunchout/public/import.php', 1).'?id='.(int) $session->id;

	llxHeader('', $langs->trans('LmdbWurthPunchoutReturnTitle'));
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutReturnTitle'), '', 'technic');
	print '<div class="ok">'.$message.'</div>';
	print '<p><a class="button button-save" href="'.dol_escape_htmltag($importUrl).'">'.$langs->trans('LmdbWurthPunchoutImportBasket').'</a></p>';
	print '<p>'.lmdbwurthpunchoutGetReturnToSupplierOrderButton($orderUrl, 'button button-save').'</p>';
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
