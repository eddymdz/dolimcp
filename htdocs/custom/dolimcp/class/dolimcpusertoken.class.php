<?php
/* Copyright (C) 2026 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/dolimcp.lib.php';

/**
 * MCP-only API token per user (not the official Dolibarr REST api_key).
 */
class DolimcpUserToken extends CommonObject
{
	/** @var string */
	public $element = 'dolimcp_token';

	/** @var string */
	public $table_element = 'dolimcp_token';

	/** @var int */
	public $id;

	/** @var int */
	public $entity;

	/** @var int */
	public $fk_user;

	/** @var string Encrypted token in database */
	public $token;

	/** @var int|string */
	public $datec;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch token row for a user in current entity.
	 *
	 * @param int $fk_user
	 * @param int $entity
	 * @return int 1 if found, 0 if not, <0 if error
	 */
	public function fetchByUser($fk_user, $entity = 0)
	{
		global $conf;

		if (empty($entity)) {
			$entity = $conf->entity;
		}

		$sql = "SELECT rowid, entity, fk_user, token, datec, tms";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $fk_user);
		$sql .= " AND entity = ".((int) $entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($this->db->num_rows($resql) < 1) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->id = $obj->rowid;
		$this->entity = $obj->entity;
		$this->fk_user = $obj->fk_user;
		$this->token = $obj->token;
		$this->datec = $this->db->jdate($obj->datec);

		return 1;
	}

	/**
	 * Find user from plain MCP token.
	 *
	 * @param string $plainToken
	 * @return int User id or 0
	 */
	public function findUserIdByPlainToken($plainToken)
	{
		global $conf;

		if (!dolimcp_is_valid_plain_token($plainToken)) {
			return 0;
		}

		$sql = "SELECT rowid, fk_user, token FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity = ".((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$stored = dolDecrypt($obj->token);
			if ($stored !== '' && hash_equals($stored, $plainToken)) {
				$this->id = $obj->rowid;
				$this->fk_user = $obj->fk_user;
				$this->token = $obj->token;
				return (int) $obj->fk_user;
			}
		}

		return 0;
	}

	/**
	 * Create or replace token for user. Returns plain token (shown once).
	 *
	 * @param int $fk_user
	 * @param int $entity
	 * @return string|false Plain token or false
	 */
	public function regenerateForUser($fk_user, $entity = 0)
	{
		global $conf;

		if (empty($entity)) {
			$entity = $conf->entity;
		}

		$plain = dolimcp_generate_plain_token();
		if ($plain === false) {
			$this->error = 'TokenGenerationFailed';
			return false;
		}

		$encrypted = dolEncrypt($plain, '', '', 'dolibarr');

		$this->db->begin();

		$sql = "DELETE FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $fk_user)." AND entity = ".((int) $entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}

		$sql = "INSERT INTO ".$this->db->prefix().$this->table_element." (entity, fk_user, token, datec)";
		$sql .= " VALUES (".((int) $entity).", ".((int) $fk_user).", '".$this->db->escape($encrypted)."', '".$this->db->idate(dol_now())."')";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}

		$this->db->commit();
		$this->fetchByUser($fk_user, $entity);

		return $plain;
	}

	/**
	 * @param int $fk_user
	 * @param int $entity
	 * @return bool
	 */
	public function revokeForUser($fk_user, $entity = 0)
	{
		global $conf;

		if (empty($entity)) {
			$entity = $conf->entity;
		}

		$sql = "DELETE FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $fk_user)." AND entity = ".((int) $entity);

		return (bool) $this->db->query($sql);
	}

	/**
	 * Mask token for display (never show full value except once after generation).
	 *
	 * @return string
	 */
	public function getMaskedLabel()
	{
		if (empty($this->token)) {
			return '';
		}
		return '******** (regenerate to obtain a new token)';
	}
}
