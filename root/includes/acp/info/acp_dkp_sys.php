<?php
/**
 * 
* @author Sajaki
* @copyright (c) 2009 bbdkp https://github.com/bbDKP
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @version 1.3.0
* @package acp
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}


/**
* @package acp
*/

class acp_dkp_sys_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_dkp_sys',
			'title'		=> 'ACP_DKP_RAIDS',
			'version'	=> '1.3.0',
			'modes'		=> array(
				'adddkpsys'		=> array('title' => 'ACP_DKP_POOL_ADD', 'auth' => 'acl_a_dkp', 'cat' => array('ACP_DKP_RAIDS') , 'display' => false),
				'listdkpsys'	=> array('title' => 'ACP_DKP_POOL_LIST', 'auth' => 'acl_a_dkp', 'cat' => array('ACP_DKP_RAIDS') , 'display' => true ),
				),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>
