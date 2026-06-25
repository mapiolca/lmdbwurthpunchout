<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file        lib/wurthpunchout.lib.php
 * \ingroup     wurthpunchout
 * \brief       Common helpers for WURTH Punchout.
 */

/**
 * Prepare admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function wurthpunchoutAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('wurthpunchout@wurthpunchout', 'admin'));

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/wurthpunchout/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/wurthpunchout/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/wurthpunchout/admin/sessions.php', 1);
	$head[$h][1] = $langs->trans('WurthPunchoutSessions');
	$head[$h][2] = 'sessions';
	$h++;

	$head[$h][0] = dol_buildpath('/wurthpunchout/admin/about.php', 1);
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
function wurthpunchoutModuleListLink()
{
	global $langs;

	return '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('wurthpunchout').'">'.$langs->trans('BackToModuleList').'</a>';
}

/**
 * Render admin page header.
 *
 * @param string $activeTab Active tab code
 * @return void
 */
function wurthpunchoutPrintAdminHeader($activeTab)
{
	global $langs;

	$head = wurthpunchoutAdminPrepareHead();
	print dol_get_fiche_head($head, $activeTab, $langs->trans('WurthPunchoutSetup'), -1, 'technic');
	print '<div class="underbanner clearboth right">'.wurthpunchoutModuleListLink().'</div>';
	print '<div class="clearboth"></div>';
}

/**
 * Check if a value exists in an associative array.
 *
 * @param array<string,mixed> $array Source array
 * @param string             $key   Key
 * @return string
 */
function wurthpunchoutArrayString($array, $key)
{
	return isset($array[$key]) ? (string) $array[$key] : '';
}
