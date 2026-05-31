<?php
/* Copyright (C) 2026 — MCP token management (generate / regenerate only) */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once __DIR__.'/lib/dolimcp.lib.php';
require_once __DIR__.'/class/dolimcpusertoken.class.php';

$langs->loadLangs(array('admin', 'users', 'dolimcp@dolimcp'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

if (empty($id)) {
	accessforbidden();
}

$object = new User($db);
if ($object->fetch($id) <= 0) {
	accessforbidden();
}

$canedit = ($user->admin || (($user->id == $id) && $user->hasRight('user', 'self', 'write')));
$canread = ($user->admin || ($user->id == $id) || $user->hasRight('user', 'user', 'lire'));

if (!$canread) {
	accessforbidden();
}

$tokenobj = new DolimcpUserToken($db);
$tokenobj->fetchByUser($id);

$plainTokenOnce = '';

if (!$cancel && $canedit) {
	if ($action === 'generate' || ($action === 'regenerate' && $confirm === 'yes')) {
		$plainTokenOnce = $tokenobj->regenerateForUser($id);
		if ($plainTokenOnce === false) {
			setEventMessages($langs->trans('DoliMCPTokenGenerateFailed'), null, 'errors');
		} else {
			setEventMessages($langs->trans('DoliMCPTokenGenerated'), null, 'mesgs');
			$tokenobj->fetchByUser($id);
		}
		$action = '';
	}

	if ($action === 'revoke' && $confirm === 'yes') {
		$tokenobj->revokeForUser($id);
		setEventMessages($langs->trans('DoliMCPTokenRevoked'), null, 'mesgs');
		$tokenobj = new DolimcpUserToken($db);
		$tokenobj->fetchByUser($id);
		$action = '';
	}
}

$form = new Form($db);
$title = $langs->trans('DoliMCPUserToken');
llxHeader('', $title);

$head = user_prepare_head($object);
print dol_get_fiche_head($head, 'dolimcp_token', $langs->trans("User"), -1, 'user');

$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'id', $linkback, $user->admin, 'rowid', 'ref', '');

print '<div class="fichecenter">';
print '<p>'.$langs->trans('DoliMCPUserTokenDesc').'</p>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('DoliMCPUserToken').'</td></tr>';

print '<tr><td class="titlefield">'.$langs->trans('DoliMCPTokenStatus').'</td><td>';
if ($tokenobj->id > 0) {
	print '<span class="badge badge-status4 badge-status">'.$langs->trans('Active').'</span>';
} else {
	print '<span class="badge badge-status8 badge-status">'.$langs->trans('None').'</span>';
}
print '</td></tr>';

print '<tr><td>'.$langs->trans('DoliMCPTokenValue').'</td><td>';
if ($plainTokenOnce !== '') {
	print '<div class="warning">';
	print $langs->trans('DoliMCPTokenCopyNow').'<br><br>';
	print '<input type="text" class="flat minwidth500" readonly="readonly" value="'.dol_escape_htmltag($plainTokenOnce).'">';
	print '</div>';
} elseif ($tokenobj->id > 0) {
	print dol_escape_htmltag($tokenobj->getMaskedLabel());
} else {
	print '<em>'.$langs->trans('DoliMCPNoTokenYet').'</em>';
}
print '</td></tr>';

if ($tokenobj->id > 0 && !empty($tokenobj->datec)) {
	print '<tr><td>'.$langs->trans('DateCreation').'</td><td>'.dol_print_date($tokenobj->datec, 'dayhour').'</td></tr>';
}

print '</table>';

print '<div class="tabsAction">';

if ($canedit) {
	if ($tokenobj->id <= 0) {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=generate&token='.newToken().'">'.$langs->trans('DoliMCPGenerateToken').'</a>';
	} else {
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=regenerate&token='.newToken().'">'.$langs->trans('DoliMCPRegenerateToken').'</a>';
		print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=revoke&token='.newToken().'">'.$langs->trans('DoliMCPRevokeToken').'</a>';
	}
}

print '</div>';

if ($action === 'regenerate' && $canedit) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$id,
		$langs->trans('DoliMCPRegenerateToken'),
		$langs->trans('DoliMCPRegenerateConfirm'),
		'regenerate',
		'',
		0,
		1
	);
}

if ($action === 'revoke' && $canedit) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$id,
		$langs->trans('DoliMCPRevokeToken'),
		$langs->trans('DoliMCPRevokeConfirm'),
		'revoke',
		'',
		0,
		1
	);
}

print '<h3>'.$langs->trans('DoliMCPCursor').'</h3>';
print '<pre>'.dol_escape_htmltag('{
  "mcpServers": {
    "dolibarr-streamable-http": {
      "url": "'.DOL_URL_ROOT.'/custom/dolimcp/mcp.php",
      "headers": {
        "DOLIMCPKEY": "${env:DOLIBARR_MCP_TOKEN}"
      }
    }
  }
}').'</pre>';

print '</div>';

print dol_get_fiche_end();
llxFooter();
