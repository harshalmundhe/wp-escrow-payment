(function() {
    tinymce.create("tinymce.plugins.ep_shortcode_btn", {

        //url argument holds the absolute url of our plugin directory
        init : function(ed, url) {

            //add new button    
            ed.addButton("ep_shortcode", {
                title : "Add Quick Email Shortcode",
                cmd : "ep_shortcode_command",
                image : url+"/../img/formicon.png"
            });

            //button functionality.
            ed.addCommand("ep_shortcode_command", function() {
                
                                // Calls the pop-up modal
                ed.windowManager.open({
                    // Modal settings
                    autoScroll: true,  
                    title: 'Insert Shortcode',
                    width: jQuery( window ).width() * 0.5,
                    // minus head and foot of dialog box
                    height: (jQuery( window ).height() - 36 - 50) * 0.6,
                    inline: 1,
                    id: 'ep-shortcode-insert-dialog',
                    buttons: [{
                        text: 'Insert',
                        id: 'ep-shortcode-button-insert',
                        class: 'insert',
                        onclick: function( e ) {
                            insertShortcode(ed);
                        },
                    },
                    {
                        text: 'Cancel',
                        id: 'ep-shortcode-button-cancel',
                        onclick: 'close'
                    }],
                });
                
                appendInsertDialog();
                
                
            });

        },

        createControl : function(n, cm) {
            return null;
        },

        getInfo : function() {
            return {
                longname : "Mumbai Freelancer Team",
                author : "Mumbai Freelancer Team",
                version : "1.0"
            };
        }
    });

    tinymce.PluginManager.add("ep_shortcode_btn", tinymce.plugins.ep_shortcode_btn);
    
    
    function appendInsertDialog () {
		var dialogBody = jQuery( '#ep-shortcode-insert-dialog-body' ).append( '<span class="loading">Loading...</span>' );
        //var dialogBody = jQuery( '#ep-shortcode-insert-dialog-body' ).append( '[Loading element like span.spinner]' );

		// Get the form template from WordPress
		jQuery.post( ajaxurl, {
			action: 'ep_shortcode_btn_insert_dialog'
		}, function( response ) {
			template = response;

			dialogBody.children( '.loading' ).remove();
			dialogBody.append( template );
		});
	}
    
    function insertShortcode(ed){
        var form = jQuery("#escrowform");
        var data = form.serializeArray();
        form.hide();
        var dialogBody = jQuery( '#ep-shortcode-insert-dialog-body' ).append( '<span class="loading">Loading...</span>' );
        
        jQuery.post( ajaxurl, {
			action: 'ep_shortcode_create',
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
                tinyMCE.activeEditor.windowManager.close();
                content =  res.shortcode;
                ed.execCommand("mceInsertContent", 0, content);
            }
			
			
			
		});
    }
	
})();