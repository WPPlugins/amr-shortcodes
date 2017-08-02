<?php
/*
Plugin Name: amr shortcodes
Plugin URI: 
Description: View the shortcodes in use on a site, with links to the pages or posts for editing.
Author: anmari
Version: 1.1
Author URI: http://webdesign.anmari.com

*/

if (!class_exists('amr_shortcodes_plugin_admin')) {
	class amr_shortcodes_plugin_admin {
		var $hook 		= 'amr_shortcodes';
		var $filename	= 'amr_shortcodes/amr_shortcodes.php';
		var $longname	= 'Shortcodes';
		var $shortname	= 'Shortcodes';
		var $optionname = '';
		var $homepage	= '';
		var $parent_slug = 'plugin_listings_menu';
		var $accesslvl	= 'manage_options';
		
		function __construct() {  
			add_action('admin_menu', array(&$this, 'register_tools_page') );	
		}		
		
		function register_tools_page() {
			add_management_page( $this->longname, $this->shortname, $this->accesslvl, $this->hook, array(&$this,'config_page'));
		}		
		
		function plugin_options_url() {
			return admin_url( 'tools.php?page='.$this->hook );
		}		
 
		function admin_heading($title)  {
		echo '<div class="wrap" >
			<div id="icon-options-general" class="icon32"><br />
			</div>
			<h2>';
			$active1 = '';
			$active2 = '';
			$active3 = '';
			if (empty($_REQUEST['tab']) ) 
				$active1 = 'nav-tab-active';
			elseif ($_REQUEST['tab'] == 'all') 
				$active2 = 'nav-tab-active';
			else 
				$active3 = 'nav-tab-active';
			echo '<a class="nav-tab '.$active1.'" href="'.$this->plugin_options_url().'">'.$title.' '
			.__('missing functions').'</a> &nbsp; '
			.'<a class="nav-tab '.$active2.'" href="'.add_query_arg('tab','all',$this->plugin_options_url()).'">'
			.__('All shortcodes in use').'</a> &nbsp; '
			.'<a class="nav-tab '.$active3.'" href="'.add_query_arg('tab','available',$this->plugin_options_url()) .'">'.__('View available shortcodes').'</a></h2>';

		}

		
		function config_page() {
			$this->admin_heading($this->longname); 

			if (empty($_REQUEST['tab']) OR ($_REQUEST['tab'] == 'all'))
				$this->where_shortcode();
			else
				$this->shortcodes_available();
		}		
		
		function shortcodes_available() {
		global $shortcode_tags;
		
			$builtin = array('caption','gallery','audio','video','playlist','embed', 'wp_caption');

			ksort($shortcode_tags);
			
			echo '<table class="widefat wp-list-table striped"><thead><tr><th class="manage-column">'
			.__('Shortcode').'</th><th>'
			.__('Built in by WordPress but could be overwritten').'</th><th>'
			.__('Function called').'</th></tr></thead><tbody>';
			foreach ($shortcode_tags as $code => $func) {
				echo '<tr><td>'.$code.'</td><td>';
				if (in_array( $code,$builtin)) _e('built-in');
				else echo ' ';
				echo '</td><td>'.$func.'</td></tr>';
			}
			echo '</tbody></table>';
		}
		
		function where_shortcode() {
			global $wpdb;
			global $shortcode_tags;

			//$pattern = get_shortcode_regex(array('do_widget'));
			
			if (!empty($_REQUEST['tab']) and ($_REQUEST['tab'] == 'all') )
				$doall = true;
			else 
				$doall = false;
			
			$types = get_post_types(array( 'public'=> true), 'names' ); 
			$text = "('".implode("','",$types)."')";
			$results 	= array();
			$query  	= "SELECT * FROM $wpdb->posts WHERE post_type IN ".$text." AND post_status IN ( 'publish', 'future') and post_content LIKE '%[%]%' AND post_date <> '' AND post_date is not null  ORDER BY post_type ASC, post_date DESC;" ;

			$results 	= $wpdb->get_results($query);
			
			echo '<table class="widefat wp-list-table striped"><thead><tr><th>';
			_e('Post');
			echo '</th><th>';
			_e('Type');
			echo '</th><th>';
			_e('Published');
			echo '</th><th>';
			_e('Shortcodes');
			echo '</th></tr></thead><tbody>';
			foreach($results as $i => $result) {
			
				preg_match_all("^\[(.*)\]^",$result->post_content,$matches, PREG_PATTERN_ORDER);

				$shorts = array();	
				
				foreach ($matches[0] as $j=> $m) {
					if (substr($m,0,2) == '[[') continue; // its really not a shortcode
					if (substr($m,0,2) == "['") continue; // its really not a shortcode

					$sp = strpos($m,' '); 			
					if (!$sp) { // there was no space
						$close = strpos($m,']')-1;
						if (!$close) { // its not a shortcode, there was no close
							$code ='';
						}
						else {
							$code =  substr($m,1,$close); // there was no space							
						} 
					}
					else { 
						$code = (substr($m,1,$sp));
					}
					$code = str_replace (' ','',$code);
				
					if (substr($code,0,1) === '/') {// might be closing shortcode, check if we had opening
							$code = substr($code,1,-1);
							//if (strpos($result->post_content,$code ) < strpos($result->post_content,'/'.$code )) {//its ok

						}
						
					if (!empty($code) and (!stristr($code, 'CDATA'))) {
						if ($doall or !shortcode_exists( $code )) {
							$shorts[$code][] = $m;
						}
					}	

				}
			
				if (empty($shorts)) {continue;}
				echo '<tr><td>';
				edit_post_link($result->post_title.' ',' ',' ',$result->ID);
				echo '</td><td>'.$result->post_type;

				echo '</td><td>';
				edit_post_link(substr($result->post_date,0,11),' ',' ',$result->ID);
				if (!($result->post_status == 'publish')) _e($result->post_status);
				echo '</td><td>';
				//preg_match_all("^\[(.*)\]^",$result->post_content,$matches, PREG_PATTERN_ORDER);

				foreach ($shorts as $short=> $instances) {
				
					if ( !shortcode_exists($short ) ) {
							$flag = '<a style="color:red;" href="'.
							get_edit_post_link($result->post_title.' ',' ',' ',$result->ID)
							.'" title="Plugin or theme no longer active - edit the post.">'
							.__('X').'</a>';					
					}
					else {
						$flag = '<span style="color:green;">'.'&#10004;'.'</span>';
					}	
					foreach ($instances as $i => $m) {
						echo $flag.' '.substr($m, 0, strpos($m,']',0)+1) ;
						echo '<br />';
					}	
											
				};
				echo '<td></tr>';
				
			}
			echo '</tbody></table>';
			
		}
	}
}	

function amr_shortcodes_add_action_links ( $links ) {
 $mylinks[] = 
 '<a title="Go" href="'.admin_url( 'tools.php?page=amr_shortcodes').'">'  . 'Manage Shortcodes</a>';
return array_merge( $links, $mylinks );
}

function amr_shortcodes_load_text() { 
// wp (see l10n.php) will check wp-content/languages/plugins if nothing found in plugin dir
	$result = load_plugin_textdomain( 'amr-shortcodes', false, 
	dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action('plugins_loaded'         , 'amr_shortcodes_load_text' );

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'amr_shortcodes_add_action_links' );

if (is_admin() ) 	$amr_shortcodes_plugin_admin = new amr_shortcodes_plugin_admin();  
?>