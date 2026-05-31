<?php
/* Copyright (C) 2026 */

/**
 * Send JSON and exit.
 *
 * @param mixed $data
 * @param int   $httpCode
 * @return never
 */
function dolimcp_json_response($data, $httpCode = 200)
{
	http_response_code($httpCode);
	header('Content-Type: application/json; charset=utf-8');
	print json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

/**
 * Send JSON-RPC 2.0 error.
 *
 * @param int         $code
 * @param string      $message
 * @param int|null    $id
 * @param int         $httpCode
 * @return never
 */
function dolimcp_jsonrpc_error($code, $message, $id = null, $httpCode = 200)
{
	dolimcp_json_response(array(
		'jsonrpc' => '2.0',
		'error' => array('code' => $code, 'message' => $message),
		'id' => $id,
	), $httpCode);
}

/**
 * CORS headers for MCP clients (e.g. Cursor).
 *
 * @return void
 */
function dolimcp_send_cors_headers()
{
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, Authorization, api_key, DOLIMCPKEY, DOLAPIENTITY, mcp-session-id, Mcp-Session-Id, Mcp-Protocol-Version');
}

/**
 * Read MCP session id from request headers.
 *
 * @return string
 */
function dolimcp_get_session_id_header()
{
	if (!empty($_SERVER['HTTP_MCP_SESSION_ID'])) {
		return trim($_SERVER['HTTP_MCP_SESSION_ID']);
	}
	return '';
}

/**
 * MCP-only token from request (not the official REST DOLAPIKEY).
 *
 * @return string
 */
function dolimcp_get_mcp_token_from_request()
{
	$token = '';
	if (!empty($_SERVER['HTTP_DOLIMCPKEY'])) {
		$token = $_SERVER['HTTP_DOLIMCPKEY'];
	}
	if ($token === '') {
		$headers = function_exists('getallheaders') ? getallheaders() : array();
		if (!empty($headers['DOLIMCPKEY'])) {
			$token = $headers['DOLIMCPKEY'];
		} elseif (!empty($headers['Authorization'])) {
			$token = preg_replace('/^Bearer\s+/i', '', $headers['Authorization']);
		}
	}
	return dol_string_nounprintableascii($token, 1);
}

/**
 * Generate a secure alphanumeric MCP token (48 chars).
 *
 * @return string|false
 */
function dolimcp_generate_plain_token()
{
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	$length = 48;
	$max = strlen($chars) - 1;
	$token = '';
	for ($i = 0; $i < $length; $i++) {
		$token .= $chars[random_int(0, $max)];
	}
	return $token;
}

/**
 * @param string $token
 * @return bool
 */
function dolimcp_is_valid_plain_token($token)
{
	return (bool) preg_match('/^[A-Za-z0-9]{32,64}$/', $token);
}

/**
 * Authenticate MCP token and set Dolibarr API user context.
 *
 * @param DoliDB $db
 * @param string $plainToken
 * @return User|false
 */
function dolimcp_authenticate_mcp_token($db, $plainToken)
{
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
	require_once __DIR__.'/../class/dolimcpusertoken.class.php';

	if (!dolimcp_is_valid_plain_token($plainToken)) {
		return false;
	}

	$tokenobj = new DolimcpUserToken($db);
	$userId = $tokenobj->findUserIdByPlainToken($plainToken);
	if ($userId <= 0) {
		return false;
	}

	$fuser = new User($db);
	if ($fuser->fetch($userId) <= 0 || $fuser->statut != User::STATUS_ENABLED) {
		return false;
	}

	$fuser->loadRights();
	dolimcp_set_api_user_context($fuser);

	return $fuser;
}

/**
 * Set global user for native API class calls (permissions enforced by Dolibarr).
 *
 * @param User $fuser
 * @return void
 */
function dolimcp_set_api_user_context($fuser)
{
	global $user;
	$user = $fuser;
	if (!class_exists('DolibarrApiAccess')) {
		require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
	}
	DolibarrApiAccess::$user = $fuser;
}

/**
 * Internal base URL for loopback REST calls (fallback when native classes unavailable).
 *
 * @return string
 */
function dolimcp_internal_api_base_url()
{
	return 'http://127.0.0.1/api/index.php';
}

/**
 * Get official REST API key for loopback (optional fallback). Does not use MCP token.
 *
 * @param DoliDB $db
 * @param int    $fk_user
 * @return string
 */
function dolimcp_get_user_rest_api_key_for_loopback($db, $fk_user)
{
	$userobj = new User($db);
	if ($userobj->fetch($fk_user) <= 0) {
		return '';
	}

	if (getDolGlobalString('API_IN_TOKEN_TABLE')) {
		$sql = "SELECT tokenstring FROM ".$db->prefix()."oauth_token";
		$sql .= " WHERE fk_user = ".((int) $fk_user);
		$sql .= " AND service = 'dolibarr_rest_api'";
		$sql .= " ORDER BY rowid DESC LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			$obj = $db->fetch_object($resql);
			return dolDecrypt($obj->tokenstring);
		}
		return '';
	}

	if (!empty($userobj->api_key)) {
		return dolDecrypt($userobj->api_key);
	}

	return '';
}
