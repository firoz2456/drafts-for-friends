
	jQuery(function() {
		jQuery('form.draftsforfriends-extend').hide();
		jQuery('a.draftsforfriends-extend').show();
		jQuery('a.draftsforfriends-extend-cancel').show();
		jQuery('a.draftsforfriends-extend-cancel').css('display', 'inline');
		jQuery('#draftsforfriends_submit').on('click',function(){
			var selected  = jQuery('#draftsforfriends-postid option:selected').val();
			if(selected.length < 1 ){
				alert('Please select any Draft');
			}else{
				jQuery('#draftsforfriends-share').submit();
			}
		});
	});
	window.draftsforfriends = {
		toggle_extend: function(key) {
			jQuery('#draftsforfriends-extend-form-'+key).show();
			jQuery('#draftsforfriends-extend-link-'+key).hide();
			jQuery('#draftsforfriends-extend-form-'+key+' input[name="expires"]').focus();
		},
		cancel_extend: function(key) {
			jQuery('#draftsforfriends-extend-form-'+key).hide();
			jQuery('#draftsforfriends-extend-link-'+key).show();
		}
	};
	