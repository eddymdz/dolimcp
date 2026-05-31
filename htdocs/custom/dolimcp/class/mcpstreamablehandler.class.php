<?php
/* Copyright (C) 2026 */

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/mcpsession.class.php';
require_once __DIR__.'/mcptoolregistry.class.php';
require_once __DIR__.'/mcpapibridge.class.php';
require_once __DIR__.'/../lib/dolimcp.lib.php';

/**
 * MCP Streamable HTTP transport (JSON-RPC 2.0) embedded in Dolibarr.
 */
class DolimcpMcpStreamableHandler
{
	/** @var DoliDB */
	private $db;

	/** @var DolimcpMcpSession */
	private $sessions;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		$dataRoot = !empty($conf->dolimcp->dir_temp) ? dirname($conf->dolimcp->dir_temp) : DOL_DATA_ROOT.'/dolimcp';
		$this->sessions = new DolimcpMcpSession($dataRoot);
	}

	/**
	 * @return void
	 */
	public function handleHealth()
	{
		global $conf;
		dolimcp_json_response(array(
			'status' => 'ok',
			'transport' => 'streamable-http',
			'protocol' => 'mcp',
			'endpoint' => DOL_URL_ROOT.'/custom/dolimcp/mcp.php',
			'auth_header' => 'DOLIMCPKEY',
			'module' => 'dolimcp',
			'enabled' => !empty($conf->dolimcp->enabled),
		));
	}

	/**
	 * @return void
	 */
	public function handle()
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$sessionId = dolimcp_get_session_id_header();

		if ($method === 'DELETE' && $sessionId) {
			$this->sessions->delete($sessionId);
			http_response_code(204);
			exit;
		}

		if ($method === 'GET' && $sessionId) {
			http_response_code(200);
			header('Content-Type: application/json');
			print '{}';
			exit;
		}

		if ($method !== 'POST') {
			dolimcp_json_response(array('error' => 'Method not allowed'), 405);
		}

		$raw = file_get_contents('php://input');
		$message = json_decode($raw, true);
		if (!is_array($message)) {
			dolimcp_jsonrpc_error(-32700, 'Parse error', null, 400);
		}

		$id = isset($message['id']) ? $message['id'] : null;
		$rpcMethod = isset($message['method']) ? $message['method'] : '';

		if (empty($sessionId) && $rpcMethod === 'initialize') {
			$this->handleInitialize($message, $id);
		}

		if (empty($sessionId)) {
			dolimcp_jsonrpc_error(-32000, 'Missing mcp-session-id header', $id, 400);
		}

		$session = $this->sessions->load($sessionId);
		if ($session === null) {
			dolimcp_jsonrpc_error(-32000, 'Invalid session', $id, 400);
		}

		$this->dispatch($rpcMethod, $message, $id, $session);
	}

	/**
	 * @param array<string,mixed> $message
	 * @param int|string|null     $id
	 * @return never
	 */
	private function handleInitialize($message, $id)
	{
		global $conf;

		if (empty($conf->dolimcp->enabled)) {
			dolimcp_jsonrpc_error(-32002, 'DoliMCP module is disabled', $id, 503);
		}

		$plainToken = dolimcp_get_mcp_token_from_request();
		if ($plainToken === '') {
			dolimcp_jsonrpc_error(-32001, 'Missing DOLIMCPKEY header (generate token in user MCP tab)', $id, 401);
		}

		$fuser = dolimcp_authenticate_mcp_token($this->db, $plainToken);
		if ($fuser === false) {
			dolimcp_jsonrpc_error(-32001, 'Invalid or revoked MCP token', $id, 401);
		}

		$sessionId = $this->sessions->createId();
		$this->sessions->save($sessionId, array(
			'user_id' => $fuser->id,
			'login' => $fuser->login,
			'created' => dol_now(),
		));

		header('Mcp-Session-Id: '.$sessionId);

		dolimcp_json_response(array(
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => array(
				'protocolVersion' => '2024-11-05',
				'capabilities' => array(
					'tools' => array('listChanged' => false),
				),
				'serverInfo' => array(
					'name' => 'dolibarr-dolimcp',
					'version' => '0.3.0',
				),
			),
		));
	}

	/**
	 * @param string              $rpcMethod
	 * @param array<string,mixed> $message
	 * @param int|string|null     $id
	 * @param array<string,mixed> $session
	 * @return never
	 */
	private function dispatch($rpcMethod, $message, $id, array $session)
	{
		if ($rpcMethod === 'notifications/initialized' || $rpcMethod === 'ping') {
			dolimcp_json_response(array('jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()));
		}

		if ($rpcMethod === 'tools/list') {
			dolimcp_json_response(array(
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => array('tools' => DolimcpMcpToolRegistry::listForMcp()),
			));
		}

		if ($rpcMethod === 'tools/call') {
			$params = isset($message['params']) && is_array($message['params']) ? $message['params'] : array();
			$toolName = isset($params['name']) ? $params['name'] : '';
			$arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : array();

			$fuser = new User($this->db);
			if ($fuser->fetch((int) $session['user_id']) <= 0) {
				dolimcp_jsonrpc_error(-32001, 'Session user not found', $id, 401);
			}
			$fuser->loadRights();
			dolimcp_set_api_user_context($fuser);

			$bridge = new DolimcpMcpApiBridge($this->db, $fuser);
			$result = $bridge->callTool($toolName, $arguments);

			dolimcp_json_response(array(
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => $result,
			));
		}

		dolimcp_jsonrpc_error(-32601, 'Method not found: '.$rpcMethod, $id);
	}
}
