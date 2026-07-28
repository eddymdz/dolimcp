<?php
/* Copyright (C) 2026
 *
 * DoliMCP — MCP Streamable HTTP server embedded in Dolibarr for AI agents.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for DoliMCP module
 */
class modDolimcp extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->numero = 104501;
		$this->rights_class = 'dolimcp';
		$this->family = "interface";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "DoliMCPBridge";
		$this->descriptionlong = "MCP Streamable HTTP endpoint for AI agents (projects, tasks, time, employees, third parties, invoices, services).";
		$this->editor_name = 'DoliMCP';
		$this->editor_url = '';
		$this->version = '0.4.3';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'technic';

		$this->module_parts = array(
			'hooks' => array('usercard'),
		);

		$this->dirs = array('/dolimcp/temp', '/dolimcp/mcp_sessions');

		$this->config_page_url = array('setup.php@dolimcp');

		// Facture / Fournisseur / Banque are soft: tools degrade if modules are off.
		$this->depends = array('modApi', 'modProjet', 'modUser', 'modSociete');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('dolimcp@dolimcp');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(17, 0);

		$this->const = array();

		$this->tabs = array();

		$this->dictionaries = array();

		$this->boxes = array();

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = 10450101;
		$this->rights[$r][1] = 'Read DoliMCP setup';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read';
		$r++;
		$this->rights[$r][0] = 10450102;
		$this->rights[$r][1] = 'Configure DoliMCP';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=home',
			'type' => 'left',
			'titre' => 'DoliMCP',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'home',
			'leftmenu' => 'dolimcp',
			'url' => '/custom/dolimcp/admin/setup.php',
			'langs' => 'dolimcp@dolimcp',
			'position' => 1000,
			'enabled' => '$conf->dolimcp->enabled',
			'perms' => '$user->hasRight("dolimcp", "read")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/dolimcp/sql/');
		if ($result < 0) {
			return -1;
		}
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
