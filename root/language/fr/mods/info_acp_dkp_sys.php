<?php
/**
 * bbdkp acp language file for DKP Pool (FR)
 * 
 * 
 * @copyright 2009 bbdkp <http://code.google.com/p/bbdkp/>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * 
 */

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// Create the lang array if it does not already exist
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// Merge the following language entries into the lang array
$lang = array_merge($lang, array(
	'ACP_DKP_POOL_ADD'		=> 'Ajouter groupe DKP',  
	'ACP_DKP_POOL_LIST'		=> 'Groupes DKP',
	'ACP_DKP_LOOTSYSTEM'	=> 'Systeme Loot',
	'ACP_DKP_LOOTSYSTEM_EXPLAIN'	=> 'Ici vous pouvez selectionnner le système de distribution de Loot',
	'ACP_DKP_LOOTSYSTEMOPTIONS'	=> 'Réglages Systemes de Loot',
	'ACP_DKP_LOOTSYSTEMEXPLAIN'	=> 'Guide Systemes de Loot',	
));

?>