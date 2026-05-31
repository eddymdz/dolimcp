<?php
/* Copyright (C) 2026
 *
 * DoliMCP — Model Context Protocol (Streamable HTTP) endpoint.
 * URL: /custom/dolimcp/mcp.php
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
if (!defined('NODEFAULTVALUES')) {
	define('NODEFAULTVALUES', '1');
}

if (!empty($_SERVER['HTTP_DOLAPIENTITY'])) {
	define('DOLENTITY', (int) $_SERVER['HTTP_DOLAPIENTITY']);
}

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once __DIR__.'/lib/dolimcp.lib.php';

dolimcp_send_cors_headers();

if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}
require_once __DIR__.'/class/mcpstreamablehandler.class.php';

if (empty($conf->dolimcp->enabled)) {
	dolimcp_json_response(array('error' => 'DoliMCP module is not enabled'), 503);
}

$handler = new DolimcpMcpStreamableHandler($db);

// Health check (GET without MCP session)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && dolimcp_get_session_id_header() === '') {
	$handler->handleHealth();
}

$handler->handle();
