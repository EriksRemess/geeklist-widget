<?php
/*
	Plugin Name: Geeklist Widget
	Plugin URI: http://geekli.st/eriks
	Description: Latest from your Geeklist account in your sidebar
	Version: 0.1
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
	
	function Geeklist_getLinks($instance){
		$count = (isset($instance['count']) && intval($instance['count']) && $instance['count'] > 0)?$instance['count']:10;
		if($count <= 50):
			$data = $this->Geeklist_apiCall($instance, "user/links", array("count" => $count));
			return $data['links'];
		else:
			$first_page = $this->Geeklist_apiCall($instance, "user/links", array("count" => 50, "page" => 1));
			$links = (array)$first_page['links'];
			if($first_page['total_links'] > 50):
				if($count > $first_page['total_links']):
					$count = $first_page['total_links'];
					$page = 2;
					do {
						$data = $this->Geeklist_apiCall($instance, "user/links", array("count" => 50, "page" => $page));
						$links = array_merge($links, $data['links']);
						$page++;
					} while(count($links) < $count);
					$links = array_slice($links, $count);
				endif;
			else:
				$links = $first_page['links'];
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
		$instance['count'] = intval($new_instance['count'])?$new_instance['count']:10;
		return $instance;
	}
	
	function form($instance){
		$defaults = array(
			'title' => __('Latest links', 'geeklist'),
			'oauth_consumer_key' => __('', 'geeklist'),
			'oauth_consumer_secret' => __('', 'geeklist'),
			'oauth_token' => __('', 'geeklist'),
			'oauth_token_secret' => __('', 'geeklist'), 
			'count' => 10
		);
		$instance = wp_parse_args((array)$instance, $defaults);
		?>
		<p>
			<label for="<?=$this->get_field_id('title'); ?>"><?php _e('Title:', 'geeklist'); ?></label>
			<input id="<?=$this->get_field_id('title'); ?>" name="<?=$this->get_field_name('title'); ?>" value="<?=$instance['title']; ?>" class="widefat" />
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
		$links = $this->Geeklist_getLinks($instance);
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
	}
	
}