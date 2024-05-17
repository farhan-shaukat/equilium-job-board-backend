<?php
/**
 * Displays plugin general settings
 */

global $wp_settings_sections;

?>
<div class="wrap">
    <h2><?php echo get_admin_page_title(); ?></h2>
    <?php settings_errors(); ?>
    <?php
        if ( ! empty($wp_settings_sections) ) {
            foreach ($wp_settings_sections as $section_id => $section) {
                if ( false === strpos($section_id, 'thjb_') ) {
                    continue;
                }
                ?>
                <form method="POST" action="options.php">
                    <?php

                    settings_fields( $section_id );
                    do_settings_sections( $section_id );

                    ?>
                    <?php submit_button(); ?>
                </form>
                <?php
            }
        }
    ?>
</div>