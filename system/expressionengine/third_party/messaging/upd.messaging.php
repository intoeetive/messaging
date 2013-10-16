<?php

/*
=====================================================
 Messaging
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2012-2013 Yuri Salimovskiy
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
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
        
        $this->EE->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if ($this->EE->db->field_exists('settings', 'modules') == FALSE)
		{
			$this->EE->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array();
        
        $data = array( 'module_name' => 'Messaging' , 'module_version' => $this->version, 'has_cp_backend' => 'n', 'settings'=> serialize($settings) ); 
        $this->EE->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'send_bulletin' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'delete_bulletin' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'send_pm' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'move_pm' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'save_folders' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'list_member' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'unlist_member' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Messaging' , 'method' => 'display_attachment' ); 
        $this->EE->db->insert('actions', $data); 
        
        return TRUE; 
        
    } 
    
    function uninstall() { 

        $this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Messaging')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Messaging'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Messaging'); 
        $this->EE->db->delete('actions'); 
        
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