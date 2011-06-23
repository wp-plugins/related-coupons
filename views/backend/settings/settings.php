<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Related Coupons Settings'); ?></h2>
	
	<form method="post">
		<h3><?php _e('Coupon Defaults'); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="related-coupons-default-keywords"><?php _e('Default Keywords'); ?></label></th>
					<td>
						<input type="text" class="text large-text code" name="related-coupons[default-keywords]" id="related-coupons-default-keywords" value="<?php esc_attr_e($settings['default-keywords']); ?>" /><br />
						<?php _e('Separate your keywords by commas.  These will be included when calculating the related coupons for each post you create.'); ?>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h3><?php _e('Display Options'); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="related-coupons-related-coupons-title"><?php _e('Related Coupons Heading'); ?></label></th>
					<td>
						<input type="text" class="text large-text code" name="related-coupons[related-coupons-title]" id="related-coupons-related-coupons-title" value="<?php esc_attr_e($settings['related-coupons-title']); ?>" /><br />
						<?php _e('This heading will appear above your related coupons.  If you wish, you can leave this blank and no heading will display.'); ?>
					</td>
				</tr>	
			</tbody>
				
		</table>
				
		<h3><?php _e('Post Types'); ?></h3>
		<p><?php _e('You can choose which post types you want to display Related Coupons for.  Please check the boxes corresponding to the types you wish to include.'); ?></p>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php _e('Enabled Content Types'); ?></th>
					<td>
						<ul>
							<?php foreach(get_post_types(array('show_ui' => true), 'objects') as $type) { ?>
							<li>
								<label>
									<input type="checkbox" <?php checked($settings['post-types'][$type->name], 'yes'); ?> name="related-coupons[post-types][<?php esc_attr_e($type->name); ?>]" value="yes" />
									<?php esc_html_e($type->labels->name); ?>
								</label>
							</li>
							<?php } ?>
						</ul>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h3><?php _e('Coupon Network Affiliate Program'); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="related-coupons-affiliate-id"><?php _e('Impact Radius Account ID'); ?></label></th>
					<td>
						<input type="text" class="text regular-text code" name="related-coupons[affiliate-id]" id="related-coupons-affiliate-id" value="<?php esc_attr_e($settings['affiliate-id']); ?>" /><br />
						<?php printf(__('Haven\'t signed up for the affiliate program yet?  <a target="_blank" href="%s">Sign up now!</a>'), 'http://www.couponnetwork.com/affiliate-program'); ?><br />
						<a href="<?php esc_attr_e($imageSrc); ?>" class="thickbox"><?php _e('Where is my Account ID?'); ?></a><br />
						<?php _e('<strong>Note</strong>: Entering your Account ID is optional.'); ?>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<?php wp_nonce_field('save-related-coupons-settings', 'save-related-coupons-settings-nonce'); ?>
			<input type="submit" class="button button-primary" name="save-related-coupons-settings" value="<?php _e('Save Changes'); ?>" />
		</p>
	</form>
</div>