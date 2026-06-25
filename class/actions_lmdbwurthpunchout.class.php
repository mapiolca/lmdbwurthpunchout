<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file        class/actions_lmdbwurthpunchout.class.php
 * \ingroup     lmdbwurthpunchout
 * \brief       Hooks for WURTH Punchout.
 */

require_once __DIR__.'/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/lmdbwurthpunchoutsecurity.class.php';

/**
 * Hook class.
 */
class ActionsLmdbwurthpunchout
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

		if (empty($conf->lmdbwurthpunchout->enabled) && !isModEnabled('lmdbwurthpunchout')) {
			return 0;
		}

		if (empty($object->id) || empty($object->socid)) {
			return 0;
		}

		if (!LmdbWurthPunchoutSecurity::canUsePunchout($user)) {
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

		if ((int) $object->socid !== LmdbWurthPunchoutConfig::getInt('FK_SOC')) {
			return 0;
		}

		if (!LmdbWurthPunchoutConfig::isComplete()) {
			return 0;
		}

		$langs->load('lmdbwurthpunchout@lmdbwurthpunchout');
		$url = dol_buildpath('/lmdbwurthpunchout/public/start.php', 1).'?id='.(int) $object->id.'&token='.urlencode(newToken());
		$mode = LmdbWurthPunchoutConfig::getOpenMode();

		if ($mode === 'popup') {
			print '<a class="butAction" href="'.$url.'" onclick="window.open(this.href, \'lmdbwurthpunchout\', \'width=1200,height=850,scrollbars=yes,resizable=yes\'); return false;">'.$langs->trans('LmdbWurthPunchoutButton').'</a>';
		} elseif ($mode === 'newtab') {
			print '<a class="butAction" target="_blank" rel="noopener" href="'.$url.'">'.$langs->trans('LmdbWurthPunchoutButton').'</a>';
		} else {
			print '<a class="butAction" href="'.$url.'">'.$langs->trans('LmdbWurthPunchoutButton').'</a>';
		}

		return 0;
	}
}
