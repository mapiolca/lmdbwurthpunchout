<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file        class/actions_wurthpunchout.class.php
 * \ingroup     wurthpunchout
 * \brief       Hooks for WURTH Punchout.
 */

require_once __DIR__.'/wurthpunchoutconfig.class.php';
require_once __DIR__.'/wurthpunchoutsecurity.class.php';

/**
 * Hook class.
 */
class ActionsWurthPunchout
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var array<string,mixed> */
	public $results = array();

	/** @var string */
	public $resprints;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add action button on supplier order card.
	 *
	 * @param array<string,mixed> $parameters Parameters
	 * @param object             $object     Current object
	 * @param string             $action     Current action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		$contexts = explode(':', (string) ($parameters['context'] ?? $hookmanager->context ?? ''));
		if (!in_array('ordersuppliercard', $contexts, true)) {
			return 0;
		}

		if (empty($conf->wurthpunchout->enabled) && !isModEnabled('wurthpunchout')) {
			return 0;
		}

		if (empty($object->id) || empty($object->socid)) {
			return 0;
		}

		if (!WurthPunchoutSecurity::canUsePunchout($user)) {
			return 0;
		}

		if (!defined('CommandeFournisseur::STATUS_DRAFT')) {
			return 0;
		}

		if ((int) $object->statut !== CommandeFournisseur::STATUS_DRAFT) {
			return 0;
		}

		if (!empty($object->entity) && (int) $object->entity !== (int) $conf->entity) {
			return 0;
		}

		if ((int) $object->socid !== WurthPunchoutConfig::getInt('FK_SOC')) {
			return 0;
		}

		if (!WurthPunchoutConfig::isComplete()) {
			return 0;
		}

		$langs->load('wurthpunchout@wurthpunchout');
		$url = dol_buildpath('/wurthpunchout/public/start.php', 1).'?id='.(int) $object->id.'&token='.urlencode(newToken());
		$mode = WurthPunchoutConfig::getOpenMode();

		if ($mode === 'popup') {
			print '<a class="butAction" href="'.$url.'" onclick="window.open(this.href, \'wurthpunchout\', \'width=1200,height=850,scrollbars=yes,resizable=yes\'); return false;">'.$langs->trans('WurthPunchoutButton').'</a>';
		} elseif ($mode === 'newtab') {
			print '<a class="butAction" target="_blank" rel="noopener" href="'.$url.'">'.$langs->trans('WurthPunchoutButton').'</a>';
		} else {
			print '<a class="butAction" href="'.$url.'">'.$langs->trans('WurthPunchoutButton').'</a>';
		}

		return 0;
	}
}
