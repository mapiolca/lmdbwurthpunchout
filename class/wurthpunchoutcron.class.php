<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/wurthpunchoutsession.class.php';

/**
 * Cron jobs for WURTH Punchout.
 */
class WurthPunchoutCron
{
	/** @var DoliDB */
	private $db;

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
	 * Expire sessions and purge old payloads.
	 *
	 * @return int
	 */
	public function runCleanup()
	{
		if (!isModEnabled('wurthpunchout')) {
			return 0;
		}

		$session = new WurthPunchoutSession($this->db);
		$expired = $session->expireOldSessions();
		if ($expired < 0) {
			dol_syslog('WurthPunchoutCron::runCleanup expire error: '.$session->error, LOG_ERR);
			return -1;
		}

		$purged = $session->purgeOldPayloads();
		if ($purged < 0) {
			dol_syslog('WurthPunchoutCron::runCleanup purge error: '.$session->error, LOG_ERR);
			return -1;
		}

		dol_syslog('WurthPunchoutCron::runCleanup expired='.$expired.' purged='.$purged, LOG_INFO);

		return 1;
	}
}
