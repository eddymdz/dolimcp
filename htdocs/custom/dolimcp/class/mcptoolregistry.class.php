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
		return array(
			'dolibarr_list_projects' => self::tool('List projects visible to the API user.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
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
			'dolibarr_create_project' => self::tool('Create a project.', array('type' => 'object'), array('POST', '/projects', 'body')),
			'dolibarr_update_project' => self::tool('Update a project.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer')),
				'required' => array('id'),
			), array('PUT', '/projects/{id}', 'body', array('id'))),
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
				'properties' => array('project_id' => array('type' => 'integer')),
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

			'dolibarr_list_tasks' => self::tool('List tasks.', array(
				'type' => 'object',
				'properties' => self::paginationProps(),
			), array('GET', '/tasks', 'query')),
			'dolibarr_get_task' => self::tool('Get a task by ID.', array(
				'type' => 'object',
				'properties' => array('id' => array('type' => 'integer'), 'includetimespent' => array('type' => 'integer')),
				'required' => array('id'),
			), array('GET', '/tasks/{id}', 'query', array('id'))),
			'dolibarr_create_task' => self::tool('Create a task (requires fk_project).', array('type' => 'object'), array('POST', '/tasks', 'body')),
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
			$list[] = array(
				'name' => $name,
				'description' => $def['description'],
				'inputSchema' => $def['inputSchema'],
			);
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
