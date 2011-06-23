<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><label for="related-coupons-opt-out"><?php _e('Turn Off for This Post'); ?></label></th>
			<td>
				<label>
					<input <?php checked($meta['opt-out'], 'yes'); ?> type="checkbox" name="related-coupons[opt-out]" id="related-coupons-opt-out" value="yes" />
					<?php _e('Don\'t display related coupons on this post'); ?>
				</label>
			</td>
		</tr>
		<tr class="related-coupons-opt-out">
			<th scope="row"><label for="related-coupons-additional-keywords"><?php _e('Additional Keywords'); ?></label></th>
			<td>
				<input type="text" class="text large-text code" name="related-coupons[additional-keywords]" id="related-coupons-additional-keywords" value="<?php esc_attr_e($meta['additional-keywords']); ?>" /><br />
				<?php _e('Separate your keywords by commas. These will be included when calculating the related coupons for this particular post.'); ?>
			</td>
		</tr>
		<tr class="related-coupons-opt-out">
			<th scope="row"><label for="related-coupons-override-category"><?php _e('Preferred Category'); ?></label></th>
			<td>
				<select name="related-coupons[override-category]" id="related-coupons-override-category">
					<option <?php selected($meta['override-category'], ''); ?> value=""><?php _e('Not Applicable'); ?></option>
					<?php foreach($categories as $category) { ?>
					<option <?php selected($meta['override-category'], $category); ?> value="<?php esc_attr_e($category); ?>"><?php esc_html_e($category); ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
		<tr class="related-coupons-opt-out">
			<th scope="row"><label for="related-coupons-override-brand"><?php _e('Preferred Brand'); ?></label></th>
			<td>
				<select name="related-coupons[override-brand]" id="related-coupons-override-brand">
					<option <?php selected($meta['override-brand'], ''); ?> value=""><?php _e('Not Applicable'); ?></option>
					<?php foreach($brands as $brand) { ?>
					<option <?php selected($meta['override-brand'], $brand); ?> value="<?php esc_attr_e($brand); ?>"><?php esc_html_e($brand); ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
	</tbody>
</table>

<?php if(!empty($keywords)) { ?> 
<p class="related-coupons-opt-out"><?php _e('Based on previous analysis, this post will display coupons related to the following terms in addition to the ones you entered above along with the post tags and categories:'); ?> <code><?php esc_html_e(implode(', ', $keywords)); ?></code></p>
<?php } ?>

<?php wp_nonce_field('save-related-coupons-post-settings', 'save-related-coupons-post-settings-nonce'); ?>
