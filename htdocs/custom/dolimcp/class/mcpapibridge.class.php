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
		$extraBody = isset($route[5]) && is_array($route[5]) ? $route[5] : array();

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
			// Forced query params (e.g. services mode=2) always win.
			$query = array_merge($query, $extraQuery);
		} elseif ($payloadMode === 'body') {
			$body = array_merge($extraBody, $arguments);
			foreach ($pathParamKeys as $key) {
				unset($body[$key]);
			}
			// Forced body params (e.g. service type=1) always win.
			$body = array_merge($body, $extraBody);
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
			return $this->dispatchProjects(new Projects(), $method, $path, $query, $body);
		}
		if (strpos($path, '/tasks') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/api_tasks.class.php';
			return $this->dispatchTasks(new Tasks(), $method, $path, $query, $body);
		}
		if (strpos($path, '/users') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/api_users.class.php';
			return $this->dispatchUsers(new Users(), $method, $path, $query, $body);
		}
		if (strpos($path, '/thirdparties') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/api_thirdparties.class.php';
			return $this->dispatchThirdparties(new Thirdparties(), $method, $path, $query, $body);
		}
		if (strpos($path, '/contacts') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/api_contacts.class.php';
			return $this->dispatchContacts(new Contacts(), $method, $path, $query, $body);
		}
		if (strpos($path, '/invoices') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/api_invoices.class.php';
			return $this->dispatchInvoices(new Invoices(), $method, $path, $query, $body);
		}
		if (strpos($path, '/supplierinvoices') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/api_supplier_invoices.class.php';
			return $this->dispatchSupplierInvoices(new SupplierInvoices(), $method, $path, $query, $body);
		}
		if (strpos($path, '/products') === 0) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/api_products.class.php';
			return $this->dispatchProducts(new Products(), $method, $path, $query, $body);
		}
		return null;
	}

	/**
	 * @param Projects                 $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchProjects($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/projects/alltimespent') {
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

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Tasks                    $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchTasks($api, $method, $path, array $query, $body)
	{
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

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Users                    $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchUsers($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/users/info') {
			return $api->info();
		}
		if ($method === 'GET' && $path === '/users/groups') {
			return $api->listGroups((int) ($query['limit'] ?? 100), (int) ($query['page'] ?? 0));
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

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Thirdparties             $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchThirdparties($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/thirdparties') {
			return $this->callCompatible($api, 'index', array(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				(int) ($query['mode'] ?? 0),
				(int) ($query['category'] ?? 0),
				$query['sqlfilters'] ?? '',
				$query['properties'] ?? '',
				false,
			));
		}
		if ($method === 'GET' && preg_match('#^/thirdparties/email/(.+)$#', $path, $m)) {
			return $api->getByEmail(urldecode($m[1]));
		}
		if ($method === 'GET' && preg_match('#^/thirdparties/barcode/(.+)$#', $path, $m)) {
			return $api->getByBarcode(urldecode($m[1]));
		}
		if ($method === 'GET' && preg_match('#^/thirdparties/(\d+)/outstandinginvoices$#', $path, $m)) {
			return $api->getOutStandingInvoices((int) $m[1], $query['mode'] ?? 'customer');
		}
		if ($method === 'GET' && preg_match('#^/thirdparties/(\d+)$#', $path, $m)) {
			return $api->get((int) $m[1]);
		}
		if ($method === 'POST' && $path === '/thirdparties') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/thirdparties/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/thirdparties/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Contacts                 $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchContacts($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/contacts') {
			return $this->callCompatible($api, 'index', array(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['thirdparty_ids'] ?? '',
				(int) ($query['category'] ?? 0),
				$query['sqlfilters'] ?? '',
				(int) ($query['includecount'] ?? 0),
				(int) ($query['includeroles'] ?? 0),
				$query['properties'] ?? '',
				false,
			));
		}
		if ($method === 'GET' && preg_match('#^/contacts/email/(.+)$#', $path, $m)) {
			return $api->getByEmail(urldecode($m[1]));
		}
		if ($method === 'GET' && preg_match('#^/contacts/(\d+)$#', $path, $m)) {
			return $api->get(
				(int) $m[1],
				(int) ($query['includecount'] ?? 0),
				(int) ($query['includeroles'] ?? 0)
			);
		}
		if ($method === 'POST' && $path === '/contacts') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/contacts/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/contacts/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Invoices                 $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchInvoices($api, $method, $path, array $query, $body)
	{
		if ($method === 'POST' && preg_match('#^/invoices/createfromorder/(\d+)$#', $path, $m)) {
			return $api->createInvoiceFromOrder((int) $m[1]);
		}
		if ($method === 'GET' && $path === '/invoices') {
			return $this->callCompatible($api, 'index', array(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['thirdparty_ids'] ?? '',
				$query['status'] ?? '',
				$query['sqlfilters'] ?? '',
				$query['properties'] ?? '',
				false,
				(int) ($query['loadlinkedobjects'] ?? 0),
				$this->queryBool($query, 'withLines', true),
			));
		}
		if ($method === 'GET' && preg_match('#^/invoices/ref/(.+)$#', $path, $m)) {
			return $api->getByRef(urldecode($m[1]));
		}
		if ($method === 'GET' && preg_match('#^/invoices/(\d+)/lines$#', $path, $m)) {
			return $api->getLines((int) $m[1]);
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/lines$#', $path, $m)) {
			return $api->postLine((int) $m[1], $body);
		}
		if ($method === 'PUT' && preg_match('#^/invoices/(\d+)/lines/(\d+)$#', $path, $m)) {
			return $api->putLine((int) $m[1], (int) $m[2], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/invoices/(\d+)/lines/(\d+)$#', $path, $m)) {
			return $api->deleteLine((int) $m[1], (int) $m[2]);
		}
		if ($method === 'GET' && preg_match('#^/invoices/(\d+)/payments$#', $path, $m)) {
			return $api->getPayments((int) $m[1]);
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/payments$#', $path, $m)) {
			return $api->addPayment(
				(int) $m[1],
				$body['datepaye'] ?? '',
				(int) ($body['paymentid'] ?? 0),
				$body['closepaidinvoices'] ?? 'no',
				(int) ($body['accountid'] ?? 0),
				$body['num_payment'] ?? '',
				$body['comment'] ?? '',
				$body['chqemetteur'] ?? '',
				$body['chqbank'] ?? ''
			);
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/validate$#', $path, $m)) {
			return $api->validate(
				(int) $m[1],
				$body['force_number'] ?? '',
				(int) ($body['idwarehouse'] ?? 0),
				(int) ($body['notrigger'] ?? 0)
			);
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/settodraft$#', $path, $m)) {
			return $api->settodraft((int) $m[1], (int) ($body['idwarehouse'] ?? -1));
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/settopaid$#', $path, $m)) {
			return $api->settopaid(
				(int) $m[1],
				$body['close_code'] ?? '',
				$body['close_note'] ?? ''
			);
		}
		if ($method === 'POST' && preg_match('#^/invoices/(\d+)/settounpaid$#', $path, $m)) {
			return $api->settounpaid((int) $m[1]);
		}
		if ($method === 'GET' && preg_match('#^/invoices/(\d+)$#', $path, $m)) {
			return $this->callCompatible($api, 'get', array(
				(int) $m[1],
				(int) ($query['contact_list'] ?? 1),
				$query['properties'] ?? '',
				$this->queryBool($query, 'withLines', true),
			));
		}
		if ($method === 'POST' && $path === '/invoices') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/invoices/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/invoices/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param SupplierInvoices         $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchSupplierInvoices($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/supplierinvoices') {
			return $this->callCompatible($api, 'index', array(
				$query['sortfield'] ?? 't.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				$query['thirdparty_ids'] ?? '',
				$query['status'] ?? '',
				$query['sqlfilters'] ?? '',
				$query['properties'] ?? '',
				false,
			));
		}
		if ($method === 'GET' && preg_match('#^/supplierinvoices/(\d+)/lines$#', $path, $m)) {
			return $api->getLines((int) $m[1]);
		}
		if ($method === 'POST' && preg_match('#^/supplierinvoices/(\d+)/lines$#', $path, $m)) {
			return $api->postLine((int) $m[1], $body);
		}
		if ($method === 'PUT' && preg_match('#^/supplierinvoices/(\d+)/lines/(\d+)$#', $path, $m)) {
			return $api->putLine((int) $m[1], (int) $m[2], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/supplierinvoices/(\d+)/lines/(\d+)$#', $path, $m)) {
			return $api->deleteLine((int) $m[1], (int) $m[2]);
		}
		if ($method === 'GET' && preg_match('#^/supplierinvoices/(\d+)/payments$#', $path, $m)) {
			return $api->getPayments((int) $m[1]);
		}
		if ($method === 'POST' && preg_match('#^/supplierinvoices/(\d+)/payments$#', $path, $m)) {
			$paymentModeId = isset($body['payment_mode_id']) ? (int) $body['payment_mode_id'] : (int) ($body['paymentid'] ?? 0);
			$amount = array_key_exists('amount', (array) $body) ? $body['amount'] : null;
			return $this->callCompatible($api, 'addPayment', array(
				(int) $m[1],
				$body['datepaye'] ?? '',
				$paymentModeId,
				$body['closepaidinvoices'] ?? 'no',
				(int) ($body['accountid'] ?? 0),
				$body['num_payment'] ?? '',
				$body['comment'] ?? '',
				$body['chqemetteur'] ?? '',
				$body['chqbank'] ?? '',
				$amount,
			));
		}
		if ($method === 'POST' && preg_match('#^/supplierinvoices/(\d+)/validate$#', $path, $m)) {
			return $api->validate(
				(int) $m[1],
				(int) ($body['idwarehouse'] ?? 0),
				(int) ($body['notrigger'] ?? 0)
			);
		}
		if ($method === 'POST' && preg_match('#^/supplierinvoices/(\d+)/settodraft$#', $path, $m)) {
			return $api->settodraft(
				(int) $m[1],
				(int) ($body['idwarehouse'] ?? -1),
				(int) ($body['notrigger'] ?? 0)
			);
		}
		if ($method === 'GET' && preg_match('#^/supplierinvoices/(\d+)$#', $path, $m)) {
			return $api->get((int) $m[1]);
		}
		if ($method === 'POST' && $path === '/supplierinvoices') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/supplierinvoices/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/supplierinvoices/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param Products                 $api
	 * @param string                   $method
	 * @param string                   $path
	 * @param array<string,mixed>      $query
	 * @param array<string,mixed>|null $body
	 * @return mixed
	 */
	private function dispatchProducts($api, $method, $path, array $query, $body)
	{
		if ($method === 'GET' && $path === '/products') {
			return $this->callCompatible($api, 'index', array(
				$query['sortfield'] ?? 't.ref',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 100),
				(int) ($query['page'] ?? 0),
				(int) ($query['mode'] ?? 0),
				(int) ($query['category'] ?? 0),
				$query['sqlfilters'] ?? '',
				$this->queryBool($query, 'ids_only', false),
				(int) ($query['variant_filter'] ?? 0),
				false,
				(int) ($query['includestockdata'] ?? 0),
				$query['properties'] ?? '',
			));
		}
		if ($method === 'GET' && preg_match('#^/products/ref/(.+)$#', $path, $m)) {
			return $this->callCompatible($api, 'getByRef', array(
				urldecode($m[1]),
				(int) ($query['includestockdata'] ?? 0),
				$this->queryBool($query, 'includesubproducts', false),
				$this->queryBool($query, 'includeparentid', false),
				$this->queryBool($query, 'includetrans', false),
			));
		}
		if ($method === 'GET' && preg_match('#^/products/(\d+)/categories$#', $path, $m)) {
			return $api->getCategories(
				(int) $m[1],
				$query['sortfield'] ?? 's.rowid',
				$query['sortorder'] ?? 'ASC',
				(int) ($query['limit'] ?? 0),
				(int) ($query['page'] ?? 0)
			);
		}
		if ($method === 'GET' && preg_match('#^/products/(\d+)$#', $path, $m)) {
			return $this->callCompatible($api, 'get', array(
				(int) $m[1],
				(int) ($query['includestockdata'] ?? 0),
				$this->queryBool($query, 'includesubproducts', false),
				$this->queryBool($query, 'includeparentid', false),
				$this->queryBool($query, 'includetrans', false),
			));
		}
		if ($method === 'POST' && $path === '/products') {
			return $api->post($body);
		}
		if ($method === 'PUT' && preg_match('#^/products/(\d+)$#', $path, $m)) {
			return $api->put((int) $m[1], $body);
		}
		if ($method === 'DELETE' && preg_match('#^/products/(\d+)$#', $path, $m)) {
			return $api->delete((int) $m[1]);
		}

		throw new RestException(501, 'Native handler not implemented for '.$method.' '.$path);
	}

	/**
	 * @param array<string,mixed> $query
	 * @param string              $key
	 * @param bool                $default
	 * @return bool
	 */
	private function queryBool(array $query, $key, $default = false)
	{
		if (!array_key_exists($key, $query)) {
			return $default;
		}
		$value = $query[$key];
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return (bool) $value;
		}
		$value = strtolower(trim((string) $value));
		if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
			return true;
		}
		if (in_array($value, array('0', 'false', 'no', 'off'), true)) {
			return false;
		}
		return $default;
	}

	/**
	 * Call an API method using only as many arguments as the installed Dolibarr signature accepts.
	 *
	 * @param object            $api
	 * @param string            $methodName
	 * @param array<int,mixed>  $args
	 * @return mixed
	 */
	private function callCompatible($api, $methodName, array $args)
	{
		$ref = new ReflectionMethod($api, $methodName);
		$max = $ref->getNumberOfParameters();
		if (count($args) > $max) {
			$args = array_slice($args, 0, $max);
		}
		return $ref->invokeArgs($api, $args);
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
