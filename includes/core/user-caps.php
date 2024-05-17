<?php
add_action( 'init', 'thjb_add_subscriber_caps' );
add_action( 'init', 'thjb_add_admin_caps' );
add_action('after_setup_theme', 'thjb_remove_admin_bar');

function thjb_add_subscriber_caps()
{
    $role = get_role( 'subscriber' );

    $caps = [
        ['job_alert', 'job_alerts'],
    ];

    foreach ( $caps as $cap ) {

        $singular = $cap[0];
        $plural = $cap[1];

        $role->add_cap( "edit_{$singular}" );
        $role->add_cap( "edit_{$plural}" );
        $role->add_cap( "publish_{$plural}" );
        $role->add_cap( "read_{$singular}" );
        $role->add_cap( "delete_{$singular}" );
        $role->add_cap( "edit_published_{$plural}" );
        $role->add_cap( "delete_published_{$plural}" );

    }

}

function thjb_add_admin_caps()
{
    $role = get_role( 'administrator' );

    $caps = [
        ['job_alert', 'job_alerts'],
    ];

    foreach ( $caps as $cap ) {

        $singular = $cap[0];
        $plural = $cap[1];

        $role->add_cap( "edit_{$singular}" );
        $role->add_cap( "edit_{$plural}" );
        $role->add_cap( "edit_others_{$plural}" );
        $role->add_cap( "publish_{$plural}" );
        $role->add_cap( "read_{$singular}" );
        $role->add_cap( "read_private_{$plural}" );
        $role->add_cap( "delete_{$singular}" );
        $role->add_cap( "delete_{$plural}" );
        $role->add_cap( "delete_private_{$plural}" );
        $role->add_cap( "delete_others_{$plural}" );
        $role->add_cap( "edit_published_{$plural}" );
        $role->add_cap( "edit_private_{$plural}" );
        $role->add_cap( "delete_published_{$plural}" );

    }

}

function thjb_remove_admin_bar()
{
    if ( ! current_user_can('administrator') ) {
        show_admin_bar(false);
    }
}
