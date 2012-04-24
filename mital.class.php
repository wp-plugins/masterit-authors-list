<?php 
class masterit_authors_list {
 	
 	var $version = "1.1.1";
 	var $authors_count;

	function initPlugin() {
		$loc = get_locale();
		
		$moFile = dirname( __FILE__ )."/masterit-authors-list-".$loc.".mo";
		//echo $moFile;
		if( !empty($loc) && file_exists( $moFile ) )
			load_plugin_textdomain( "masterit-authors-list", false, "masterit-authors-list");

		//register_sidebar_widget(__('Authors List', 'masterit-authors-list', 'masterit-authors-list'), array("masterit_top_authos")); 
	}

	// Installs the plugin.^
	public static function MITal_install() {
		global $wpdb, $table_prefix, $db_version;
		$wpdb->show_errors(); 
		
		if( method_exists ( $wpdb, "get_blog_prefix" ) )
			$blog_prefix = $wpdb->get_blog_prefix(1);
		else
			$blog_prefix = $table_prefix; 

		$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."authors` (
			`user_id` smallint(5) UNSIGNED NOT NULL ,
			`user_role` varchar(32) NOT NULL,
			`post_count` int(11) UNSIGNED NOT NULL,
			`comment_count` int(11) UNSIGNED NOT NULL,
			`dt_login` datetime NOT NULL,
			PRIMARY KEY  (`user_id`),
			KEY `user_role` (`user_role`),
			KEY `post_count` (`post_count`),
			KEY `comment_count` (`comment_count`),
			KEY `dt_login` (`dt_login`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);
	
		$wpdb->query("CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."authors_stats` (`stats_id` int(10) unsigned NOT NULL auto_increment, `num_views` int(10) unsigned NOT NULL,`num_clicks` smallint(5) unsigned NOT NULL,`num_users` int(10) unsigned NOT NULL,`num_posts` int(10) unsigned NOT NULL,`dt_update` date NOT NULL default '0000-00-00',PRIMARY KEY  (`stats_id`),KEY `num_views` (`num_views`),KEY `num_users` (`num_users`),KEY `num_posts` (`num_posts`),KEY `dt_update` (`dt_update`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8");
		//Все пользователи
		$users = get_users_of_blog();
		//получаем роли (права)
		$users_meta = $wpdb->get_results("
             SELECT user_id, meta_key, meta_value
                    FROM
              $wpdb->usermeta
                    WHERE
              meta_key LIKE '%_capabilities'"
			);
			
			//создаем массивчик по user_id
			$author_meta=array();
			
			foreach ( (array) $users_meta  as $row ) {
				$meta = array_keys(unserialize($row->meta_value));
				$author_meta[$row->user_id] = $meta[0];
			}
 

			//Теперь готовим данные
			$values =array();
        		foreach ( (array) $users as $user ) {
        			$values[] = "($user->ID,'".$author_meta[$user->ID]."')";
			}
 
			if( count( $values ) )
				$wpdb->query("INSERT IGNORE into `".$wpdb->prefix."authors` (`user_id`,`user_role`) VALUES".join(",", $values));
			
			//Обновляем количество постов	
			$wpdb->query("UPDATE `".$wpdb->prefix."authors` a SET a.post_count = (SELECT COUNT(*) FROM `$wpdb->posts` WHERE post_author = a.user_id AND post_type = 'post' AND post_status = 'publish')");

			//Обновляем количество комментов
			$wpdb->query("UPDATE `".$wpdb->prefix."authors` a SET a.comment_count = (SELECT COUNT(*) FROM `$wpdb->comments` c WHERE c.user_id = a.user_id AND comment_approved = 1)");

			add_option("mital_db_version", $db_version );
			wp_schedule_event( mktime(0,0,0,date("m"), date("d")+1, date("Y")), 'daily','recount_posts_cron');
	}


	/* return top authors */
	public function masterit_top_authors( ) {
		global $wpdb, $current_user;
		
		$this->authors_count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."authors WHERE 1");
		if( $this->authors_count ) {
			$offset=0;
			
			if( isset($_REQUEST['offset']) ) 
				$offset = $_REQUEST['offset'];
			
			$order = "post_count desc";
			$sorted_field = "post_count";
			if( isset($_REQUEST['order']) ) {
				switch($_REQUEST['order']) {
					case"comment_count":
						$order = "comment_count desc,post_count desc,dt_login desc";
						$sorted_field = "comment_count";
					break;
					case"post_count":
						$order = "post_count desc,dt_login desc,comment_count desc";
						$sorted_field = "post_count";
					break;					
					case"dt_login":
						$order = "dt_login desc, post_count desc,comment_count desc";
						$sorted_field = "dt_login";
					break;					
					
/*					
 * 					case"nic":
						$order = "nicename asc";
					break;					
 *
 */
				}
			}
			
			//Get authors statistic
			$authors = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."authors` WHERE 1 ORDER BY ".$order." LIMIT $offset,20");
			$author_ids = array();
			$author_stat = array();
			$authors_list = array();
			foreach ((array) $authors as $author ){
				$author_ids[] = $author->user_id;
				$author_stat[$author->user_id] = (array) $author;
			}
			
			$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE ID IN(".join(",",$author_ids).")");
			$sorted = array();
			$i=0;
			foreach ((array) $users as $user ){
				$sorted[$i] = (array) $author_stat[$user->ID][$sorted_field];
				$authors_list[$i] = array(
					"user_id" => $user->ID,
					"user_nicename" => $user->user_nicename,
					"display_name" => $user->display_name,
					"post_count" => $author_stat[$user->ID]['post_count'],
					"comment_count" => $author_stat[$user->ID]['comment_count'],
					"last_login" => $author_stat[$user->ID]['dt_login']
				);
				$i++;
			}
			array_multisort($sorted, SORT_DESC, $authors_list);
			return $authors_list;
		} else {
			return array();
		}
	}


	

	public function get_plugin_url() {
		
		//Try to use WP API if possible, introduced in WP 2.6
		if (function_exists('plugins_url')) return trailingslashit(plugins_url(basename(dirname(__FILE__))));
		
		//Try to find manually... can't work if wp-content was renamed or is redirected
		$path = dirname(__FILE__);
		$path = str_replace("\\","/",$path);
		$path = trailingslashit(get_bloginfo('wpurl')) . trailingslashit(substr($path,strpos($path,"wp-content/")));
		return $path;
	}

	
	//Update author counts
	public function update_author_posts($post_ID) {
		global $wpdb;
		$post = get_post($post_id );
		//just add 1 post to post_count
		$wpdb->get_results("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET post_count = post_count + 1 WHERE user_id = ".$post->post_author);
		wp_schedule_single_event( time()+10, 'recount_author_posts_cron', array($post->post_author) );
		return $post_ID;
	}

	//Update author comment counts
	public function update_author_comments( $comment ) {
		global $wpdb, $current_user;
		//just add 1 post to post_count
		if( $comment->user_id  ) {
			$wpdb->get_results("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET comment_count = comment_count + 1 WHERE user_id = ".$comment->user_id);
			$wpdb->print_error();
			wp_schedule_single_event( time()+10, 'recount_author_comments_cron', array($comment->user_id) );
		}
		return $comment;
	}
	
	
	//recount author's posts 
	public function recount_author_posts( $author_id ) {
		global $wpdb;
		if( intval($author_id ) > 0 )  {
			$posts_count = $wpdb->get_var($wpdb->prepare("
				SELECT 
					COUNT(ID) AS count 
				FROM $wpdb->posts 
				WHERE 
					post_author = ".$author_id." AND 
					post_type = 'post' AND 
					" . get_private_posts_cap_sql( 'post' ) . " 
				")
			);
			$wpdb->query("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET post_count = ".$posts_count." WHERE user_id = ".$author_id );
		}
	}
	
	//recount all posts by authors
	public function recount_posts( ) {
		global $wpdb;
		$wpdb->query("UPDATE `".$wpdb->prefix."authors` a SET a.post_count = (SELECT COUNT(*) FROM `$wpdb->posts` WHERE post_author = a.user_id AND post_type = 'post' AND post_status = 'publish')");
		$wpdb->query("UPDATE `".$wpdb->prefix."authors` a SET a.comment_count = (SELECT COUNT(*) FROM `$wpdb->comments` c WHERE c.user_id = a.user_id AND comment_approved = 1)");
		$wpdb->query("ALTER TABLE `".$wpdb->prefix."authors` ORDER BY `post_count` DESC");
	}
	
	//recount totalcomments
	public function recount_author_comments( $author_id ) {
		global $wpdb;
		if( intval($author_id ) > 0 )  {
			$comment_count = $wpdb->get_var($wpdb->prepare("
				SELECT 
					COUNT(comment_ID) AS count 
				FROM 
					$wpdb->comments 
				WHERE 
					user_id = ".$author_id." AND 
					comment_approved = 1 
				"
				)
			);
			$wpdb->query("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET comment_count = ".$comment_count." WHERE user_id = ".$author_id );
		}

	//	$wpdb->print_error();
	//	$wpdb->print_error();
	}
	
	
	public function add_author( $user_id ) {
		global $wpdb;
		$wpdb->query("INSERT INTO `".$wpdb->prefix."authors` (user_id,user_role,post_count,comment_count,dt_login)VALUES($user_id,'author',0,0,0)");
		return $user_id;
		
	}
	public function delete_author( $user_id ) {
		global $wpdb;
		$wpdb->query("DELETE LOW_PRIORITY FROM `".$wpdb->prefix."authors` user_id = $user_id LIMIT 1");
		return $user_id;
	}
	
	//При удалении просто отнимаем и ставим на очередь пересчет
	public function delete_post( $post_id ) {
		global $wpdb;
		$post = get_post($post_id );
		$wpdb->query("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET post_count = IF(post_count > 1, post_count - 1,0) WHERE user_id = $post->post_author LIMIT 1");
		wp_schedule_single_event( time()+10, 'recount_author_posts_cron', array($post->post_author) );
		return $post_id;
	}


	//При редактировании ставим пересчет в крон
	public function edit_post( $post_id ) {
		global $wpdb;
		$post = get_post($post_id );
		wp_schedule_single_event( time()+10, 'recount_author_posts_cron', array($post->post_author) );
		return $post_id;
	}
	
	public function delete_comment( $comment_id ) {
		global $wpdb;
		$comment = get_comment($comment_id );

		if( $comment->user_id ) {
			$wpdb->query("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET comment_count = IF(comment_count > 1,comment_count - 1,0) WHERE user_id = $comment->user_id LIMIT 1");
			wp_schedule_single_event( time()+10, 'recount_author_comments_cron', array($comment->user_id) );
		}
		return $comment_id;
	}
	
	
	
	public function user_login(){
		global $wpdb, $current_user;
		$wpdb->query("UPDATE LOW_PRIORITY `".$wpdb->prefix."authors` SET dt_login = NOW() WHERE user_id = ".$current_user->ID );
	}
	
	
} // masterit_authors_list


