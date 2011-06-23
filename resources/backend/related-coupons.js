jQuery(document).ready(function($) {
	$('#related-coupons-opt-out').bind('change click', function() {
		var $this = $(this);
		var $dependents = $('.related-coupons-opt-out');
		if($this.is(':checked')) {
			$dependents.hide();
		} else {
			$dependents.show();
		}
	}).change();
});
