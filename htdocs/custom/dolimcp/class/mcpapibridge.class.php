<?php
/* Copyright (C) 2026 */

require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/RestException.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Executes MCP tools using the authenticated Dolibarr user (native API + loopback fallback).
 */
class DolimcpMcpApiBridge
{
	/** @var DoliDB */
	private $db;

	/** @var User */
	private $user;

	/** @var string */
	private $loopbackApiKey;

	/**
	 * @param DoliDB $db
	 * @param User   $user Authenticated user (from MCP token)
	 */
	public function __construct($db, $user)
	{
		$this->db = $db;
		$this->user = $user;
		dolimcp_set_api_user_context($user);
		$this->loopbackApiKey = dolimcp_get_user_rest_api_key_for_loopback($db, $user->id);
	}

	/**
	 * Load REST API base classes (MCP entry does not bootstrap /api/index.php).
	 *
	 * @return void
	 */
	private function ensureDolibarrApiLoaded()
	{
		if (!class_exists('DolibarrApi')) {
			require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
		}
		if (!class_exists('DolibarrApiAccess')) {
			require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
		}
	}

	/**
	 * @param string              $toolName
	 * @param array<string,mixed> $arguments
	 * @return array{content:array<int,array<string,string>>,isError?:bool}
	 */
	public function callTool($toolName, array $arguments)
	{
		$def = DolimcpMcpToolRegistry::getTool($toolName);
		if ($def === null) {
			return $this->errorResult('Unknown tool: '.$toolName);
		}

		$route = $def['route'];
		$method = $route[0];
		$pathTemplate = $route[1];
		$payloadMode = isset($route[2]) ? $route[2] : null;
		$pathParamKeys = isset($route[3]) && is_array($route[3]) ? $route[3] : array();
		$extraQuery = isset($route[4]) && is_array($route[4]) ? $route[4] : array();

		$path = $pathTemplate;
		foreach ($pathParamKeys as $key) {
			if (!isset($arguments[$key])) {
				return $this->errorResult('Missing path parameter: '.$key);
			}
			$path = str_replace('{'.$key.'}', rawurlencode((string) $arguments[$key]), $path);
		}

		$query = $extraQuery;
		$body = null;

		if ($payloadMode === 'query') {
			foreach ($arguments as $k => $v) {
				if ($v === null || $v === '') {
					continue;
				}
				if (strpos($path, '{'.$k.'}') !== false) {
					continue;
				}
				$qkey = ($k === 'user_id') ? 'userid' : $k;
				$query[$qkey] = $v;
			}
		} elseif ($payloadMode === 'body') {
			$body = $arguments;
			foreach ($pathParamKeys as $key) {
				unset($body[$key]);
			}
		}

		try {
			$data = $this->invokeNative($method, $path, $query, $body);
			if ($data === null) {
				$data = $this->requestLoopback($method, $path, $query, $body);
			}
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
					),
				),
			);
		} catch (Throwable $e) {
			$message = $e->getMessage();
			if ($e instanceof RestException) {
				$message = 'Dolibarr API ('.$e->getCode().'): '.$e->getMessage();
			}
			return $this->errorResult($message.' ['.get_class($e).']');
		}
	}

	/**
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed|null Null if no native handler
	 */
	private function invokeNative($method, $path, array $query = array(), $body = null)
	{
		$this->ensureDolibarrApiLoaded();

		if (strpos($path, '/projects') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/api_projects.class.php';
			$api = new Projects();
			return $this->dispatchPath($api, $method, $path, $query, $body, array(
				'/projects/alltimespent' => 'alltimespent',
			));
		}
		if (strpos($path, '/tasks') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/api_tasks.class.php';
			$api = new Tasks();
			return $this->dispatchPath($api, $method, $path, $query, $body, array());
		}
		if (strpos($path, '/users') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/api_users.class.php';
			$api = new Users();
			return $this->dispatchPath($api, $method, $path, $query, $body, array(
				'/users/info' => 'info',
				'/users/groups' => 'indexGroups',
			));
		}
		return null;
	}

	/**
	 * @param object                   $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @param array<string,string>     $pathToMethod
	 * @return mixed
	 */
	private function dispatchPath($api, $method, $path, array $query, $body, array $pathToMethod)
	{
		foreach ($pathToMethod as $prefix => $methodName) {
			if ($path === $prefix || strpos($path, $prefix.'/') === 0) {
				return $this->callApiMethod($api, $methodName, $method, $query, $body);
			}
		}

		if ($method === 'GET' && preg_match('#^/projects/(\d+)$#', $path, $m)) {
			return $api->get((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/projects/ref/(.+)$#', $path, $m)) {
			return $api->getByRef(urldecode($m[1]));
		}
		if ($method === 'GET' && $path === '/projects') {
			return $api->index(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['thirdparty_ids'] ?? '',
				(int) ($query['category'] ?? 0),
				$query['sqlfilters'] ?? '',
				$query['properties'] ?? ''
			);
		}
		if ($method === 'POST' && $path === '/projects') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/projects/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/projects/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}
		if ($method === 'POST' && preg_match('#^/projects/(\d+)/validate$#', $path, $m)) {
			return $api->validate((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/projects/(\d+)/tasks$#', $path, $m)) {
			return $api->getTasks((int) $m[1], (int) ($query['includetimespent'] ?? 0));
		}
		if ($method === 'POST' && preg_match('#^/projects/(\d+)/tasks$#', $path, $m)) {
			return $api->postTask((int) $m[1], $body);
		}
		if ($method === 'PUT' && preg_match('#^/projects/(\d+)/tasks/(\d+)$#', $path, $m)) {
			return $api->putTask((int) $m[1], (int) $m[2], $body);
		}
		if ($method === 'GET' && preg_match('#^/projects/(\d+)/timespent$#', $path, $m)) {
			return $api->getTimespent((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/projects/(\d+)/roles$#', $path, $m)) {
			return $api->getRoles((int) $m[1], (int) ($query['userid'] ?? 0));
		}
		if ($method === 'GET' && preg_match('#^/projects/(\d+)/contacts$#', $path, $m)) {
			return $api->getContacts((int) $m[1]);
		}

		if ($method === 'GET' && $path === '/tasks') {
			return $api->index(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['sqlfilters'] ?? '',
				$query['properties'] ?? ''
			);
		}
		if ($method === 'GET' && preg_match('#^/tasks/(\d+)$#', $path, $m)) {
			return $api->get((int) $m[1], (int) ($query['includetimespent'] ?? 0));
		}
		if ($method === 'POST' && $path === '/tasks') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/tasks/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/tasks/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/tasks/(\d+)/timespent$#', $path, $m)) {
			return $api->getTimeSpent((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/tasks/(\d+)/getTimeSpent/(\d+)$#', $path, $m)) {
			return $api->getTimeSpentById((int) $m[1], (int) $m[2]);
		}
		if ($method === 'POST' && preg_match('#^/tasks/(\d+)/addtimespent$#', $path, $m)) {
			$productId = isset($body['product_id']) && $body['product_id'] !== '' ? (int) $body['product_id'] : null;
			return $api->addTimeSpent(
				(int) $m[1],
				$body['date'] ?? '',
				(int) ($body['duration'] ?? 0),
				$productId,
				(int) ($body['user_id'] ?? 0),
				$body['note'] ?? '',
				isset($body['progress']) ? (int) $body['progress'] : -1
			);
		}
		if ($method === 'PUT' && preg_match('#^/tasks/(\d+)/timespent/(\d+)$#', $path, $m)) {
			return $api->putTimeSpent(
				(int) $m[1],
				(int) $m[2],
				$body['date'] ?? '',
				(int) ($body['duration'] ?? 0),
				(int) ($body['user_id'] ?? 0),
				$body['note'] ?? '',
				isset($body['progress']) ? (int) $body['progress'] : -1
			);
		}
		if ($method === 'DELETE' && preg_match('#^/tasks/(\d+)/timespent/(\d+)$#', $path, $m)) {
			return $api->deleteTimeSpent((int) $m[1], (int) $m[2]);
		}
		if ($method === 'GET' && preg_match('#^/tasks/(\d+)/roles$#', $path, $m)) {
			return $api->getRoles((int) $m[1], (int) ($query['userid'] ?? 0));
		}

		if ($method === 'GET' && $path === '/users/info') {
			return $api->info();
		}
		if ($method === 'GET' && $path === '/users') {
			return $api->index(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['sqlfilters'] ?? ''
			);
		}
		if ($method === 'GET' && preg_match('#^/users/(\d+)$#', $path, $m)) {
			return $api->get((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/users/login/(.+)$#', $path, $m)) {
			return $api->getByLogin(urldecode($m[1]));
		}
		if ($method === 'POST' && $path === '/users') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/users/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/users/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/users/(\d+)/groups$#', $path, $m)) {
			return $api->getGroups((int) $m[1]);
		}
		if ($method === 'GET' && $path === '/users/groups') {
			return $api->listGroups((int) ($query['limit'] ?? 100), (int) ($query['page'] ?? 0));
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param object $api
	 * @param string $methodName
	 * @return mixed
	 */
	private function callApiMethod($api, $methodName, $httpMethod, array $query, $body)
	{
		if ($methodName === 'alltimespent') {
			return $api->allTimespent(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['sqlfilters'] ?? '',
				$query['project_ids'] ?? '',
				$query['task_ids'] ?? '',
				$query['user_ids'] ?? ''
			);
		}
		if ($methodName === 'info') {
			return $api->info();
		}
		if ($methodName === 'indexGroups') {
			return $api->listGroups((int) ($query['limit'] ?? 100), (int) ($query['page'] ?? 0));
		}
		throw new RestException(501, 'Unknown API method '.$methodName);
	}

	/**
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function requestLoopback($method, $path, array $query = array(), $body = null)
	{
		if ($this->loopbackApiKey === '') {
			throw new RestException(503, 'No REST API key available for this user. Enable REST API module.');
		}

		$url = dolimcp_internal_api_base_url().$path;
		if (!empty($query)) {
			$url .= '?'.http_build_query($query);
		}

		$ch = curl_init($url);
		if ($ch === false) {
			throw new Exception('Failed to init curl');
		}

		$headers = array(
			'DOLAPIKEY: '.$this->loopbackApiKey,
			'Accept: application/json',
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);

		if ($body !== null && $method !== 'GET') {
			$json = json_encode($body);
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		}

		$response = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$decoded = json_decode((string) $response, true);
		if ($httpCode >= 400) {
			$msg = is_array($decoded) && isset($decoded['error']['message']) ? $decoded['error']['message'] : (string) $response;
			throw new RestException($httpCode, $msg);
		}

		return $decoded !== null ? $decoded : $response;
	}

	/**
	 * @param string $message
	 * @return array{content:array<int,array<string,string>>,isError:bool}
	 */
	private function errorResult($message)
	{
		return array(
			'content' => array(
				array('type' => 'text', 'text' => json_encode(array('error' => $message))),
			),
			'isError' => true,
		);
	}
}
