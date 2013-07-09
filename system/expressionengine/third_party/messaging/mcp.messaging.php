<?php

/*
=====================================================
 Messaging
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011-2012 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: mcp.messaging.php
-----------------------------------------------------
 Purpose: Tool for private & public messages management
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'messaging/config.php';

class Messaging_mcp {

    var $version = MESSAGING_ADDON_VERSION;
    
    var $settings = array();
    
    var $docs_url = "http://www.intoeetive.com/docs/messaging.html";
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function index()
    {
  
    }


}
/* END */
?>