<?php
/*
Plugin Name: MasterIT Authors List
Plugin URI: http://www.masterit.ru/wp-plugin-authors-list/
Description: Plugin for ordered authors list in WordPress blog
Version: 1.1.1
Author: Alexey Balin
Author URI: http://www.masterit.ru
*/

/* Copyright (C) 2010 MasterIT (www.masterit.ru)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA */

	
	global $wpdb, $current_user, $user_ID;	

	$db_version = 12;
	require_once dirname(__FILE__).'/mital.class.php';
	require_once dirname(__FILE__).'/../../../wp-includes/pluggable.php';
	get_currentuserinfo();
	//init class
	$MIT_al_plugin = new masterit_authors_list();

	function MITal_activate() {
		$MIT_al_plugin = new masterit_authors_list();
	}

	//installation when activate plugin
	register_activation_hook(__FILE__, array("masterit_authors_list","MITal_install") );

	add_action("plugins_loaded", array($MIT_al_plugin, 'initPlugin'));
	add_action('recount_author_posts_cron',  array($MIT_al_plugin, 'recount_author_posts'));
	add_action('recount_author_comments_cron',  array($MIT_al_plugin, 'recount_author_comments'));
	add_action('recount_posts_cron',  array($MIT_al_plugin, 'recount_posts'));


	
	add_action('publish_post', array($MIT_al_plugin, 'update_author_posts'));
	add_action('user_register', array($MIT_al_plugin, 'add_author'));
	add_action('preprocess_comment', array($MIT_al_plugin, 'update_author_comments'));
	add_action('delete_user', array($MIT_al_plugin, 'delete_author'));
	add_action('delete_post', array($MIT_al_plugin, 'delete_post'));
	add_action('edit_post', array($MIT_al_plugin, 'edit_post'));
	add_action('delete_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('edit_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('spammed_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('spam_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('trash_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('unspammed_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('unspam_comment', array($MIT_al_plugin, 'delete_comment'));
	add_action('untrash_comment', array($MIT_al_plugin, 'delete_comment'));

	add_shortcode( 'authors-list', 'print_autors_list' );

	$MIT_al_plugin->user_login();


	function print_autors_list(){
		
	}
