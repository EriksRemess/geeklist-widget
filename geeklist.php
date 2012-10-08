<?php
/*
	Plugin Name: Geeklist Widget
	Plugin URI: http://wordpress.org/extend/plugins/geeklist-widget/
	Description: Latest from your Geeklist account in your sidebar
	Version: 0.4
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
						if(count($data) < 50):
							break;
						else:
							$page++;
						endif;
					} while(count($links) < $count);
					$links = array_slice($links, $count);
				endif;
			else:
				$links = $first_page[$group];
			endif;
			return $links;
		endif;
	}
	
	function Geeklist_UserActivities($instance){
		$count = (isset($instance['count']) && intval($instance['count']) && $instance['count'] > 0)?$instance['count']:10;
		if($count <= 50):
			$activities = $this->Geeklist_ApiCall($instance, "user/activity", array("count" => $count));
		else:
			$activities = $this->Geeklist_apiCall($instance, "user/activity", array("count" => 50, "page" => 1));
			if(!count($activities) < 50):
				$page = 2;
				do {
					$data = $this->Geeklist_apiCall($instance, "users/activity", array("count" => 50, "page" => $page));
					$activities = array_merge($activities, $data);
					if(count($data) < 50):
						break;
					else:
						$page++;
					endif;
				} while(count($activities) < $count);
				$activities = array_slice($activities, $count);
			endif;
		endif;
		return $activities;
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
		$instance['listtype'] = in_array($new_instance['listtype'], array("cards", "contribs", "links", "useractivity"))?$new_instance['listtype']:"links";
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
				<option value="useractivity"<?=($instance['listtype']=="useractivity"?" selected":"");?>>my activity</option>
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
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$instance['listtype'] = in_array($instance['listtype'], array("cards", "contribs", "links", "useractivity"))?$instance['listtype']:"links";
		if($instance['listtype'] == "links"):
			$links = $this->Geeklist_getList($instance, "links");
			if(!empty($links)):
				echo $before_widget;
				if($title):
					echo $before_title.$title.$after_title;
				endif;
				echo '<ul class="geeklist">';
				foreach($links as $link):
					printf(__('<li><a href="%1$s" title="%2$s">%3$s</a></li>'), $link['url'], htmlspecialchars($link['description']), htmlspecialchars($link['title']));
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
					printf(
						__('<li><a href="https://geekli.st%1$s" title="%2$s">%3$s</a>'),
						$card['permalink'],
						(isset($card['tasks'])&&(!empty($card['tasks']))?htmlspecialchars(sprintf(__('I %1$s'), implode(", ", $card['tasks'])).
						(isset($card['skills'])&&(!empty($card['skills']))?sprintf(__(' using %1$s'), implode(", ", $card['skills'])):"")):""),
						htmlspecialchars($card['headline'])
					);
				endforeach;
				echo '</ul>';
				echo $after_widget;
			endif;
		elseif($instance['listtype'] == "useractivity"):
			$activities = $this->Geeklist_UserActivities($instance);
			if(!empty($activities)):
				echo $before_widget;
				if($title):
					echo $before_title.$title.$after_title;
				endif;
				echo '<ul class="geeklist">';
				foreach($activities as $activity):
					$activity_time = human_time_diff(strtotime($activity['updated_at']), time());
					echo '<li>';
					switch($activity['type']):
						case "vote":
							printf(__('I voted on a link <a href="https://geekli.st%1$s">%2$s</a> by <a href="https://geekli.st/%3$s">%3$s</a>'), $activity['gfk']['permalink'], $activity['gfk']['title'], $activity['gfk']['screen_name']);
							break;
						case "commit":
							printf(__('I made a commit <a href="https://geekli.st%1$s">%2$s</a> to <a href="https://geekli.st/%3$s">%4$s</a>'), $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['status']), $activity['gfk']['commit']['repo_url'], htmlspecialchars($activity['gfk']['commit']['repo']));
							break;
						case "highfive":
							printf(__('I high fived a %1$s "<a href="https://geekli.st%2$s">%3$s</a>" by <a href="https://geekli.st/%4$s">%4$s</a>'), $activity['gfk']['type'], $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['headline']), $activity['gfk']['screen_name']);
							break;
						case "link":
							printf(__('I added a new link <a href="https://geekli.st%s">%s</a>'), $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['link']['title']));
							break;
						case "follow":
							printf(__('I followed <a href="https://geekli.st/%1$s">%1$s</a>'), $activity['gfk']['screen_name']);
							break;
						case "connection":
							printf(__('I connected with <a href="https://geekli.st/%1$s">%1$s</a>'), $activity['gfk']['screen_name']);
							break;
						case "card":
							if(!isset($activity['subtype'])):
								printf(__('I published a card <a href="https://geekli.st%1$s">%2$s</a>', $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['headline'])));
							else:
								if($activity['subtype'] == "info-update"):
									printf(__('I updated information on my card <a href="https://geekli.st%1$s">%2$s</a>'), $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['headline']));
								elseif($activity['subtype'] == "screenshots-update"):
									printf(__('I updated screenshots on my card <a href="https://geekli.st%1$s">%2$s</a>'), $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['headline']));
								endif;
							endif;
							break;
						case "repo":
							$repos = array();
							foreach($activity['gfk']['repos'] as $repo):
								$repos[] = '<a href="https://geekli.st'.$repo['permalink'].'">'.htmlspecialchars($repo['name']).'</a>';
							endforeach;
							printf(__('I will publish micro updates from the following Github repos: %1$s'), implode(', ', $repos));
							break;
						case "micro":
							printf(__('I published a micro <a href="https://geekli.st%1$s">%2$s</a>'), $activity['gfk']['permalink'], htmlspecialchars($activity['gfk']['status']));
							break;
						case "profile":
							if($activity['gfk']['headline'] == "featured cards"):
								printf(__('I changed my featured cards'));
							endif;
							break;
					endswitch;
					printf(__(' [%1$s ago]'), $activity_time);
					echo '</li>';
				endforeach;
				echo '</ul>';
				echo $after_widget;
			endif;
		endif;
	}
	
}