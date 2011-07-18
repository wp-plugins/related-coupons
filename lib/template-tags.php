<?php

function related_coupons_get_related_coupons_output($post_id = null) {
	$instance = Related_Coupons::getInstance();
	return apply_filters('related_coupons_get_related_coupons_output', $instance->getRelatedCouponContentForPost($post_id));
}
function related_coupons_the_related_coupons_output($post_id = null) {
	echo apply_filters('related_coupons_the_related_coupons_output', related_coupons_get_related_coupons_output($post_id));
}
