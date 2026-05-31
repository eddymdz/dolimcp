<?php
/* Copyright (C) 2026 */

/**
 * Hooks for DoliMCP module.
 */
class ActionsDolimcp
{
	/**
	 * Add MCP token tab on user card.
	 *
	 * @param array<string,mixed> $parameters
	 * @param User                $object
	 * @param string              $action
	 * @param HookManager         $hookmanager
	 * @return int
	 */
	public function completeTabsHead($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!is_object($object) || empty($object->id)) {
			return 0;
		}

		if (empty($user->rights->dolimcp->read) && !$user->admin) {
			return 0;
		}

		$targetUserId = (int) $object->id;
		if (!$user->admin && $user->id != $targetUserId && !$user->hasRight('user', 'user', 'lire')) {
			return 0;
		}

		$langs->load('dolimcp@dolimcp');

		$parameters['head'][] = array(
			DOL_URL_ROOT.'/custom/dolimcp/user_token.php?id='.$targetUserId,
			$langs->trans('DoliMCPUserToken'),
			'dolimcp_token',
		);

		return 0;
	}
}
