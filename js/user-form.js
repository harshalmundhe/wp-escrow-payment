function wp_createorder(shortcode_id){
    var form = jQuery(".escrowform"+shortcode_id);
    var data = form.serializeArray();
    form.hide();
    var dialogBody = jQuery( '.loadingdiv' ).html( '<span class="loading">Loading...</span>' );
    jQuery.post( ajaxobject.ajax_url, {
        action: 'ep_create_order',
        data: data
        }, function( response ) {
            res = {};
            if(response !== ""){
                res = JSON.parse(response);
            }
            dialogBody.children( '.loading' ).remove();
            if(res.error){
                form.show();
                jQuery(".error_msg").show();
                jQuery(".errormsg").html(res.message);
            }else{
                tb_remove();
                jQuery(".escrowdiv"+shortcode_id).html("<div class='escrowsuccess'>Your Transaction has been created.Please check your email for futher details</div>");
            }	
    });
}
