<?php
namespace bbdkp;
/**
 * @package 	bbDKP
 * @link http://www.bbdkp.com
 * @author Sajaki@gmail.com
 * @copyright 2013 bbdkp
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 1.3.0
 * @since 1.3.0
 *
 */

/**
 * @ignore
 */
if (! defined('IN_PHPBB'))
{
	exit();
}

$phpEx = substr(strrchr(__FILE__, '.'), 1);
global $phpbb_root_path;

if (!class_exists('\bbdkp\Admin'))
{
	require ("{$phpbb_root_path}includes/bbdkp/admin.$phpEx");
}

/**
 *  Pool Class
 *  this class manages the points Pool in the phpbb_bbdkp_dkpsystem table.  
 *  Pools are a superset for events and dkp accounts.
 *  this class needs no controller so it extends admin.
 *  
 * @package 	bbDKP
 */
class Pool extends \bbdkp\Admin
 {
 	
 	public $dkpsys_id; 
 	private $dkpsys_name;
 	private $dkpsys_status;
 	private $dkpsys_addedby;
 	private $dkpsys_updatedby;
 	private $dkpsys_default;
 	private $poolcount;
 	
	function __construct($dkpsys = 0) 
	{
		global $db;
		parent::__construct(); //to load admin
		if($dkpsys > 0)
		{
			$this->dkpsys_id = $dkpsys;
			$this->read(); 	
		}
		
		$sql1 = 'SELECT * FROM ' . DKPSYS_TABLE;
		$result1 = $db->sql_query ( $sql1 );
		$rows1 = $db->sql_fetchrowset ( $result1 );
		$db->sql_freeresult ( $result1 );
		$this->poolcount = count ( $rows1 );
		
	}
	
	/**
	 *
	 * @param string $property
	 */
	public function __get($property)
	{
		global $user;
	
		if (property_exists($this, $property))
		{
			return $this->$property;
		}
		else
		{
			trigger_error($user->lang['ERROR'] . '  '. $property, E_USER_WARNING);
		}
	}
	
	/**
	 *
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public function __set($property, $value)
	{
		global $user;
		switch ($property)
		{
			default:
				if (property_exists($this, $property))
				{
					switch($property)
					{
						case 'dkpsys_id':
							if(is_numeric($value))
							{
								$this->$property = $value;
							}
							break;
						case 'dkpsys_name':
							// limit this to 255
							$this->$property = (strlen($value) > 255) ? substr($value,0, 250).'...' : $string;
							break;
						case 'dkpsys_status':
							if($value === 'N' or $value === 'Y')
							{
								$this->$property = $value;
							}
							elseif ($value === false)
							{
								$this->$property = 'N'; 
							}
							elseif ($value === true)
							{
								$this->$property = 'Y';
							}		
							else
							{
								$this->$property = 'Y';
							}					
							break;
						case 'dkpsys_addedby':
							$this->$property = $value;
							break;
						case 'dkpsys_updatedby':
							$this->$property = $value;
							break;
						case 'dkpsys_default':
							if($value === 'Y')
							{
								$this->$property = $value;
								$this->updateotherdefaults();
							}
							if($value === 'N')
							{
								$this->$property = $value;
							}
							elseif ($value === false)
							{
								$this->$property = 'N';
							}
							elseif ($value === true)
							{
								$this->$property = 'Y';
								$this->updateotherdefaults();
							}
							else
							{
								$this->$property = 'N';
								
							}
							break;												
					}
				}
				else
				{
					trigger_error($user->lang['ERROR'] . '  '. $property, E_USER_WARNING);
				}
		}
	}
	
	private function updateotherdefaults()
	{
		global $db; 
		$sql = 'UPDATE ' . DKPSYS_TABLE . " SET dkpsys_default = 'N' WHERE dkpsys_id != " . (int) $this->dkpsys_id;
		$db->sql_query ( $sql );
	}
	
	/**
	 * read account
	 */
	private function read()
	{
		global $db;
	
		$sql = 'SELECT * FROM ' . DKPSYS_TABLE . ' WHERE dkpsys_id = ' . (int) $this->dkpsys_id;
		$result = $db->sql_query ($sql);
		while ( ($row = $db->sql_fetchrow ( $result )) )
		{
			$this->dkpsys_name = $row['dkpsys_name'];
			$this->dkpsys_default = $row['dkpsys_default'];
			$this->dkpsys_status = $row['dkpsys_status'];	
			$this->dkpsys_addedby = $row['dkpsys_addedby'];
			$this->dkpsys_updatedby  = $row['dkpsys_updatedby'];		
		}
		$db->sql_freeresult ($result);
		
	}
	
	/**
	 * read account
	 */
	public function listpools($order = 'dkpsys_name', $start = 0, $mode = 0)
	{
		global $config, $db;
	
		$sql = 'SELECT * FROM ' . DKPSYS_TABLE . ' ORDER BY ' . $order; 
		if($mode == 1)
		{
			$result = $db->sql_query_limit ( $sql, $config ['bbdkp_user_elimit'], $start );
		}
		else
		{
			$result = $db->sql_query($sql);
		}
		$listpools = array(); 
		while ( ($row = $db->sql_fetchrow ( $result )) )
		{
			$listpools [$row['dkpsys_id']]= array(
				'dkpsys_name' => $row['dkpsys_name'], 
				'dkpsys_default' => $row['dkpsys_default'], 
				'dkpsys_status' => $row['dkpsys_status'], 
				'dkpsys_addedby' => $row['dkpsys_addedby'], 
				'dkpsys_updatedby'  => $row['dkpsys_updatedby']); 
		}
		$db->sql_freeresult ($result);
		return $listpools; 
	}
	
	/**
	 * insert new pool
	 */
	public function add()
	{
		global $user, $db;
		$query = $db->sql_build_array ('INSERT',
			array (
					'dkpsys_name' => $this->dkpsys_name ,
					'dkpsys_status' => $this->dkpsys_status ,
					'dkpsys_addedby' => $user->data['username'],
					'dkpsys_default' => $this->dkpsys_default ) );
		$db->sql_query ( 'INSERT INTO ' . DKPSYS_TABLE . $query );
		$this->dkpsys_id = $db->sql_nextid();

		$log_action = array (
				'header' => 'L_ACTION_DKPSYS_ADDED',
				'L_DKPSYS_NAME' => $this->dkpsys_name,
				'L_DKPSYS_STATUS' => $this->dkpsys_status,
				'L_ADDED_BY' => $user->data['username'] );
		
		$this->log_insert ( array (
				'log_type' => $log_action ['header'],
				'log_action' => $log_action ) );
		
	}
	
	
	/**
	 * update the pool. you have to pass the old object for logging
	 * @param Pool $olddkpsys
	 */
	public function update(Pool $olddkpsys)
	{
		global $user, $db;
		$query = $db->sql_build_array (
				'UPDATE',
				array (
						'dkpsys_name' => $this->dkpsys_name,
						'dkpsys_status' => $this->dkpsys_status, 
						'dkpsys_default' => $this->dkpsys_default, 
						'dkpsys_updatedby' => $user->data['username']));
		
		
		$sql = 'UPDATE ' . DKPSYS_TABLE . ' SET ' . $query . ' WHERE dkpsys_id = ' . (int) $this->dkpsys_id;
		$db->sql_query ( $sql );
		
		
		// Logging, put old & new
		$log_action = array (
				'header' => 'L_ACTION_DKPSYS_UPDATED',
				'id' => $this->dkpsys_id,
				'L_DKPSYSNAME_BEFORE' => $olddkpsys->dkpsys_name,
				'L_DKPSYSSTATUS_BEFORE' => $olddkpsys->dkpsys_status,
				'L_DKPSYSNAME_AFTER' => $this->dkpsys_name,
				'L_DKPSYSSTATUS_AFTER' => $this->dkpsys_status, 
				'L_DKPSYSUPDATED_BY' => $user->data['username'] );
		$this->log_insert (
			array ( 'log_type' => $log_action ['header'],
				'log_action' => $log_action ) );
			
		
	}
	
	/**
	 * delete the pool. 
	 * check if there are still raids/events on the pool
	 */
	public function delete()
	{
		global $user, $db;
		
		$sql = 'SELECT * FROM ' . RAIDS_TABLE . ' a, ' . EVENTS_TABLE . ' b 
				WHERE b.event_id = a.event_id and b.event_dkpid = ' . (int) $this->dkpsys_id;
		$result = $db->sql_query ( $sql );
		if ($row = $db->sql_fetchrow ( $result ))
		{
			$db->sql_freeresult ( $result );
			trigger_error ( $user->lang ['FV_RAIDEXIST'], E_USER_WARNING );
		}
		

		$sql = 'SELECT * FROM ' . EVENTS_TABLE . ' WHERE event_dkpid = ' . (int) $this->dkpsys_id;
		$result = $db->sql_query ( $sql );
		if ($row = $db->sql_fetchrow ( $result ))
		{
			$db->sql_freeresult ( $result );
			trigger_error ( $user->lang ['FV_EVENTEXIST'], E_USER_WARNING );
		}
		
		$sql = 'DELETE FROM ' . DKPSYS_TABLE . ' WHERE dkpsys_id = ' . (int) $this->dkpsys_id;
		$db->sql_query ($sql);
		
		$log_action = array (
				'header' => 'L_ACTION_DKPSYS_DELETED',
				'id' => $this->dkpsys_id,
				'L_DKPSYS_NAME' => $this->dkpsys_name,
				'L_DKPSYS_STATUS' => $this->dkpsys_status);
		
		$this->log_insert ( array (
				'log_type' => $log_action ['header'],
				'log_action' => $log_action ));
		
	}
	
	
	
}

?>