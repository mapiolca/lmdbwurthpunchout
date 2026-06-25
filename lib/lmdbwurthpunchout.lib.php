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
		'compatibility' => 'LmdbWurthPunchoutCompatibilityPageHelp',
		'sessions' => 'LmdbWurthPunchoutSessionsPageHelp',
		'about' => 'LmdbWurthPunchoutAboutPageHelp',
	);
	if (!empty($helpKeys[$activeTab])) {
		print '<span class="opacitymedium">'.$langs->trans($helpKeys[$activeTab]).'</span><br><br>';
	}
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
