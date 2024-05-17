<?php

// add notice if acf plugin not exists
if ( ! class_exists('ACF') ) {
    add_action( 'admin_notices', 'thjb_add_notice_not_exists_acf_plugin' );
}

function thjb_add_notice_not_exists_acf_plugin()
{
    ?>
    <div class="notice error my-acf-notice is-dismissible" >
        <p><?php _e( 'ACF is not necessary for this plugin, but it will make your experience better, install it now!', 'th_jb' ); ?></p>
    </div>
    <?php
}