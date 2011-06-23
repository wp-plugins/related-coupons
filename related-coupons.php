<?php
/*
 Plugin Name: Coupon Network Related Coupons
 Plugin URI: http://www.couponnetwork.com
 Description: Easily display related coupons from Coupon Network on your blog posts.  Also includes shortcodes and template tags that you can use to display related coupons outside your blog posts.
 Version: 1.0.0
 Author: Coupon Network
 Author URI: http://www.couponnetwork.com
 */

if(!class_exists('Related_Coupons')) {
	class Related_Coupons {
		      
		private static $instance;
		
		private $_cn_CacheExpirationTime = 21600;
		private $_cn_FeedUrl = 'http://www.couponnetwork.com/index.rss';
		private $_cn_CategoryFeedUrl = 'http://www.couponnetwork.com/categories.json';
		private $_cn_BrandFeedUrl = 'http://www.couponnetwork.com/brands.json';

		private $_compare_Keywords = null;

		private $_data_Version = '1.0.0';
		
		private $_ir_BaseUrl = 'http://partners.couponnetwork.com/c/%d/11107/520';

		private $_meta_CachedCoupons = '_related_coupons_cached';
		private $_meta_KeywordsKey = '_related_coupons_keywords';
		private $_meta_PostSettingsKey = '_related_coupons_post_settings';

		private $_option_SettingsDefaults = array('post-types' => array('post' => 'yes', 'page' => 'yes'), 'related-coupons-title');
		private $_option_SettingsKey = '_related_coupons_settings';

		private $_transient_BrandsFeed = '_related_coupons_brands_feed';
		private $_transient_CategoriesFeed = '_related_coupons_categories_feed';
		private $_transient_Feed = '_related_coupons_feed';

		private $_yahoo_ApiId = 'V3m9GfzV34GREeoWQH_W7wLXF2ej8EHKRLYMmrzVgwt3OxyDUSLBKQVHe1xvVomzNt9K';
		private $_yahoo_ApiUrl = 'http://search.yahooapis.com/ContentAnalysisService/V1/termExtraction';

		private function __construct() {
			$this->addActions();
			$this->addFilters();
			$this->initialize();
			$this->registerShortcodes();
		}

		private function addActions() {
			add_action('admin_menu', array($this, 'addBackendInterfaceElements'));
			add_action('save_post', array($this, 'doAnalysisOfTextContent'), 10, 2);
			add_action('save_post', array($this, 'savePostSettings'), 10, 2);
			add_action('wp_head', array($this, 'enqueueFrontendResources'), -1000);
		}

		private function addFilters() {
			add_filter('the_content', array($this, 'possiblyAddRelatedCouponsToEndOfPostContent'));
			add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'addSettingsLinkToPluginRow'));
		}
		
		private function initialize() {
			
		}

		private function registerShortcodes() {

		}
		
		public static function getInstance() {
			if(empty(self::$instance)) {
				self::$instance = new Related_Coupons;
			}
			
			return self::$instance;
		}

		/// CALLBACKS

		public function addBackendInterfaceElements() {
			$hooks = array('post-new.php', 'post.php');

			$hooks[] = $settings = add_options_page(__('Related Coupons'),   __('Related Coupons'), 'manage_options', 'related-coupons', array(&$this, 'displaySettingsPage'));
			add_action("load-{$settings}", array(&$this, 'processSettingsSave'));

			foreach($hooks as $hook) {
				add_action("admin_print_styles-$hook", array(&$this, 'enqueueBackendResources'));
			}

			$settings = $this->getSettings();
			$types = array_keys((array)$settings['post-types']);
			foreach($types as $type) {
				add_action("add_meta_boxes_{$type}", array(&$this, 'addPostSettingsMetaBox'));
			}
		}

		public function addPostSettingsMetaBox($post) {
			add_meta_box('related-coupons-post-settings',   __('Related Coupons Settings'), array(&$this, 'displayPostSettingsMetaBox'), $post->post_type, 'normal');
		}
		
		public function addSettingsLinkToPluginRow($actions) {
			$actions['settings'] = sprintf('<a href="%s" title="%s">%s</a>', admin_url('options-general.php?page=related-coupons'), esc_attr(__('Configure the Related Coupons plugin')), __('Settings'));
			
			return $actions;
		}

		public function doAnalysisOfTextContent($id, $post) {
			if(wp_is_post_autosave($id) || wp_is_post_revision($id) || 'auto-draft' == $post->post_status) {
				return  ;
			}

			$settings = $this->getSettings();
			if(in_array($post->post_type,   array_keys((array)$settings['post-types']))) {
				$keywords = $this->analyzeContent($post->post_content);
				$this->setKeywords($id, $keywords);
			}
			
			delete_transient($id.$this->_meta_CachedCoupons);
		}

		public function enqueueBackendResources() {
			wp_enqueue_style('related-coupons-backend',   plugins_url('resources/backend/related-coupons.css', __FILE__), array('thickbox'), $this->_data_Version);
			wp_enqueue_script('related-coupons-backend',   plugins_url('resources/backend/related-coupons.js', __FILE__), array('jquery', 'thickbox'), $this->_data_Version);
		}

		public function enqueueFrontendResources() {
			$settings = $this->getSettings();
			$types = array_keys($settings['post-types']);
			if(is_singular($types)) {
				wp_enqueue_style('related-coupons-frontend',   plugins_url('resources/frontend/related-coupons.css', __FILE__), array(), $this->_data_Version);
				wp_enqueue_script('related-coupons-frontend',   plugins_url('resources/frontend/related-coupons.js', __FILE__), array('jquery'), $this->_data_Version);
			}
		}

		public function possiblyAddRelatedCouponsToEndOfPostContent($content) {
			$settings = $this->getSettings();
			$types = array_keys($settings['post-types']);

			global $post;
			$meta = $this->getPostSettings($post->ID);
			if(is_singular($types) && 'yes' != $meta['opt-out']) {

				$coupons = $this->getRelatedCouponDataForPost($post->ID);
				
				if(!empty($coupons)) {
					if(!empty($settings['related-coupons-title'])) {
						$content .= "<h3 class='related-coupons-title'>{$settings['related-coupons-title']}</h3>";
					}
					
					$content .= '<div class="coupon-network-related-coupons">';
					foreach($coupons as $coupon) {
						$coupon['link'] = add_query_arg(array('utm_campaign' => 'related_coupons'), $coupon['link']);
						
						if(!empty($settings['affiliate-id'])) {
							$coupon['link'] = add_query_arg(array('u' => urlencode($coupon['link'])), sprintf($this->_ir_BaseUrl, $settings['affiliate-id']));
						}
						
						$content .= $this->getCouponMarkupForCouponData($coupon);
					}
					$content .= '</div>';
				}
			}

			return $content;
		}

		public function processSettingsSave() {
			$data = stripslashes_deep($_POST);

			if(isset($data['save-related-coupons-settings-nonce']) && wp_verify_nonce($data['save-related-coupons-settings-nonce'], 'save-related-coupons-settings')) {
				$settings = trim_r($data['related-coupons']);
				$this->setSettings($settings);

				wp_redirect(admin_url('options-general.php?page=related-coupons&updated=true'));
				exit;
			}

		}

		public function savePostSettings($id, $post) {
			$data = stripslashes_deep($_POST);
			if(wp_is_post_autosave($id) || wp_is_post_revision($id) || 'auto-draft' == $post->post_status || !wp_verify_nonce($data['save-related-coupons-post-settings-nonce'], 'save-related-coupons-post-settings')) {
				return;
			}

			$settings = trim_r($data['related-coupons']);
			$this->setPostSettings($id, $settings);
		}

		/// DISPLAY CALLBACKS

		public function displayPostSettingsMetaBox($post) {
			$meta = $this->getPostSettings($post->ID);
			$keywords = $this->getKeywords($post->ID, false);
			
			$categories = $this->getAvailableCategories();
			if(!empty($meta['override-category']) && !in_array($meta['override-category'], $categories)) {
				$categories = array_merge(array($meta['override-category']), $categories);
			}

			$brands = $this->getAvailableBrands();
			if(!empty($meta['override-brand']) && !in_array($meta['override-brand'], $brands)) {
				$brands = array_merge(array($meta['override-brand']), $brands);
			}
			
			include ('views/backend/meta-boxes/post-settings.php');
		}

		public function displaySettingsPage() {
			$settings = $this->getSettings();
			$imageSrc = plugins_url('resources/backend/images/mp-account-id-location.jpg', __FILE__);
			include ('views/backend/settings/settings.php');
		}

		public function getCouponMarkupForCouponData($coupon) {
			ob_start();
			include('views/frontend/coupon.php');
			return ob_get_clean();
		}

		/// SHORTCODE CALLBACKS

		public function showRelatedCouponsForShortcode($atts, $content =null) {

			return '';
		}

		/// SETTINGS

		private function getSettings() {
			$settings = wp_cache_get($this->_option_SettingsKey);

			if(!is_array($settings)) {
				$settings = (array)get_option($this->_option_SettingsKey, $this->_option_SettingsDefaults);
				wp_cache_set($this->_option_SettingsKey, $settings, null, time() + 24 * 60 * 60);
			}

			return $settings;
		}

		private function setSettings($settings) {
			if(is_array($settings)) {
				update_option($this->_option_SettingsKey, $settings);
				wp_cache_set($this->_option_SettingsKey, $settings, null, time() + 24 * 60 * 60);
			}
		}

		/// COUPON DATA
		
		public function compareItemKeywordIndices($a, $b) {
			return $b['keyword-index'] - $a['keyword-index'];
		}
		
		public function compareItemPrecedence($a, $b) {
			return $b['precedence'] - $a['precedence'];
		}
		
		private function getKeywordIndex($item, $keywords) {
			$words = $item['description'].' '.$item['name'].' '.implode(' ', $item['stores']).' '.implode(' ', $item['categories']);
			$words = preg_replace(array('/[^A-Za-z0-9\s]/', '/\s+/'), array('', ' '), strtolower($words));
			$words = array_unique(array_filter(explode(' ', $words)));
		
			$contained = array_intersect($keywords, $words);
			
			$index = count($contained);
			return $index;
		}
		
		private function filterRelevantCouponsFromKeywords($items, $keywords, $brand, $category, $count = 3) {
			foreach($items as $key => $item) {
				$items[$key]['keyword-index'] = $this->getKeywordIndex($item, $keywords);
			}
			
			usort($items, array($this, 'compareItemKeywordIndices'));
			
			$usable = array();
			if(!empty($brand)) {
				foreach($items as $item) {
					if(count($usable) >= $count) {
						break;
					} elseif($brand == $item['brand']) {
						$usable[] = $item;
					}
				}
			}
			
			if(!empty($category)) {
				foreach($items as $item) {
					if(count($usable) >= $count) {
						break;
					} elseif(in_array($category, $item['categories'])) {
						$usable[] = $item;
					}
				}
			}
			
			foreach($items as $item) {
				if(count($usable) >= $count || $item['keyword-index'] <= 0) {
					 break; 
				} else {
					$usable[] = $item;
				}
			}
			
			if(count($usable) < $count) {
				usort($items, array($this, 'compareItemPrecedence'));
				foreach($items as $item) {
					if(count($usable) >= $count) {
						break;
					} else {
						$usable[] = $item;
					}
				}
			}
			
			return $usable;
		}
		
		private function getCachedCouponData($postId) {			
			$cached = get_transient($postId.$this->_meta_CachedCoupons);
			             
			return empty($cached) ? false : $cached;
		}
		
		private function setCachedCouponData($postId, $data) {
			$timeout = get_option('_transient_timeout_'.$this->_transient_Feed, time() + $this->_cn_CacheExpirationTime);
			$expiration = $timeout - time();
			
			set_transient($postId.$this->_meta_CachedCoupons, $data, $expiration);
		}
		
		private function getCouponFilteringKeywordsForPost($postId) {
			$post = get_post($postId);
			
			$titleKeywords = explode(' ',preg_replace(array('/[^A-Za-z0-9\s]/', '/\s+/'), array('', ' '), strtolower($post->post_title)));
			$keywords = $this->getKeywords($postId, false);
			$tags = wp_get_post_tags($postId, array('fields' => 'names'));
			$categories = wp_get_post_categories($postId, array('fields' => 'names'));
			
			$meta = $this->getPostSettings($postId);
			$extrapost = array_filter(trim_r(explode(',', $meta['additional-keywords'])));
			if(!empty($meta['override-brand'])) {
				$extrapost = array_merge(array($meta['override-brand']), $extrapost);
			}
			
			$settings = $this->getSettings();
			$extrasettings = array_filter(trim_r(explode(',', $settings['default-keywords'])));
			
			$filtering = array_merge($keywords, $tags, $categories, $extrapost, $extrasettings, $titleKeywords);
			
			$filtering = array_unique(explode(' ', strtolower(preg_replace('/\s+/', ' ', implode(' ', $filtering)))));
			
			// remove indefinite articles and products keyword
			$notUseful = array('in', 'on', 'im', 'products', 'product', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'the', 'a', 'an');
			$filtering = array_diff($filtering, $notUseful);
			
			return $filtering;
		}
		
		private function getRelatedCouponDataForPost($postId) {
			$filtered = $this->getCachedCouponData($postId);
			
			if(!is_array($filtered)) {
				$items = $this->getCouponNetworkFeedItems();
				$keywords = $this->getCouponFilteringKeywordsForPost($postId);
				$meta = $this->getPostSettings($postId);
				
				$filtered = $this->filterRelevantCouponsFromKeywords($items, $keywords, $meta['override-brand'], $meta['override-category']);
				
				$this->setCachedCouponData($postId, $filtered);
			}
			
			return $filtered;
		}
		
		
		/// META

		private function getKeywords($id, $refresh = true) {
			if(empty($id)) {
				global $post;
				$id = $post->ID;
			}

			$keywords = wp_cache_get($this->_meta_KeywordsKey, $id);

			if(!is_array($keywords)) {
				$keywords = get_post_meta($id, $this->_meta_KeywordsKey, true);

				if(!is_array($keywords) && $refresh) {
					$post = get_post($id);
					$keywords = $this->analyzeContent($post->post_content);
					$this->setKeywords($id, $keywords);
				}
				
				if(is_array($keywords)) {
					wp_cache_set($this->_meta_KeywordsKey, $keywords, $id, time() + 24 * 60 * 60);
				}
			}

			if(!is_array($keywords)) {
				$keywords = array();
			}

			return $keywords;
		}

		private function setKeywords($id, $keywords) {
			if(is_array($keywords)) {
				update_post_meta($id, $this->_meta_KeywordsKey, $keywords);
				wp_cache_set($this->_meta_KeywordsKey, $keywords, $id, time() + 24 * 60 * 60);
			}
		}

		private function getPostSettings($id) {
			if(empty($id)) {
				global $post;
				$id = $post->ID;
			}

			$settings = wp_cache_get($this->_meta_PostSettingsKey, $id);

			if(!is_array($PostSettings)) {
				$settings = (array)get_post_meta($id, $this->_meta_PostSettingsKey, true);

				wp_cache_set($this->_meta_PostSettingsKey, $settings, $id, time() + 24 * 60 * 60);
			}

			return $settings;
		}

		private function setPostSettings($id, $settings) {
			if(is_array($settings)) {
				update_post_meta($id, $this->_meta_PostSettingsKey, $settings);
				wp_cache_set($this->_meta_PostSettingsKey, $settings, $id, time() + 24 * 60 * 60);
			}
		}

		/// UTILITY

		/**
 		* Post the specified to the Yahoo! Term Extraction service and return an array of keywords that were returned.
 		*
 		* @link http://developer.yahoo.com/search/content/V1/termExtraction.html
 		* @param string $content A string of content to analyze.
 		* @return array An array of keywords for the content that was analyzed.
 		*/
		private function analyzeContent($content) {
			$args = array('appid' => $this->_yahoo_ApiId, 'context' => $content, 'output' => 'json');

			$response = wp_remote_post($this->_yahoo_ApiUrl, array('body' => $args));
			if(!is_wp_error($response)) {
				$body = wp_remote_retrieve_body($response);
				$terms = @json_decode($body);
			}

			if(is_object($terms)) {
				return $terms->ResultSet->Result;
			} else {
				return array();
			}
		}

		private function getAvailableBrands() {
			$items = get_transient($this->_transient_BrandsFeed);
			
			if(!is_array($items)) {
				$items = array();
				
				$response = wp_remote_get($this->_cn_BrandFeedUrl);
				if(!is_wp_error($response)) {
					$brands = json_decode(wp_remote_retrieve_body($response));
					if(is_array($brands)) {
						foreach($brands as $brand) {
							$name = $brand->name;
							
							if($name == 'All Brands') { continue; } 
							$items[] = (string)$name;
						}
					}
				}
				
				if(!empty($items)) {
					set_transient($this->_transient_BrandsFeed, $items, $this->_cn_CacheExpirationTime);
				}
			}
			
			return $items;
		}
		
		private function getAvailableCategories() {
			$items = get_transient($this->_transient_CategoriesFeed);
			
			if(!is_array($items)) {
				$items = array();
				
				$response = wp_remote_get($this->_cn_CategoryFeedUrl);
				if(!is_wp_error($response)) {
					$categories = json_decode(wp_remote_retrieve_body($response));
					if(is_array($categories)) {
						foreach($categories as $category) {
							$name = (string)$category->name;
							if($name == 'All Categories') { continue; } 
							$items[] = $name;
						}
					}
				}
				
				if(!empty($items)) {
					set_transient($this->_transient_CategoriesFeed, $items, $this->_cn_CacheExpirationTime);
				}
			}
			
			return $items;
		}

		private function getCouponNetworkFeedItems() {
			$items = get_transient($this->_transient_Feed);
			
			if(!is_array($items)) {
				$items = array();
				
				$response = wp_remote_get($this->_cn_FeedUrl);
				if(!is_wp_error($response)) {
					$xml = @simplexml_load_string(wp_remote_retrieve_body($response));
					if(is_object($xml)) {
						foreach($xml->channel->item as $item) {
							$children = $item->children('http://www.couponnetwork.com/ns/1.0');
							
							$data = array();
							
							// MAIN ATTRIBUTES
							$data['name'] = (string)$item->title;
							$data['description'] = (string)$item->description;
							$data['link'] = (string)$item->link;
							$data['pubdate'] = (string)$item->pubDate;
							
							// CN ATTRIBUTES
							$data['precedence'] = (string)$children->precedence;
							$data['urlcode'] = (string)$children->urlCode;
							$data['urlprint'] = (string)$children->urlPrint;
							$data['offertype'] = (string)$children->offerType;

							$data['expirationdate'] = (string)$children->expirationDate;
							$data['startdate'] = (string)$children->startDate;
							$data['enddate'] = (string)$children->endDate;
							$data['brandname'] = (string)$children->brandName;
														
							$data['image'] = (string)$children->image;
							$data['imagethumbnail'] = (string)$children->imageThumbnail;
														
							/// CN DISCOUNT
							$discountAttributes = $children->discount->attributes();
							$data['discount_type'] = (string)$discountAttributes->type;
							$data['discount_currency'] = (string)$discountAttributes->currency;
							$data['discount_amount'] = (string)$children->discount;
							
							/// CN CATEGORIES
							$data['categories'] = array();
							foreach($children->categories->category as $category) {
								$data['categories'][] = (string)$category->name;
							}
							
							// CN BRANDS
							$data['brand'] = (string)$children->brandName;
							
							
							/// CN STORES
							$data['stores'] = array();
							foreach($children->stores->store as $store) {
								$data['stores'][] = (string)$store->name;
							}
							
							$items[] = $data;
						}
					}
				}				
				
				set_transient($this->_transient_Feed, $items, $this->_cn_CacheExpirationTime);
			}
		
			return $items;
		}
	}

	Related_Coupons::getInstance();

	require_once ('lib/template-tags.php');
	require_once ('lib/utility.php');
}
