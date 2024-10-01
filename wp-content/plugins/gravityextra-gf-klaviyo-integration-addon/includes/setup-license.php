<?php
/**
 * Gravity Extra License Setup
 */

?>
<style>
	.settings_license_instructions{
		padding: 20px;
		margin-top: 10px;
		margin-bottom: 5px;
	}
    .settings_license_message {
        padding: 10px;
        margin-bottom: 10px;
    }
    .ge-spinner-inline {
        transform: translate(5px, 5px);
        position: relative;
        display: inline-block;
        opacity: 0;
        visibility: hidden;
    }

    .ge-spinner-inline.loading {
        opacity: 1;
        visibility: visible;
    }

    .ge-spinner {
        animation: ge-rotate 2s linear infinite;
        width: 20px;
        height: 20px;
    }

    .ge-spinner .path {
        stroke: #455d6f;
        stroke-linecap: round;
        animation: ge-dash 1.5s ease-in-out infinite;
    }

    @keyframes ge-rotate {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes ge-dash {
        0% {
            stroke-dasharray: 1, 150;
            stroke-dashoffset: 0;
        }
        50% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -35;
        }
        100% {
            stroke-dasharray: 90, 150;
            stroke-dashoffset: -124;
        }
    }

</style>
<div class="<?php if( !empty($license_status) && !$license_status["success"] ) echo 'alert_red';?> settings_license_message" style="<?php if( empty($license_status) || (!empty($license_status) && $license_status["success"] ) ) echo 'display: none;';?>">
	<?php 
    if(!$license_status["success"]){
        echo $license_status['msg'];
    }
    ?>
</div>
<div id="gform_setting_license_key" class="gform-settings-field gform-settings-field__text">

    <div class="gform-settings-field__header">
        <label class="gform-settings-label" for="license_key"><?php echo esc_html__( 'License Key.', $plugin_domain );?></label> 
        <button onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip" aria-label="<?php echo esc_html__( 'Enter your license key that generate when you purchased this product.', $plugin_domain );?>">
            <i class="gform-icon gform-icon--question-mark" aria-hidden="true"></i>
        </button>
    </div>
    <span class="gform-settings-description" id="gravityextra-description-license_key"><?php esc_html__( 'Enter your license key.', $plugin_domain );?></span>

    <span class="gform-settings-input__container <?php if( !empty($license_status) && $license_status['success'] ) echo 'gform-settings-input__container--feedback-success'; else if(!empty($license_status) && !$license_status['success']){ echo 'gform-settings-input__container--feedback-error'; }?>">
       
        <input type="text" name="gravityextra-license_key" value="<?php echo $license_key;?>" aria-describedby="gravityextra-description-license_key" class="small" id="gravityextra-license_key" required <?php if( !empty($license_status) && $license_status["success"] ) echo 'readonly';?>>


        <input type="hidden" name="gravityextra-license_plugin" value="<?php echo $slug;?>" id="gravityextra-license_plugin"> 
    </span>

</div>

<p>
    <?php if( empty($license_status) || ( !empty($license_status) && !$license_status["success"] ) ){ ?>
        <a class="secondary button gravityextra-trigger-license" href="#"><?php esc_html_e( 'Active License', $plugin_domain ); ?></a> 
        <input type="hidden" name="gravityextra-license_action" value="activate_license" id="gravityextra-license_action">
    <?php }else if( !empty($license_status) && $license_status["success"] ){ ?>
        <a class="secondary button gravityextra-trigger-license" href="#"><?php esc_html_e( 'Deactive License', $plugin_domain ); ?></a> 
        <input type="hidden" name="gravityextra-license_action" value="deactivate_license" id="gravityextra-license_action">
    <?php } ?>

    <span class="ge-spinner-inline"><svg class="ge-spinner" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg></span>
</p>

<p><?php esc_html_e( 'Get Gravity Extra License Key.', $plugin_domain ); ?> <a href="#" class="gravityextra-license-instructions"><?php esc_html_e( 'View Instructions', $plugin_domain ); ?></a></p>


		<div class="alert_yellow settings_license_instructions" style="display:none;">
			<ol>
				<li><?php
					printf(
						// translators: Placeholders represent opening and closing link tag.
						esc_html__( 'Navigate to the %1$sGravity Extra%2$s.', $plugin_domain ),
						'<a href="'.$service_site.'" target="_blank">',
						'</a>'
					);
				?></li>
     
                <li><?php
					printf(
						// translators: Placeholders represent opening and closing link tag.
						esc_html__( 'Access to %1$sMy Account%2$s page.', $plugin_domain ),
						'<strong>',
						'</strong>'
					);
				?></li>

                <li><?php
					printf(
						// translators: Placeholders represent opening and closing link tag.
						esc_html__( 'Click to the %1$sLicense keys%2$s tab.', $plugin_domain ),
						'<strong>',
						'</strong>'
					);
				?></li>

				<li><?php esc_html_e( 'Get license key.', $plugin_domain ); ?></li>
			</ol>
		</div>


<script>
    //
	jQuery( function( $ ) {
		$( '.gravityextra-license-instructions' ).on( 'click', function( e ) {
			e.preventDefault();
			$( '.settings_license_instructions' ).slideToggle( 'fast' );
		});

        $( '.gravityextra-trigger-license' ).on( 'click', function( e ) {
			e.preventDefault();
            let license = $('#gravityextra-license_key').val(),
             license_action = $('#gravityextra-license_action').val();
            if(license.length == 0){
                $(".settings_license_message").removeClass("alert_green").addClass("alert_red").show().html('Please enter you license key.');
                return;
            }
            call_license_api(license, license_action);
		});

        function call_license_api(license, ge_license_action){
            return $.ajax({
                type : "POST",
                dataType : "json",
                url : '<?php echo admin_url('admin-ajax.php');?>',
                data : {
                    action: "gravity_extra_license__<?php echo $slug;?>",
                    license : license,
                    ge_license_action : ge_license_action,
                },
                context: this,
                beforeSend: function(){
                    $('.ge-spinner-inline').addClass("loading");
                },
                success: function(response) {
                    
                    if(response.success) {
                        if( ge_license_action == 'deactivate_license'){
                            $(".settings_license_message").removeClass("alert_red").addClass("alert_green").show().html(response.msg);
                            $("#gform_setting_license_key .gform-settings-input__container").removeClass('gform-settings-input__container--feedback-success').removeClass('gform-settings-input__container--feedback-error');
                            $("#gravityextra-license_key").attr("readonly", false);
                            $("#gravityextra-license_action").val('activate_license');
                            $(".gravityextra-trigger-license").html("<?php esc_html_e( 'Active License', $plugin_domain ); ?>");
                        }else if( ge_license_action == 'activate_license' ){
                            $(".settings_license_message").removeClass("alert_red").addClass("alert_green").show().html(response.msg);
                            $("#gform_setting_license_key .gform-settings-input__container").addClass('gform-settings-input__container--feedback-success');
                            $("#gravityextra-license_key").attr("readonly", true);
                            $("#gravityextra-license_action").val('deactivate_license');
                            $(".gravityextra-trigger-license").html("<?php esc_html_e( 'Deactive License', $plugin_domain ); ?>");
                            //window.location.href=window.location.href;
                        }
                        
                    }
                    else {
                        $(".settings_license_message").removeClass("alert_green").addClass("alert_red").show().html(response.msg);
                        $("#gform_setting_license_key .gform-settings-input__container").addClass('gform-settings-input__container--feedback-error');
                    }

                    $('.ge-spinner-inline').removeClass("loading");
                },
                error: function( jqXHR, textStatus, errorThrown ){
                    
                    console.log( 'The following error occured: ' + textStatus, errorThrown );
                    $('.ge-spinner-inline').removeClass("loading");
                }
            })
        }
        
	});
</script>
