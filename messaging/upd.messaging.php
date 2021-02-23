<?php

/*
=====================================================
 Messaging
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2012-2016 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: upd.messaging.php
-----------------------------------------------------
 Purpose: Tool for private & public messages management
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'messaging/config.php';

class Messaging_upd {

    var $version = MESSAGING_ADDON_VERSION;
    
    function __construct() { 

    } 
    
    function install() { 
        
        ee()->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if (ee()->db->field_exists('settings', 'modules') == FALSE)
		{
			ee()->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array();
        
        $data = array( 'module_name' => 'Messaging' , 'module_version' => $this->version, 'has_cp_backend' => 'n', 'settings'=> serialize($settings) ); 
        ee()->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'send_bulletin' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'delete_bulletin' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'send_pm' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'move_pm' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'save_folders' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'list_member' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'unlist_member' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'display_attachment' ); 
        ee()->db->insert('actions', $data); 
        
        return TRUE; 
        
    } 
    
    function uninstall() { 

        ee()->db->select('module_id'); 
        $query = ee()->db->get_where('modules', array('module_name' => 'Messaging')); 
        
        ee()->db->where('module_id', $query->row('module_id')); 
        ee()->db->delete('module_member_groups'); 
        
        ee()->db->where('module_name', 'Messaging'); 
        ee()->db->delete('modules'); 
        
        ee()->db->where('class', 'Messaging'); 
        ee()->db->delete('actions'); 
        
        return TRUE; 
    } 
    
    function update($current='') { 
        if ($current < 2.0) { 
            // Do your 2.0 version update queries 
        } if ($current < 3.0) { 
            // Do your 3.0 v. update queries 
        } 
        return TRUE; 
    } 
	

}
/* END */
?>
