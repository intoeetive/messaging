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
 File: mod.messaging.php
-----------------------------------------------------
 Purpose: Tool for private & public messages management
=====================================================
*/


if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}


class Messaging {

    var $return_data	= ''; 						// Bah!
    
    var $settings = array();
    
    var $perpage = 25;

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
        ee()->lang->loadfile('member');
        ee()->lang->loadfile('members');
        ee()->lang->loadfile('messages');
        ee()->lang->loadfile('messaging');
    }
    /* END */

    
    //display bulletins aka public messages
    function bulletins()
    {
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
        ee()->db->update('members', array('last_view_bulletins'=>ee()->localize->now));
     
        $start = 0;
        $paginate = (ee()->TMPL->fetch_param('paginate')=='top')?'top':((ee()->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        
        $basepath = ee()->functions->create_url(ee()->uri->uri_string);
        $query_string = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = reduce_double_slashes(str_replace($match[0], '', $basepath));
		}
        
        ee()->db->start_cache();
        ee()->db->select('exp_member_bulletin_board.*, exp_members.member_id, exp_members.username, exp_members.screen_name, exp_members.email, exp_members.avatar_filename, exp_members.photo_filename');
        ee()->db->from('exp_member_bulletin_board');
        ee()->db->join('exp_members', 'exp_member_bulletin_board.sender_id=exp_members.member_id', 'left');
        ee()->db->where('exp_member_bulletin_board.bulletin_group', ee()->session->userdata('group_id'));
        //ee()->db->where('(bulletin_group='.ee()->session->userdata('group_id').' OR sender_id='.ee()->session->userdata('member_id').')');
        if (ee()->TMPL->fetch_param('message_id')!='') 
        {
            ee()->db->where('exp_member_bulletin_board.bulletin_id', ee()->TMPL->fetch_param('message_id'));
        }
        if (ee()->TMPL->fetch_param('show_expired')!='yes') 
        {
            ee()->db->where('exp_member_bulletin_board.bulletin_expires >= ', ee()->localize->now);
        }
        if (ee()->TMPL->fetch_param('only_new')=='yes') 
        {
            ee()->db->where('exp_member_bulletin_board.bulletin_date > ', ee()->session->userdata('last_view_bulletins'));
        }
        ee()->db->order_by('exp_member_bulletin_board.bulletin_id', 'desc');
        if (ee()->TMPL->fetch_param('limit')!='') $this->perpage = ee()->TMPL->fetch_param('limit');
        ee()->db->stop_cache();
        
        $total = ee()->db->count_all_results();

        ee()->db->limit($this->perpage, $start);
        $query = ee()->db->get();

        ee()->db->flush_cache();
        
        if ($query->num_rows()==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $tagdata_orig = ee()->TMPL->swap_var_single('total_results', $total, ee()->TMPL->tagdata);
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata_orig, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata_orig = str_replace($tmp[0][0], '', $tagdata_orig);
        }

        $out = '';
        $i = 0;
        
        foreach ($query->result_array() as $row)
        {
            $i++;
            
            $cond['can_delete'] = (ee()->session->userdata('group_id') == 1 OR $row['sender_id'] == ee()->session->userdata('member_id')) ? TRUE : FALSE;
            $tagdata = ee()->functions->prep_conditionals($tagdata_orig, $cond);
            
            if ($cond['can_delete']==true)
            {
                $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='delete_bulletin'");
                $delete_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id').'&message_id='.$row['bulletin_id'];
                if (ee()->TMPL->fetch_param('ajax')=='yes') $delete_url .= '&ajax=yes';
                $delete_link = '<a href="'.$delete_url.'" onclick="return confirm(\''.ee()->lang->line('delete_bulletin_popup').'\')" class="bulletin_delete_link">'.ee()->lang->line('delete_bulletin').'</a>';
                $tagdata = ee()->TMPL->swap_var_single('delete_link', $delete_link, $tagdata);    
            }
            else
            {
                $tagdata = ee()->TMPL->swap_var_single('delete_link', '', $tagdata);    
            }
            
            $tagdata = ee()->TMPL->swap_var_single('count', $i, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('absolute_count', $start+$i, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('message_id', $row['bulletin_id'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_member_id', $row['member_id'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_username', $row['username'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_screen_name', $row['screen_name'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_email', $row['email'], $tagdata);
            $avatar_url = ($row['avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['avatar_filename'] : '';
            $tagdata = ee()->TMPL->swap_var_single('sender_avatar_url', $avatar_url, $tagdata);
            $photo_url = ($row['photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['photo_filename'] : '';
            $tagdata = ee()->TMPL->swap_var_single('sender_photo_url', $photo_url, $tagdata);
            if (preg_match_all("#".LD."message_date format=[\"|'](.+?)[\"|']".RD."#", $tagdata, $matches))
    		{
                foreach ($matches['1'] as $match)
    			{
    				$tagdata = preg_replace("#".LD."message_date format=.+?".RD."#", $this->_format_date($match, $row['bulletin_date']), $tagdata, true);
    			}
    		}
            
            ee()->load->library('typography');
            ee()->typography->initialize(array(
                'highlight_code'	=> TRUE)
            );

            $message = ee()->typography->parse_type(stripslashes($row['bulletin_message']), 
									 		 								  array(
									 		 								  'text_format'	=> 'xhtml',
									 		 								  'html_format'	=> ee()->config->item('prv_msg_html_format'),
									 		 								  'auto_links'	=> ee()->config->item('prv_msg_auto_links'),
									 		 								  'allow_img_url' => 'y'
									 		 								 ));
            if (ee()->config->item('enable_censoring') == 'y' && ee()->config->item('censored_words') != '')
            {
                $message = ee()->typography->filter_censored_words($message);
            }
            
            $tagdata = ee()->TMPL->swap_var_single('message', $message, $tagdata);

            $out .= $tagdata;
             
        }
        
        $out = trim($out);
        
        if (ee()->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval(ee()->TMPL->fetch_param('backspace'));
            $out = substr($out, 0, - $backspace);
        }
        
        $out = $this->_process_pagination($total, $this->perpage, $start, $basepath, $out, $paginate, $paginate_tagdata);
        
        
        
        return $out;
      
    }
    
    
    
    
    function delete_bulletin()
    {
        if (ee()->session->userdata('group_id')!=1 && ee()->session->userdata('can_send_bulletins') != 'y')
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
        
        ee()->db->select('sender_id')
                    ->from('member_bulletin_board')
                    ->where('bulletin_id', ee()->input->get_post('message_id'));
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('message_no_bulletins');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('message_no_bulletins')));
        }
        
        if (ee()->session->userdata('group_id')!=1 && ee()->session->userdata('member_id') != $q->row('sender_id'))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
        
        ee()->db->where('bulletin_id', ee()->input->get_post('message_id'));
        ee()->db->delete('member_bulletin_board');
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo ee()->lang->line('bulletin_deleted');
            exit();
        }
    
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> ee()->lang->line('bulletin_deleted'),
        				'link'		=> array('javascript:history.go(-1)', ee()->lang->line('back'))
        			 );
			
		ee()->output->show_message($data);
    }
    
    
    
    
    
    function bulletin_compose()
    {
        if (ee()->session->userdata('group_id')!=1 && ee()->session->userdata('can_send_bulletins') != 'y')
        {
            if (ee()->TMPL->fetch_param('silent')=='yes')
            {
                return ee()->TMPL->no_results();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
        
        $tagdata = ee()->TMPL->tagdata;
        ee()->load->helper('form');
        
        if ( preg_match_all("/".LD."recipients.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/recipients".RD."/s", $tagdata, $tmp)!=0)
        {
            $recipients_tagdata_orig = $tmp[3][0];
            $recipients_out = '';

            ee()->db->select('group_id, group_title');
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->where('include_in_memberlist', 'y');
            ee()->db->where_not_in('group_id', array(2,3,4));
            $q = ee()->db->get('member_groups');
            $result = $q->result_array();
            $all_groups = array('group_id'=>0, 'group_title'=>ee()->lang->line('mbr_all_member_groups'));
            array_unshift($result, $all_groups);
            
            foreach ($result as $row)
            {
                $recipients_tagdata = $recipients_tagdata_orig;
                $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_id', $row['group_id'], $recipients_tagdata);
                $recipients_tagdata = ee()->TMPL->swap_var_single('group_id', $row['group_id'], $recipients_tagdata);
                $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_name', $row['group_title'], $recipients_tagdata);
                $recipients_tagdata = ee()->TMPL->swap_var_single('group_name', $row['group_title'], $recipients_tagdata);
                $recipients_out .= $recipients_tagdata;
            }
            
            $backspace_var = $tmp[2][0];
            $recipients_out = trim($recipients_out);
            $recipients_out	= substr($recipients_out, 0, strlen($recipients_out)-$backspace_var);
            
            $tagdata = str_replace($tmp[0][0], $recipients_out, $tagdata);
        }

        return $this->_form($tagdata, 'send_bulletin');
    }
    
    
    
    
    
    function send_bulletin()
    {
        if (ee()->session->userdata('group_id')!=1 && ee()->session->userdata('can_send_bulletins') != 'y')
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
        
        ee()->db->select('group_id');
        ee()->db->where('include_in_memberlist', 'y');
        ee()->db->where_not_in('group_id', array(2,3,4));
        $q = ee()->db->get('member_groups');
        
        $recipients = array();
        $valid_recipients = array();
        foreach ($q->result_array() as $row)
        {
            $valid_recipients[] = $row['group_id'];
        } 
        if (!is_array($_POST['recipients'])) $_POST['recipients'] = array($_POST['recipients']);
        foreach ($_POST['recipients'] as $recipient)
        {
            if ($recipient==0 )
            {
                $recipients = $valid_recipients;
                break;
            }
            if (in_array($recipient, $valid_recipients))
            {
                $recipients[] = $recipient;
            }
        }
        
        if (empty($recipients))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('no_recipients');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('empty_recipients_field')));
        }
        
        if (empty($_POST['message']))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('empty_body_field');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('empty_body_field')));
        }
    	
        if (version_compare(APP_VER, '3.0.0', '<'))	
        {
            if (ee()->security->secure_forms_check(ee()->input->post('XID')) == FALSE)
    		{
    			if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('not_authorized');
                    exit();
                }
                return ee()->output->show_user_error('submission', array(ee()->lang->line('not_authorized')));
    		}
        }
        
        $data['sender_id'] = ee()->session->userdata('member_id');
        $data['bulletin_date'] = ee()->localize->now;
        $data['hash'] = ee()->functions->random('alnum', 10);
        $expires = (version_compare(APP_VER, '2.6.0', '<'))?ee()->localize->convert_human_date_to_gmt($_POST['bulletin_expires']):ee()->localize->string_to_timestamp($_POST['bulletin_expires']);
        $data['bulletin_expires'] = ($expires!=0)?$expires:(ee()->localize->now + 30*24*60*60);
        $data['bulletin_message'] = ee()->input->post('message');
        
        foreach($recipients as $group_id)
		{
			$data['bulletin_group'] = $group_id;
			
			ee()->db->insert('member_bulletin_board', $data);
            
            ee()->db->update('members', array('last_bulletin_date'=>ee()->localize->now), array('group_id'=>$group_id));
            
		}
		
		// -------------------------------------------
		// 'messaging_bulletin_sent' hook.
		//  - Do something after bulletin is sent
		//
			if (ee()->extensions->active_hook('messaging_bulletin_sent') === TRUE)
			{
				$edata = ee()->extensions->call('messaging_bulletin_sent', $data['bulletin_message'], $recipients);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
        // -------------------------------------------
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo ee()->lang->line('bulletin_success');
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
            
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> ee()->lang->line('bulletin_success'),
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
			
		ee()->output->show_message($data);
    }    
    
    
    
    
    
    function add_to_buddies_link($type='buddy') 
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $member_id = ee()->TMPL->fetch_param('member_id');
        if ($member_id=='' || $member_id==0)
        {
            return ee()->TMPL->no_results();
        }
        
        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_site_index()),'/');
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_current_uri()),'/');
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->TMPL->fetch_param('return')),'/');
        }
        else
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='list_member'");
        $add_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id').'&type='.$type.'&member_id='.$member_id.'&RET='.$return;
        if (ee()->TMPL->fetch_param('ajax')=='yes') $add_url .= '&ajax=yes';
        
        if (ee()->TMPL->fetch_param('url_only')=='yes') return $add_url;
        
        $text = ($type=='buddy')?ee()->lang->line('add_buddy'):ee()->lang->line('add_block');
        $add_link = '<a href="'.$add_url.'" class="buddy_add_link">'.$text.'</a>';
        return $add_link;
    }
    
    
    function add_to_blocked_link() 
    {
        return $this->add_to_buddies_link('blocked');
    }
    
    
    
    
    
    function buddies($type='buddy') 
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $start = 0;
        $paginate = (ee()->TMPL->fetch_param('paginate')=='top')?'top':((ee()->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        if (ee()->TMPL->fetch_param('limit')!='') $this->perpage = ee()->TMPL->fetch_param('limit');
        
        $basepath = ee()->functions->create_url(ee()->uri->uri_string);
        $query_string = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = reduce_double_slashes(str_replace($match[0], '', $basepath));
		}
		
		ee()->db->select('COUNT(*) AS cnt')
                    ->from('exp_message_listed')                  
                    ->where('member_id', ee()->session->userdata('member_id'))
                    ->where('listed_type', $type);
        if (ee()->TMPL->fetch_param('buddy_id')!='')
        {
        	ee()->db->where('listed_member', ee()->TMPL->fetch_param('buddy_id'));
        }
        $q = ee()->db->get();
        if ($q->num_rows()==0 || $q->row('cnt')==0)
        {
            return ee()->TMPL->no_results();
        }
        else
        {
        	$total = $q->row('cnt');
        }
		
		$tagdata_orig = ee()->TMPL->tagdata;
        
        $tagdata_orig = ee()->TMPL->swap_var_single('total_results', $total, $tagdata_orig);
        $tagdata_orig = ee()->TMPL->swap_var_single('buddy_total_results', $total, $tagdata_orig);
        
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata_orig, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata_orig = str_replace($tmp[0][0], '', $tagdata_orig);
        }
        
        
        ee()->db->select('exp_members.*')
                    ->from('exp_members')
                    ->join('exp_message_listed', 'exp_message_listed.listed_member=exp_members.member_id', 'left')                    
                    ->where('exp_message_listed.member_id', ee()->session->userdata('member_id'))
                    ->where('listed_type', $type);
        if (ee()->TMPL->fetch_param('buddy_id')!='')
        {
        	ee()->db->where('listed_member', ee()->TMPL->fetch_param('buddy_id'));
        }
        ee()->db->limit($this->perpage, $start);
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $output = '';
        
        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_site_index()),'/');
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_current_uri()),'/');
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->TMPL->fetch_param('return')),'/');
        }
        else
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='unlist_member'");
        $remove_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id').'&type='.$type.'&RET='.$return;
        if (ee()->TMPL->fetch_param('ajax')=='yes') $remove_url .= '&ajax=yes';
        
        $text = ($type=='buddy')?ee()->lang->line('remove_buddy'):ee()->lang->line('remove_block');
        
        $cnt = 0;
        
        foreach($q->result_array() as $row)
		{
			$tagdata = $tagdata_orig;
			$cnt++;
			$row['count'] = $cnt;
			$row['absolute_count'] = $start+$cnt;
			
			foreach ($row as $key=>$val)
			{
                $tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				$tagdata	= ee()->TMPL->swap_var_single('buddy_'.$key, $val, $tagdata);  
				
				if ($key=='avatar_filename')
				{
					$avatar_url = ($val != '') ? ee()->config->slash_item('avatar_url').$val : '';
		            $tagdata = ee()->TMPL->swap_var_single('avatar_url', $avatar_url, $tagdata);
		            $tagdata = ee()->TMPL->swap_var_single('buddy_avatar_url', $avatar_url, $tagdata);
				}
	            if ($key=='photo_filename')
				{
		            $photo_url = ($val != '') ? ee()->config->slash_item('photo_url').$val : '';
		            $tagdata = ee()->TMPL->swap_var_single('photo_url', $photo_url, $tagdata);
		            $tagdata = ee()->TMPL->swap_var_single('buddy_photo_url', $photo_url, $tagdata);
    			}
                
                $tagdata = ee()->TMPL->swap_var_single('remove_url', $remove_url.'&member_id='.$row['member_id'], $tagdata); 
                $remove_link = '<a href="'.$remove_url.'&member_id='.$row['member_id'].'" class="buddy_remove_link">'.$text.'</a>';
                $tagdata = ee()->TMPL->swap_var_single('remove_link', $remove_link, $tagdata);  
			}
			$output		.= $tagdata;				
		}
		
		//$output	= ee()->TMPL->swap_var_single('total_results', $cnt, $output);
		//$output	= ee()->TMPL->swap_var_single('buddy_total_results', $cnt, $output);  
		
		
		$output = trim($output);
        
        if (ee()->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval(ee()->TMPL->fetch_param('backspace'));
            $output = substr($output, 0, - $backspace);
        }
        
        $output = $this->_process_pagination($total, $this->perpage, $start, $basepath, $output, $paginate, $paginate_tagdata);
        
        return $output;
    }
 
 
 
 
    function check_buddy_state() 
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
		
		$member_id = ee()->TMPL->fetch_param('member_id');
        if ($member_id=='' || $member_id==0)
        {
            return ee()->TMPL->no_results();
        }
        
        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_site_index()),'/');
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_current_uri()),'/');
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->TMPL->fetch_param('return')),'/');
        }
        else
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        
        $vars = array(array(
				'blocked' 	=> false,
				'buddy'		=> false,
				'remove_url'=> '',
				'remove_link'=>''
			));
        
        
		ee()->db->select('listed_type')
                    ->from('message_listed')
                    ->where('member_id', ee()->session->userdata('member_id'))
                    ->where('listed_member', $member_id);
        $q = ee()->db->get();
        if ($q->num_rows()>0)
        {
            foreach($q->result_array() as $row)
			{
				$vars[0][$row['listed_type']] = true;	
				$type = $row['listed_type'];
				
				$act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='unlist_member'");
		        $vars[0]['remove_url'] = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id').'&type='.$type.'&member_id='.$member_id.'&RET='.$return;
		        if (ee()->TMPL->fetch_param('ajax')=='yes') $vars[0]['remove_url'] .= '&ajax=yes';
		        
		        $text = ($type=='buddy')?ee()->lang->line('remove_buddy'):ee()->lang->line('remove_block');

                $vars[0]['remove_link'] = '<a href="'.$vars[0]['remove_url'].'" class="buddy_remove_link">'.$text.'</a>';
			}
        }

        $output = ee()->TMPL->parse_variables(trim(ee()->TMPL->tagdata), $vars);
        
        return $output;
    }
 
 
    
    
    
    function list_member() 
    {
        
        if (ee()->session->userdata('member_id')==0)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('must_be_logged_in');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('must_be_logged_in')));
        }
        
        if (ee()->input->get_post('member_id')=='' || ee()->input->get_post('member_id')==0) 
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('mbr_id_not_found');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('mbr_id_not_found')));
        }
        
        $type = (ee()->input->get_post('type')=='blocked')?'blocked':'buddy';
        
        ee()->db->select('listed_id')
                    ->from('message_listed')
                    ->where('member_id', ee()->session->userdata('member_id'))
                    ->where('listed_member', ee()->input->get_post('member_id'))
                    ->where('listed_type', $type)
                    ->limit(1);
        $q = ee()->db->get();
        if ($q->num_rows()>0)
        {
            $text = ($type=='buddy')?ee()->lang->line('aleady_listed_buddy'):ee()->lang->line('aleady_listed_blocked');
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$text;
                exit();
            }
            return ee()->output->show_user_error('general', $text);
        }
        
        ee()->db->select('member_id')
                    ->from('members')
                    ->where('member_id', ee()->input->get_post('member_id'))
                    ->limit(1);
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('mbr_id_not_found');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('mbr_id_not_found')));
        }
        
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
        ee()->db->where('listed_member', ee()->input->get_post('member_id'));
        ee()->db->delete('message_listed');
        
        $data = array(
                'member_id' => ee()->session->userdata('member_id'), 
                'listed_member' => ee()->input->get_post('member_id'), 
                'listed_type' => $type
            );
        ee()->db->insert('message_listed', $data);
        
        // -------------------------------------------
		// 'messaging_member_listed' hook.
		//  - Do something when member is added to buddies/blocked list
		//
			if (ee()->extensions->active_hook('messaging_member_listed') === TRUE)
			{
				$edata = ee()->extensions->call('messaging_member_listed', $member_id, $listed_member, $type);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
        // -------------------------------------------
        
        $text = ($type=='buddy')?ee()->lang->line('listed_buddy'):ee()->lang->line('listed_blocked');
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo $text;
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
    
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> $text,
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
			
		ee()->output->show_message($data);

    }
    
    
    function unlist_member() 
    {
        if (ee()->session->userdata('member_id')==0)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('must_be_logged_in');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('must_be_logged_in')));
        }
        
        if (ee()->input->get_post('member_id')=='' || ee()->input->get_post('member_id')==0) 
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('mbr_id_not_found');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('mbr_id_not_found')));
        }
        
        $type = (ee()->input->get_post('type')=='blocked')?'blocked':'buddy';
        
        ee()->db->select('listed_id')
                    ->from('message_listed')
                    ->where('member_id', ee()->session->userdata('member_id'))
                    ->where('listed_member', ee()->input->get_post('member_id'))
                    ->where('listed_type', $type);
        $list_q = ee()->db->get();
        if ($list_q->num_rows()==0)
        {
            $text = ($type=='buddy')?ee()->lang->line('not_listed_buddy'):ee()->lang->line('not_listed_blocked');
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$text;
                exit();
            }
            return ee()->output->show_user_error('general', $text);
        }
        
        foreach ($list_q->result_array() as $row)
        {
            ee()->db->where('listed_id', $row['listed_id']);
            ee()->db->delete('message_listed');
        }
        
        $text = ($type=='buddy')?ee()->lang->line('unlisted_buddy'):ee()->lang->line('unlisted_blocked');
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo $text;
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
    
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> $text,
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
			
		ee()->output->show_message($data);
    }
    
    
    
    function blocked()
    {
        return $this->buddies('blocked');
    }
    
    
    
    
    
    
    
    function folders()
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        ee()->db->select('*')
                    ->from('message_folders')
                    ->where('member_id', ee()->session->userdata('member_id'));
        $query = ee()->db->get();
		if ($query->num_rows() == 0)
		{
			ee()->db->insert('message_folders', array('member_id' => ee()->session->userdata('member_id')));
            ee()->db->select('*')
                    ->from('message_folders')
                    ->where('member_id', ee()->session->userdata('member_id'));
            $query = ee()->db->get();
		}
        
        if (ee()->TMPL->fetch_param('folder_id')!='')
        {
			$folder_id_param = ee()->TMPL->fetch_param('folder_id');
            if (!is_numeric($folder_id_param) || $folder_id_param<0 || $folder_id_param>10 || $query->row('folder'.$folder_id_param.'_name')=='')
            {
                return ee()->TMPL->no_results();
            }
        }
        
        if (ee()->TMPL->fetch_param('folder')!='')
        {
            $folder = strtolower(ee()->TMPL->fetch_param('folder'));
            if ($folder=='trash' || $folder=='deleted')
            {
            	$folder_id_param = 0;
            }
        }
        
        $named_folders = array();
        $unnamed_folders = array();
        $folder_names = array();
        $messages_count = array();
	
		for($i=1; $i <= 10; $i++)
		{
			$folder_names[$i] = $query->row('folder'.$i.'_name');
            if ($query->row('folder'.$i.'_name')!='')
            {
                $named_folders[] = $i;
                if (isset($folder) && strtolower($query->row('folder'.$i.'_name'))==$folder)
                {
                	$folder_id_param = $i;
                }
            }
            else
            {
                $unnamed_folders[] = $i;
            }
		}
        
        $folders = $named_folders;

        if (ee()->TMPL->fetch_param('show_new')=='all')
        {
           $folders = array_merge($folders, $unnamed_folders);
        }
        else if (ee()->TMPL->fetch_param('show_new')=='one')
        {
            array_push($folders, $unnamed_folders[0]);
        }
        
        if (ee()->TMPL->fetch_param('exclude_trash')!='yes')
        {
            $folders[] = 0;
            $folder_names[0] = ee()->lang->line('deleted_messages');
        }
		
        if (!isset($folder_id_param) || $folder_id_param!=0)
        {
            ee()->db->select('COUNT(*) AS count, message_folder')
                        ->from('message_copies')
                        ->where('recipient_id', ee()->session->userdata('member_id'))
                        ->where('message_deleted', 'n')
                        ->group_by('message_folder');
    		$messages = ee()->db->get();
            
            foreach ($messages->result_array() as $row)
            {
                $messages_count[$row['message_folder']] = $row['count'];
            }
            
            
            
            
            
            ee()->db->select('COUNT(*) AS count, message_folder')
                        ->from('message_copies')
                        ->where('recipient_id', ee()->session->userdata('member_id'))
                        ->where('message_deleted', 'n')
                        ->where('message_read', 'n')
                        ->group_by('message_folder');
    		$unread_messages = ee()->db->get();
            
            foreach ($unread_messages->result_array() as $row)
            {
                $unread_count[$row['message_folder']] = $row['count'];
            }
        }
        
        if ((ee()->TMPL->fetch_param('exclude_trash')!='yes' && !isset($folder_id_param)) || (isset($folder_id_param) && $folder_id_param==0))
        {
            ee()->db->select('COUNT(*) AS count')
                        ->from('message_copies')
                        ->where('recipient_id', ee()->session->userdata('member_id'))
                        ->where('message_deleted', 'y');
    		$messages = ee()->db->get();
            $messages_count[0] = $messages->row('count');
            
            
            
            
            ee()->db->select('COUNT(*) AS count')
                        ->from('message_copies')
                        ->where('recipient_id', ee()->session->userdata('member_id'))
                        ->where('message_deleted', 'y')
						->where('message_read', 'n');
    		$unread_messages = ee()->db->get();
            $unread_count[0] = $unread_messages->row('count');
        }
	
        $out = '';
        
        if (isset($folder_id_param))
        {
            $folders = array($folder_id_param);
        }

        foreach($folders as $folder_id)
        {
            $tagdata = ee()->TMPL->tagdata;;
            $tagdata = ee()->TMPL->swap_var_single('folder_id', $folder_id, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('folder_name', $folder_names[$folder_id], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('messages_count', (isset($messages_count[$folder_id]))?$messages_count[$folder_id]:0, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('unread_count', (isset($unread_count[$folder_id]))?$unread_count[$folder_id]:0, $tagdata);
            $out .= $tagdata;
        }
        
        return $out;
	       
    }
    
    function edit_folders()
    {
        return $this->_form(ee()->TMPL->tagdata, 'save_folders');
    }
    
    function save_folders()
    {
        if (ee()->session->userdata('member_id')==0)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('must_be_logged_in');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('must_be_logged_in')));
        }

        if (ee()->input->post('folder0')==='' || ee()->input->post('folder1')=='' || ee()->input->post('folder2')=='')
        {
            ee()->db->select('*')
                        ->from('message_folders')
                        ->where('member_id', ee()->session->userdata('member_id'));
            $query = ee()->db->get();
            $folders = array();
            if (ee()->input->post('folder0')==='')
            {
                $folders[] = ee()->lang->line('deleted_messages');
            }
            if (ee()->input->post('folder1')=='')
            {
                $folders[] = $query->row('folder1_name');
            }
            if (ee()->input->post('folder2')=='')
            {
                $folders[] = $query->row('folder2_name');
            }
            $text = str_replace('%x', implode(' and ', $folders), ee()->lang->line('folder_cant_be_deleted'));
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$text;
                exit();
            }
            return ee()->output->show_user_error('general', $text);
        }
        
        if (version_compare(APP_VER, '3.0.0', '<'))	
        {
            if (ee()->security->secure_forms_check(ee()->input->post('XID')) == FALSE)
    		{
    			if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('not_authorized');
                    exit();
                }
                return ee()->output->show_user_error('submission', array(ee()->lang->line('not_authorized')));
    		}
        }
        
        $data = array();
        
        for($i=1; $i <= 10; $i++)
		{
            if ( ! isset($_POST['folder'.$i]) OR $_POST['folder'.$i] == '')
			{
				$data['folder'.$i.'_name'] = '';
				ee()->db->query("UPDATE exp_message_copies SET message_deleted = 'y' 
							WHERE recipient_id = '".ee()->session->userdata('member_id')."' AND message_folder = '{$i}'");
			}
			else
			{
				$data['folder'.$i.'_name'] = $this->_xss_clean($_POST['folder'.$i]);
			}
		}
        
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
        ee()->db->update('message_folders', $data);
                        
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo ee()->lang->line('folders_updated');
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
            
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> ee()->lang->line('folders_updated'),
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
			
		ee()->output->show_message($data);
        
    }
    
    
    function pm_thread()
    {
    	if (ee()->session->userdata('member_id')==0)
        {
        	return ee()->TMPL->no_results();
        }
		
		$message_id = ee()->TMPL->fetch_param('message_id');
    	if ($message_id == '')
    	{
    		return ee()->TMPL->no_results();
    	}
    	
    	ee()->db->select('exp_message_copies.sender_id, exp_message_copies.recipient_id, exp_message_data.message_subject');
		ee()->db->from('exp_message_copies');
        ee()->db->join('exp_message_data', 'exp_message_copies.message_id = exp_message_data.message_id', 'left');
        ee()->db->where('exp_message_copies.copy_id', $message_id);
        ee()->db->limit(1);
        $q = ee()->db->get();
    	
    	if ($q->num_rows()==0)
    	{
    		return ee()->TMPL->no_results();
    	}
    	
    	$sender_id = $q->row('sender_id');
    	$recipient_id = $q->row('recipient_id');
    	$subject = $q->row('message_subject');
    	if (strpos($subject, "Re: ")===0)
    	{
    		$subject = substr($subject, 4);
    	}

    	$messages = array();
    	$order_by = (ee()->TMPL->fetch_param('sort')=='desc')?'desc':'asc';
    	$query = ee()->db->select('exp_message_copies.message_id, message_subject')
					->from('exp_message_copies')
					->join('exp_message_data', 'exp_message_copies.message_id = exp_message_data.message_id', 'left')
					->where("message_subject LIKE '%".ee()->db->escape_str($subject)."' AND ((exp_message_copies.sender_id=$sender_id AND exp_message_copies.recipient_id=$recipient_id) OR (exp_message_copies.sender_id=$recipient_id AND exp_message_copies.recipient_id=$sender_id))")
					->order_by('message_date', $order_by)
					->get();

		foreach ($query->result_array() as $row)
		{
			$clean_subject = $row['message_subject'];
	    	if (strpos($clean_subject, "Re: ")===0)
	    	{
	    		$clean_subject = substr($clean_subject, 4);
	    	}
	    	if ($clean_subject==$subject)
	    	{
	    		$messages[] = $row['message_id'];
	    	}
		}
		
		$total = count($messages);
		$start = 0;
        $paginate = (ee()->TMPL->fetch_param('paginate')=='top')?'top':((ee()->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        if (ee()->TMPL->fetch_param('limit')!='') $this->perpage = ee()->TMPL->fetch_param('limit');
        
        $basepath = ee()->functions->create_url(ee()->uri->uri_string);
        $query_string = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = reduce_double_slashes(str_replace($match[0], '', $basepath));
		}
        
        $tagdata = ee()->TMPL->swap_var_single('total_results', $total, ee()->TMPL->tagdata);
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata = str_replace($tmp[0][0], '', $tagdata);
        }

    	$query = ee()->db->select('copy_id')
					->from('exp_message_copies')
					->where('recipient_id', ee()->session->userdata('member_id'))
					->where_in('message_id', $messages)
					->order_by('copy_id', $order_by)
					->limit($this->perpage, $start)
					->get();
							
		$out = '';
		$i = 0;
		$cond = array();
		
		if (ee()->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval(ee()->TMPL->fetch_param('backspace'));
            unset(ee()->TMPL->tagparams['backspace']);
        }
		
		foreach ($query->result_array() as $row)
		{
			$i++;
			$tmp =  ee()->TMPL->swap_var_single('count', $i, $tagdata);
			$tmp =  ee()->TMPL->swap_var_single('absolute_count', $i, $tmp);
			$tmp =  $this->private_messages($row['copy_id'], $tmp);
			$cond['current_message'] = ($row['copy_id']==$message_id)?true:false;
			$tmp = ee()->functions->prep_conditionals($tmp, $cond);
			$out .= $tmp;
		}
		
		if (isset($backspace))
        {
            $out = substr($out, 0, - $backspace);
        }
        
        
        $out = $this->_process_pagination($total, $this->perpage, $start, $basepath, $out, $paginate, $paginate_tagdata);
        
    	return $out;
    }
    
    
    //display list of messages in conversation mode
    function conversations()
    {
    	if (ee()->session->userdata('member_id')==0)
        {
        	return ee()->TMPL->no_results();
        }
		
		//do we have folder specified?
        $folder = (ee()->TMPL->fetch_param('folder')!='')?strtolower(ee()->TMPL->fetch_param('folder')):false;
        if ($folder===false)
        {
            $folder_id = (ee()->TMPL->fetch_param('folder_id')!='')?ee()->TMPL->fetch_param('folder_id'):1;
        }
        
        $start = 0;
        $paginate = (ee()->TMPL->fetch_param('paginate')=='top')?'top':((ee()->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        if (ee()->TMPL->fetch_param('limit')!='') $this->perpage = ee()->TMPL->fetch_param('limit');
        
        $basepath = ee()->functions->create_url(ee()->uri->uri_string);
        $query_string = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = reduce_double_slashes(str_replace($match[0], '', $basepath));
		}
		
		$tagdata = ee()->TMPL->tagdata;
        
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata = str_replace($tmp[0][0], '', $tagdata);
        }
                
    	//get all recipient/subject pairs
    	
    	
    	ee()->db->select('exp_message_copies.message_id, exp_message_copies.sender_id, exp_message_copies.recipient_id, exp_message_data.message_subject, exp_message_data.message_date');
		ee()->db->from('exp_message_copies');
        ee()->db->join('exp_message_data', 'exp_message_copies.message_id = exp_message_data.message_id', 'left');
        //recipient is current member        
        //exclude 'self-copies', they are of no use to us
        ee()->db->where('exp_message_copies.sender_id != exp_message_copies.recipient_id AND (exp_message_copies.recipient_id = '.ee()->session->userdata('member_id').' OR exp_message_copies.sender_id = '.ee()->session->userdata('member_id').')', null, false);
        
        
        //limit to certain folder?
        if (ee()->TMPL->fetch_param('combined')!==false)
        {
	        if ($folder == 'trash' || $folder == 'deleted' || (isset($folder_id) && $folder_id == 0))
			{
				ee()->db->where('exp_message_copies.message_deleted', 'y');
			}
			else
			{
	            if (!isset($folder_id))
	            {
	                //inbox - 1
	                //deleted - 0
	                //sent - 2
	                switch ($folder)
	                {
	                    case 'inbox':
	                        $folder_id = 1;
	                        break;
	                    case 'sent':
	                        $folder_id = 2;
	                        break;
	                    case 'deleted':
	                    case 'trash':
	                        $folder_id = 0;
	                        break;
	                    default:
	                        $sql = "SELECT * FROM exp_message_folders WHERE member_id=".ee()->session->userdata('member_id');
	                        $q = ee()->db->query($sql);
	                        $my_folders = $q->result_array();
	                        foreach ($my_folders[0] as $key=>$val)
	                        {
	                            if (strtolower($val) == $folder)
	                            {
	                                $folder_id = str_replace('folder', '', str_replace('_name', '', $key));
	                                break;
	                            }
	                        }
	                        break;
	                }
	            }
	            if (!isset($folder_id))
	            {
	                ee()->db->stop_cache();
	                ee()->db->flush_cache();
	                return ee()->TMPL->no_results();
	            }
	            ee()->db->where('exp_message_copies.message_folder', $folder_id);
	            ee()->db->where('exp_message_copies.message_deleted', 'n');
   			}
		}		
		else
		{
			ee()->db->where('exp_message_copies.message_deleted', 'n');
		}
		
		ee()->db->order_by('message_date', 'desc');
		
        $q = ee()->db->get();
    	
    	if ($q->num_rows()==0)
    	{
    		return ee()->TMPL->no_results();
    	}
    	
    	$conversations = array();
    	$conversations_data = array();
    	
    	foreach ($q->result_array() as $row)
    	{
    		if (strpos($row['message_subject'], "Re: ")===0)
	    	{
	    		$row['message_subject'] = substr($row['message_subject'], 4);
    		}
			$index = $row['sender_id']."::".$row['recipient_id']."::".$row['message_subject'];
			$alt_index = $row['recipient_id']."::".$row['sender_id']."::".$row['message_subject'];
			if (!isset($conversations[$index]) && !isset($conversations[$alt_index]))
			{
				$conversations[$index] = $row['message_id'];
				$conversations_data[$row['message_id']] = $row;
			}
    	}
    	
    	$total = count($conversations);
    	if ($total>$this->perpage)
    	{
    		$conversations = array_slice($conversations, $start, $this->perpage);
    	}
     	
     	$out = '';
		$i = 0;
		
		if (ee()->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval(ee()->TMPL->fetch_param('backspace'));
            unset(ee()->TMPL->tagparams['backspace']);
        }
     	
     	foreach ($conversations as $idx=>$message_id)
     	{
			$i++;
			$row_tagdata =  ee()->TMPL->swap_var_single('conversation_count', $i, $tagdata);
			$row_tagdata =  ee()->TMPL->swap_var_single('conversation_absolute_count', $start+$i, $row_tagdata);
			$row_tagdata =  ee()->TMPL->swap_var_single('conversation_total_results', $total, $row_tagdata);
            $row_tagdata =  ee()->TMPL->swap_var_single('total_results', count($conversations), $row_tagdata);
			$row_tagdata =  ee()->TMPL->swap_var_single('subject', $conversations_data[$message_id]['message_subject'], $row_tagdata);
			$query = ee()->db->select('exp_message_copies.message_status, exp_message_copies.message_id, exp_message_copies.message_received, exp_message_copies.message_read, exp_message_copies.copy_id,  exp_message_data.sender_id,  exp_message_data.message_date,  exp_message_data.message_subject, exp_message_data.message_body, exp_message_data.message_recipients, exp_message_data.message_cc, exp_message_data.message_attachments, sender.screen_name AS sender_screen_name, sender.username AS sender_username, sender.email AS sender_email, sender.avatar_filename AS sender_avatar_filename, sender.photo_filename AS sender_photo_filename, recipient.member_id AS recipient_member_id, recipient.screen_name AS recipient_screen_name, recipient.username AS recipient_username, recipient.email AS recipient_email, recipient.avatar_filename AS recipient_avatar_filename, recipient.photo_filename AS recipient_photo_filename')
					->from('exp_message_copies')
					->join('exp_message_data', 'exp_message_copies.message_id = exp_message_data.message_id', 'left')
					->join('exp_members AS recipient', 'recipient.member_id = exp_message_copies.recipient_id', 'left')	
					->join('exp_members AS sender', 'sender.member_id = exp_message_copies.sender_id', 'left')					
					->where("message_subject LIKE '%".ee()->db->escape_str($conversations_data[$message_id]['message_subject'])."' AND ((exp_message_copies.sender_id={$conversations_data[$message_id]['sender_id']} AND exp_message_copies.recipient_id={$conversations_data[$message_id]['recipient_id']}) OR (exp_message_copies.sender_id={$conversations_data[$message_id]['recipient_id']} AND exp_message_copies.recipient_id={$conversations_data[$message_id]['sender_id']}))")
					->order_by('message_date', 'desc')
					->get();
			$j = 0;
			
			if ( preg_match_all("/".LD."conversation.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/conversation".RD."/s", $row_tagdata, $tmp)!=0)
	        {
	            $conversation_tagdata_orig = $tmp[3][0];
	            $backspace_var = $tmp[2][0];
                $conversation_out = '';
                
                ee()->load->library('typography');
	            ee()->typography->initialize(array(
	                'highlight_code'	=> TRUE)
	            );
                
	        }
			
			foreach ($query->result_array() as $row)
			{
				$j++; 
				$row_tagdata =  ee()->TMPL->swap_var_single('last_message_id', $row['copy_id'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_sender_member_id', $row['sender_id'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_sender_username', $row['sender_username'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_sender_screen_name', $row['sender_screen_name'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_sender_email', $row['sender_email'], $row_tagdata);
				$avatar_url = ($row['sender_avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['sender_avatar_filename'] : '';
		        $row_tagdata = ee()->TMPL->swap_var_single('last_sender_avatar_url', $avatar_url, $row_tagdata);
		        $photo_url = ($row['sender_photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['sender_photo_filename'] : '';
		        $row_tagdata = ee()->TMPL->swap_var_single('last_sender_photo_url', $photo_url, $row_tagdata);
		        $row_tagdata =  ee()->TMPL->swap_var_single('last_recipient_member_id', $row['recipient_member_id'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_recipient_username', $row['recipient_username'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_recipient_screen_name', $row['recipient_screen_name'], $row_tagdata);
				$row_tagdata =  ee()->TMPL->swap_var_single('last_recipient_email', $row['recipient_email'], $row_tagdata);
				$avatar_url = ($row['recipient_avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['recipient_avatar_filename'] : '';
		        $row_tagdata = ee()->TMPL->swap_var_single('last_recipient_avatar_url', $avatar_url, $row_tagdata);
		        $photo_url = ($row['recipient_photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['recipient_photo_filename'] : '';
		        $row_tagdata = ee()->TMPL->swap_var_single('last_recipient_photo_url', $photo_url, $row_tagdata);
		        if (preg_match_all("#".LD."last_message_date format=[\"|'](.+?)[\"|']".RD."#", $row_tagdata, $matches))
				{
		            foreach ($matches['1'] as $match)
					{
						$row_tagdata = preg_replace("#".LD."last_message_date format=.+?".RD."#", $this->_format_date($match, $row['message_date']), $row_tagdata, true);
					}
				}
				
				if (isset($conversation_out))
				{
					$conversation_tagdata = $conversation_tagdata_orig;
                    
                    $cond = array();
		            $conversation_tagdata = ee()->TMPL->swap_var_single('count', $j, $conversation_tagdata);
		            $cond['read'] = false;
		            $cond['unread'] = true;
		            $cond['replied'] = false;
		            $cond['forwarded'] = false;
		            if ($row['message_read']=='y')
		            {
		                $cond['read'] = true;
		                $cond['unread'] = false;
		            }
		            if ($row['message_status']=='replied') 
		            {
		                $cond['replied'] = true;
		            }
		            if ($row['message_status']=='forwarded') 
		            {
		                $cond['forwarded'] = true;
		            }
		            $conversation_tagdata = ee()->TMPL->swap_var_single('message_id', $row['copy_id'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_member_id', $row['sender_id'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_username', $row['sender_username'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_screen_name', $row['sender_screen_name'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_email', $row['sender_email'], $conversation_tagdata);
		            $avatar_url = ($row['sender_avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['sender_avatar_filename'] : '';
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_avatar_url', $avatar_url, $conversation_tagdata);
		            $photo_url = ($row['sender_photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['sender_photo_filename'] : '';
		            $conversation_tagdata = ee()->TMPL->swap_var_single('sender_photo_url', $photo_url, $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_member_id', $row['recipient_member_id'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_username', $row['recipient_username'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_screen_name', $row['recipient_screen_name'], $conversation_tagdata);
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_email', $row['recipient_email'], $conversation_tagdata);
		            $avatar_url = ($row['recipient_avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['recipient_avatar_filename'] : '';
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_avatar_url', $avatar_url, $conversation_tagdata);
		            $photo_url = ($row['recipient_photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['recipient_photo_filename'] : '';
		            $conversation_tagdata = ee()->TMPL->swap_var_single('recipient_photo_url', $photo_url, $conversation_tagdata);

		            if (preg_match_all("#".LD."message_date format=[\"|'](.+?)[\"|']".RD."#", $conversation_tagdata, $matches))
		    		{
		    			foreach ($matches['1'] as $match)
		    			{
		    				$conversation_tagdata = preg_replace("#".LD."message_date format=.+?".RD."#", $this->_format_date($match, $row['message_date']), $conversation_tagdata, true);
		    			}
		    		}
		            
		            //if message body is displayed we update the status
		            if (strpos($conversation_tagdata, LD.'message'.RD)!==false && isset($row['message_body']))
		            {
		                   
		                
						$message = ee()->typography->parse_type(stripslashes($row['message_body']), 
		    									 		 								  array(
		    									 		 								  'text_format'	=> 'xhtml',
		    									 		 								  'html_format'	=> ee()->config->item('prv_msg_html_format'),
		    									 		 								  'auto_links'	=> ee()->config->item('prv_msg_auto_links'),
		    									 		 								  'allow_img_url' => 'y'
		    									 		 								 ));
		                $conversation_tagdata = ee()->TMPL->swap_var_single('message', $message, $conversation_tagdata);
		
		                if ($row['message_read']!='y' && ee()->TMPL->fetch_param('mark_read')!='no' && $row['recipient_member_id']==ee()->session->userdata('member_id'))
		                {
		                    $data = array();
		                    $data['message_read'] = 'y';
		                    $data['message_time_read'] = ee()->localize->now;
		                    ee()->db->where('copy_id', $row['copy_id']);
		                    ee()->db->update('message_copies', $data);
		                    ee()->session->userdata['private_messages']--;
		                    ee()->db->where('member_id', ee()->session->userdata('member_id'));
		                    ee()->db->update('members', array('private_messages'=>ee()->session->userdata['private_messages']));
		                }
		            }
		            
		            if ($row['message_attachments']=='y') 
		            {
		                $cond['has_attachment'] = true;
		                $cond['has_attachments'] = true;
		            }
		            else
		            {
		                $cond['has_attachment'] = false;
		                $cond['has_attachments'] = false;
		            }
                    
                    $conversation_tagdata = ee()->functions->prep_conditionals($conversation_tagdata, $cond);
                                        
                    $conversation_out .= $conversation_tagdata;
				}
				
			}
			
			if (isset($conversation_out))
			{
				$conversation_out = trim($conversation_out);
	            $conversation_out	= substr($conversation_out, 0, strlen($conversation_out)-$backspace_var);
	            
	            $row_tagdata = str_replace($tmp[0][0], $conversation_out, $row_tagdata);
    		}
			
			$row_tagdata =  ee()->TMPL->swap_var_single('messages_in_conversation', $j, $row_tagdata);					

			$out .= $row_tagdata;
		}
		
		$out = ee()->TMPL->parse_globals($out);
		
		if (isset($backspace))
        {
            $out = substr($out, 0, - $backspace);
        }
        
        $out = $this->_process_pagination($total, $this->perpage, $start, $basepath, $out, $paginate, $paginate_tagdata);

    	return $out;
    }
    
    
    function pm()
    {
        return $this->private_messages();
    }
    
    function pms()
    {
        return $this->private_messages();
    }



    //display all private messages
    function private_messages($message_id=false, $tagdata='')
    {
        $embedded_mode = ($tagdata!='') ? true : false;
				
		if (ee()->session->userdata('member_id')==0)
        {
        	return ee()->TMPL->no_results();
        }
		
		//do we have folder specified?
        $folder = (ee()->TMPL->fetch_param('folder')!='')?strtolower(ee()->TMPL->fetch_param('folder')):false;
        if ($folder===false)
        {
            $folder_id = (ee()->TMPL->fetch_param('folder_id')!='')?ee()->TMPL->fetch_param('folder_id'):1;
        }
        
        //do we have message ID specified?
        if ($message_id===false)
        {
	        if (ee()->TMPL->fetch_param('message_id')!='')
	        {
	            $message_id = ee()->TMPL->fetch_param('message_id');
	        }
	        else
	        {
	            $message_id = false;
	        }
        }
        
        $start = 0;
        $paginate = (ee()->TMPL->fetch_param('paginate')=='top')?'top':((ee()->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        if (ee()->TMPL->fetch_param('limit')!='') $this->perpage = ee()->TMPL->fetch_param('limit');
        
        $basepath = ee()->functions->create_url(ee()->uri->uri_string);
        $query_string = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = reduce_double_slashes(str_replace($match[0], '', $basepath));
		}
        
        ee()->db->start_cache();
        ee()->db->select('exp_message_copies.message_status, exp_message_copies.message_id, exp_message_copies.message_received, exp_message_copies.message_read, exp_message_copies.copy_id,  exp_message_copies.recipient_id, exp_message_data.sender_id,  exp_message_data.message_date,  exp_message_data.message_subject, exp_message_data.message_body, exp_message_data.message_recipients, exp_message_data.message_cc, exp_message_data.message_attachments, exp_members.screen_name, exp_members.username, exp_members.email, exp_members.avatar_filename, exp_members.photo_filename');
		ee()->db->from('exp_message_copies');
        ee()->db->join('exp_message_data', 'exp_message_copies.message_id = exp_message_data.message_id', 'left');
        ee()->db->join('exp_members', 'exp_members.member_id = exp_message_copies.sender_id', 'left');
        
		if ($message_id === false)
        {
    		
			ee()->db->where('exp_message_copies.recipient_id', ee()->session->userdata('member_id'));
			
			if ($folder == 'trash' || $folder == 'deleted' || (isset($folder_id) && $folder_id == 0))
    		{
    			ee()->db->where('exp_message_copies.message_deleted', 'y');
    		}
    		else
    		{
                if (!isset($folder_id))
                {
                    //inbox - 1
                    //deleted - 0
                    //sent - 2
                    switch ($folder)
                    {
                        case 'inbox':
                            $folder_id = 1;
                            break;
                        case 'sent':
                            $folder_id = 2;
                            break;
                        case 'deleted':
                        case 'trash':
                            $folder_id = 0;
                            break;
                        default:
                            $sql = "SELECT * FROM exp_message_folders WHERE member_id=".ee()->session->userdata('member_id');
                            $q = ee()->db->query($sql);
                            $my_folders = $q->result_array();
                            foreach ($my_folders[0] as $key=>$val)
                            {
                                if (strtolower($val) == $folder)
                                {
                                    $folder_id = str_replace('folder', '', str_replace('_name', '', $key));
                                    break;
                                }
                            }
                            break;
                    }
                }
                if (!isset($folder_id))
                {
                    ee()->db->stop_cache();
                    ee()->db->flush_cache();
                    return ee()->TMPL->no_results();
                }
                ee()->db->where('exp_message_copies.message_folder', $folder_id);
                ee()->db->where('exp_message_copies.message_deleted', 'n');
    		}		
		}
        else
        {
            ee()->db->where('exp_message_copies.copy_id', $message_id);
        }
        ee()->db->where('exp_message_data.message_status', 'sent');	
        $order_by = (ee()->TMPL->fetch_param('sort')=='asc')?'asc':'desc';
        ee()->db->order_by('exp_message_data.message_date', $order_by);
        ee()->db->stop_cache();
        
        if ($message_id === false)
        {
            $total = ee()->db->count_all_results();
            ee()->db->limit($this->perpage, $start);
        }
        else
        {
            $total = 1;
        }

        $query = ee()->db->get();

        ee()->db->flush_cache();
        
        if ($query->num_rows()==0)
        {
            return ee()->TMPL->no_results();
        }
        
        if ($message_id !== false)
        {
            if ($query->row('sender_id') != ee()->session->userdata('member_id') && $query->row('recipient_id') != ee()->session->userdata('member_id'))
            {
            	return ee()->TMPL->no_results();
            }
        }
        
        $tagdata_orig = ($tagdata!='') ? $tagdata : ee()->TMPL->tagdata;
        
        $tagdata_orig = ee()->TMPL->swap_var_single('total_results', $total, $tagdata_orig);
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata_orig, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata_orig = str_replace($tmp[0][0], '', $tagdata_orig);
        }

        $out = '';
        $i = 0;
        
        $received = array();
        
        $all_recipients = array();
        
        $delete_act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='move_pm'");
        $delete_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$delete_act->row('action_id').'&delete=y';
        if (ee()->TMPL->fetch_param('ajax')=='yes') $delete_url .= '&ajax=yes';
        
        $download_act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Messaging' AND method='display_attachment'");
        $download_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$download_act->row('action_id');
                
        ee()->load->library('typography');
        ee()->typography->initialize(array(
            'highlight_code'	=> TRUE)
        );
        
        foreach ($query->result_array() as $row)
        {
            $i++;
            $cond = array();
            $tagdata = $tagdata_orig;
            $tagdata = ee()->TMPL->swap_var_single('count', $i, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('absolute_count', $start+$i, $tagdata);
            $cond['read'] = false;
            $cond['unread'] = true;
            $cond['replied'] = false;
            $cond['forwarded'] = false;
            if ($row['message_read']=='y')
            {
                $cond['read'] = true;
                $cond['unread'] = false;
            }
            if ($row['message_status']=='replied') 
            {
                $cond['replied'] = true;
            }
            if ($row['message_status']=='forwarded') 
            {
                $cond['forwarded'] = true;
            }
            $tagdata = ee()->TMPL->swap_var_single('message_id', $row['copy_id'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_member_id', $row['sender_id'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_username', $row['username'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_screen_name', $row['screen_name'], $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('sender_email', $row['email'], $tagdata);
            $avatar_url = ($row['avatar_filename'] != '') ? ee()->config->slash_item('avatar_url').$row['avatar_filename'] : '';
            $tagdata = ee()->TMPL->swap_var_single('sender_avatar_url', $avatar_url, $tagdata);
            $photo_url = ($row['photo_filename'] != '') ? ee()->config->slash_item('photo_url').$row['photo_filename'] : '';
            $tagdata = ee()->TMPL->swap_var_single('sender_photo_url', $photo_url, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('subject', $row['message_subject'], $tagdata);
            if (preg_match_all("#".LD."message_date format=[\"|'](.+?)[\"|']".RD."#", $tagdata, $matches))
    		{
    			foreach ($matches['1'] as $match)
    			{
    				$tagdata = preg_replace("#".LD."message_date format=.+?".RD."#", $this->_format_date($match, $row['message_date']), $tagdata, true);
    			}
    		}
            
            if (ee()->TMPL->fetch_param('return')=='')
            {
                $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_site_index()),'/');
            }
            else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
            {
                $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->functions->fetch_current_uri()),'/');
            }
            else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
            {
                $return = '/'.ltrim(str_replace(ee()->config->item('site_url'), '', ee()->TMPL->fetch_param('return')),'/');
            }
            else
            {
                $return = ee()->TMPL->fetch_param('return');
            }

            $tagdata = ee()->TMPL->swap_var_single('delete_url', $delete_url.'&message_id='.$row['copy_id'].'&RET='.$return, $tagdata); 
            $delete_link = '<a href="'.$delete_url.'&message_id='.$row['copy_id'].'&RET='.$return.'" class="pm_delete_link">'.ee()->lang->line('messages_delete').'</a>';
            $tagdata = ee()->TMPL->swap_var_single('delete_link', $delete_link, $tagdata);   
            
            if ($folder_id==0)
            {
                $empty_trash_url = $delete_url.'&empty_trash=yes&RET='.$return;
                $empty_trash_link = '<a href="'.$delete_url.'&empty_trash=yes&RET='.$return.'" class="pm_empty_trash_link">'.ee()->lang->line('empty_trash').'</a>';
                
            }
            else
            {
                $empty_trash_url = '';
                $empty_trash_link = '';
            }
            $tagdata = ee()->TMPL->swap_var_single('empty_trash_url', $empty_trash_url, $tagdata);   
            $tagdata = ee()->TMPL->swap_var_single('empty_trash_link', $empty_trash_link, $tagdata);   
            
            //if message body is displayed we update the status
            if (strpos($tagdata, LD.'message'.RD)!==false && isset($row['message_body']))
            {
                   
                $message = ee()->typography->parse_type(stripslashes($row['message_body']), 
    									 		 								  array(
    									 		 								  'text_format'	=> 'xhtml',
    									 		 								  'html_format'	=> ee()->config->item('prv_msg_html_format'),
    									 		 								  'auto_links'	=> ee()->config->item('prv_msg_auto_links'),
    									 		 								  'allow_img_url' => 'y'
    									 		 								 ));
                $tagdata = ee()->TMPL->swap_var_single('message', $message, $tagdata);

                if ($row['message_read']!='y' && ee()->TMPL->fetch_param('mark_read')!='no')
                {
                    $data = array();
                    $data['message_read'] = 'y';
                    $data['message_time_read'] = ee()->localize->now;
                    ee()->db->where('copy_id', $row['copy_id']);
                    ee()->db->update('message_copies', $data);
                    ee()->session->userdata['private_messages']--;
                    ee()->db->where('member_id', ee()->session->userdata('member_id'));
                    ee()->db->update('members', array('private_messages'=>ee()->session->userdata['private_messages']));
                }
            }
            
            if ($row['message_received']=='n')
            {
                $received[] = $row['copy_id'];
            }
            
            if ($row['message_attachments']=='y') 
            {
                $cond['has_attachment'] = true;
                $cond['has_attachments'] = true;
            }
            else
            {
                $cond['has_attachment'] = false;
                $cond['has_attachments'] = false;
            }
            
            if ( preg_match_all("/".LD."attachments.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/attachments".RD."/s", $tagdata, $tmp)!=0)
            {
                $attachments_tagdata_orig = $tmp[3][0];
                $attachments_out = '';
    
                if ($row['message_attachments']=='y') 
                {
                    ee()->db->select('attachment_id, attachment_name, attachment_hash, attachment_extension, attachment_size')
                                ->from('message_attachments')
                                ->where('message_id', $row['message_id']);
                    $attachment_q = ee()->db->get();

         			if ($attachment_q->num_rows() > 0)
         			{
 
                        foreach ($attachment_q->result_array() as $attachment_row)
                        {
                            $attachments_tagdata = $attachments_tagdata_orig;
                            $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_id', $attachment_row['attachment_id'], $attachments_tagdata);
                            $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_name', $attachment_row['attachment_name'], $attachments_tagdata);
                            $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_size', $attachment_row['attachment_size'], $attachments_tagdata);
                            $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_extension', $attachment_row['attachment_extension'], $attachments_tagdata);
                            $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_hash', $attachment_row['attachment_hash'], $attachments_tagdata);
                            $attachments_tagdata = ee()->TMPL->swap_var_single('download_url', $download_url.'&aid='.$attachment_row['attachment_id'].'&hash='.$attachment_row['attachment_hash'], $attachments_tagdata);
                            
                            $attachments_out .= $attachments_tagdata;
                        }
                    }
                }
                
                $backspace_var = $tmp[2][0];
                $attachments_out = trim($attachments_out);
                $attachments_out	= substr($attachments_out, 0, strlen($attachments_out)-$backspace_var);
                
                $tagdata = str_replace($tmp[0][0], $attachments_out, $tagdata);
            }
            
            $cnt = 0;
            
            if ( ($recipients_preg=preg_match_all("/".LD."recipients.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/recipients".RD."/s", $tagdata, $tmp))!=0)
            {
				for ($j=0; $j<$recipients_preg; $j++)
				{
					$recipients_tagdata_orig = $tmp[3][$j];
	                $recipients_out = '';
	                $cnt = 0;
	    
	                $current_recipients_array = array();
	                $recipients_array = explode("|",$row['message_recipients']);

	                foreach ($recipients_array as $recipient_id)
	                {
	                    $cnt++;
						$recipients_tagdata = $recipients_tagdata_orig;
	                    if (!isset($all_recipients[$recipient_id]))
	                    {
	                        $all_recipients[$recipient_id] = new stdClass();
							ee()->db->select('member_id, username, screen_name, email, avatar_filename, photo_filename');
	                        ee()->db->where('member_id', $recipient_id);
	                        $recipient_q = ee()->db->get('members');

	                        if ($recipient_q->num_rows()==0)
	                        {
	                        	$all_recipients[$recipient_id]->member_id = 0;
					            $all_recipients[$recipient_id]->screen_name = lang('guest');
					            $all_recipients[$recipient_id]->username = '';
					            $all_recipients[$recipient_id]->email = '';
					            $all_recipients[$recipient_id]->avatar_url = '';
					            $all_recipients[$recipient_id]->photo_url = '';
                        	}
                        	else
                        	{				            
					            $all_recipients[$recipient_id]->member_id = $recipient_q->row('member_id');
					            $all_recipients[$recipient_id]->screen_name = $recipient_q->row('screen_name');
					            $all_recipients[$recipient_id]->username = $recipient_q->row('username');
					            $all_recipients[$recipient_id]->email = $recipient_q->row('email');
					            $all_recipients[$recipient_id]->avatar_url = ($recipient_q->row('avatar_filename') != '') ? ee()->config->slash_item('avatar_url').$recipient_q->row('avatar_filename') : '';
					            $all_recipients[$recipient_id]->photo_url = ($recipient_q->row('photo_filename') != '') ? ee()->config->slash_item('photo_url').$recipient_q->row('photo_filename') : '';
							}
	    				}
	
						$recipients_tagdata = ee()->TMPL->swap_var_single('recipient_member_id', $all_recipients[$recipient_id]->member_id, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_screen_name', $all_recipients[$recipient_id]->screen_name, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_username', $all_recipients[$recipient_id]->username, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_email', $all_recipients[$recipient_id]->email, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_avatar_url', $all_recipients[$recipient_id]->avatar_url, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_photo_url', $all_recipients[$recipient_id]->photo_url, $recipients_tagdata);
	                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_count', $cnt, $recipients_tagdata);
	
	                    $recipients_out .= $recipients_tagdata;
	                }
	                
	                $backspace_var = $tmp[2][$j];
	                $recipients_out = trim($recipients_out);
	                $recipients_out	= substr($recipients_out, 0, strlen($recipients_out)-$backspace_var);
	                
	                $tagdata = str_replace($tmp[0][$j], $recipients_out, $tagdata);
 				}
            }
            
            $tagdata = ee()->TMPL->swap_var_single('total_recipients', $cnt, $tagdata);
            
            if ( ($recipients_preg=preg_match_all("/".LD."cc.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/cc".RD."/s", $tagdata, $tmp))!=0)
            {
                for ($j=0; $j<$recipients_preg; $j++)
				{
					
				$recipients_tagdata_orig = $tmp[3][$j];
	                $recipients_out = '';
	                $cnt = 0;
	                
	                if ($row['message_cc']!='' && (!isset($row['message_hide_cc']) || $row['message_hide_cc']!='n'))
	                {
	                    $current_recipients_array = array();
	                    $recipients_array = explode("|",$row['message_cc']);
	                    foreach ($recipients_array as $recipient_id)
	                    {
	                        $cnt++;
							$recipients_tagdata = $recipients_tagdata_orig;
	                        if (!isset($all_recipients[$recipient_id]))
		                    {
		                        $all_recipients[$recipient_id] = new stdClass();
								ee()->db->select('member_id, username, screen_name, email, avatar_filename, photo_filename');
		                        ee()->db->where('member_id', $recipient_id);
		                        $recipient_q = ee()->db->get('members');
		                        $tagdata = ee()->TMPL->swap_var_single('sender_email', $row['email'], $tagdata);
					            $avatar_url = ($recipient_q->row('avatar_filename') != '') ? ee()->config->slash_item('avatar_url').$recipient_q->row('avatar_filename') : '';
					            $tagdata = ee()->TMPL->swap_var_single('sender_avatar_url', $avatar_url, $tagdata);
					            $photo_url = ($recipient_q->row('photo_filename') != '') ? ee()->config->slash_item('photo_url').$recipient_q->row('photo_filename') : '';
					            $tagdata = ee()->TMPL->swap_var_single('sender_photo_url', $photo_url, $tagdata);
					            
					            $all_recipients[$recipient_id]->member_id = $recipient_q->row('member_id');
					            $all_recipients[$recipient_id]->screen_name = $recipient_q->row('screen_name');
					            $all_recipients[$recipient_id]->username = $recipient_q->row('username');
					            $all_recipients[$recipient_id]->email = $recipient_q->row('email');
					            $all_recipients[$recipient_id]->avatar_url = $avatar_url;
					            $all_recipients[$recipient_id]->photo_url = $photo_url;
		    				}
								
							$recipients_tagdata = ee()->TMPL->swap_var_single('recipient_member_id', $all_recipients[$recipient_id]->member_id, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_screen_name', $all_recipients[$recipient_id]->screen_name, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_username', $all_recipients[$recipient_id]->username, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_email', $all_recipients[$recipient_id]->email, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_avatar_url', $all_recipients[$recipient_id]->avatar_url, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_photo_url', $all_recipients[$recipient_id]->photo_url, $recipients_tagdata);
		                    $recipients_tagdata = ee()->TMPL->swap_var_single('recipient_count', $cnt, $recipients_tagdata);
	                        $recipients_out .= $recipients_tagdata;
	                    }
	                    
	                }
	                
	                $backspace_var = $tmp[2][$j];
	                $recipients_out = trim($recipients_out);
	                $recipients_out	= substr($recipients_out, 0, strlen($recipients_out)-$backspace_var);
	                
	                $tagdata = str_replace($tmp[0][$j], $recipients_out, $tagdata);
       			}
       			$tagdata = ee()->TMPL->swap_var_single('total_cc', $cnt, $tagdata);
            }
            
            $tagdata = ee()->functions->prep_conditionals($tagdata, $cond);
            
            $out .= $tagdata;
            
        }
        
        if (!empty($received))
        {
            $data = array();
            $data['message_received'] = 'y';
            ee()->db->where_in('copy_id', $received);
            ee()->db->update('message_copies', $data);
        }
        
        if (ee()->TMPL->fetch_param('form')=='yes')
        {
            $vars = array();
            $vars['folders'] = array();
            if (!isset($my_folders))
            {
                $sql = "SELECT * FROM exp_message_folders WHERE member_id=".ee()->session->userdata('member_id');
                $my_folders_q = ee()->db->query($sql);
                $my_folders = $my_folders_q->result_array();
            }
            for ($j=1; $j<=10; $j++)
            {
                if ($my_folders[0]['folder'.$j.'_name'] != '')
                {
                    $vars['folders'][$j] = $my_folders[0]['folder'.$j.'_name'];
                }
            }
            $vars['folders'][0] = ee()->lang->line('deleted_messages');
            $folder_select = ee()->load->view('folder_select', $vars, TRUE);
            $out = ee()->TMPL->swap_var_single('folder_select', $folder_select, $out);
        }
        else
        {
            $out = ee()->TMPL->swap_var_single('folder_select', '', $out);
        }
        
        $out = trim($out);
        
        if (ee()->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval(ee()->TMPL->fetch_param('backspace'));
            $out = substr($out, 0, - $backspace);
        }
        
        if ($embedded_mode==false)
        {
        
        $out = $this->_process_pagination($total, $this->perpage, $start, $basepath, $out, $paginate, $paginate_tagdata);
    	
    	}
	        
        if (ee()->TMPL->fetch_param('form')=='yes')
        {
            return $this->_form($out, 'move_pm');
        }
        else
        {
            return $out;
        }
        
    }
    
    
    
    function author()
    {
        if (ee()->TMPL->fetch_param('message_id')!='')
        {
            ee()->db->select('screen_name')
                        ->from('exp_message_copies')
                        ->join('exp_members', 'exp_message_copies.sender_id=exp_members.member_id', 'left')
                        ->where('copy_id', ee()->TMPL->fetch_param('message_id'));
            $q = ee()->db->get();
            if ($q->num_rows()>0)
            {
                return $q->row('screen_name');
            }
        }
    }
    
    
    function recipients()
    {
        if (ee()->TMPL->fetch_param('message_id')!='')
        {
            //get message id
            ee()->db->select('message_id')
                        ->from('exp_message_copies')
                        ->where('copy_id', ee()->TMPL->fetch_param('message_id'));
            if (ee()->session->userdata('group_id')!=1)
            {
                ee()->db->where('exp_message_copies.recipient_id', ee()->session->userdata('member_id')); //only where I am sender
            }
            $q = ee()->db->get();
            if ($q->num_rows()==0) return;
            
            ee()->db->select('screen_name')
                        ->from('message_copies')
                        ->join('members', 'members.member_id=message_copies.recipient_id', 'left')
                        ->where('recipient_id != ', ee()->session->userdata('member_id'))
                        ->where('recipient_id != sender_id')//exclude self-copy
                        ->where('message_id', $q->row('message_id'));
            
            $q = ee()->db->get();

            if ($q->num_rows()>0)
            {
                $out = '';
                foreach ($q->result_array() as $row)
                {
                    $out .= $row['screen_name'].', ';
                }
                return trim($out, ', ');
            }
        }
    }
    
    
    
    
    
    function private_compose()
    {
        return $this->pm_compose();
    }
    
    function compose_private()
    {
        return $this->pm_compose();
    }




    function pm_compose()
    {
        if (ee()->session->userdata('can_send_private_messages') != 'y' && ee()->session->userdata('group_id') != 1)
		{
            if (ee()->TMPL->fetch_param('silent')=='yes')
            {
                return;
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
        
        @session_start();
        
        $tagdata = ee()->TMPL->tagdata;
        $cond = array();
        
        $extra = array();
        if (ee()->TMPL->fetch_param('replying')!='')
        {
            $original_id = $extra['replying'] = ee()->TMPL->fetch_param('replying');
        }
        else if (ee()->TMPL->fetch_param('forwarding')!='')
        {
            $original_id = $extra['forwarding'] = ee()->TMPL->fetch_param('forwarding');
        }
        if (ee()->TMPL->fetch_param('save_sent')=='yes')
        {
            $extra['sent_copy'] = 'y';
        }
        if (ee()->TMPL->fetch_param('hide_cc')=='yes')
        {
            $extra['hide_cc'] = 'y';
        }
        if (ee()->TMPL->fetch_param('tracking')=='yes')
        {
            $extra['tracking'] = 'y';
        }
                
        if (isset($original_id))
        {
            ee()->db->select('message_subject, message_body, message_date, screen_name')
                        ->from('exp_message_data')
                        ->join('exp_message_copies', 'exp_message_data.message_id=exp_message_copies.message_id')
                        ->join('exp_members', 'exp_message_data.sender_id=exp_members.member_id')
                        ->where('copy_id', $original_id)
                        ->where('recipient_id', ee()->session->userdata('member_id'));
            $original_q = ee()->db->get();
            if ($original_q->num_rows()==0)
            {
                unset($extra['replying']);
                unset($extra['forwarding']);
            }
            else
            {
                $original_subject = $original_q->row('message_subject');
                if (strpos($original_subject, "Re: ")===0)
		    	{
		    		$original_subject = substr($original_subject, 4);
		    	}
		    	if (strpos($original_subject, "Fwd: ")===0)
		    	{
		    		$original_subject = substr($original_subject, 5);
		    	}
                $original_body = $original_q->row('message_body');
                if (version_compare(APP_VER, '2.6.0', '<'))
	        	{
	                $dvar = ee()->localize->fetch_date_params("%Y-%m-%d %H:%i");
	                $original_date = str_replace($dvar, ee()->localize->convert_timestamp($dvar, $original_q->row('message_date'), TRUE), "%Y-%m-%d %H:%i");
          		}
          		else
          		{
          			$original_date = ee()->localize->format_date("%Y-%m-%d %H:%i", $original_q->row('message_date'), TRUE);
          		}
                $original_author = $original_q->row('screen_name');
            }
        }
        
        if (isset($_SESSION['messaging']['subject']))
        {
            $tagdata = ee()->TMPL->swap_var_single('subject', $_SESSION['messaging']['subject'], $tagdata);
        }
        else if (isset($extra['replying']))
        {
            $tagdata = ee()->TMPL->swap_var_single('subject', 'Re: '.$original_subject, $tagdata);
        }
        else if (isset($extra['forwarding']))
        {
            $tagdata = ee()->TMPL->swap_var_single('subject', 'Fwd: '.$original_subject, $tagdata);
        }
        else
        {
            $tagdata = ee()->TMPL->swap_var_single('subject', '', $tagdata);
        }
        
        if (isset($_SESSION['messaging']['message']))
        {
            $tagdata = ee()->TMPL->swap_var_single('message', $_SESSION['messaging']['message'], $tagdata);
        }
        else if (isset($extra['replying']) || isset($extra['forwarding']))
        {
            $tagdata = ee()->TMPL->swap_var_single('message', '[quote author="'.$original_author.'" date="'.$original_date.'"]'.$original_body.'[/quote]', $tagdata);
        }
        else
        {
            $tagdata = ee()->TMPL->swap_var_single('message', '', $tagdata);
        }
        
        if (empty($_SESSION['messaging']['warning']))
        {
            $warning = '';
        }
        else
        {
            $vars = array();
            $vars['warnings'] = $_SESSION['messaging']['warning'];
        }
        //sending limit reached?
		$query = ee()->db->query("SELECT COUNT(c.copy_id) AS count 
							 FROM exp_message_copies c, exp_message_data d
							 WHERE c.message_id = d.message_id
							 AND c.sender_id = '".ee()->session->userdata('member_id')."'
							 AND d.message_status = 'sent'
							 AND d.message_date > ".(ee()->localize->now - 24*60*60));

		if ($query->row('count') >= ee()->session->userdata('prv_msg_send_limit'))
		{
			$vars['warnings'][] = ee()->lang->line('sending_limit_warning');
		}

        if (!empty($vars))
        {
            $warning = ee()->load->view('warning', $vars, TRUE);
        }
        $tagdata = ee()->TMPL->swap_var_single('warning', $warning, $tagdata);
        $cond['warning'] = ($warning!='')?TRUE:FALSE;
        
        $cond['recipient_from_url'] = FALSE;
        if (strpos($tagdata, LD.'recipient_from_url'.RD)!==false)
        {
            $qstr = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;
            ee()->db->select('screen_name');
            if (is_numeric($qstr))
            {
                ee()->db->where('member_id', $qstr);
            }
            else
            {
                ee()->db->where('username', $qstr);
            }
            $q = ee()->db->get('members');

            if ($q->num_rows() == 1)
            {
                $recipient = $q->row('screen_name');
                $cond['recipient_from_url'] = TRUE;
            }
            else
            {
                $recipient = '';
            }

            $tagdata = ee()->TMPL->swap_var_single('recipient_from_url', $recipient, $tagdata);
        }
        
        if (ee()->config->slash_item('prv_msg_upload_path')!='' && (ee()->session->userdata('group_id')==1 || ee()->session->userdata('can_attach_in_private_messages')=='y'))
        {
            $cond['attachments_allowed'] = TRUE;
        }
        else
        {
            $cond['attachments_allowed'] = FALSE;
        }
        
        $cond['attachments'] = FALSE;
        if ( preg_match_all("/".LD."attachments.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/attachments".RD."/s", $tagdata, $tmp)!=0)
        {
            $attachments_tagdata_orig = $tmp[3][0];
            $attachments_out = '';

            if (isset($extra['forwarding'])) 
            {
                ee()->db->select('attachment_id, attachment_name, attachment_extension, attachment_size')
                            ->from('exp_message_attachments')
                            ->join('exp_message_copies', 'exp_message_attachments.message_id=exp_message_copies.message_id', 'left')
                            ->where('copy_id', $extra['forwarding']);
                $attachment_q = ee()->db->get();

     			if ($attachment_q->num_rows() > 0)
     			{
                    $cond['attachments'] = TRUE;
                    foreach ($attachment_q->result_array() as $attachment_row)
                    {
                        $attachments_tagdata = $attachments_tagdata_orig;
                        $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_id', $attachment_row['attachment_id'], $attachments_tagdata);
                        $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_name', $attachment_row['attachment_name'], $attachments_tagdata);
                        $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_size', $attachment_row['attachment_size'], $attachments_tagdata);
                        $attachments_tagdata = ee()->TMPL->swap_var_single('attachment_extension', $attachment_row['attachment_extension'], $attachments_tagdata);
                        
                        $attachments_out .= $attachments_tagdata;
                    }
                }
            }
            
            $backspace_var = $tmp[2][0];
            $attachments_out = trim($attachments_out);
            $attachments_out	= substr($attachments_out, 0, strlen($attachments_out)-$backspace_var);
            
            $tagdata = str_replace($tmp[0][0], $attachments_out, $tagdata);
        }
        
        $tagdata = ee()->functions->prep_conditionals($tagdata, $cond);
        
        
        return $this->_form($tagdata, 'send_pm', $extra);
    }
    
    
    
    
    
    
    function send_pm()
    {
        if (ee()->session->userdata('can_send_private_messages') != 'y' && ee()->session->userdata('group_id') != '1')
		{
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }
		
		if (ee()->session->userdata['is_banned'] === true)
		{			
			if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
		}
	 
		if (ee()->config->item('require_ip_for_posting') == 'y')
		{
			if (ee()->input->ip_address() == '0.0.0.0' OR ee()->session->userdata['user_agent'] == '')
			{			
				if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('not_authorized');
                    exit();
                }
                return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
			}
		}

		//  Already Sent?
		/*
		if (ee()->input->get_post('message_id') !== FALSE && is_numeric(ee()->input->get_post('message_id')))
		{
			$query = ee()->db->query("SELECT message_status FROM exp_message_data WHERE message_id = '".ee()->db->escape_str(ee()->input->get_post('message_id'))."'");
			
			if ($query->num_rows() > 0 && $query->row('message_status')  == 'sent')
			{
				if (ee()->input->get_post('ajax')=='yes')
                {
                    return ee()->lang->line('messsage_already_sent');
                }
                return ee()->output->show_user_error('general', array(ee()->lang->line('messsage_already_sent')));
			}
		}
        */
		
		// just registered?
		
		$waiting_period = (ee()->config->item('prv_msg_waiting_period') !== FALSE) ? (int) ee()->config->item('prv_msg_waiting_period') : 1;
		if (ee()->session->userdata('group_id') != 1 && ee()->session->userdata('join_date') > (ee()->localize->now - $waiting_period * 60 * 60))
		{
			$text = str_replace(array('%time%', '%email%', '%site%'), 
	                                               array($waiting_period, ee()->functions->encode_email(ee()->config->item('webmaster_email')), ee()->config->item('site_name')), 
												  ee()->lang->line('waiting_period_not_reached')
                                                  );
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$text;
                exit();
            }
            return ee()->output->show_user_error('general', $text);
		}
		
		
		// is sender too fast?
		
		if (ee()->session->userdata('group_id') != 1)
		{
			$period = (ee()->config->item('prv_msg_throttling_period') !== FALSE) ? (int) ee()->config->item('prv_msg_throttling_period') : 30;
		
			$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_message_data
								 WHERE sender_id = '".ee()->session->userdata('member_id')."'
								 AND message_status = 'sent'
								 AND message_date > ".ee()->db->escape_str(ee()->localize->now - $period));				 
			if ($query->row('count')  > 0)
			{
				$text = str_replace('%x', $period, ee()->lang->line('send_throttle'));
                if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".$text;
                    exit();
                }
                return ee()->output->show_user_error('submission', $text);
			}
		}
            
                        
        
        if (empty($_POST['recipients']))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('empty_recipients_field');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('empty_recipients_field')));
        }
        
        if (empty($_POST['subject']))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('empty_subject_field');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('empty_subject_field')));
        }
        
        if (empty($_POST['message']))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('empty_body_field');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('empty_body_field')));
        }
        
        //save subject and body to session
        //just in case, you know...
        @session_start();
        $_SESSION['messaging'] = array();
        $_SESSION['messaging']['subject'] = ee()->input->post('subject');
        $_SESSION['messaging']['message'] = ee()->input->post('message');
    		
        if (version_compare(APP_VER, '2.6.0', '<'))
        {
        if (ee()->security->check_xid(ee()->input->post('XID')) == FALSE)
		{
			if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('not_authorized');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('not_authorized')));
		}
        }
        
        $warning = array();
        
        //duplicate?
		if (ee()->config->item('deny_duplicate_data') == 'y')
		{
			$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_message_data
								 WHERE sender_id = '".ee()->session->userdata('member_id')."'
								 AND message_status = 'sent'
								 AND message_body = '".ee()->db->escape_str($this->_xss_clean(ee()->input->post('message')))."'");
								 
			if ($query->row('count')  > 0)
			{
				if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('duplicate_message_sent');
                    exit();
                }
                return ee()->output->show_user_error('submission', array(ee()->lang->line('duplicate_message_sent')));
			}
		}
        
        if ( ! class_exists('EE_Messages'))
		{
			require_once APPPATH.'libraries/Messages.php';
            $MESS = new EE_Messages;
		}

		$recipients = $MESS->convert_recipients(ee()->input->get_post('recipients'), 'array', 'member_id');

		$cc = (trim(ee()->input->get_post('cc')) == '') ? array() : $MESS->convert_recipients(ee()->input->get_post('cc'), 'array', 'member_id');
		
		$recip_orig	= count($recipients);
		$cc_orig	= count($cc);
		
		// Make sure CC does not contain members in Recipients
		$cc = array_diff($cc, $recipients);

		if (count($recipients) == 0)
		{
			$warning[] = ee()->lang->line('empty_recipients_field');
		}

		if ($MESS->invalid_name === TRUE)
		{
			$warning[] = ee()->lang->line('invalid_username');
		}
        
        $prefs = array( 'prv_msg_attach_maxsize',
						'prv_msg_attach_total',
						'prv_msg_html_format',
						'prv_msg_auto_links',
						'prv_msg_max_chars',
						'prv_msg_max_attachments'
						);
						
		for($i=0, $t = count($prefs); $i < $t; ++$i)
		{
			if (FALSE !== ($value = ee()->config->item($prefs[$i])))
			{
				$name = str_replace('prv_msg_', '', $prefs[$i]);
				
				$this->{$name} = $value;
			}
		}		
		$this->upload_path	 = ee()->config->slash_item('prv_msg_upload_path');
        
        //message too large?
        if ($this->max_chars != 0 && strlen(ee()->input->get_post('message')) > $this->max_chars)
		{
			$warning[] = str_replace('%max%', $this->max_chars, ee()->lang->line('message_too_large'));
		}
        
        if (ee()->session->userdata('group_id') != 1)
		{
			//sending limit reached?
			$query = ee()->db->query("SELECT COUNT(c.copy_id) AS count 
								 FROM exp_message_copies c, exp_message_data d
								 WHERE c.message_id = d.message_id
								 AND c.sender_id = '".ee()->session->userdata('member_id')."'
								 AND d.message_status = 'sent'
								 AND d.message_date > ".(ee()->localize->now - 24*60*60));

			if (($query->row('count')  + count($recipients) + count($cc)) > ee()->session->userdata('prv_msg_send_limit'))
			{
				$warning[] = ee()->lang->line('sending_limit_warning');
			}

			//storing limit reached?
            $storage_limit	= (ee()->session->userdata('group_id') == 1) ? 0 : ee()->session->userdata('prv_msg_storage_limit');
			if ($storage_limit != 0 && ee()->input->get_post('sent_copy') == 'y')
			{
				$total_messages = 0;
                
                ee()->db->where('recipient_id', ee()->session->userdata('member_id'));
                ee()->db->where('message_deleted', 'n');
                $total_messages += ee()->db->count_all_results('message_copies');
                
                ee()->db->where('sender_id', ee()->session->userdata('member_id'));
                ee()->db->where('message_status', 'draft');
                $total_messages += ee()->db->count_all_results('message_data');

				if (($total_messages + 1) > $storage_limit)
				{
					$warning[] = ee()->lang->line('storage_limit_warning');
				}
			}			
		}
        
        $details  = array();
		$details['overflow_recipients'] = array();
		$details['overflow_cc'] = array();
		
		for($i=0, $size = count($recipients); $i < $size; $i++)
		{
			if ($MESS->_check_overflow($recipients[$i]) === FALSE)
			{
				$details['overflow_recipients'][] = $recipients[$i];
				unset($recipients[$i]);
			}
		}
			
		for($i=0, $size = count($cc); $i < $size; $i++)
		{
			if ($MESS->_check_overflow($cc[$i]) === FALSE)
			{
				$details['overflow_cc'][] = $cc[$i];
				unset($cc[$i]);
			}
		}
		

		/* -------------------------------------------------
		/*  If we have people unable to receive a message
		/*  because of an overflow we make the message a 
		/*  preview and will send a message to the sender.
		/* -------------------------------------*/
		if (count($details['overflow_recipients']) > 0 OR count($details['overflow_cc']) > 0)
		{
			sort($recipients);
			sort($cc);
			$overflow_names = array();
			
			/* -------------------------------------
			/*  Send email alert regarding a full
			/*  inbox to these users, load names
			/*  for error message
			/* -------------------------------------*/
			
			$query = ee()->db->query("SELECT exp_members.screen_name, exp_members.email, exp_members.accept_messages, exp_member_groups.prv_msg_storage_limit
								 FROM exp_members
								 LEFT JOIN exp_member_groups ON exp_member_groups.group_id = exp_members.group_id
								 WHERE exp_members.member_id IN ('".implode("','",array_merge($details['overflow_recipients'], $details['overflow_cc']))."')
								 AND exp_member_groups.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'");
			
			if ($query->num_rows() > 0)
			{
				ee()->load->library('email');

				ee()->email->wordwrap = true;
				
				$swap = array(
							  'sender_name'			=> ee()->session->userdata('screen_name'),
							  'site_name'			=> stripslashes(ee()->config->item('site_name')),
							  'site_url'			=> ee()->config->item('site_url')
							  );
				
				$template = ee()->functions->fetch_email_template('pm_inbox_full');
				$email_tit = ee()->functions->var_swap($template['title'], $swap);
				$email_msg = ee()->functions->var_swap($template['data'], $swap);

				foreach($query->result_array() as $row)
				{
					$overflow_names[] = $row['screen_name'];
					
					if ($row['accept_messages'] != 'y')
					{
						continue;
					}
					
					ee()->email->EE_initialize();
					ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));	
					ee()->email->to($row['email']); 
					ee()->email->subject($email_tit);	
					ee()->email->message(ee()->functions->var_swap($email_msg, array('recipient_name' => $row['screen_name'], 'pm_storage_limit' => $row['prv_msg_storage_limit'])));		
					ee()->email->send();
				}	
			}
			
			$warning[] = str_replace('%overflow_names%', implode(', ', $overflow_names), ee()->lang->line('overflow_recipients'));
		}
        
        //attachments handling
        $this->temp_message_id = ee()->functions->random('nozero', 9);
        
        $attachment_exist = $this->_attachment_exist();     
        
        if (ee()->session->userdata('group_id')!=1 && ee()->session->userdata('can_attach_in_private_messages')!='y' && $attachment_exist)
        {
            $warning[] = ee()->lang->line('no_more_attachments');
        }
        
        if ($this->upload_path == '' && $attachment_exist)
		{
			$warning[] = ee()->lang->line('unable_to_recieve_attach');
		}
        
        $_SESSION['messaging']['warning'] = $warning;
        if (!empty($warning))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ";
                foreach ($warning as $w) echo $w;
                exit();
            }
            return ee()->functions->redirect($_POST['PRV']);
        }
        
        $warning = array();
        
        //if this is forward - duplicate attachments
        $this->attachments = array();
        if (ee()->input->post('forward_attachments')!='')
        {
            foreach ($_POST['forward_attachments'] as $forward)
            {
                $this->attachments[] = $forward;
            }
            
            if ( ! class_exists('EE_Messages_send'))
    		{
    			require_once APPPATH.'libraries/Messages_send.php';
    		}
            $MESS_Send = new EE_Messages_send;
            $MESS_Send->attachments = $this->attachments;
            $MESS_Send->attach_total = $this->attach_total;
            $MESS_Send->upload_path = $this->upload_path;
            $MESS_Send->member_id = ee()->session->userdata('member_id');
            $MESS_Send->_duplicate_files();
            
            $this->attachments = $MESS_Send->attachments;
        }
        
        //process attachments

        if ($attachment_exist)
        {
            $attachment_data = $this->_do_upload();
            if ($attachment_data!=false)
            {

                if ($this->max_attachments!='' && count($attachment_data)>$this->max_attachments)
                {
                    $warning[] = ee()->lang->line('no_more_attachments');
                }
                
                $total_size = 0;
                foreach ($attachment_data as $data)
                {
                    $total_size += $data['attachment_size'];  
                }
                
                if ($this->attach_total != 0)
    			{
    				if ($total_size > ($this->attach_total * 1024))
    				{
    					$warning[] = ee()->lang->line('too_many_attachments');
    				}
    			}
                
                $_SESSION['messaging']['warning'] = $warning;
                if (!empty($warning))
                {
                    if (ee()->input->get_post('ajax')=='yes')
                    {
                        echo lang('error').": ";
                        foreach ($warning as $w) echo $w;
                        exit();
                    }
                    return ee()->functions->redirect($_POST['PRV']);
                }
                
                foreach ($attachment_data as $data)
                {
                    ee()->db->insert('message_attachments', $data);
                    $this->attachments[] = ee()->db->insert_id();
                }
            }
        }
        
        ee()->db->select('member_id')
                    ->from('message_listed')
                    ->where('listed_type', 'blocked')
                    ->where('listed_member', ee()->session->userdata('member_id'))
                    ->where_in('member_id', $recipients);
					
		if (count($cc) > 0)
		{
			ee()->db->where_in('member_id', $cc);
		}
			
		$blocked = ee()->db->get();

		if ($blocked->num_rows() > 0)
		{	
			foreach($blocked->result_array() as $row)
			{
				$details['blocked'][] = $row['member_id'];
			}
			
			$recipients = array_diff($recipients, $details['blocked']);
			$cc = (count($cc) > 0) ? array_diff($cc, $details['blocked']) : array();
			
			if (count($recipients)==0)
			{
				if (ee()->input->get_post('ajax')=='yes')
	            {
	                echo lang('error').": ".ee()->lang->line('blocked_recipients');
	                exit();
	            }
	            return ee()->output->show_user_error('submission', array(ee()->lang->line('blocked_recipients')));
	   		}
			
						
			sort($recipients);
			sort($cc);
			
		}

		//get ready to insert
        if (version_compare(APP_VER, '2.6.0', '<'))
       	{
		  ee()->security->delete_xid(ee()->input->post('XID'));
        }
		
		$data = array('sender_id' 			=> ee()->session->userdata('member_id'),
					  'message_date' 		=> ee()->localize->now,
					  'message_subject' 	=> $this->_xss_clean(ee()->input->get_post('subject')),
					  'message_body'		=> $this->_xss_clean(ee()->input->get_post('message')),
					  'message_tracking' 	=> ( ! ee()->input->get_post('tracking')) ? 'n' : 'y',
					  'message_attachments' => (count($this->attachments) > 0) ? 'y' : 'n',
					  'message_recipients'	=> implode('|', $recipients),
					  'message_cc'			=> implode('|', $cc),
					  'message_hide_cc'		=> ( ! ee()->input->get_post('hide_cc')) ? 'n' : 'y',
					  'message_sent_copy'	=> ( ! ee()->input->get_post('sent_copy')) ? 'n' : 'y',
					  'total_recipients'	=> (count($recipients) + count($cc)),
					  'message_status'		=> 'sent');
		

        ee()->db->insert('message_data', $data);
		$message_id = ee()->db->insert_id();

		$copy_data = array(	'message_id' => $message_id,
							'sender_id'	 => ee()->session->userdata('member_id'));
		
	
		for($i=0, $size = count($recipients); $i < $size; $i++)
		{
			$copy_data['recipient_id'] 		= $recipients[$i];
			$copy_data['message_authcode']	= ee()->functions->random('alnum', 10);
			ee()->db->insert('message_copies', $copy_data);
		}
		
		for($i=0, $size = count($cc); $i < $size; $i++)
		{
			$copy_data['recipient_id']		= $cc[$i];
			$copy_data['message_authcode']	= ee()->functions->random('alnum', 10);
			ee()->db->insert('message_copies', $copy_data);
		}
		
		ee()->db->query("UPDATE exp_members SET private_messages = private_messages + 1
					WHERE member_id IN ('".implode("','",array_merge($recipients, $cc))."')");
					
					
		if ($data['message_sent_copy'] == 'y')
		{
			$copy_data['recipient_id'] 		= ee()->session->userdata('member_id');
			$copy_data['message_authcode']	= ee()->functions->random('alnum', 10);
			$copy_data['message_folder']	= '2';  // Sent Message Folder
			$copy_data['message_read']		= 'y';  // Already read automatically
			$copy_data['message_time_read']		= ee()->localize->now;  // Already read automatically
			ee()->db->insert('exp_message_copies', $copy_data);
			
			//ee()->db->query("UPDATE exp_members SET private_messages = private_messages + 1
			//		WHERE member_id=".ee()->session->userdata('member_id'));
		}
		
		if (ee()->input->get_post('replying') !== FALSE || ee()->input->get_post('forwarding') !== FALSE)
		{
			$copy_id = (ee()->input->get_post('replying') !== FALSE) ? ee()->input->get_post('replying') : ee()->input->get_post('forwarding');
			$status['message_status']  = (ee()->input->get_post('replying') !== FALSE) ? 'replied' : 'forwarded';
			
			ee()->db->where('copy_id', $copy_id);
            ee()->db->update('message_copies', $status);            
		}
		
		if (count($this->attachments) > 0)
		{
			$upd_data = array('message_id'=>$message_id, 'is_temp'=>'n');
            ee()->db->where_in('attachment_id', $this->attachments);
            ee()->db->update('message_attachments', $upd_data);
		}
					
		// -------------------------------------------
		// 'messaging_pm_sent' hook.
		//  - Do something after PM is sent, but before email notification goes out
		//
			if (ee()->extensions->active_hook('messaging_pm_sent') === TRUE)
			{
				$edata = ee()->extensions->call('messaging_pm_sent', $data, $recipients, $cc);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
        // -------------------------------------------
					
		ee()->db->select('screen_name, email')
                    ->from('members')
                    ->where_in('member_id', array_merge($recipients, $cc))
                    ->where('notify_of_pm', 'y')
                    ->where('member_id != '.ee()->session->userdata('member_id'));
		
		$query = ee()->db->get();
							 
		if ($query->num_rows() > 0)
		{
			ee()->load->library('typography');
			ee()->typography->initialize(array(
			 				'parse_images'		=> FALSE,
			 				'smileys'			=> FALSE,
			 				'highlight_code'	=> TRUE)
			 				);

			if (ee()->config->item('enable_censoring') == 'y' AND ee()->config->item('censored_words') != '')
    		{
				$subject = ee()->typography->filter_censored_words($this->_xss_clean(ee()->input->get_post('subject')));
			}
			else
			{
				$subject = $this->_xss_clean(ee()->input->get_post('subject'));
			}
			
			$body = ee()->typography->parse_type(stripslashes($this->_xss_clean(ee()->input->get_post('message'))),
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
			
			ee()->load->library('email');

			ee()->email->wordwrap = true;
			
			$swap = array(
						  'sender_name'			=> ee()->session->userdata('screen_name'),
						  'message_subject'		=> $subject, 
						  'message_content'		=> $body,
						  'site_name'			=> stripslashes(ee()->config->item('site_name')),
						  'site_url'			=> ee()->config->item('site_url')
						  );
			
			$template = ee()->functions->fetch_email_template('private_message_notification');
			$email_tit = ee()->functions->var_swap($template['title'], $swap);
			$email_msg = ee()->functions->var_swap($template['data'], $swap);

			// Load the text helper
			ee()->load->helper('text');

			foreach($query->result_array() as $row)
			{	
				
				ee()->email->EE_initialize();
				ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));	
				ee()->email->to($row['email']); 
				ee()->email->subject($email_tit);	
				ee()->email->message(entities_to_ascii(ee()->functions->var_swap($email_msg, array('recipient_name' => $row['screen_name']))));		
				ee()->email->send();
			}
		}
		
		unset($_SESSION['messaging']);    
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo ee()->lang->line('message_sent');
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');    
        
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
        
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
		
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> ee()->lang->line('message_sent'),
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
            
		ee()->output->show_message($data);
        
    }       
    
    
    
    //delete or move
    function move_pm()
    {
        if (ee()->input->get('empty_trash')=='yes')
        {
            ee()->db->select('copy_id')
                    ->from('message_copies')
                    ->where('recipient_id', ee()->session->userdata('member_id'))
                    ->where('message_deleted', 'y');
            $q = ee()->db->get();
            if ($q->num_rows()==0)
            {
                if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('trash_empty');
                    exit();
                }
                return ee()->output->show_user_error('submission', array(ee()->lang->line('trash_empty')));
            }
            
            $_POST['message_id'] = array();
            foreach ($q->result_array() as $row)
            {
                $_POST['message_id'][] = $row['copy_id'];
            }
        }
        
        if (empty($_POST['message_id']) && !empty($_GET['message_id']))
        {
            $_POST['message_id'] = array($_GET['message_id']);
        }
        if (empty($_POST['message_id']))
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('no_messages');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('no_messages')));
        }
        
        if ((!isset($_POST['folder']) || $_POST['folder']=='') && ee()->input->get_post('delete')===false)
        {
            if (ee()->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".ee()->lang->line('provide_valid_folder');
                exit();
            }
            return ee()->output->show_user_error('submission', array(ee()->lang->line('provide_valid_folder')));
        }
        
        if (ee()->input->get_post('delete')===false && !in_array(ee()->input->get_post('folder'), array(0,1,2)))
        {
            $sql = "SELECT * FROM exp_message_folders WHERE member_id=".ee()->session->userdata('member_id');
            $my_folders_q = ee()->db->query($sql);
            $my_folders = $my_folders_q->result_array();
            foreach ($my_folders[0] as $key=>$val)
            {
                if ($val != '')
                {
                    $folder_id = str_replace('folder', '', str_replace('_name', '', $key));
                    $folders[$folder_id] = $val;
                }
            }
            if (!isset($folders[ee()->input->get_post('folder')]))
            {
                if (ee()->input->get_post('ajax')=='yes')
                {
                    echo lang('error').": ".ee()->lang->line('provide_valid_folder');
                    exit();
                }
                return ee()->output->show_user_error('submission', array(ee()->lang->line('provide_valid_folder')));
            }
        }
        
        $messages = array();
        foreach ($_POST['message_id'] as $message_id)
        {
            $messages[] = $message_id;
        }
        $messages = array_unique($messages);
        
        ee()->db->select('message_id, copy_id, message_folder, message_deleted')
                    ->from('message_copies')
                    ->where('recipient_id', ee()->session->userdata('member_id'))
                    ->where_in('copy_id', $messages);
        $q = ee()->db->get();
        
        if (ee()->input->get_post('folder')==0 || ee()->input->get_post('delete')!==false)
        {
            foreach ($q->result_array() as $row)
            {
                $this->_delete_messages($row);
            }
        }
        else
        {
            foreach ($q->result_array() as $row)
            {
                $data = array('message_folder'=>ee()->input->get_post('folder'), 'message_deleted'=>'n');
                ee()->db->where('copy_id', $row['copy_id']);
                ee()->db->where('recipient_id', ee()->session->userdata('member_id'));
                ee()->db->update('message_copies', $data);
            }
        }
    
        $text  = (ee()->input->get_post('delete')!==false) ? ee()->lang->line('messages_deleted') : ee()->lang->line('messages_moved');
        
        if (ee()->input->get_post('ajax')=='yes')
        {
            echo $text;
            exit();
        }
        
        $return = (ee()->input->get_post('RET')!==false)?ee()->input->get_post('RET'):ee()->config->item('site_url');    
        $site_name = (ee()->config->item('site_name') == '') ? ee()->lang->line('back') : stripslashes(ee()->config->item('site_name'));
        
        if (ee()->input->get_post('skip_success_message')=='y')
        {
        	ee()->functions->redirect($return);
        }
		
        $data = array(	'title' 	=> ee()->lang->line('success'),
        				'heading'	=> ee()->lang->line('success'),
        				'content'	=> $text,
        				'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        'rate'		=> 3
        			 );
            
		ee()->output->show_message($data);
    }
    
    
    function info()
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        ee()->db->select('*')
                    ->from('message_folders')
                    ->where('member_id', ee()->session->userdata('member_id'));
        $query = ee()->db->get();
		if ($query->num_rows() == 0)
		{
			ee()->db->insert('message_folders', array('member_id' => ee()->session->userdata('member_id')));
		}
        
        $tagdata = ee()->TMPL->tagdata;
                
        $storage_limit	= (ee()->session->userdata('group_id') == 1) ? '&#8734;' : ee()->session->userdata('prv_msg_storage_limit');
        $tagdata = ee()->TMPL->swap_var_single('messages_limit', $storage_limit, $tagdata);
        $tagdata = ee()->TMPL->swap_var_single('messages_unread', ee()->session->userdata('private_messages'), $tagdata);
        if (strpos($tagdata, LD.'messages_total'.RD)!==false || strpos($tagdata, LD.'messages_percent'.RD)!==false)
        {
        	ee()->db->from('message_copies');
	        ee()->db->where('recipient_id', ee()->session->userdata('member_id'));
	        $count = ee()->db->count_all_results(); 
	        $tagdata = ee()->TMPL->swap_var_single('messages_total', $count, $tagdata);
            
            $messages_percent = (ee()->session->userdata('group_id') == 1) ? 0 : round((100*$count/ee()->session->userdata('prv_msg_storage_limit')),2);
            $tagdata = ee()->TMPL->swap_var_single('messages_percent', $messages_percent, $tagdata);
        }
        $tagdata = ee()->TMPL->swap_var_single('send_limit', ee()->session->userdata('prv_msg_send_limit'), $tagdata);
		
		$prefs = array( 'prv_msg_attach_maxsize',
						'prv_msg_attach_total',
						'prv_msg_max_chars',
						'prv_msg_max_attachments'
						);
						
		for($i=0, $t = count($prefs); $i < $t; ++$i)
		{
			if (FALSE !== ($value = ee()->config->item($prefs[$i])))
			{
				$name = str_replace('prv_msg_', '', $prefs[$i]);
				$tagdata = ee()->TMPL->swap_var_single($name, $value, $tagdata);
			}
		}	
        
        return $tagdata;	
    }
    
    
    function _delete_messages($row)
    {
        //message_id, copy_id, message_folder, message_deleted
        if ($row['message_deleted']=='n')
        {
            $data = array('message_deleted'=>'y');
            ee()->db->where('copy_id', $row['copy_id']);
            ee()->db->update('message_copies', $data);
        }
        else if (ee()->input->get_post('delete')!==false)
        {
            ee()->db->from('message_copies');
            ee()->db->where('message_id', $row['message_id']);
            ee()->db->where('copy_id != ', $row['copy_id']);
            $count = ee()->db->count_all_results();                        
            if ($count==0)
            {
                ee()->db->where('message_id', $row['message_id']);
                ee()->db->delete('message_data');
                
                ee()->db->select('attachment_location')
                            ->from('message_attachments')
                            ->where('message_id', $row['message_id']);
                $q = ee()->db->get();
                if ($q->num_rows()>0)
                {
                    foreach ($q->result_array() as $attachment_row)
                    {
                        @unlink($attachment_row['attachment_location']);
                    }
                }
                ee()->db->where('message_id', $row['message_id']);
                ee()->db->delete('message_attachments');
            }
            
            ee()->db->where('copy_id', $row['copy_id']);
            ee()->db->delete('message_copies');
		
        }
		
        ee()->db->from('message_copies');
        ee()->db->where('recipient_id', ee()->session->userdata('member_id'));
        ee()->db->where('message_read', 'n');
        $count = ee()->db->count_all_results(); 
		
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
        ee()->db->update('members', array('private_messages'=>$count));
        ee()->session->userdata['private_messages'] = $count;

    }
    
    
    
    function display_attachment()
    {
        if (ee()->input->get('aid')=='' || ee()->input->get('hash')=='')
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('attachment_not_found')));
        }
        
        ee()->db->select()
                    ->from('message_attachments')
                    ->where('attachment_id', ee()->input->get('aid'))
                    ->where('attachment_hash', ee()->input->get('hash'));
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('attachment_not_found')));
        }
        
        if (ee()->session->userdata('group_id')!=1)
        {
            ee()->db->select('copy_id')
                        ->from('exp_message_copies')
                        ->join('exp_message_attachments', 'exp_message_copies.message_id=exp_message_attachments.message_id', 'left')
                        ->or_where('recipient_id', ee()->session->userdata('member_id'));
            $check_q = ee()->db->get();
            if ($check_q->num_rows()==0)
            {
                return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
            }
        }

		$filepath = rtrim(ee()->config->item('prv_msg_upload_path'), '/').'/'.$q->row('attachment_location');
		
		$extension = strtolower(str_replace('.', '', $q->row('attachment_extension') ));
        
        include_once(APPPATH.'config/mimes.php');			
		
        if (version_compare(APP_VER, '2.10.0', '<'))
        {
    		if ($mimes[$extension] == 'html')
    		{
    			$mime = 'text/html';
    		}
    		else
    		{
    			$mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
    		}
            
        }
        else
        {
            ee()->load->library('mime_type');
            $mime = ee()->mime_type->ofFile($filepath);
        }
        
        if ( ! file_exists($filepath) OR ! isset($mime) OR $mime==false)
		{
			return ee()->output->show_user_error('general', ee()->lang->line('not_authorized'));
		}
        
		ee()->db->where('message_id', $q->row('message_id'));
		ee()->db->where('recipient_id', ee()->session->userdata('member_id'));
		ee()->db->update('message_copies', array('attachment_downloaded'=>'y'));
        
        header('Content-Disposition: filename="'.$q->row('attachment_name') .'"');		
		header('Content-Type: '.$mime);
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.filesize($filepath));
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', ee()->localize->now).' GMT');
			
		if ( ! $fp = @fopen($filepath, FOPEN_READ))
		{
			return ee()->output->show_user_error('submission', ee()->lang->line('not_authorized'));
		}
		
		fpassthru($fp);
		@fclose($fp);
		exit;
    }
    
    
    function _attachment_exist()
    {
        if (isset($_FILES['attachment']['name']) && !empty($_FILES['attachment']['name']))
        {
            if (!is_array($_FILES['attachment']['name']))
            {
                if ($_FILES['attachment']['name']!='')
                {
                    return true;
                }
            }
            else
            {
                foreach ($_FILES['attachment']['name'] as $filename)
                {
                    if ($filename!='')
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }



	function _do_upload($field = 'attachment')
	{
		//ee()->lang->loadfile('upload');
        
        if (version_compare(APP_VER, '3.0.0', '<'))
        {
            if ( ! class_exists('CI_Upload'))
            {
            	require_once BASEPATH.'libraries/Upload.php';
            }
            
            if ( ! class_exists('EE_Upload'))
            {
            	require_once APPPATH.'libraries/EE_Upload.php';
                $UP = new EE_Upload;
            }
        }
        else
        {
            require_once BASEPATH.'libraries/Upload.php';
            $UP = new EE_Upload;
        }
        
       // Is $_FILES[$field] set? If not, no reason to continue.
		if ( ! isset($_FILES[$field]))
		{
			//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_no_file_selected')));
            return ee()->output->show_user_error('general', array(lang('attachment_problem')));
		}
        
        
        $this->upload_path	= $UP->upload_path = (empty($this->upload_path))?ee()->config->slash_item('prv_msg_upload_path'):$this->upload_path;
        $this->allowed_types	= $UP->allowed_types	= (empty($this->allowed_types))?'*':$this->allowed_types;
        $this->max_size	= $UP->max_size	= (empty($this->max_size))?ee()->config->slash_item('prv_msg_attach_maxsize'):$this->max_size;
		if (ee()->config->item('xss_clean_uploads') == 'n')
		{
			$this->xss_clean	= $UP->xss_clean = FALSE;
		}
		else
		{
			$this->xss_clean	= $UP->xss_clean = (ee()->session->userdata('group_id') == 1) ? FALSE : TRUE;
		}
        

		// Is the upload path valid?
		if ( ! $UP->validate_upload_path())
		{
			// errors will already be set by validate_upload_path() so just return FALSE
			return FALSE;
		}
        
        $i = 0;

        if (is_array($_FILES[$field]['name']))
        {
            $files = array();
            foreach ($_FILES[$field]['name'] as $index=>$value)
            {
                if ($value!='')
                {
                    $files[$i]['name'] = $value;
                    $files[$i]['type'] = $_FILES[$field]['type'][$index];
                    $files[$i]['tmp_name'] = $_FILES[$field]['tmp_name'][$index];
                    $files[$i]['error'] = $_FILES[$field]['error'][$index];
                    $files[$i]['size'] = $_FILES[$field]['size'][$index];
                    $i++;
                }
            }
        }
        else
        {
            $files[$i]['name'] = $_FILES[$field]['name'];
            $files[$i]['type'] = $_FILES[$field]['type'];
            $files[$i]['tmp_name'] = $_FILES[$field]['tmp_name'];
            $files[$i]['error'] = $_FILES[$field]['error'];
            $files[$i]['size'] = $_FILES[$field]['size'];
            $i++;
        }

        for ($index=0; $index<$i; $index++)
        {
    
    		// Was the file able to be uploaded? If not, determine the reason why.
    		if ( ! is_uploaded_file($files[$index]['tmp_name']))
    		{
    			$error = ( ! isset($files[$index]['error'])) ? 4 : $files[$index]['error'];
    
    			switch($error)
    			{
    				case 1:	// UPLOAD_ERR_INI_SIZE
    					$UP->set_error('upload_file_exceeds_limit');
    					break;
    				case 2: // UPLOAD_ERR_FORM_SIZE
    					$UP->set_error('upload_file_exceeds_form_limit');
    					break;
    				case 3: // UPLOAD_ERR_PARTIAL
    					$UP->set_error('upload_file_partial');
    					break;
    				case 4: // UPLOAD_ERR_NO_FILE
    					$UP->set_error('upload_no_file_selected');
    					break;
    				case 6: // UPLOAD_ERR_NO_TMP_DIR
    					$UP->set_error('upload_no_temp_directory');
    					break;
    				case 7: // UPLOAD_ERR_CANT_WRITE
    					$UP->set_error('upload_unable_to_write_file');
    					break;
    				case 8: // UPLOAD_ERR_EXTENSION
    					$UP->set_error('upload_stopped_by_extension');
    					break;
    				default :   $UP->set_error('upload_no_file_selected');
    					break;
    			}
                if ($UP->error_msg!='')
                {
                    //return ee()->output->show_user_error('general', $UP->error_msg);
                    return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    			}
    		}
    
    
    		// Set the uploaded data as class variables
    		$UP->file_temp = $this->file_temp = $files[$index]['tmp_name'];
    		$UP->file_size = $this->file_size = $files[$index]['size'];
    		$this->file_type = preg_replace("/^(.+?);.*$/", "\\1", $files[$index]['type']);
    		$UP->file_type = $this->file_type = strtolower(trim(stripslashes($this->file_type), '"'));
    		$UP->file_name = $this->file_name = $this->_prep_filename($files[$index]['name']);
    		$UP->file_ext	 = $this->file_ext	 = $UP->get_extension($this->file_name);
    		$UP->client_name = $this->client_name = $UP->file_name;
    
    		// Is the file type allowed to be uploaded?
    		if ( ! $UP->is_allowed_filetype())
    		{
    			//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_invalid_filetype')));
                return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    		}
    
    		// Convert the file size to kilobytes
    		if ($this->file_size > 0)
    		{
    			$UP->file_size = $this->file_size = round($this->file_size/1024, 2);
    		}
    
    		// Is the file size within the allowed maximum?
    		if ( ! $UP->is_allowed_filesize())
    		{
    			//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_invalid_filesize')));
                return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    		}
    
    		// Are the image dimensions within the allowed size?
    		// Note: This can fail if the server has an open_basdir restriction.
    		if ( ! $UP->is_allowed_dimensions())
    		{
    			//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_invalid_dimensions')));
                return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    		}
    
    		// Sanitize the file name for security
    		$UP->file_name = $this->file_name = $UP->clean_file_name($this->file_name);
    
    		// Truncate the file name if it's too long
    		if ($UP->max_filename > 0)
    		{
    			$UP->file_name = $this->file_name = $UP->limit_filename_length($this->file_name, $this->max_filename);
    		}
    
    		// Remove white spaces in the name
   			$UP->file_name = $this->file_name = preg_replace("/\s+/", "_", $this->file_name);
    
    		/*
    		 * Validate the file name
    		 * This function appends an number onto the end of
    		 * the file if one with the same name already exists.
    		 * If it returns false there was a problem.
    		 */
    		$UP->orig_name = $this->orig_name = $this->file_name;
    
    		if ($UP->overwrite == FALSE)
    		{
    			$UP->file_name = $this->file_name = $UP->set_filename($this->upload_path, $this->file_name);
    
    			if ($this->file_name === FALSE)
    			{
    				return FALSE;
    			}
    		}
    
    		/*
    		 * Run the file through the XSS hacking filter
    		 * This helps prevent malicious code from being
    		 * embedded within a file.  Scripts can easily
    		 * be disguised as images or other file types.
    		 */
    		if ($UP->xss_clean)
    		{
    			if ($UP->do_xss_clean() === FALSE)
    			{
    				//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_unable_to_write_file')));
                    return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    			}
    		}
    
    		/*
    		 * Move the file to the final destination
    		 * To deal with different server configurations
    		 * we'll attempt to use copy() first.  If that fails
    		 * we'll use move_uploaded_file().  One of the two should
    		 * reliably work in most environments
    		 */
    		if ( ! @copy($this->file_temp, $this->upload_path.$this->file_name))
    		{
    			if ( ! @move_uploaded_file($this->file_temp, $this->upload_path.$this->file_name))
    			{
    				//return ee()->output->show_user_error('general', array(ee()->lang->line('upload_destination_error')));
                    return ee()->output->show_user_error('general', array(lang('attachment_problem')));
    			}
    		}
    
    		/*
    		 * Set the finalized image dimensions
    		 * This sets the image width/height (assuming the
    		 * file was an image).  We use this information
    		 * in the "data" function.
    		 */
    		$UP->set_image_properties($this->upload_path.$this->file_name);
            
            //and we also want to return the data here

    		$data[] = array(
    					'sender_id'				=> ee()->session->userdata('member_id'),
    					'message_id'			=> $this->temp_message_id,
    					'attachment_name'		=> $this->file_name,
    					'attachment_hash'		=> ee()->functions->random('alnum', 20),
    					'attachment_extension'  => $this->file_ext,
    					'attachment_location'	=> $this->file_name,
    					'attachment_date'		=> ee()->localize->now,
    					'attachment_size'		=> $this->file_size
    				);
    					
        
        }

        return $data;
	}
    
    
    
    
   	function _prep_filename($filename)
	{
		if (strpos($filename, '.') === FALSE OR $this->allowed_types == '*')
		{
			return $filename;
		}

		$parts		= explode('.', $filename);
		$ext		= array_pop($parts);
		$filename	= array_shift($parts);

		foreach ($parts as $part)
		{
			if ( ! in_array(strtolower($part), $this->allowed_types) OR $this->mimes_types(strtolower($part)) === FALSE)
			{
				$filename .= '.'.$part.'_';
			}
			else
			{
				$filename .= '.'.$part;
			}
		}

		$filename .= '.'.$ext;

		return $filename;
	}
    
    
    
    
    
    
    function _form($tagdata = '', $action = 'send_private', $extra=array())
    {    
        
        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = ee()->functions->fetch_site_index();
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = ee()->functions->fetch_current_uri();
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        else
        {
            $return = ee()->functions->create_url(ee()->TMPL->fetch_param('return'));
        }
        
        $data['hidden_fields']['ACT'] = ee()->functions->fetch_action_id('Messaging', $action);
		$data['hidden_fields']['RET'] = $return;
        $data['hidden_fields']['PRV'] = ee()->functions->fetch_current_uri();
        
        if (!empty($extra))
        {
            foreach ($extra as $hidden_key=>$hidden_val)
            {
                $data['hidden_fields'][$hidden_key] = $hidden_val;
            }
        }
        
        if (ee()->TMPL->fetch_param('skip_success_message')=='yes')
        {
            $data['hidden_fields']['skip_success_message'] = 'y';
        }
        
        if (ee()->TMPL->fetch_param('ajax')=='yes') $data['hidden_fields']['ajax'] = 'yes';
        if ($action == 'send_pm') $data['enctype'] = 'multipart/form-data';
									      
        $data['id']		= (ee()->TMPL->fetch_param('id')!='') ? ee()->TMPL->fetch_param('id') : 'messaging_form';
        $data['name']		= (ee()->TMPL->fetch_param('name')!='') ? ee()->TMPL->fetch_param('name') : 'messaging_form';
        $data['class']		= (ee()->TMPL->fetch_param('class')!='') ? ee()->TMPL->fetch_param('class') : 'messaging_form';

        $out = ee()->functions->form_declaration($data).$tagdata."\n"."</form>";
        
        return $out;
    }
    
    
    function _format_date($one='', $two='', $three=true)
    {
    	if (version_compare(APP_VER, '2.6.0', '>='))
    	{
			return ee()->localize->format_date($one, $two, $three);
		}
		else
		{
			return ee()->localize->decode_date($one, $two, $three);
		}
    }
    
    
    function _process_pagination($total, $perpage, $start, $basepath='', $out='', $paginate='bottom', $paginate_tagdata='')
    {
        if (version_compare(APP_VER, '2.4.0', '>='))
		{
	        ee()->load->library('pagination');
	        if (version_compare(APP_VER, '2.6.0', '>='))
	        {
	        	$pagination = ee()->pagination->create(__CLASS__);
	        }
	        else
	        {
	        	$pagination = new Pagination_object(__CLASS__);
	        }
            if (version_compare(APP_VER, '2.8.0', '>='))
            {
                ee()->TMPL->tagdata = $pagination->prepare(ee()->TMPL->tagdata);
                $pagination->build($total, $perpage);
            }
            else
            {
                $pagination->get_template();
    	        $pagination->per_page = $perpage;
    	        $pagination->total_rows = $total;
    	        $pagination->offset = $start;
    	        $pagination->build($pagination->per_page);
            }
	        
	        $out = $pagination->render($out);
  		}
  		else
  		{
        
	        if ($total > $perpage)
	        {
	            ee()->load->library('pagination');
	
				$config['base_url']		= $basepath;
				$config['prefix']		= 'P';
				$config['total_rows'] 	= $total;
				$config['per_page']		= $perpage;
				$config['cur_page']		= $start;
				$config['first_link'] 	= ee()->lang->line('pag_first_link');
				$config['last_link'] 	= ee()->lang->line('pag_last_link');
	
				ee()->pagination->initialize($config);
				$pagination_links = ee()->pagination->create_links();	
	            $paginate_tagdata = ee()->TMPL->swap_var_single('pagination_links', $pagination_links, $paginate_tagdata);			
	        }
	        else
	        {
	            $paginate_tagdata = ee()->TMPL->swap_var_single('pagination_links', '', $paginate_tagdata);		
	        }
	        
	        switch ($paginate)
	        {
	            case 'top':
	                $out = $paginate_tagdata.$out;
	                break;
	            case 'both':
	                $out = $paginate_tagdata.$out.$paginate_tagdata;
	                break;
	            case 'bottom':
	            default:
	                $out = $out.$paginate_tagdata;
	        }
	        
    	}
        
        return $out;
    }
    
    private function _xss_clean($data)
    {
        if (version_compare(APP_VER, '3.0.0', '<'))	
        {
            return ee()->security->xss_clean($data);
        }
        else
        {
            return ee('Security/XSS')->clean($data);
        }
    }



}
/* END */
?>