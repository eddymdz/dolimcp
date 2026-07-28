<?php
/* Copyright (C) 2026 */

/**
 * MCP tool definitions and mapping to Dolibarr REST API routes.
 */
class DolimcpMcpToolRegistry
{
	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function getTools()
	{
		return array_merge(
			self::projectTools(),
			self::taskTools(),
			self::userTools(),
			self::thirdpartyTools(),
			self::contactTools(),
			self::invoiceTools(),
			self::supplierInvoiceTools(),
			self::serviceTools()
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function projectTools()
	{
		return array(
			'dolibarr_list_projects' => self::tool('List projects visible to the API user.', array(
				'type' => 'object',
				'properties' => array_merge(array(
					'thirdparty_ids' => array('type' => 'string', 'description' => 'Comma-separated third-party IDs to filter by.'),
					'category' => array('type' => 'integer'),
					'properties' => array('type' => 'string', 'description' => 'Comma-separated property names to return.'),
				), self::paginationProps()),
			), array('GET', '/projects', 'query')),
			'dolibarr_get_project' => self::tool('Get a project by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/projects/{id}', null, array('id'))),
			'dolibarr_get_project_by_ref' => self::tool('Get a project by reference.', array(
				'type' => 'object',
				'properties' => array('ref' => array('type' => 'string')),
				'required' => array('ref'),
			), array('GET', '/projects/ref/{ref}', null, array('ref'))),
			'dolibarr_create_project' => self::tool(
				'Create a Dolibarr project (draft status). REQUIRED parameter: title (string). OPTIONAL parameters: ref (default "auto"), socid (customer third-party ID), description, date_start, date_end, public (0|1), budget_amount, opp_amount, opp_percent, opp_status, usage_opportunity, usage_task, usage_bill_time, note_public, note_private, ref_ext, fk_project, array_options. Example arguments: {"title":"Website redesign","socid":12,"date_start":"2026-08-01","date_end":"2026-12-31","public":1,"budget_amount":15000}. After create, call dolibarr_validate_project with the returned ID.',
				array(
					'type' => 'object',
					'properties' => array(
						'title' => array(
							'type' => 'string',
							'description' => 'REQUIRED. Project label / title.',
						),
						'ref' => array(
							'type' => 'string',
							'description' => 'Project reference. Use "auto" for automatic numbering (this is the default when omitted).',
							'default' => 'auto',
						),
						'socid' => array(
							'type' => 'integer',
							'description' => 'Customer third-party ID to link (from dolibarr_list_thirdparties / dolibarr_create_thirdparty).',
						),
						'description' => array(
							'type' => 'string',
							'description' => 'Long project description.',
						),
						'date_start' => array(
							'type' => 'string',
							'description' => 'Start date as YYYY-MM-DD or Unix timestamp.',
						),
						'date_end' => array(
							'type' => 'string',
							'description' => 'End date as YYYY-MM-DD or Unix timestamp.',
						),
						'public' => array(
							'type' => 'integer',
							'description' => '0 = private project, 1 = public project.',
							'enum' => array(0, 1),
						),
						'budget_amount' => array(
							'type' => 'number',
							'description' => 'Budget amount.',
						),
						'opp_amount' => array(
							'type' => 'number',
							'description' => 'Opportunity amount (if opportunities enabled).',
						),
						'opp_percent' => array(
							'type' => 'number',
							'description' => 'Opportunity probability 0-100.',
						),
						'opp_status' => array(
							'type' => 'integer',
							'description' => 'Opportunity status ID (llx_c_lead_status).',
						),
						'fk_opp_status' => array(
							'type' => 'integer',
							'description' => 'Alias of opp_status.',
						),
						'usage_opportunity' => array(
							'type' => 'integer',
							'description' => '1 = treat as opportunity, 0 = no.',
							'enum' => array(0, 1),
						),
						'usage_task' => array(
							'type' => 'integer',
							'description' => '1 = enable tasks on this project, 0 = no.',
							'enum' => array(0, 1),
						),
						'usage_bill_time' => array(
							'type' => 'integer',
							'description' => '1 = time spent is billable, 0 = no.',
							'enum' => array(0, 1),
						),
						'usage_organize_event' => array(
							'type' => 'integer',
							'description' => '1 = enable event organization on this project, 0 = no.',
							'enum' => array(0, 1),
						),
						'note_public' => array(
							'type' => 'string',
							'description' => 'Public note.',
						),
						'note_private' => array(
							'type' => 'string',
							'description' => 'Private internal note.',
						),
						'ref_ext' => array(
							'type' => 'string',
							'description' => 'External reference / integration key.',
						),
						'fk_project' => array(
							'type' => 'integer',
							'description' => 'Parent project ID (sub-project).',
						),
						'dateo' => array(
							'type' => 'string',
							'description' => 'Alias of date_start (YYYY-MM-DD or Unix timestamp).',
						),
						'datee' => array(
							'type' => 'string',
							'description' => 'Alias of date_end (YYYY-MM-DD or Unix timestamp).',
						),
						'date_start_event' => array(
							'type' => 'string',
							'description' => 'Event start date (event organization module).',
						),
						'date_end_event' => array(
							'type' => 'string',
							'description' => 'Event end date (event organization module).',
						),
						'location' => array(
							'type' => 'string',
							'description' => 'Location / venue.',
						),
						'array_options' => array(
							'type' => 'object',
							'description' => 'Extrafields object, keys use options_ prefix.',
							'additionalProperties' => true,
						),
					),
					'required' => array('title'),
					'additionalProperties' => true,
				),
				array('POST', '/projects', 'body', null, null, array('ref' => 'auto'))
			),
			'dolibarr_update_project' => self::tool(
				'Update an existing project. REQUIRED: id. OPTIONAL (same as create): title, socid, date_start, date_end, public, budget_amount, description, note_public, note_private, etc. Pass only fields to change.',
				array(
					'type' => 'object',
					'properties' => array_merge(
						array(
							'id' => array(
								'type' => 'integer',
								'description' => 'REQUIRED. Project ID to update.',
							),
						),
						self::projectWriteProps(true)
					),
					'required' => array('id'),
					'additionalProperties' => true,
				),
				array('PUT', '/projects/{id}', 'body', array('id'))
			),
			'dolibarr_delete_project' => self::tool('Delete a project.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/projects/{id}', null, array('id'))),
			'dolibarr_validate_project' => self::tool('Validate a draft project.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('POST', '/projects/{id}/validate', null, array('id'))),
			'dolibarr_list_project_tasks' => self::tool('List tasks on a project.', array(
				'type' => 'object',
				'properties' => array_merge(array('project_id' => array('type' => 'integer'), 'includetimespent' => array('type' => 'integer')), self::paginationProps()),
				'required' => array('project_id'),
			), array('GET', '/projects/{project_id}/tasks', 'query', array('project_id'))),
			'dolibarr_create_project_task' => self::tool('Create a task on a project.', array(
				'type' => 'object',
				'properties' => array(
					'project_id' => array('type' => 'integer'),
					'ref' => array('type' => 'string'),
					'label' => array('type' => 'string'),
					'description' => array('type' => 'string'),
					'planned_workload' => array('type' => 'integer', 'description' => 'Planned workload in seconds.'),
					'date_start' => array('type' => 'string'),
					'date_end' => array('type' => 'string'),
				),
				'required' => array('project_id'),
			), array('POST', '/projects/{project_id}/tasks', 'body', array('project_id'))),
			'dolibarr_update_project_task' => self::tool('Update a task on a project.', array(
				'type' => 'object',
				'properties' => array('project_id' => array('type' => 'integer'), 'task_id' => array('type' => 'integer')),
				'required' => array('project_id', 'task_id'),
			), array('PUT', '/projects/{project_id}/tasks/{task_id}', 'body', array('project_id', 'task_id'))),
			'dolibarr_list_project_timespent' => self::tool('List time entries on a project.', array(
				'type' => 'object',
				'properties' => array('project_id' => array('type' => 'integer')),
				'required' => array('project_id'),
			), array('GET', '/projects/{project_id}/timespent', null, array('project_id'))),
			'dolibarr_list_all_timespent' => self::tool('List time entries across projects.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/projects/alltimespent', 'query')),
			'dolibarr_get_project_roles' => self::tool('Get project role assignments.', array(
				'type' => 'object',
				'properties' => array('project_id' => array('type' => 'integer'), 'user_id' => array('type' => 'integer')),
				'required' => array('project_id'),
			), array('GET', '/projects/{project_id}/roles', 'query', array('project_id'))),
			'dolibarr_get_project_contacts' => self::tool('List project contacts.', array(
				'type' => 'object',
				'properties' => array('project_id' => array('type' => 'integer')),
				'required' => array('project_id'),
			), array('GET', '/projects/{project_id}/contacts', null, array('project_id'))),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function taskTools()
	{
		return array(
			'dolibarr_list_tasks' => self::tool('List tasks.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/tasks', 'query')),
			'dolibarr_get_task' => self::tool('Get a task by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer'), 'includetimespent' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/tasks/{id}', 'query', array('id'))),
			'dolibarr_create_task' => self::tool('Create a task (requires fk_project).', array(
				'type' => 'object',
				'properties' => array(
					'fk_project' => array('type' => 'integer'),
					'ref' => array('type' => 'string'),
					'label' => array('type' => 'string'),
					'description' => array('type' => 'string'),
					'planned_workload' => array('type' => 'integer'),
				),
				'required' => array('fk_project'),
			), array('POST', '/tasks', 'body')),
			'dolibarr_update_task' => self::tool('Update a task.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('PUT', '/tasks/{id}', 'body', array('id'))),
			'dolibarr_delete_task' => self::tool('Delete a task.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/tasks/{id}', null, array('id'))),
			'dolibarr_list_task_timespent' => self::tool('List time on a task.', array(
				'type' => 'object',
				'properties' => array('task_id' => array('type' => 'integer')),
				'required' => array('task_id'),
			), array('GET', '/tasks/{task_id}/timespent', null, array('task_id'))),
			'dolibarr_get_task_timespent' => self::tool('Get one time entry.', array(
				'type' => 'object',
				'properties' => array('task_id' => array('type' => 'integer'), 'timespent_id' => array('type' => 'integer')),
				'required' => array('task_id', 'timespent_id'),
			), array('GET', '/tasks/{task_id}/getTimeSpent/{timespent_id}', null, array('task_id', 'timespent_id'))),
			'dolibarr_add_task_timespent' => self::tool('Log time on a task (duration in seconds).', array(
				'type' => 'object',
				'properties' => array(
					'task_id' => array('type' => 'integer'),
					'date' => array('type' => 'string'),
					'duration' => array('type' => 'integer'),
					'user_id' => array('type' => 'integer'),
					'note' => array('type' => 'string'),
				),
				'required' => array('task_id', 'date', 'duration'),
			), array('POST', '/tasks/{task_id}/addtimespent', 'body', array('task_id'))),
			'dolibarr_update_task_timespent' => self::tool('Update time on a task.', array(
				'type' => 'object',
				'properties' => array('task_id' => array('type' => 'integer'), 'timespent_id' => array('type' => 'integer')),
				'required' => array('task_id', 'timespent_id'),
			), array('PUT', '/tasks/{task_id}/timespent/{timespent_id}', 'body', array('task_id', 'timespent_id'))),
			'dolibarr_delete_task_timespent' => self::tool('Delete time on a task.', array(
				'type' => 'object',
				'properties' => array('task_id' => array('type' => 'integer'), 'timespent_id' => array('type' => 'integer')),
				'required' => array('task_id', 'timespent_id'),
			), array('DELETE', '/tasks/{task_id}/timespent/{timespent_id}', null, array('task_id', 'timespent_id'))),
			'dolibarr_get_task_roles' => self::tool('Get task role assignments.', array(
				'type' => 'object',
				'properties' => array('task_id' => array('type' => 'integer'), 'user_id' => array('type' => 'integer')),
				'required' => array('task_id'),
			), array('GET', '/tasks/{task_id}/roles', 'query', array('task_id'))),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function userTools()
	{
		return array(
			'dolibarr_list_users' => self::tool('List users.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/users', 'query')),
			'dolibarr_list_employees' => self::tool('List users with employee=1.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/users', 'query', null, array('sqlfilters' => '(t.employee:=:1)'))),
			'dolibarr_get_current_user' => self::tool('Current API user.', array('type' => 'object'), array('GET', '/users/info')),
			'dolibarr_get_user' => self::tool('Get user by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/users/{id}', null, array('id'))),
			'dolibarr_get_user_by_login' => self::tool('Get user by login.', array(
				'type' => 'object',
				'properties' => array('login' => array('type' => 'string')),
				'required' => array('login'),
			), array('GET', '/users/login/{login}', null, array('login'))),
			'dolibarr_create_user' => self::tool('Create a user.', array('type' => 'object'), array('POST', '/users', 'body')),
			'dolibarr_update_user' => self::tool('Update a user.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('PUT', '/users/{id}', 'body', array('id'))),
			'dolibarr_delete_user' => self::tool('Delete a user.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/users/{id}', null, array('id'))),
			'dolibarr_list_user_groups' => self::tool('Groups for a user.', array(
				'type' => 'object',
				'properties' => array('user_id' => array('type' => 'integer')),
				'required' => array('user_id'),
			), array('GET', '/users/{user_id}/groups', null, array('user_id'))),
			'dolibarr_list_groups' => self::tool('List all user groups.', array(
				'type' => 'object',
				'properties' => array('limit' => array('type' => 'integer'), 'page' => array('type' => 'integer')),
			), array('GET', '/users/groups', 'query')),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function thirdpartyTools()
	{
		$thirdpartyProps = array(
			'name' => array('type' => 'string', 'description' => 'Company / third-party name (required to create).'),
			'name_alias' => array('type' => 'string'),
			'email' => array('type' => 'string'),
			'phone' => array('type' => 'string'),
			'fax' => array('type' => 'string'),
			'url' => array('type' => 'string', 'description' => 'Website URL.'),
			'address' => array('type' => 'string'),
			'zip' => array('type' => 'string'),
			'town' => array('type' => 'string'),
			'country_id' => array('type' => 'integer'),
			'country_code' => array('type' => 'string', 'description' => 'ISO country code (e.g. ES, FR, US) if country_id unknown.'),
			'state_id' => array('type' => 'integer'),
			'client' => array('type' => 'integer', 'description' => '0=not customer, 1=customer, 2=prospect, 3=customer+prospect.'),
			'fournisseur' => array('type' => 'integer', 'description' => '0=not supplier, 1=supplier.'),
			'code_client' => array('type' => 'string', 'description' => 'Customer code, or "auto".'),
			'code_fournisseur' => array('type' => 'string', 'description' => 'Supplier code, or "auto".'),
			'tva_intra' => array('type' => 'string', 'description' => 'VAT / tax ID.'),
			'idprof1' => array('type' => 'string'),
			'idprof2' => array('type' => 'string'),
			'note_public' => array('type' => 'string'),
			'note_private' => array('type' => 'string'),
			'status' => array('type' => 'integer', 'description' => '0=closed, 1=open.'),
		);

		return array(
			'dolibarr_list_thirdparties' => self::tool(
				'List third parties (companies). mode: 0=all, 1=customers, 2=prospects, 3=neither customer nor prospect, 4=suppliers.',
				array(
					'type' => 'object',
					'properties' => array_merge(array(
						'mode' => array('type' => 'integer', 'description' => '0=all, 1=customers, 2=prospects, 3=other, 4=suppliers.'),
						'category' => array('type' => 'integer'),
						'properties' => array('type' => 'string'),
					), self::paginationProps()),
				),
				array('GET', '/thirdparties', 'query')
			),
			'dolibarr_list_customers' => self::tool('List customers (third parties with client flag).', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/thirdparties', 'query', null, array('mode' => 1))),
			'dolibarr_list_suppliers' => self::tool('List suppliers / providers (third parties with fournisseur=1).', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/thirdparties', 'query', null, array('mode' => 4))),
			'dolibarr_get_thirdparty' => self::tool('Get a third party by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/thirdparties/{id}', null, array('id'))),
			'dolibarr_get_thirdparty_by_email' => self::tool('Get a third party by email.', array(
				'type' => 'object',
				'properties' => array('email' => array('type' => 'string')),
				'required' => array('email'),
			), array('GET', '/thirdparties/email/{email}', null, array('email'))),
			'dolibarr_get_thirdparty_by_barcode' => self::tool('Get a third party by barcode.', array(
				'type' => 'object',
				'properties' => array('barcode' => array('type' => 'string')),
				'required' => array('barcode'),
			), array('GET', '/thirdparties/barcode/{barcode}', null, array('barcode'))),
			'dolibarr_create_thirdparty' => self::tool(
				'Register a third party (company, customer, supplier/provider). Set client=1 for customers, fournisseur=1 for suppliers; both can be set. Required: name.',
				array(
					'type' => 'object',
					'properties' => $thirdpartyProps,
					'required' => array('name'),
				),
				array('POST', '/thirdparties', 'body')
			),
			'dolibarr_update_thirdparty' => self::tool('Update a third party.', array(
				'type' => 'object',
				'properties' => array_merge(array('id' => array('type' => 'integer')), $thirdpartyProps),
				'required' => array('id'),
			), array('PUT', '/thirdparties/{id}', 'body', array('id'))),
			'dolibarr_delete_thirdparty' => self::tool('Delete a third party.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/thirdparties/{id}', null, array('id'))),
			'dolibarr_get_thirdparty_outstanding_invoices' => self::tool('Get outstanding invoice amounts for a third party.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'mode' => array('type' => 'string', 'description' => '"customer" or "supplier".'),
				),
				'required' => array('id'),
			), array('GET', '/thirdparties/{id}/outstandinginvoices', 'query', array('id'))),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function contactTools()
	{
		return array(
			'dolibarr_list_contacts' => self::tool('List contact persons (people linked to third parties).', array(
				'type' => 'object',
				'properties' => array_merge(array(
					'thirdparty_ids' => array('type' => 'string', 'description' => 'Comma-separated third-party IDs.'),
					'category' => array('type' => 'integer'),
				), self::paginationProps()),
			), array('GET', '/contacts', 'query')),
			'dolibarr_get_contact' => self::tool('Get a contact by ID.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'includecount' => array('type' => 'integer'),
					'includeroles' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('GET', '/contacts/{id}', 'query', array('id'))),
			'dolibarr_get_contact_by_email' => self::tool('Get a contact by email.', array(
				'type' => 'object',
				'properties' => array('email' => array('type' => 'string')),
				'required' => array('email'),
			), array('GET', '/contacts/email/{email}', null, array('email'))),
			'dolibarr_create_contact' => self::tool(
				'Create a contact person. Required: lastname. Link to a company with socid (third-party ID).',
				array(
					'type' => 'object',
					'properties' => array(
						'lastname' => array('type' => 'string'),
						'firstname' => array('type' => 'string'),
						'socid' => array('type' => 'integer', 'description' => 'Third-party ID to link this contact to.'),
						'email' => array('type' => 'string'),
						'phone_pro' => array('type' => 'string'),
						'phone_mobile' => array('type' => 'string'),
						'address' => array('type' => 'string'),
						'zip' => array('type' => 'string'),
						'town' => array('type' => 'string'),
						'country_id' => array('type' => 'integer'),
						'poste' => array('type' => 'string', 'description' => 'Job title.'),
						'note_public' => array('type' => 'string'),
						'note_private' => array('type' => 'string'),
					),
					'required' => array('lastname'),
				),
				array('POST', '/contacts', 'body')
			),
			'dolibarr_update_contact' => self::tool('Update a contact person.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'lastname' => array('type' => 'string'),
					'firstname' => array('type' => 'string'),
					'socid' => array('type' => 'integer'),
					'email' => array('type' => 'string'),
					'phone_pro' => array('type' => 'string'),
					'phone_mobile' => array('type' => 'string'),
					'poste' => array('type' => 'string'),
				),
				'required' => array('id'),
			), array('PUT', '/contacts/{id}', 'body', array('id'))),
			'dolibarr_delete_contact' => self::tool('Delete a contact person.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/contacts/{id}', null, array('id'))),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function invoiceTools()
	{
		$lineProps = array(
			'desc' => array('type' => 'string', 'description' => 'Line description.'),
			'subprice' => array('type' => 'number', 'description' => 'Unit price HT.'),
			'qty' => array('type' => 'number'),
			'tva_tx' => array('type' => 'number', 'description' => 'VAT rate percent.'),
			'fk_product' => array('type' => 'integer', 'description' => 'Product/service ID (optional).'),
			'product_type' => array('type' => 'integer', 'description' => '0=product, 1=service.'),
			'remise_percent' => array('type' => 'number'),
			'date_start' => array('type' => 'string'),
			'date_end' => array('type' => 'string'),
			'label' => array('type' => 'string'),
		);

		return array(
			'dolibarr_list_invoices' => self::tool(
				'List customer invoices. status filter: draft, unpaid, paid, cancelled.',
				array(
					'type' => 'object',
					'properties' => array_merge(array(
						'thirdparty_ids' => array('type' => 'string', 'description' => 'Comma-separated customer IDs.'),
						'status' => array('type' => 'string', 'description' => 'draft | unpaid | paid | cancelled'),
						'properties' => array('type' => 'string'),
						'withLines' => array('type' => 'boolean'),
					), self::paginationProps()),
				),
				array('GET', '/invoices', 'query')
			),
			'dolibarr_get_invoice' => self::tool('Get a customer invoice by ID (includes lines by default).', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'contact_list' => array('type' => 'integer'),
					'properties' => array('type' => 'string'),
					'withLines' => array('type' => 'boolean'),
				),
				'required' => array('id'),
			), array('GET', '/invoices/{id}', 'query', array('id'))),
			'dolibarr_get_invoice_by_ref' => self::tool('Get a customer invoice by reference.', array(
				'type' => 'object',
				'properties' => array('ref' => array('type' => 'string')),
				'required' => array('ref'),
			), array('GET', '/invoices/ref/{ref}', null, array('ref'))),
			'dolibarr_create_invoice' => self::tool(
				'Create a customer invoice. Required: socid (third-party ID). Optional: date, type (0=standard, 2=credit note, 3=deposit), note_public, note_private, lines[{desc,subprice,qty,tva_tx,fk_product,...}], fk_project, cond_reglement_id, mode_reglement_id.',
				array(
					'type' => 'object',
					'properties' => array(
						'socid' => array('type' => 'integer', 'description' => 'Customer third-party ID.'),
						'date' => array('type' => 'string', 'description' => 'Invoice date (YYYY-MM-DD or Unix timestamp).'),
						'type' => array('type' => 'integer', 'description' => '0=standard, 2=credit note, 3=deposit.'),
						'ref_ext' => array('type' => 'string'),
						'fk_project' => array('type' => 'integer'),
						'note_public' => array('type' => 'string'),
						'note_private' => array('type' => 'string'),
						'cond_reglement_id' => array('type' => 'integer'),
						'mode_reglement_id' => array('type' => 'integer'),
						'lines' => array(
							'type' => 'array',
							'description' => 'Invoice lines to create with the invoice.',
							'items' => array('type' => 'object', 'properties' => $lineProps),
						),
					),
					'required' => array('socid'),
				),
				array('POST', '/invoices', 'body')
			),
			'dolibarr_update_invoice' => self::tool('Update a customer invoice (draft only for most fields).', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'socid' => array('type' => 'integer'),
					'date' => array('type' => 'string'),
					'note_public' => array('type' => 'string'),
					'note_private' => array('type' => 'string'),
					'fk_project' => array('type' => 'integer'),
					'cond_reglement_id' => array('type' => 'integer'),
					'mode_reglement_id' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('PUT', '/invoices/{id}', 'body', array('id'))),
			'dolibarr_delete_invoice' => self::tool('Delete a customer invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/invoices/{id}', null, array('id'))),
			'dolibarr_validate_invoice' => self::tool('Validate a draft customer invoice (assigns final ref).', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'force_number' => array('type' => 'string'),
					'idwarehouse' => array('type' => 'integer'),
					'notrigger' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('POST', '/invoices/{id}/validate', 'body', array('id'))),
			'dolibarr_settodraft_invoice' => self::tool('Set a customer invoice back to draft.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'idwarehouse' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('POST', '/invoices/{id}/settodraft', 'body', array('id'))),
			'dolibarr_settopaid_invoice' => self::tool('Mark a customer invoice as paid (without creating a payment record).', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'close_code' => array('type' => 'string'),
					'close_note' => array('type' => 'string'),
				),
				'required' => array('id'),
			), array('POST', '/invoices/{id}/settopaid', 'body', array('id'))),
			'dolibarr_settounpaid_invoice' => self::tool('Mark a customer invoice as unpaid.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('POST', '/invoices/{id}/settounpaid', null, array('id'))),
			'dolibarr_get_invoice_lines' => self::tool('List lines of a customer invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/invoices/{id}/lines', null, array('id'))),
			'dolibarr_add_invoice_line' => self::tool('Add a line to a customer invoice.', array(
				'type' => 'object',
				'properties' => array_merge(array('id' => array('type' => 'integer')), $lineProps),
				'required' => array('id'),
			), array('POST', '/invoices/{id}/lines', 'body', array('id'))),
			'dolibarr_update_invoice_line' => self::tool('Update a line on a customer invoice.', array(
				'type' => 'object',
				'properties' => array_merge(array(
					'id' => array('type' => 'integer'),
					'lineid' => array('type' => 'integer'),
				), $lineProps),
				'required' => array('id', 'lineid'),
			), array('PUT', '/invoices/{id}/lines/{lineid}', 'body', array('id', 'lineid'))),
			'dolibarr_delete_invoice_line' => self::tool('Delete a line from a customer invoice.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'lineid' => array('type' => 'integer'),
				),
				'required' => array('id', 'lineid'),
			), array('DELETE', '/invoices/{id}/lines/{lineid}', null, array('id', 'lineid'))),
			'dolibarr_get_invoice_payments' => self::tool('List payments recorded on a customer invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/invoices/{id}/payments', null, array('id'))),
			'dolibarr_add_invoice_payment' => self::tool(
				'Register a payment on a customer invoice. Required: id, datepaye, paymentid (payment mode), closepaidinvoices (yes|no), accountid (bank account).',
				array(
					'type' => 'object',
					'properties' => array(
						'id' => array('type' => 'integer', 'description' => 'Invoice ID.'),
						'datepaye' => array('type' => 'string', 'description' => 'Payment date.'),
						'paymentid' => array('type' => 'integer', 'description' => 'Payment mode ID (llx_c_paiement).'),
						'closepaidinvoices' => array('type' => 'string', 'description' => '"yes" or "no".'),
						'accountid' => array('type' => 'integer', 'description' => 'Bank account ID.'),
						'num_payment' => array('type' => 'string'),
						'comment' => array('type' => 'string'),
						'chqemetteur' => array('type' => 'string'),
						'chqbank' => array('type' => 'string'),
					),
					'required' => array('id', 'datepaye', 'paymentid', 'closepaidinvoices', 'accountid'),
				),
				array('POST', '/invoices/{id}/payments', 'body', array('id'))
			),
			'dolibarr_create_invoice_from_order' => self::tool('Create a customer invoice from an existing order.', array(
				'type' => 'object',
				'properties' => array('orderid' => array('type' => 'integer')),
				'required' => array('orderid'),
			), array('POST', '/invoices/createfromorder/{orderid}', null, array('orderid'))),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function supplierInvoiceTools()
	{
		$lineProps = array(
			'desc' => array('type' => 'string'),
			'description' => array('type' => 'string'),
			'pu_ht' => array('type' => 'number', 'description' => 'Unit price HT.'),
			'subprice' => array('type' => 'number'),
			'qty' => array('type' => 'number'),
			'tva_tx' => array('type' => 'number'),
			'fk_product' => array('type' => 'integer'),
			'product_type' => array('type' => 'integer'),
			'remise_percent' => array('type' => 'number'),
		);

		return array(
			'dolibarr_list_supplier_invoices' => self::tool(
				'List supplier / vendor invoices. status: draft, unpaid, paid, cancelled.',
				array(
					'type' => 'object',
					'properties' => array_merge(array(
						'thirdparty_ids' => array('type' => 'string'),
						'status' => array('type' => 'string'),
						'properties' => array('type' => 'string'),
					), self::paginationProps()),
				),
				array('GET', '/supplierinvoices', 'query')
			),
			'dolibarr_get_supplier_invoice' => self::tool('Get a supplier invoice by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/supplierinvoices/{id}', null, array('id'))),
			'dolibarr_create_supplier_invoice' => self::tool(
				'Create a supplier invoice. Required: socid (supplier third-party ID). Recommended: ref_supplier, date. Optional lines[{description,pu_ht,qty,tva_tx,fk_product}].',
				array(
					'type' => 'object',
					'properties' => array(
						'socid' => array('type' => 'integer', 'description' => 'Supplier third-party ID.'),
						'ref' => array('type' => 'string', 'description' => 'Internal ref, often "auto".'),
						'ref_supplier' => array('type' => 'string', 'description' => 'Supplier invoice reference.'),
						'date' => array('type' => 'string'),
						'fk_project' => array('type' => 'integer'),
						'note_public' => array('type' => 'string'),
						'note_private' => array('type' => 'string'),
						'cond_reglement_id' => array('type' => 'integer'),
						'mode_reglement_id' => array('type' => 'integer'),
						'fk_account' => array('type' => 'integer'),
						'lines' => array(
							'type' => 'array',
							'items' => array('type' => 'object', 'properties' => $lineProps),
						),
					),
					'required' => array('socid'),
				),
				array('POST', '/supplierinvoices', 'body')
			),
			'dolibarr_update_supplier_invoice' => self::tool('Update a supplier invoice.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'ref_supplier' => array('type' => 'string'),
					'date' => array('type' => 'string'),
					'note_public' => array('type' => 'string'),
					'note_private' => array('type' => 'string'),
					'fk_project' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('PUT', '/supplierinvoices/{id}', 'body', array('id'))),
			'dolibarr_delete_supplier_invoice' => self::tool('Delete a supplier invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/supplierinvoices/{id}', null, array('id'))),
			'dolibarr_validate_supplier_invoice' => self::tool('Validate a draft supplier invoice.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'idwarehouse' => array('type' => 'integer'),
					'notrigger' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('POST', '/supplierinvoices/{id}/validate', 'body', array('id'))),
			'dolibarr_settodraft_supplier_invoice' => self::tool('Set a supplier invoice back to draft.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'idwarehouse' => array('type' => 'integer'),
					'notrigger' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('POST', '/supplierinvoices/{id}/settodraft', 'body', array('id'))),
			'dolibarr_get_supplier_invoice_lines' => self::tool('List lines of a supplier invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/supplierinvoices/{id}/lines', null, array('id'))),
			'dolibarr_add_supplier_invoice_line' => self::tool('Add a line to a supplier invoice.', array(
				'type' => 'object',
				'properties' => array_merge(array('id' => array('type' => 'integer')), $lineProps),
				'required' => array('id'),
			), array('POST', '/supplierinvoices/{id}/lines', 'body', array('id'))),
			'dolibarr_update_supplier_invoice_line' => self::tool('Update a line on a supplier invoice.', array(
				'type' => 'object',
				'properties' => array_merge(array(
					'id' => array('type' => 'integer'),
					'lineid' => array('type' => 'integer'),
				), $lineProps),
				'required' => array('id', 'lineid'),
			), array('PUT', '/supplierinvoices/{id}/lines/{lineid}', 'body', array('id', 'lineid'))),
			'dolibarr_delete_supplier_invoice_line' => self::tool('Delete a line from a supplier invoice.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'lineid' => array('type' => 'integer'),
				),
				'required' => array('id', 'lineid'),
			), array('DELETE', '/supplierinvoices/{id}/lines/{lineid}', null, array('id', 'lineid'))),
			'dolibarr_get_supplier_invoice_payments' => self::tool('List payments on a supplier invoice.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/supplierinvoices/{id}/payments', null, array('id'))),
			'dolibarr_add_supplier_invoice_payment' => self::tool(
				'Register a payment on a supplier invoice. Required: id, datepaye, payment_mode_id, closepaidinvoices, accountid.',
				array(
					'type' => 'object',
					'properties' => array(
						'id' => array('type' => 'integer'),
						'datepaye' => array('type' => 'string'),
						'payment_mode_id' => array('type' => 'integer'),
						'closepaidinvoices' => array('type' => 'string', 'description' => '"yes" or "no".'),
						'accountid' => array('type' => 'integer'),
						'num_payment' => array('type' => 'string'),
						'comment' => array('type' => 'string'),
						'chqemetteur' => array('type' => 'string'),
						'chqbank' => array('type' => 'string'),
						'amount' => array('type' => 'number'),
					),
					'required' => array('id', 'datepaye', 'payment_mode_id', 'closepaidinvoices', 'accountid'),
				),
				array('POST', '/supplierinvoices/{id}/payments', 'body', array('id'))
			),
		);
	}

	/**
	 * Services are products with type=1 (Dolibarr Services module / Products API mode=2).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function serviceTools()
	{
		$serviceProps = array(
			'ref' => array('type' => 'string', 'description' => 'Service reference (required).'),
			'label' => array('type' => 'string', 'description' => 'Service label (required).'),
			'description' => array('type' => 'string'),
			'price' => array('type' => 'number', 'description' => 'Selling price HT.'),
			'price_ttc' => array('type' => 'number', 'description' => 'Selling price TTC.'),
			'price_base_type' => array('type' => 'string', 'description' => 'HT or TTC.'),
			'tva_tx' => array('type' => 'number', 'description' => 'VAT rate percent.'),
			'status' => array('type' => 'integer', 'description' => '1=on sell, 0=off sell.'),
			'status_buy' => array('type' => 'integer', 'description' => '1=can be purchased, 0=no.'),
			'duration_value' => array('type' => 'integer', 'description' => 'Service duration value.'),
			'duration_unit' => array('type' => 'string', 'description' => 'h, d, w, m, y.'),
			'note_public' => array('type' => 'string'),
			'note_private' => array('type' => 'string'),
			'barcode' => array('type' => 'string'),
			'fk_unit' => array('type' => 'integer'),
			'accountancy_code_sell' => array('type' => 'string'),
			'accountancy_code_buy' => array('type' => 'string'),
		);

		return array(
			'dolibarr_list_services' => self::tool(
				'List services (Products API with mode=2 / product_type=1). Requires the Services module.',
				array(
					'type' => 'object',
					'properties' => array_merge(array(
						'category' => array('type' => 'integer'),
						'ids_only' => array('type' => 'boolean'),
						'properties' => array('type' => 'string'),
					), self::paginationProps()),
				),
				array('GET', '/products', 'query', null, array('mode' => 2))
			),
			'dolibarr_get_service' => self::tool('Get a service by ID (product with type=1).', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'includestockdata' => array('type' => 'integer'),
					'includesubproducts' => array('type' => 'boolean'),
					'includeparentid' => array('type' => 'boolean'),
					'includetrans' => array('type' => 'boolean'),
				),
				'required' => array('id'),
			), array('GET', '/products/{id}', 'query', array('id'))),
			'dolibarr_get_service_by_ref' => self::tool('Get a service by reference.', array(
				'type' => 'object',
				'properties' => array('ref' => array('type' => 'string')),
				'required' => array('ref'),
			), array('GET', '/products/ref/{ref}', null, array('ref'))),
			'dolibarr_create_service' => self::tool(
				'Create a service. Required: ref, label. type is forced to 1 (service). Optional: price, tva_tx, status, duration_value, duration_unit, description.',
				array(
					'type' => 'object',
					'properties' => $serviceProps,
					'required' => array('ref', 'label'),
				),
				array('POST', '/products', 'body', null, null, array(), array('type' => 1))
			),
			'dolibarr_update_service' => self::tool(
				'Update a service. type remains 1 (service).',
				array(
					'type' => 'object',
					'properties' => array_merge(array('id' => array('type' => 'integer')), $serviceProps),
					'required' => array('id'),
				),
				array('PUT', '/products/{id}', 'body', array('id'), null, array(), array('type' => 1))
			),
			'dolibarr_delete_service' => self::tool('Delete a service.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('DELETE', '/products/{id}', null, array('id'))),
			'dolibarr_get_service_categories' => self::tool('List categories linked to a service.', array(
				'type' => 'object',
				'properties' => array(
					'id' => array('type' => 'integer'),
					'sortfield' => array('type' => 'string'),
					'sortorder' => array('type' => 'string'),
					'limit' => array('type' => 'integer'),
					'page' => array('type' => 'integer'),
				),
				'required' => array('id'),
			), array('GET', '/products/{id}/categories', 'query', array('id'))),
		);
	}

	/**
	 * @param string $name
	 * @return array<string,mixed>|null
	 */
	public static function getTool($name)
	{
		$tools = self::getTools();
		return isset($tools[$name]) ? $tools[$name] : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function listForMcp()
	{
		$list = array();
		foreach (self::getTools() as $name => $def) {
			$item = array(
				'name' => $name,
				'description' => $def['description'],
				'inputSchema' => $def['inputSchema'],
			);
			// Some MCP clients also read "parameters" as an alias of inputSchema.
			$item['parameters'] = $def['inputSchema'];
			$list[] = $item;
		}
		return $list;
	}

	/**
	 * @param string               $description
	 * @param array<string,mixed>  $inputSchema
	 * @param array<int,mixed>     $route
	 * @return array<string,mixed>
	 */
	private static function tool($description, $inputSchema, $route)
	{
		return array(
			'description' => $description,
			'inputSchema' => $inputSchema,
			'route' => $route,
		);
	}

	/**
	 * Writable project fields for create/update tools (Dolibarr Project / REST API).
	 *
	 * @param bool $forUpdate When true, omit create-only hints from descriptions where useful.
	 * @return array<string,array<string,mixed>>
	 */
	private static function projectWriteProps($forUpdate = false)
	{
		return array(
			'ref' => array(
				'type' => 'string',
				'description' => $forUpdate
					? 'Project reference.'
					: 'Project reference. Use "auto" (default) to let Dolibarr assign the next number from the projects numbering module.',
			),
			'title' => array(
				'type' => 'string',
				'description' => 'Project label / title (mandatory on create).',
			),
			'description' => array(
				'type' => 'string',
				'description' => 'Long description of the project.',
			),
			'ref_ext' => array(
				'type' => 'string',
				'description' => 'External reference (integration key).',
			),
			'socid' => array(
				'type' => 'integer',
				'description' => 'Third-party (customer) ID to link. Use dolibarr_list_thirdparties / dolibarr_create_thirdparty first if needed.',
			),
			'fk_project' => array(
				'type' => 'integer',
				'description' => 'Parent project ID (for sub-projects), if used.',
			),
			'date_start' => array(
				'type' => 'string',
				'description' => 'Start date as YYYY-MM-DD or Unix timestamp. Alias: dateo.',
			),
			'date_end' => array(
				'type' => 'string',
				'description' => 'End date as YYYY-MM-DD or Unix timestamp. Alias: datee.',
			),
			'dateo' => array(
				'type' => 'string',
				'description' => 'Deprecated alias of date_start (YYYY-MM-DD or Unix timestamp).',
			),
			'datee' => array(
				'type' => 'string',
				'description' => 'Deprecated alias of date_end (YYYY-MM-DD or Unix timestamp).',
			),
			'public' => array(
				'type' => 'integer',
				'description' => 'Visibility: 0 = private (contacts only), 1 = public (all internal users).',
				'enum' => array(0, 1),
			),
			'budget_amount' => array(
				'type' => 'number',
				'description' => 'Project budget amount.',
			),
			'opp_amount' => array(
				'type' => 'number',
				'description' => 'Opportunity amount (when PROJECT_USE_OPPORTUNITIES is enabled).',
			),
			'opp_percent' => array(
				'type' => 'number',
				'description' => 'Opportunity win probability percent (0–100).',
			),
			'opp_status' => array(
				'type' => 'integer',
				'description' => 'Opportunity status ID (llx_c_lead_status). Alias of fk_opp_status.',
			),
			'fk_opp_status' => array(
				'type' => 'integer',
				'description' => 'Opportunity status ID (llx_c_lead_status).',
			),
			'usage_opportunity' => array(
				'type' => 'integer',
				'description' => '1 = use as opportunity, 0 = no.',
				'enum' => array(0, 1),
			),
			'usage_task' => array(
				'type' => 'integer',
				'description' => '1 = use tasks on this project, 0 = no.',
				'enum' => array(0, 1),
			),
			'usage_bill_time' => array(
				'type' => 'integer',
				'description' => '1 = time spent is billable, 0 = not billable.',
				'enum' => array(0, 1),
			),
			'usage_organize_event' => array(
				'type' => 'integer',
				'description' => '1 = use event organization features on this project, 0 = no.',
				'enum' => array(0, 1),
			),
			'date_start_event' => array(
				'type' => 'string',
				'description' => 'Event start date (YYYY-MM-DD or Unix timestamp), when event organization is enabled.',
			),
			'date_end_event' => array(
				'type' => 'string',
				'description' => 'Event end date (YYYY-MM-DD or Unix timestamp).',
			),
			'location' => array(
				'type' => 'string',
				'description' => 'Event / project location.',
			),
			'accept_conference_suggestions' => array(
				'type' => 'integer',
				'description' => '1 = allow unknown people to suggest conferences.',
				'enum' => array(0, 1),
			),
			'accept_booth_suggestions' => array(
				'type' => 'integer',
				'description' => '1 = allow unknown people to suggest booths.',
				'enum' => array(0, 1),
			),
			'price_registration' => array(
				'type' => 'number',
				'description' => 'Registration price (event organization).',
			),
			'price_booth' => array(
				'type' => 'number',
				'description' => 'Booth price (event organization).',
			),
			'max_attendees' => array(
				'type' => 'integer',
				'description' => 'Maximum number of attendees (event organization).',
			),
			'note_public' => array(
				'type' => 'string',
				'description' => 'Public note (visible to customers with access).',
			),
			'note_private' => array(
				'type' => 'string',
				'description' => 'Private internal note.',
			),
			'model_pdf' => array(
				'type' => 'string',
				'description' => 'PDF template name for project documents.',
			),
			'email_msgid' => array(
				'type' => 'string',
				'description' => 'Email Message-ID when the project was created from an email.',
			),
			'import_key' => array(
				'type' => 'string',
				'description' => 'Import key (deduplication / external sync).',
			),
			'statut' => array(
				'type' => 'integer',
				'description' => 'Status: 0=draft, 1=validated/open, 2=closed. Prefer dolibarr_validate_project instead of setting this on create.',
				'enum' => array(0, 1, 2),
			),
			'status' => array(
				'type' => 'integer',
				'description' => 'Alias of statut (0=draft, 1=open, 2=closed).',
				'enum' => array(0, 1, 2),
			),
			'array_options' => array(
				'type' => 'object',
				'description' => 'Extrafields map, e.g. {"options_myfield": "value"}. Keys must use the options_ prefix.',
				'additionalProperties' => true,
			),
		);
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	private static function paginationProps()
	{
		return array(
			'sortfield' => array('type' => 'string'),
			'sortorder' => array('type' => 'string'),
			'limit' => array('type' => 'integer'),
			'page' => array('type' => 'integer'),
			'sqlfilters' => array('type' => 'string'),
		);
	}
}
