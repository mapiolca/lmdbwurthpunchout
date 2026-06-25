<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * About page.
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
require_once __DIR__.'/../core/modules/modWurthPunchout.class.php';

$langs->loadLangs(array('admin', 'wurthpunchout@wurthpunchout'));

if (!$user->admin && !WurthPunchoutSecurity::canConfigure($user)) {
	accessforbidden();
}

$module = new modWurthPunchout($db);

llxHeader('', $langs->trans('About'));
wurthpunchoutPrintAdminHeader('about');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('About').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>WURTH Punchout</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($module->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>Pierre Ardoin &lt;developpeur@lesmetiersdubatiment.fr&gt;</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('WurthPunchoutModuleDescriptionLong').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Compatibility').'</td><td>Dolibarr 20+, PHP 8.0+</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('WurthPunchoutDependencies').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MainFeatures').'</td><td>'.$langs->trans('WurthPunchoutMainFeatures').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('UsefulLinks').'</td><td><a href="https://wiki.dolibarr.org/index.php/Module_development" target="_blank" rel="noopener">Dolibarr Module Development</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>GPL-3.0-or-later</td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
