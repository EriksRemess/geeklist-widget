<?php
/*
	Plugin Name: Geeklist Widget
	Plugin URI: https://github.com/EriksRemess/Geeklist-Wordpress-Widget
	Description: Latest from your Geeklist account in your sidebar
	Version: 0.2
	Author: Eriks Remess
	Author URI: http://geekli.st/eriks
*/
add_action('widgets_init', 'load_geeklist');
function load_geeklist() {
	register_widget('Geeklist_Widget');
}
class Geeklist_Widget extends WP_Widget {
	
	function Geeklist_Widget(){
		$widget_ops = array('description' => __('Latest from your Geeklist account in your sidebar', 'geeklist'));
		$control_ops = array('width' => 300, 'height' => 350, 'id_base' => 'geeklist-widget');
		$this->WP_Widget('geeklist-widget', __('Geeklist', 'geeklist'), $widget_ops, $control_ops);
	}
	
	function Geeklist_getList($instance, $listtype){
		if($listtype == "cards"):
			$method = "user/cards";
			$total = "total_cards";
			$group = "cards";
		elseif($listtype == "contribs"):
			$method = "user/contribs";
			$total = "total_cards";
			$group = "cards";
		elseif($listtype == "links"):
			$method = "user/links";
			$total = "total_links";
			$group = "links";
		endif;
		$count = (isset($instance['count']) && intval($instance['count']) && $instance['count'] > 0)?$instance['count']:10;
		if($count <= 50):
			$data = $this->Geeklist_apiCall($instance, $method, array("count" => $count));
			return $data[$group];
		else:
			$first_page = $this->Geeklist_apiCall($instance, $method, array("count" => 50, "page" => 1));
			$links = (array)$first_page[$group];
			if($first_page[$total] > 50):
				if($count > $first_page[$total]):
					$count = $first_page[$total];
					$page = 2;
					do {
						$data = $this->Geeklist_apiCall($instance, $method, array("count" => 50, "page" => $page));
						$links = array_merge($links, $data[$group]);
						$page++;
					} while(count($links) < $count);
					$links = array_slice($links, $count);
				endif;
			else:
				$links = $first_page[$group];
			endif;
			return $links;
		endif;
	}
	
	function Geeklist_ApiCall($instance, $method, $params = array(), $http_method = "GET"){
		if(isset(
			$instance["oauth_consumer_key"],
			$instance["oauth_consumer_secret"],
			$instance["oauth_token"],
			$instance["oauth_token_secret"]
		)):
			$url = "http://api.geekli.st/v1/".$method;
			$params = array_merge($params, array(
				"oauth_nonce" => md5(microtime().mt_rand()),
				"oauth_timestamp" => time(),
				"oauth_consumer_key" => $instance["oauth_consumer_key"],
				"oauth_token" => $instance["oauth_token"]
			));
			ksort($params);
			$params = array_merge($params, array(
				"oauth_signature" => base64_encode(hash_hmac('sha1',
					implode("&", array(
						$http_method,
						rawurlencode($url),
						rawurlencode(http_build_query($params))
					)),
					implode("&", array(
						$instance["oauth_consumer_secret"],
						$instance["oauth_token_secret"]
					)),
					true
				))
			));
			ksort($params);
			if($http_method == "POST"):
				$response = wp_remote_post($url, array("body" => $params),
					array(
						'sslverify' => apply_filters('https_local_ssl_verify', false)
					)
				);
			else:
				$response = wp_remote_get($url."?".http_build_query($params),
					array(
						'sslverify' => apply_filters('https_local_ssl_verify', false)
					)
				);
			endif;
			if(!is_wp_error($response) && $response['response']['code'] < 400 && $response['response']['code'] >= 200):
				$result = json_decode($response['body'], true);
				if($result && $result['status'] == "ok"):
					return $result['data'];
				endif;
			endif;
		endif;
		return array();
	}
	
	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['oauth_consumer_key'] = strip_tags($new_instance['oauth_consumer_key']);
		$instance['oauth_consumer_secret'] = strip_tags($new_instance['oauth_consumer_secret']);
		$instance['oauth_token'] = strip_tags($new_instance['oauth_token']);
		$instance['oauth_token_secret'] = strip_tags($new_instance['oauth_token_secret']);
		$instance['listtype'] = in_array($new_instance['listtype'], array("cards", "contribs", "links"))?$new_instance['listtype']:"links";
		$instance['count'] = intval($new_instance['count'])?$new_instance['count']:10;
		return $instance;
	}
	
	function form($instance){
		$defaults = array(
			'title' => __('Latest at Geeklist', 'geeklist'),
			'oauth_consumer_key' => __('', 'geeklist'),
			'oauth_consumer_secret' => __('', 'geeklist'),
			'oauth_token' => __('', 'geeklist'),
			'oauth_token_secret' => __('', 'geeklist'),
			'listtype' => __('links', 'geeklist'),
			'count' => 10
		);
		$instance = wp_parse_args((array)$instance, $defaults);
		?>
		<p>
			<label for="<?=$this->get_field_id('title'); ?>"><?php _e('Title:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('title'); ?>" name="<?=$this->get_field_name('title'); ?>" value="<?=$instance['title']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?=$this->get_field_id('listtype'); ?>"><?php _e('List type:', 'geeklist'); ?></label>
			<select name="<?=$this->get_field_name('listtype'); ?>" id="<?=$this->get_field_id('listtype'); ?>">
				<option value="cards"<?=($instance['listtype']=="cards"?" selected":"");?>>cards</option>
				<option value="contribs"<?=($instance['listtype']=="contribs"?" selected":"");?>>contributions</option>
				<option value="links"<?=($instance['listtype']=="links"?" selected":"");?>>links</option>
			</select>
		</p>
		<p>
			<label for="<?=$this->get_field_id('oauth_consumer_key'); ?>"><?php _e('Consumer key:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('oauth_consumer_key'); ?>" name="<?=$this->get_field_name('oauth_consumer_key'); ?>" value="<?=$instance['oauth_consumer_key']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?=$this->get_field_id('oauth_consumer_secret'); ?>"><?php _e('Consumer secret:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('oauth_consumer_secret'); ?>" name="<?=$this->get_field_name('oauth_consumer_secret'); ?>" value="<?=$instance['oauth_consumer_secret']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?=$this->get_field_id('oauth_token'); ?>"><?php _e('Access token:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('oauth_token'); ?>" name="<?=$this->get_field_name('oauth_token'); ?>" value="<?=$instance['oauth_token']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?=$this->get_field_id('oauth_token_secret'); ?>"><?php _e('Access token secret:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('oauth_token_secret'); ?>" name="<?=$this->get_field_name('oauth_token_secret'); ?>" value="<?=$instance['oauth_token_secret']; ?>" class="widefat" />
		</p>
		<p>
			<label for="<?=$this->get_field_id('count'); ?>"><?php _e('Count:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('count'); ?>" name="<?=$this->get_field_name('count'); ?>" value="<?=$instance['count']; ?>" class="widefat" />
		</p>
		<?php
	}
	
	function widget($args, $instance) {
		extract( $args);
		$title = apply_filters('widget_title', $instance['title']);
		$instance['listtype'] = in_array($instance['listtype'], array("cards", "contribs", "links"))?$instance['listtype']:"links";
		if($instance['listtype'] == "links"):
			$links = $this->Geeklist_getList($instance, "links");
			if(!empty($links)):
				echo $before_widget;
				if($title):
					echo $before_title.$title.$after_title;
				endif;
				echo '<ul class="geeklist">';
				foreach($links as $link):
					echo '<li><a href="'.$link['url'].'" title="'.htmlspecialchars($link['description']).'">'.htmlspecialchars($link['title']).'</a></li>';
				endforeach;
				echo '</ul>';
				echo $after_widget;
			endif;
		elseif(in_array($instance['listtype'], array("cards", "contribs"))):
			$cards = $this->Geeklist_getList($instance, $instance['listtype']);
			if(!empty($cards)):
				echo $before_widget;
				if($title):
					echo $before_title.$title.$after_title;
				endif;
				echo '<ul class="geeklist">';
				foreach($cards as $card):
					echo '<li><a href="https://geekli.st'.$card['permalink'].'" title="'.(isset($card['tasks'])?htmlspecialchars("I ".implode(", ", $card['tasks']).(isset($card['skills'])?" using ".implode(", ", $card['skills']):"")):"").'">'.htmlspecialchars($card['headline']).'</a></li>';
				endforeach;
				echo '</ul>';
				echo $after_widget;
			endif;
		endif;
	}
	
}