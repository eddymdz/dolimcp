<?php
/* DoliMCP setup page */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'dolimcp@dolimcp'));

if (!$user->hasRight('dolimcp', 'read')) {
	accessforbidden();
}

$mcpUrl = DOL_URL_ROOT.'/custom/dolimcp/mcp.php';

$title = $langs->trans('DoliMCPSetup');
llxHeader('', $title);

print load_fiche_titre($title, '', 'technic');

print '<div class="fichecenter">';
print '<p>'.$langs->trans('DoliMCPSetupDesc').'</p>';

print '<h3>'.$langs->trans('DoliMCPPrerequisites').'</h3>';
print '<ul>';
print '<li>'.($conf->global->MAIN_MODULE_API ? '✓' : '✗').' REST API (modApi)</li>';
print '<li>'.($conf->global->MAIN_MODULE_PROJET ? '✓' : '✗').' Projects (modProjet)</li>';
print '<li>'.($conf->global->MAIN_MODULE_USER ? '✓' : '✗').' Users (modUser)</li>';
print '</ul>';

print '<h3>'.$langs->trans('DoliMCPApiKeys').'</h3>';
print '<p>'.$langs->trans('DoliMCPApiKeysDesc').'</p>';
print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/user/list.php">'.$langs->trans('Users').'</a></p>';

print '<h3>'.$langs->trans('DoliMCPEndpoints').'</h3>';
print '<p><strong>MCP Streamable HTTP:</strong> <code>'.dol_escape_htmltag($mcpUrl).'</code></p>';
print '<p><strong>REST explorer (official API):</strong> <code>'.dol_escape_htmltag(DOL_URL_ROOT).'/api/index.php/explorer</code></p>';

print '<h3>'.$langs->trans('DoliMCPCursor').'</h3>';
print '<p>'.$langs->trans('DoliMCPTransportDesc').'</p>';
print '<pre>'.dol_escape_htmltag('{
  "mcpServers": {
    "dolibarr-streamable-http": {
      "url": "'.$mcpUrl.'",
      "headers": {
        "DOLIMCPKEY": "${env:DOLIBARR_MCP_TOKEN}"
      }
    }
  }
}').'</pre>';

print '</div>';

llxFooter();
