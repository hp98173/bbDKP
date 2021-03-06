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

if (!class_exists('\bbdkp\Points'))
{
	require("{$phpbb_root_path}includes/bbdkp/Points/Points.$phpEx");
}

/**
 * 
 * 
 * @package 	bbDKP
 */
class PointsController  extends \bbdkp\Admin
{
	private $Points; 
	
	function __construct() 
	{
		//load model
		parent::__construct();
		$this->Points = new \bbdkp\Points();  
	}
	
	
	/**
	 * wrapper for new raid; this must be called after the raid and raid detail tables are filled.
	 * 
	 * @param float $raid_value
	 * @param float $time_bonus
	 * @param int $raid_start
	 * @param int $dkpid
	 * @param int $member_id
	 */
	public function add_raid($raid_id)
	{
		global $config, $db;
		
		$new_raid = new \bbdkp\Raids($raid_id);
		$raiddetail = new \bbdkp\Raiddetail($raid_id);
		$raiddetail->Get($raid_id);
		$a = 'debug'; 
		foreach ((array) $raiddetail->raid_details as $member_id => $attendee)
		{
			$this->Points = new \bbdkp\Points(); 
			$this->Points->dkpid = $new_raid->event_dkpid;
			$this->Points->member_id = $member_id;
			
			if($this->Points->has_account($member_id, $new_raid->event_dkpid))
			{
				$this->Points->read_account();
				$this->Points->raid_value += $attendee['raid_value'];
				$this->Points->time_bonus += $attendee['time_bonus'];
				$this->Points->zerosum_bonus += $attendee['zerosum_bonus'];
				
				// update firstraid if it's later than this raid's starting time
				if ( $this->Points->firstraid >  $new_raid->raid_start )
				{
					$this->Points->firstraid =  $new_raid->raid_start;
				}
				
				// update their lastraid if it's earlier than this raid's starting time
				if ( $this->Points->lastraid <  $new_raid->raid_start )
				{
					$this->Points->lastraid =  $new_raid->raid_start;
				}
				$this->Points->update_account();
				$this->update_raiddate($member_id, $new_raid->event_dkpid);
				
			}
			else
			{
				
				$this->Points->raid_value = $attendee['raid_value'];
				$this->Points->time_bonus = $attendee['time_bonus'];
				$this->Points->zerosum_bonus = $attendee['zerosum_bonus'];
				$this->Points->earned_decay = 0.0; 
				$this->Points->spent = 0.0;
				$this->Points->item_decay = 0.0;
				$this->Points->adjustment = 0.0;
				$this->Points->adj_decay = 0.0;
				$this->Points->firstraid = $new_raid->raid_start ;
				$this->Points->lastraid = $new_raid->raid_start;
				$this->Points->raidcount = 1;
				$this->Points->status = 1;
				$this->Points->open_account();
				$this->update_raiddate($member_id, $new_raid->event_dkpid);
			}
			
			unset($this->Points); 
			
		}
		
		if ($config ['bbdkp_hide_inactive'] == 1)
		{
			$this->update_player_status ($new_raid->event_dkpid);
		}
	}
	
	/**
	 * remove a raid from existing dkprecord
	 * @param unknown_type $raid_value
	 * @param unknown_type $timebonus
	 * @param unknown_type $raidstart
	 * @param unknown_type $dkpid
	 * @param unknown_type $member_id
	 */
	public function removeraid_delete_dkprecord($raid_id, $member_id = 0)
	{
		global $config; 
	
		if($member_id = 0)
		{
			$old_raid = new \bbdkp\Raids($raid_id);
			$raiddetail = new \bbdkp\Raiddetail($raid_id);
			$raiddetail->Get($raid_id);
			$members = array();
			foreach ((array) $raiddetail->raid_details as $member_id => $attendee)
			{
				$this->Points->dkpid = $old_raid->event_dkpid;
				$this->Points->member_id = $member_id;
				$this->Points->read_account();
				$this->Points->raid_value -= $attendee['raid_value'];
				$this->Points->time_bonus -= $attendee['time_bonus'];
				$this->Points->zerosum_bonus -= $attendee['zerosum_bonus'];
				$this->Points->earned_decay -= $raiddetail->raid_decay;
				$this->Points->update_account();
			}
				
		}
		else
		{
			$old_raid = new \bbdkp\Raids($raid_id);
			$raiddetail = new \bbdkp\Raiddetail($raid_id);
			$raiddetail->Get($raid_id, $member_id);
				
			$this->Points->dkpid = $old_raid->event_dkpid;
			$this->Points->member_id = $member_id;
			$this->Points->read_account();
				
				
			$this->Points->raid_value -= $raiddetail->raid_value;
			$this->Points->time_bonus -= $raiddetail->time_bonus;
			$this->Points->zerosum_bonus -= $raiddetail->zerosum_bonus;
			$this->Points->earned_decay -= $raiddetail->raid_decay;
				
			$this->Points->update_account();
				
		}

		if ($config ['bbdkp_hide_inactive'] == 1)
		{
			$this->update_player_status ( $old_raid->event_dkpid);
		}
		
	}
	
	
	public function addloot_update_dkprecord($item_value, $dkpid, $member_id)
	{
		$this->Points->dkpid = $dkpid;
		$this->Points->member_id = $member_id;
		$this->Points->read_account();
		
		$this->Points->spent += $item_value; 
		$this->Points->update_account();	
	}
	
	public function removeloot_update_dkprecord($item_value, $dkpid, $member_id)
	{
		$this->Points->dkpid = $dkpid;
		$this->Points->member_id = $member_id;
		$this->Points->read_account();
		
		$this->Points->spent -= $item_value;
		$this->Points->update_account();	
	}


	/**
	 *
	 * update_raiddate : update dkp record first and lastraids
	 * @param int $member_id
	 * @param int $dkpid
	 */
	private function update_raiddate($member_id, $dkpid)
	{
		global $db, $user;
	
		// get first & last raids
		$sql_array = array (
				'SELECT' => 'MIN(r.raid_start) AS member_firstraid, MAX(r.raid_start) AS member_lastraid, ra.member_id ',
				'FROM' => array (
						RAIDS_TABLE => 'r',
						RAID_DETAIL_TABLE => 'ra' ,
						EVENTS_TABLE => 'e'
				),
				'WHERE' => ' ra.raid_id = r.raid_id
					AND r.event_id = e.event_id
					AND e.event_dkpid = ' . $dkpid . '
					AND ra.member_id  = ' . $member_id,
				'GROUP_BY' => 'member_id',
		);
	
		$sql = $db->sql_build_query ( 'SELECT', $sql_array );
		$result = $db->sql_query ( $sql );
		$member_firstraid = 0;
		$member_lastraid = 0;
		while ( $row = $db->sql_fetchrow ($result))
		{
			$member_firstraid = $row['member_firstraid'];
			$member_lastraid = $row['member_lastraid'];
		}
		$db->sql_freeresult ($result);
	
		$first_raid = ( isset($member_firstraid) ? $member_firstraid : 0 );
		$last_raid = ( isset($member_lastraid) ? $member_lastraid : 0 );
	
		$query = $db->sql_build_array ( 'UPDATE', array (
				'member_firstraid' 		=> $first_raid,
				'member_lastraid' 		=> $last_raid,
		));
	
		$db->sql_query ( 'UPDATE ' . MEMBER_DKP_TABLE . ' SET ' . $query . '
	            WHERE member_id = ' . $member_id . '
	            AND  member_dkpid = ' . $dkpid);
	}
	


	/**
	 * Set active or inactive based on last raid. only for current raids dkp pool
	 * Update active inactive player status column member_status
	 * active = 1 inactive = 0
	 *
	 * @param int $dkpid
	 * @return bool
	 */
	private function update_player_status($dkpid)
	{
		global $db, $user, $config;
	
		$inactive_time = mktime (0, 0, 0, date ( 'm' ), date ( 'd' ) - $config ['bbdkp_inactive_period'], date ( 'Y' ) );
	
		$active_members = array ();
		$inactive_members = array ();
	
		// Don't do active/inactive adjustments if we don't need to.
		if (($config ['bbdkp_active_point_adj'] != 0) || ($config ['bbdkp_inactive_point_adj'] != 0))
		{
			// adapt status and set adjustment points
			$sql_array = array (
					'SELECT' => 'a.member_id, b.member_name, a.member_status, a.member_lastraid',
					'FROM' => array (
							MEMBER_DKP_TABLE => 'a',
							MEMBER_LIST_TABLE => 'b'
					),
					'WHERE' => ' a.member_id = b.member_id AND a.member_dkpid =' . $dkpid
			);
			$adj_value = 0.00;
			$adj_reason = '';
			$sql = $db->sql_build_query ( 'SELECT', $sql_array );
			$result = $db->sql_query ( $sql );
			while ( $row = $db->sql_fetchrow ( $result ) )
			{
				unset ( $adj_value ); // destroy local
				unset ( $adj_reason );
	
				// Active -> Inactive
				if (((float) $config ['bbdkp_inactive_point_adj'] != 0.00) && ($row['member_status'] == 1) && ($row['member_lastraid'] < $inactive_time))
				{
					$adj_value = $config ['bbdkp_inactive_point_adj'];
					$adj_reason = 'Inactive adjustment';
					$inactive_members [] = $row['member_id'];
					$inactive_membernames [] = $row['member_name'];
				}
				// Inactive -> Active
				elseif (( (float) $config ['bbdkp_active_point_adj'] != 0.00) && ($row['member_status'] == 0) && ($row['member_lastraid'] >= $inactive_time))
				{
					$adj_value = $config ['bbdkp_active_point_adj'];
					$adj_reason = 'Active adjustment';
					$active_members [] = $row['member_id'];
					$active_membernames [] = $row['member_name'];
				}
	
				//
				// Insert individual adjustment
				if ((isset ( $adj_value )) && (isset ( $adj_reason )))
				{
				$group_key = $this->gen_group_key ( $this->time, $adj_reason, $adj_value );
				$query = $db->sql_build_array ( 'INSERT',
				array (
		'adjustment_dkpid' 		=> $dkpid,
		'adjustment_value' 		=> $adj_value,
		'adjustment_date' 		=> $this->time,
		'member_id' 			=> $row['member_id'],
		'adjustment_reason' 	=> $adj_reason,
		'adjustment_group_key' 	=> $group_key,
		'adjustment_added_by' 	=> $user->data ['username'] ));
					
				$db->sql_query ( 'INSERT INTO ' . ADJUSTMENTS_TABLE . $query );
		}
		}
		$db->sql_freeresult( $result );
			
		// Update members to inactive and put dkp adjustment
		if (sizeof ( $inactive_members ) > 0)
		{
			$adj_value = (float) $config ['bbdkp_inactive_point_adj'];
			$adj_reason = 'Inactive adjustment';
	
			$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
		SET member_status = 0, member_adjustment = member_adjustment + ' . (string) $adj_value . '
		WHERE member_dkpid = ' . $dkpid . '  AND ' . $db->sql_in_set ( 'member_id', $inactive_members ) ;
			$db->sql_query($sql);
	
			$log_action = array (
					'header' 		=> 'L_ACTION_INDIVADJ_ADDED',
					'L_ADJUSTMENT' 	=> $config ['bbdkp_inactive_point_adj'],
					'L_MEMBERS' 	=> implode ( ', ', $inactive_membernames ),
					'L_REASON' 		=> $user->lang['INACTIVE_POINT_ADJ'],
					'L_ADDED_BY'	=> $user->data ['username'] );
	
			$this->log_insert ( array (
					'log_type' 		=> $log_action ['header'],
					'log_action' 	=> $log_action ));
		}
			
		// Update active members' adjustment
		if (sizeof ( $active_members ) > 0)
		{
			$adj_value = (float) $config ['bbdkp_active_point_adj'];
	
			$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
		SET member_status = 1, member_adjustment = member_adjustment + ' . (string) $adj_value . '
		WHERE member_dkpid = ' . $dkpid . '  AND ' . $db->sql_in_set ( 'member_id', $active_members );
	
			$db->sql_query($sql);
	
			$log_action = array (
					'header' 		=> 'L_ACTION_INDIVADJ_ADDED',
					'L_ADJUSTMENT' 	=> $config ['bbdkp_active_point_adj'],
					'L_MEMBERS' 	=> implode ( ', ', $active_membernames ),
					'L_REASON' 		=> $user->lang['ACTIVE_POINT_ADJ'],
					'L_ADDED_BY' 	=> $user->data ['username'] );
	
			$this->log_insert ( array ('log_type' => $log_action ['header'], 'log_action' => $log_action ) );
		}
		}
		else
		{
			// only adapt status
				
			// Active -> Inactive
			$db->sql_query ( 'UPDATE ' . MEMBER_DKP_TABLE . " SET member_status = 0 WHERE member_dkpid = " . $dkpid . "
		AND (member_lastraid <  " . $inactive_time . ") AND (member_status= 1)" );
				
			// Inactive -> Active
			$db->sql_query ( 'UPDATE ' . MEMBER_DKP_TABLE . " SET member_status = 1 WHERE member_dkpid = " . $dkpid . "
		AND (member_lastraid >= " . $inactive_time . ") AND (member_status= 0)" );
		}
	
		return true;
	}
		
	
	/**
	 * 
	 * @param int $mode 0 or 1 (0= set to 0, 1="resynchronise")
	 * @return boolean|number
	 */
	public function sync_zerosum($mode)
	{
		global $user, $db, $config;
	
		switch ($mode)
		{
			case 0:
				// set all to 0
				//  update raid detail table to 0
				$sql = 'UPDATE ' . RAID_DETAIL_TABLE . ' SET zerosum_bonus = 0 ' ;
				$db->sql_query ( $sql );
	
				// update dkp account
				$sql = 'UPDATE ' . MEMBER_DKP_TABLE . ' SET member_zerosum_bonus = 0, member_earned = member_raid_value + member_time_bonus';
				$db->sql_query ( $sql );

				$log_action = array (
						'header' 		=> 'L_ACTION_ZSYNC',
						'L_USER' 		=>  $user->data['user_id'],
						'L_USERCOLOUR' 	=>  $user->data['user_colour'],
							
				);
				$this->log_insert ( array (
						'log_type' 		=> $log_action ['header'],
						'log_action' 	=> $log_action ) );
	
				\trigger_error ( sprintf($user->lang ['RESYNC_ZEROSUM_DELETED']) , E_USER_NOTICE );
	
				return true;
				break;
	
			case 1:
				// redistribute
	
				//  update raid detail table to 0
				$sql = 'UPDATE ' . RAID_DETAIL_TABLE . ' SET zerosum_bonus = 0 ' ;
				$db->sql_query ( $sql );
	
				// update dkp account
				$sql = 'UPDATE ' . MEMBER_DKP_TABLE . ' SET member_zerosum_bonus = 0, member_earned = member_raid_value + member_time_bonus';
				$db->sql_query ( $sql );
	
	
				// loop raids having items
				$sql = 'SELECT e.event_dkpid, r.raid_id FROM '.
						RAIDS_TABLE. ' r, ' .
						EVENTS_TABLE . ' e, ' .
						RAID_ITEMS_TABLE . ' i
					WHERE e.event_id = r.event_id
					AND r.raid_id = i.raid_id
					GROUP BY e.event_dkpid, r.raid_id' ;
				$result = $db->sql_query ($sql);
				$countraids=0;
				$raids = array();
				while ( ($row = $db->sql_fetchrow ( $result )) )
				{
					$raids[$row['raid_id']]['dkpid']=$row['event_dkpid'];
					$countraids++;
				}
				$db->sql_freeresult ( $result);
	
				//build an array
				foreach($raids as $raid_id => $raid)
				{
					$numraiders = 0;
					$sql = 'SELECT member_id FROM ' . RAID_DETAIL_TABLE . ' WHERE raid_id = ' . $raid_id;
					$result = $db->sql_query($sql);
					while ( $row = $db->sql_fetchrow ($result))
					{
						if ($row['member_id'] != $config['bbdkp_bankerid'])
						{
							$raids[$raid_id]['raiders'][]= $row['member_id'];
						}
						$numraiders++;
					}
					$raids[$raid_id]['numraiders'] = $numraiders;
	
					$db->sql_freeresult ( $result);
	
					$sql = 'SELECT member_id, item_value, item_id FROM ' . RAID_ITEMS_TABLE . ' WHERE raid_id = ' . $raid_id;
					$result = $db->sql_query($sql);
					$numbuyers=0;
					while ( $row = $db->sql_fetchrow ($result))
					{
						$raids[$raid_id]['item'][$row['item_id']]['buyer'] = $row['member_id'];
						$raids[$raid_id]['item'][$row['item_id']]['item_value'] = $row['item_value'];
	
						$distributed = round($row['item_value'] / max(1, $numraiders), 2);
						$raids[$raid_id]['item'][$row['item_id']]['distributed_value']= $distributed;
	
						// rest of division
						$restvalue = $row['item_value'] - ($numraiders * $distributed);
						$raids[$raid_id]['item'][$row['item_id']]['rest_value'] = $restvalue;
	
						$numbuyers++;
	
					}
	
					$db->sql_freeresult ( $result);
					$raids[$raid_id]['numbuyers'] = $numbuyers;
				}
	
				/*
				 * now process the raid array with following structure
				 "$raids[1]"	Array [5]
				dkpid	(string:1) 1
				raiders	Array [4]
					0	(string:1) 2
					1	(string:1) 3
					2	(string:1) 4
					3	(string:1) 5
				numraiders	(int) 4
				item	Array [2]
					1	Array [4]
				buyer	(string:1) 5
				item_value	(string:5) 15.00
				distributed_value	(double) 3.75
				rest_value	(double) 0
					2	Array [4]
				buyer	(string:1) 4
				item_value	(string:5) 15.00
				distributed_value	(double) 3.75
				rest_value	(double) 0
				numbuyers	(int) 2
				*/
	
				$itemcount = 0;
				$accountupdates=0;
				foreach($raids as $raid_id => $raid)
				{
					$accountupdates += $raid['numraiders'];
					$items = $raid['item'];
					foreach($items as $item_id => $item)
					{
						// distribute this item value as income to all raiders
						$sql = 'UPDATE ' . RAID_DETAIL_TABLE . '
						SET 	zerosum_bonus = zerosum_bonus + ' . (float) $item['distributed_value'] . '
								WHERE raid_id = ' . (int) $raid_id . ' AND ' . $db->sql_in_set('member_id', $raid['raiders']);
						$db->sql_query ( $sql );
						$itemcount ++;
		
						// update their dkp account aswell
						$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
							SET member_zerosum_bonus = member_zerosum_bonus + ' . (float) $item['distributed_value']  .  ',
								member_earned = member_earned + ' . (float) $item['distributed_value']  .  '
										WHERE member_dkpid = ' . (int) $raid['dkpid']  . '
							  	AND ' . $db->sql_in_set('member_id', $raid['raiders']   ) ;
					  	$db->sql_query ( $sql );
										  	
					  	// give rest value to the buyer in raiddetail or to the guild bank
					  	if($item['rest_value']!=0 )
					  	{
						  	$sql = 'UPDATE ' . RAID_DETAIL_TABLE . '
					  			SET zerosum_bonus = zerosum_bonus + ' . (float) $item['rest_value']  .  '
					  			WHERE raid_id = ' . (int) $raid_id . '
					  			AND member_id = ' . ($config['bbdkp_zerosumdistother'] == 1 ? $config['bbdkp_bankerid'] : $item['buyer'])  ;
		  					$db->sql_query ( $sql );

		  					$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
			  					SET member_zerosum_bonus = member_zerosum_bonus + ' . (float) $item['rest_value']  .  ',
								member_earned = member_earned + ' . (float) $item['rest_value']  .  '
								WHERE member_dkpid = ' . (int) $raid['dkpid']  . '
								AND member_id = ' .  ($config['bbdkp_zerosumdistother'] == 1 ? $config['bbdkp_bankerid'] : $item['buyer']);
						  	$db->sql_query ( $sql );
					  	}
					}
				}
				
				$log_action = array (
						'header' 		=> 'L_ACTION_ZSYNC',
						'L_USER' 		=>  $user->data['user_id'],
						'L_USERCOLOUR' 	=>  $user->data['user_colour'],
				);
				$this->log_insert ( array (
						'log_type' 		=> $log_action ['header'],
						'log_action' 	=> $log_action ) );
	
				\trigger_error ( sprintf($user->lang ['RESYNC_ZEROSUM_SUCCESS'], $itemcount, $accountupdates ) , E_USER_NOTICE );
				return $countraids;
				break;
		}
	
	}
	
	
	/**
	 * resynchronises the DKP points table with the adjustments, raids, items.
	 *
	 */
	public function syncdkpsys($mode = 1)
	{
		global $user, $db, $phpbb_admin_path, $phpEx, $config;
		$link = '<br /><a href="' . append_sid ( "{$phpbb_admin_path}index.$phpEx", "i=dkp_sys&amp;mode=listdkpsys" ) . '"><h3>'. $user->lang['RETURN_DKPPOOLINDEX'].'</h3></a>';
	
		/* start transaction */
	
		/* reinintialise the dkp points table */
		$sql = "DELETE from " . MEMBER_DKP_TABLE;
		$db->sql_query ($sql);
	
		$db->sql_transaction('begin');
		/* select adjustments */
		$sql = "SELECT adjustment_dkpid, member_id, SUM(adjustment_value) AS adjustment_value
			FROM " . ADJUSTMENTS_TABLE . '
			GROUP BY adjustment_dkpid, member_id
			ORDER BY adjustment_dkpid, member_id';
		$result = $db->sql_query ($sql);
		while ($row = $db->sql_fetchrow ( $result))
		{
	
			$query = $db->sql_build_array('INSERT', array(
					'member_dkpid'     	   => $row['adjustment_dkpid'],
					'member_id'           =>  $row['member_id'],
					'member_earned'       =>  0.00,
					'member_spent'        =>  0.00,
					'member_adjustment'   =>  $row['adjustment_value'],
					'member_status'       =>  1,
					'member_firstraid'    =>  0,
					'member_lastraid'     =>  0,
					'member_raidcount'    =>  0 )
			);
			$db->sql_query('INSERT INTO ' . MEMBER_DKP_TABLE . $query);
		}
		$db->sql_freeresult ( $result);
	
		/* select raids */
		$sql = 'SELECT e.event_dkpid, d.member_id FROM '.
				EVENTS_TABLE . ' e
			INNER JOIN ' . RAIDS_TABLE. ' r ON e.event_id = r.event_id
			INNER JOIN ' . RAID_DETAIL_TABLE . ' d ON r.raid_id = d.raid_id
			GROUP BY e.event_dkpid, d.member_id';
	
		$dkpcorr = 0;
		$dkpadded = 0;
		$dkpspentcorr = 0;
			
		$result0 = $db->sql_query ($sql);
		while ($row = $db->sql_fetchrow ( $result0 ))
		{
			$member_id = $row['member_id'];
			$event_dkpid = $row['event_dkpid'];
	
			/* select raid values */
			$sql = 'SELECT
				MIN(r.raid_start) as first_raid,
				MAX(r.raid_start) as last_raid,
				COUNT(d.raid_id) as raidcount,
				SUM(d.raid_value) as raid_value,
				SUM(d.time_bonus) as time_bonus,
				SUM(d.raid_decay) as raid_decay,
				SUM(d.zerosum_bonus) as zerosum_bonus
				FROM '. EVENTS_TABLE . ' e
				INNER JOIN ' . RAIDS_TABLE. ' r ON e.event_id = r.event_id
				INNER JOIN ' . RAID_DETAIL_TABLE . ' d ON r.raid_id = d.raid_id
				WHERE d.member_id = ' . $member_id . '
				AND	e.event_dkpid = ' . $event_dkpid;
			$result = $db->sql_query ($sql);
			while ( ($rowd = $db->sql_fetchrow ( $result )) )
			{
				$first_raid = $rowd['first_raid'];
				$last_raid = $rowd['last_raid'];
				$raidcount= $rowd['raidcount'];
				$raid_value = $rowd['raid_value'];
				$time_bonus = $rowd['time_bonus'];
				$raid_decay= $rowd['raid_decay'];
				$zerosum_bonus = $rowd['zerosum_bonus'];
			}
			$db->sql_freeresult ( $result);
				
			$sql =  'SELECT count(*) as count FROM ' . MEMBER_DKP_TABLE . ' WHERE member_id = ' . $member_id . '
			AND	member_dkpid = ' . $event_dkpid;
			$result = $db->sql_query ($sql);
			$count = $db->sql_fetchfield('count', false, $result);
			$db->sql_freeresult ( $result);
				
			//this will be zero at first loop
			if($count ==1)
			{
				$sql =  'SELECT * FROM ' . MEMBER_DKP_TABLE . ' WHERE member_id = ' . $member_id . '
				AND	member_dkpid = ' . $event_dkpid;
				$result = $db->sql_query ($sql);
				while ( ($rowe = $db->sql_fetchrow ( $result )) )
				{
					$first_raid_accounted = $rowe['member_firstraid'];
					$last_raid_accounted = $rowe['member_lastraid'];
					$raidcount_accounted= $rowe['member_raidcount'];
					$raid_value_accounted = $rowe['member_raid_value'];
					$time_bonus_accounted = $rowe['member_time_bonus'];
					$raid_decay_accounted= $rowe['member_raid_decay'];
					$zerosum_bonus_accounted = $rowe['member_zerosum_bonus'];
					$earned_accounted = $rowe['member_earned'];
				}
				$db->sql_freeresult ( $result);
					
				if(( $first_raid != $first_raid_accounted) ||
						($last_raid != $last_raid_accounted) ||
						($raidcount != $raidcount_accounted) ||
						($raid_value != $raid_value_accounted) ||
						($time_bonus != $time_bonus_accounted) ||
						($raid_decay != $raid_decay_accounted) ||
						($zerosum_bonus != $zerosum_bonus_accounted))
				{
					$dkpcorr +=1;
						
					$data = array(
							'member_firstraid'      => $first_raid,
							'member_lastraid'       => $last_raid,
							'member_raidcount'      => $raidcount,
							'member_raid_value'     => $raid_value,
							'member_time_bonus'     => $time_bonus,
							'member_raid_decay'     => $raid_decay,
							'member_zerosum_bonus'	=> $zerosum_bonus,
							'member_earned'     	=> $raid_value+$time_bonus+$zerosum_bonus-$raid_decay,
					);
						
					$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
					SET ' . $db->sql_build_array('UPDATE', $data) .
						' WHERE member_id = ' . $member_id . '
					AND	member_dkpid = ' . $event_dkpid;
					$db->sql_query ($sql);
						
				}
			}
			else
			{
				//delete and reinsert
				$sql = 'DELETE FROM ' . MEMBER_DKP_TABLE . ' WHERE member_id = ' . $member_id . '
				AND	member_dkpid = ' . $event_dkpid;
				$db->sql_query ($sql);
	
				$data = array(
						'member_dkpid'      	=> $event_dkpid,
						'member_id'      		=> $member_id,
						'member_status'      	=> 1,
						'member_firstraid'      => $first_raid,
						'member_lastraid'       => $last_raid,
						'member_raidcount'      => $raidcount,
						'member_raid_value'     => $raid_value,
						'member_time_bonus'     => $time_bonus,
						'member_raid_decay'     => $raid_decay,
						'member_zerosum_bonus'	=> $zerosum_bonus,
						'member_earned'     	=> $raid_value+$time_bonus+$zerosum_bonus-$raid_decay,
				);
				$dkpadded +=1;
	
				$sql = 'INSERT INTO ' . MEMBER_DKP_TABLE . $db->sql_build_array('INSERT', $data);
				$db->sql_query ($sql);
	
			}
		}
		$db->sql_freeresult ( $result0);
			
		/* select loot */
		$sql = 'SELECT e.event_dkpid, i.member_id FROM '.
				EVENTS_TABLE . ' e
				INNER JOIN ' . RAIDS_TABLE. ' r ON e.event_id = r.event_id
				INNER JOIN ' . RAID_ITEMS_TABLE . ' i ON r.raid_id = i.raid_id
				GROUP BY e.event_dkpid, i.member_id' ;
	
		$result0 = $db->sql_query ($sql);
	
		while ($row = $db->sql_fetchrow ( $result0 ))
		{
			$member_id = $row['member_id'];
			$event_dkpid = $row['event_dkpid'];
			$item_value = 0;
			$item_decay =0;
			/* select lootvalues */
			$sql = 'SELECT
				SUM(i.item_value) as item_value,
				SUM(i.item_decay) as item_decay
				FROM '. EVENTS_TABLE . ' e
				INNER JOIN ' . RAIDS_TABLE. ' r ON e.event_id = r.event_id
				INNER JOIN ' . RAID_ITEMS_TABLE . ' i ON i.raid_id = r.raid_id
				WHERE i.member_id = ' . $member_id . '
				AND	e.event_dkpid = ' . $event_dkpid;
			$result = $db->sql_query ($sql);
			while ( ($rowd = $db->sql_fetchrow ( $result )) )
			{
				$item_value = $rowd['item_value'];
				$item_decay= $rowd['item_decay'];
			}
			$db->sql_freeresult ( $result);
				
			$sql =  'SELECT count(*) as count FROM ' . MEMBER_DKP_TABLE . ' WHERE member_id = ' . $member_id . '
			AND	member_dkpid = ' . $event_dkpid;
			$result = $db->sql_query ($sql);
			$count = $db->sql_fetchfield('count', false, $result);
			$db->sql_freeresult ( $result);
			if($count == 1 )
			{
				$sql =  'SELECT * FROM ' . MEMBER_DKP_TABLE . ' WHERE member_id = ' . $member_id . '
				AND	member_dkpid = ' . $event_dkpid;
				$result = $db->sql_query ($sql);
				while ( ($rowe = $db->sql_fetchrow ( $result )) )
				{
					$item_value_accounted = $rowe['member_spent'];
					$item_decay_accounted = $rowe['member_item_decay'];
				}
				$db->sql_freeresult ( $result);
				if(( $item_value  != $item_value_accounted) ||
						($item_decay  != $item_decay_accounted))
				{
					$dkpspentcorr += 1;
					/* account exists */
					$data = array(
							'member_spent'     		=> $item_value,
							'member_item_decay'     => $item_decay,
					);
						
					$sql = 'UPDATE ' . MEMBER_DKP_TABLE . '
					SET ' . $db->sql_build_array('UPDATE', $data) .
						' WHERE member_id = ' . $member_id . '
					AND	member_dkpid = ' . $event_dkpid;
					$db->sql_query ($sql);
	
						
				}
			}
			// case count=0 is not possible
		}
		$db->sql_freeresult ( $result0);
	
		$db->sql_transaction('commit');
	
		$log_action = array (
				'header' 	=> 'L_ACTION_DKPSYNC',
				'L_USER' 		=>  $user->data['user_id'],
				'L_USERCOLOUR' 	=>  $user->data['user_colour'],
				'L_LOG_1'		=>  $dkpcorr,
				'L_LOG_2'		=>  $dkpspentcorr,
		);
		$this->log_insert ( array (
				'log_type' 		=> $log_action ['header'],
				'log_action' 	=> $log_action ) );
	
		if ($mode==1)
		{
			//otherwise do silent sync
			$message = sprintf($user->lang['ADMIN_DKPPOOLSYNC_SUCCESS'] , $dkpcorr  + $dkpspentcorr + $dkpadded);
			trigger_error ( $message . $this->link , E_USER_NOTICE );
		}
	
	}
		
	/**
	 * calculates decay on epoch timedifference (seconds) and earned
	 * we decay the sum of earned ( = raid value + time bonus + zerosumpoints) 
	 * @param int $value = the value to decay
	 * @param int $timediff = diff in seconds since raidstart
	 * @param int $mode = 1 for raid, 2 for items
	 *
	 */
	public function decay($value, $timediff, $mode)
	{
		global $user, $config, $db;
		$i=0;
		switch ($mode)
		{
			case 1:
				// get raid decay rate in pct
				$i = (float) $config['bbdkp_raiddecaypct']/100;
				break;
			case 2:
				// get item decay rate in pct
				$i = (float) $config['bbdkp_itemdecaypct']/100;
				break;
		}
	
		// get decay frequency
		$freq = $config['bbdkp_decayfrequency'];
		if ($freq==0)
		{
			//frequency can't be 0. throw error
			trigger_error($user->lang['FV_FREQUENCY_NOTZERO'],E_USER_WARNING );
		}
	
		//pick decay frequency type (0=days, 1=weeks, 2=months) and convert timediff to that
		$t=0;
		switch ($config['bbdkp_decayfreqtype'])
		{
			case 0:
				//days
				$t = (float) $timediff / 86400;
				break;
			case 1:
				//weeks
				$t = (float) $timediff / (86400*7);
				break;
			case 2:
				//months
				$t = (float) $timediff / (86400*30.44);
				break;
		}
	
		// take the integer part of time and interval division base 10,
		// since we only decay after a set interval
		$n = intval($t/$freq, 10);
	
		//calculate rounded raid decay, defaults to rounds half up PHP_ROUND_HALF_UP, so 9.495 becomes 9.50
		$decay = round($value * (1 - pow(1-$i, $n)), 2);
	
		return array($decay, $n) ;
	
	}
	
		
		
		/**
		 * Recalculates and updates decay
		 * loops all raids - caution this may run a long time
		 *
		 * @param $mode 1 for recalculating, 0 for setting decay to zero.
		 */
		public function sync_decay($mode, $origin= '')
		{
			global $user, $db;
			switch ($mode)
			{
				case 0:
					//  Decay = OFF : set all decay to 0
					//  update item detail to new decay value
					$sql = 'UPDATE ' . RAID_DETAIL_TABLE . ' SET raid_decay = 0 ' ;
					$db->sql_query ( $sql );
		
					// update dkp account, deduct old, add new decay
					$sql = 'UPDATE ' . MEMBER_DKP_TABLE . ' SET member_raid_decay = 0, member_item_decay = 0';
					$db->sql_query ( $sql );
		
					$sql = 'UPDATE ' . RAID_ITEMS_TABLE . ' SET item_decay = 0';
					$db->sql_query ( $sql);
		
					if ($origin != 'cron')
					{
						//no logging for cronjobs
						$log_action = array (
								'header' 		=> 'L_ACTION_DECAYOFF',
								'L_USER' 		=>  $user->data['user_id'],
								'L_USERCOLOUR' 	=>  $user->data['user_colour'],
								'L_ORIGIN' 		=>  $origin
						);
		
						$this->log_insert ( array (
								'log_type' 		=> $log_action ['header'],
								'log_action' 	=> $log_action ) );
					}
		
					return true;
					break;
		
				case 1:
					// Decay is ON : synchronise
					// loop all raids
					$sql = 'SELECT e.event_dkpid, r.raid_id FROM '. RAIDS_TABLE. ' r, ' . EVENTS_TABLE . ' e WHERE e.event_id = r.event_id ' ;
					$result = $db->sql_query ($sql);
					$countraids=0;
					while ( ($row = $db->sql_fetchrow ( $result )) )
					{
						$this->decayraid($row['raid_id'], $row['event_dkpid']);
						$countraids++;
					}
					$db->sql_freeresult ($result);
		
					if ($countraids > 0 && $origin != 'cron' )
					{
						//no logging for cronjobs due to users just not getting it.
						$log_action = array (
								'header' 	=> 'L_ACTION_DECAYSYNC',
								'L_USER' 	=>  $user->data['user_id'],
								'L_USERCOLOUR' 	=>  $user->data['user_colour'],
								'L_RAIDS' 	=> $countraids,
								'L_ORIGIN' 		=>  $origin
						);
		
						$this->log_insert ( array (
								'log_type' 		=> $log_action ['header'],
								'log_action' 	=> $log_action ) );
					}
		
					return $countraids;
		
					break;
						
			}
		
		
		}
		
		
		
}

?>