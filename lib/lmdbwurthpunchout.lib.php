<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file        lib/lmdbwurthpunchout.lib.php
 * \ingroup     lmdbwurthpunchout
 * \brief       Common helpers for WURTH Punchout.
 */

/**
 * Prepare admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function lmdbwurthpunchoutAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('lmdbwurthpunchout@lmdbwurthpunchout', 'admin'));

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/lmdbwurthpunchout/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbwurthpunchout/admin/repmap.php', 1);
	$head[$h][1] = $langs->trans('LmdbWurthPunchoutRepMapping');
	$head[$h][2] = 'repmap';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbwurthpunchout/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbwurthpunchout/admin/sessions.php', 1);
	$head[$h][1] = $langs->trans('LmdbWurthPunchoutSessions');
	$head[$h][2] = 'sessions';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbwurthpunchout/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	return $head;
}

/**
 * Return link to Dolibarr module list.
 *
 * @return string
 */
function lmdbwurthpunchoutModuleListLink()
{
	global $langs;

	return '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbwurthpunchout').'">'.$langs->trans('BackToModuleList').'</a>';
}

/**
 * Render admin page header.
 *
 * @param string $activeTab Active tab code
 * @return void
 */
function lmdbwurthpunchoutPrintAdminHeader($activeTab)
{
	global $langs;

	$head = lmdbwurthpunchoutAdminPrepareHead();
	print load_fiche_titre($langs->trans('LmdbWurthPunchoutSetup'), lmdbwurthpunchoutModuleListLink(), 'title_setup');
	print dol_get_fiche_head($head, $activeTab, '', -1);

	$helpKeys = array(
		'settings' => 'LmdbWurthPunchoutSettingsPageHelp',
		'repmap' => 'LmdbWurthPunchoutRepMappingPageHelp',
		'compatibility' => 'LmdbWurthPunchoutCompatibilityPageHelp',
		'sessions' => 'LmdbWurthPunchoutSessionsPageHelp',
		'about' => 'LmdbWurthPunchoutAboutPageHelp',
	);
	if (!empty($helpKeys[$activeTab])) {
		print '<span class="opacitymedium">'.$langs->trans($helpKeys[$activeTab]).'</span><br><br>';
	}
}

/**
 * Print browser-side helper used to leave a Punchout modal/popup and reload the supplier order card.
 *
 * @param string $orderUrl    Supplier order URL for automatic return
 * @param int    $autoDelayMs Automatic return delay in milliseconds, negative to disable
 * @return void
 */
function lmdbwurthpunchoutPrintReturnToSupplierOrderJavascript($orderUrl = '', $autoDelayMs = -1)
{
	static $functionPrinted = false;

	if ($functionPrinted && ($orderUrl === '' || $autoDelayMs < 0)) {
		return;
	}

	print '<script>';
	if (!$functionPrinted) {
		print 'function lmdbWurthPunchoutReturnToSupplierOrder(url){';
		print 'if(window.parent&&window.parent!==window&&typeof window.parent.lmdbWurthPunchoutCloseModal==="function"){window.parent.lmdbWurthPunchoutCloseModal(url);return false;}';
		print 'if(window.opener&&!window.opener.closed){window.opener.location.href=url;window.close();return false;}';
		print 'if(window.top&&window.top!==window.self){window.top.location.href=url;return false;}';
		print 'window.location.href=url;return false;';
		print '}';
		$functionPrinted = true;
	}
	if ($orderUrl !== '' && $autoDelayMs >= 0) {
		print 'window.setTimeout(function(){lmdbWurthPunchoutReturnToSupplierOrder('.json_encode($orderUrl).');}, '.((int) $autoDelayMs).');';
	}
	print '</script>';
}

/**
 * Build a supplier order return button that works inside modal iframe, popup, or normal page.
 *
 * @param string $orderUrl Supplier order URL
 * @param string $cssClass Button CSS classes
 * @return string
 */
function lmdbwurthpunchoutGetReturnToSupplierOrderButton($orderUrl, $cssClass = 'button')
{
	global $langs;

	return '<a class="'.dol_escape_htmltag($cssClass).'" href="'.dol_escape_htmltag($orderUrl).'" onclick="return lmdbWurthPunchoutReturnToSupplierOrder(this.href);">'.$langs->trans('BackToSupplierOrder').'</a>';
}

/**
 * Check if a value exists in an associative array.
 *
 * @param array<string,mixed> $array Source array
 * @param string             $key   Key
 * @return string
 */
function lmdbwurthpunchoutArrayString($array, $key)
{
	return isset($array[$key]) ? (string) $array[$key] : '';
}
