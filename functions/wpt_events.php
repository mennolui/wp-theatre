<?php
class WPT_Events {

	function __construct($args = array()) {

		// Set filters
		$defaults = array(
			'limit' => false,
			'upcoming' => true,
			'past' => false,
			'month' => false,
			'category' => false,
			'production' => false
		);
		$this->filters = wp_parse_args( $args, $defaults );

		add_action( 'plugins_loaded', array($this,'plugins_loaded' ));

	}
	
	public function __toString() {
		return $this->html();
	}
	
	public function __invoke() {
		return $this->get();
	}
	
	/**
	 * Set month and category filters from GET parameters.
	 * @since 0.5
	 */
	function plugins_loaded() {
		if (!empty($_GET[__('month','wp_theatre')])) {
			$this->filters['month'] = $_GET[__('month','wp_theatre')];
		}		
		if (!empty($_GET[__('category','wp_theatre')])) {
			if ($category = get_category_by_slug($_GET[__('category','wp_theatre')])) {
	  			$this->filters['category'] = $category->term_id;				
			}
		}
	}
	
	/**
	 * An array of all events.
	 * @since 0.5
	 */
 	public function all() {
 		$this->filters['past'] = false;
 		$this->filters['upcoming'] = false;
 		return $this->get();
	}
		
	/**
	 * An array of all categories with upcoming events.
	 * @since 0.5
	 */
	function categories() {
		$current_category = $this->filters['category'];
		
		// temporarily disable current month filter
		$this->filters['category'] = false;

		// get all events according to remaining filters
		$events = $this->get();		
		$categories = array();
		foreach ($events as $event) {
			$post_categories = wp_get_post_categories( $event->production()->ID );
			foreach($post_categories as $c){
				$cat = get_category( $c );
				$categories[$cat->slug] = $cat->name;
			}
		}
		asort($categories);
		
		// reset current month filter
		$this->filters['category'] = $current_category;
		
		return $categories;
		
	}
	
	/**
	 * An array of all filtered events.
	 * @since 0.5
	 */
	function get() {
		$hash = md5(serialize($this->filters));
		if (empty($this->events[$hash])) {
			$this->events[$hash] = $this->load();
		}
		return $this->events[$hash];				
	}

	/**
	 * A list of upcoming events in HTML.
	 * 
	 * Example:
	 *
	 * $args = array('paginateby'=>'month');
	 * echo $wp_theatre->events->html($args); // a list of all upcoming events, paginated by month
	 *
	 * @since 0.5
	 *
	 * @param array $args {
	 *     An array of arguments. Optional.
	 *
	 *     @type bool $paged Paginate the list by month. Default <false>.
	 *     @type bool $grouped Group the list by month. Default <false>.
	 *     @type int $limit Limit the list to $limit events. Use <false> for an unlimited list. Default <false>.
	 * }
 	 * @return string HTML.
	 */
	public function html($args=array()) {
		$defaults = array(
			'paged' => false, //deprecated
			'grouped' => false,
			'thumbnail'=>true,
			'tickets'=>true,
			'fields'=>NULL,
			'hide'=>NULL,
			'paginateby' => array()
		);
		$args = wp_parse_args( $args, $defaults );
		
		// translate deprecated 'paged' argument
		if ($args['paged'] && !in_array('month', $args['paginateby'])) {
			$args['paginateby'][] ='month';
		}

		$classes = array();
		$classes[] = "wpt_events";

		// Thumbnail
		if (!$args['thumbnail']) {
			$classes[] = 'wpt_events_without_thumbnail';
		}

		$html = '';

		if (in_array('month',$args['paginateby'])) {
			$months = $this->months();
			
			if (!empty($_GET[__('month','wp_theatre')])) {
				$page = $_GET[__('month','wp_theatre')];
			} else {
				$page = $months[0];				
			}

			$html.= '<nav>';
			foreach($months as $month) {
				$url = remove_query_arg(__('month','wp_theatre'));
				$url = add_query_arg( __('month','wp_theatre'), sanitize_title($month) , $url);
				$html.= '<span>';
				
				$title = date_i18n('M Y',strtotime($month));
				if (sanitize_title($month) != $page) {
					$html.= '<a href="'.$url.'">'.$title.'</a>';
				} else {
					$html.= $title;
					
				}
				$html.= '</span>';
			}
			$html.= '</nav>';
			
			$events_args[__('month','wp_theatre')] = $page;
		}
	
		if (in_array('category',$args['paginateby'])) {
			$categories = $this->categories();

			$page = '';
			if (!empty($_GET[__('category','wp_theatre')])) {
				$page = $_GET[__('category','wp_theatre')];
			}
			
			$html.= '<nav class="wpt_event_categories">';
			if (empty($page)) {
				$html.= __('All','wp_theatre').' '.__('categories','wp_theatre');
			} else {				
				$url = remove_query_arg(__('category','wp_theatre'));
				$html.= '<a href="'.$url.'">'.__('All','wp_theatre').' '.__('categories','wp_theatre').'</a>';
			}
			
			$html.= '<span>';
			
			$html.= '</span>';
			
			foreach($categories as $slug=>$name) {
				$url = remove_query_arg(__('category','wp_theatre'));
				$url = add_query_arg( __('category','wp_theatre'), $slug , $url);
				$html.= '<span>';
				
				if ($slug != $page) {
					$html.= '<a href="'.$url.'">'.$name.'</a>';
				} else {
					$html.= $name;
					
				}
				$html.= '</span>';
			}
			$html.= '</nav>';
		}


		$events = $this->get();

		$event_args = array();
		if (isset($args['fields'])) { $event_args['fields'] = $args['fields']; }
		if (isset($args['hide'])) { $event_args['hide'] = $args['hide']; }
		if (isset($args['thumbnail'])) { $event_args['thumbnail'] = $args['thumbnail']; }
		if (isset($args['tickets'])) { $event_args['tickets'] = $args['tickets']; }

		$group = '';
		foreach ($events as $event) {
			if ($args['grouped']) {
				$month = date('Y-m',$event->datetime());
				if ($group != $month) {
					$html.= '<h3>'.date_i18n('F',$event->datetime()).'</h3>';
					$group = $month;
				}
			}
			$html.=$event->html($event_args);
		}

		// Wrapper
		$html = '<div class="'.implode(' ',$classes).'">'.$html.'</div>'; 
		
		return $html;
	}
	
	/**
	 * Setup the current selection of events.
	 * 
	 * @since 0.5
	 *
 	 * @return array Events.
	 */
	function load() {
		global $wpdb;

		$querystr = "
			SELECT events.ID
			FROM $wpdb->posts AS
			events
			
			JOIN $wpdb->postmeta AS productions on events.ID = productions.post_ID
			LEFT OUTER JOIN $wpdb->term_relationships AS categories on productions.meta_value = categories.object_id
			JOIN $wpdb->postmeta AS event_date on events.ID = event_date.post_ID
			
			WHERE 
			events.post_type = '".WPT_Event::post_type_name."'
			AND events.post_status='publish'
			AND productions.meta_key = '".WPT_Production::post_type_name."'
			AND event_date.meta_key = 'event_date'
		";
		
		if ($this->filters['upcoming']) {
			$querystr.= ' AND event_date.meta_value > NOW( )';
		} elseif ($this->filters['past']) {
			$querystr.= ' AND event_date.meta_value < NOW( )';
		}
		
		if ($this->filters['month']) {
			$querystr.= ' AND event_date.meta_value LIKE "'.$this->filters['month'].'%"';
		}
		
		if ($this->filters['category']) {
			$querystr.= ' AND term_taxonomy_id = '.$this->filters['category'];
		}
		
		if ($this->filters['production']) {
			$querystr.= ' AND productions.meta_value='.$this->filters['production'].'';			
		}
		$querystr.= ' GROUP BY events.ID';
		$querystr.= ' ORDER BY event_date.meta_value';
		
		if ($this->filters['limit']) {
			$querystr.= ' LIMIT 0,'.$args['limit'];
		}

		$posts = $wpdb->get_results($querystr, OBJECT);

		$events = array();
		for ($i=0;$i<count($posts);$i++) {
			$events[] = new WPT_Event($posts[$i]->ID);
		}
		
		return $events;
	}

	/**
	 * An array of all months with upcoming events.
	 * @since 0.5
	 */
	function months() {
		$current_month = $this->filters['month'];
		
		// temporarily disable current month filter
		$this->filters['month'] = false;

		// get all event according to remaining filters
		$events = $this->get();		
		$months = array();
		foreach ($events as $event) {
			$months[] = date('Y-m',$event->datetime());
		}
		sort($months);
		
		// reset current month filter
		$this->filters['month'] = $current_month;
		
		return $months;
	}
	
	
	public function meta($args) {
		$defaults = array(
			'paged' => false,
			'grouped' => false
		);
		$args = wp_parse_args( $args, $defaults );

		$html = '';

		$events = $this->get();
		
		$uniqid = uniqid();
		
		for($i=0;$i<count($events);$i++) {
		
			if ($i==0) {
				$html.= '<span itemscope itemtype="http://schema.org/Event">';			
				$html.= '<meta itemprop="name" id="'.WPT_Production::post_type_name.'_title_'.$uniqid.'" content="'.$events[$i]->production()->title().'" />';
				$html.= '<meta itemprop="url" id="'.WPT_Production::post_type_name.'_permalink_'.$uniqid.'" content="'.$events[$i]->production()->permalink().'" />';
				$html.= '<meta itemprop="image" id="'.WPT_Production::post_type_name.'_thumbnail_'.$uniqid.'" content="'.wp_get_attachment_url($events[$i]->production()->thumbnail()).'" />';
			} else {
				$html.= '<span itemscope itemtype="http://schema.org/Event" itemref="'.WPT_Production::post_type_name.'_title_'.$uniqid.' '.WPT_Production::post_type_name.'_permalink_'.$uniqid.' '.WPT_Production::post_type_name.'_thumbnail_'.$uniqid.'">';
			}
		
			$html.= '<meta itemprop="startDate" content="'.date('c',$events[$i]->datetime()).'" />';
			$html.= '<span class="'.WPT_Event::post_type_name.'_location" itemprop="location" itemscope itemtype="http://data-vocabulary.org/Organization">';
			$venue = get_post_meta($events[$i]->ID,'venue',true);
			$city = get_post_meta($events[$i]->ID,'city',true);
			if ($venue!='') {
				$html.= '<meta itemprop="name" content="'.$venue.'" />';
			}
			if ($city!='') {
				$html.= '<span itemprop="address" itemscope itemtype="http://data-vocabulary.org/Address">';
				$html.= '<meta itemprop="locality" content="'.$city.'" />';
				$html.= '</span>';
			}
			$html.= '</span>'; // .location

			$html.= '</span>'; // .event
		
		}

		return $html;
	}
	
	/**
	 * An array of all past filtered events.
	 * @since 0.5
	 */
 	public function past() {
 		$this->filters['upcoming'] = false;
 		$this->filters['past'] = true;
 		return $this->get();
 	}
 	
	/**
	 * An array of all upcoming filtered events.
	 * @since 0.5
	 */
 	public function upcoming() {
 		$this->filters['past'] = false;
 		$this->filters['upcoming'] = true;
 		return $this->get();
 	}
 	
	/**
	 * Deprecated functions. 
	 *
	 * @deprecated 0.5.
	 */
	public function html_listing($args=array()) {
		return $this->html($args);
	}
	
	public function meta_listing($args=array()) {
		return $this->meta($args);
	}
	
}
?>