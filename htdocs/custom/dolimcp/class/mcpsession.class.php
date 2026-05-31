<?php
/* Copyright (C) 2026 */

/**
 * File-based MCP session store (Streamable HTTP).
 */
class DolimcpMcpSession
{
	/** @var string */
	private $dir;

	/**
	 * @param string $dataRoot Dolibarr data root (documents)
	 */
	public function __construct($dataRoot)
	{
		$this->dir = rtrim($dataRoot, '/').'/mcp_sessions';
		if (!is_dir($this->dir)) {
			dol_mkdir($this->dir);
		}
	}

	/**
	 * @param string $sessionId
	 * @return array<string,mixed>|null
	 */
	public function load($sessionId)
	{
		$path = $this->path($sessionId);
		if (!is_readable($path)) {
			return null;
		}
		$raw = file_get_contents($path);
		if ($raw === false) {
			return null;
		}
		$data = json_decode($raw, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * @param string               $sessionId
	 * @param array<string,mixed>  $data
	 * @return void
	 */
	public function save($sessionId, array $data)
	{
		$data['updated'] = dol_now();
		file_put_contents($this->path($sessionId), json_encode($data));
	}

	/**
	 * @param string $sessionId
	 * @return void
	 */
	public function delete($sessionId)
	{
		$path = $this->path($sessionId);
		if (is_file($path)) {
			unlink($path);
		}
	}

	/**
	 * @return string
	 */
	public function createId()
	{
		return bin2hex(random_bytes(16));
	}

	/**
	 * @param string $sessionId
	 * @return string
	 */
	private function path($sessionId)
	{
		$safe = preg_replace('/[^a-f0-9]/', '', strtolower($sessionId));
		return $this->dir.'/'.$safe.'.json';
	}
}
