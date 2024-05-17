<?php
/**
 * Register custom post types and taxonomies
 */

add_action('init', 'thjb_register_post_types');
add_action('init', 'thjb_register_taxonomies');
add_action( 'init', 'thjb_register_custom_post_status' );

add_action('admin_footer-post.php', 'thjb_add_custom_job_status_to_edit_post_page');
add_action('admin_footer-edit.php', 'thjb_add_custom_job_status_to_quick_edit');


function thjb_register_post_types()
{
    register_post_type('jobs', [
        'public'                => true,
        'publicly_queryable'    => true,
        'supports'              => ['title', 'editor' ],
        'menu_position'         => null,
        'menu_icon'             => 'dashicons-id',
        'labels'                => [
            'name'          => __('Jobs', 'th_jb'),
            'singular_name' => __('Job', 'th_jb'),
            'add_new'       => _x('Add New', 'jobs'),
            'add_new_item'  => __('Add new Job', 'th_jb'),
            'edit_item'     => __('Edit Job', 'th_jb'),
            'new_item'      => __('New', 'th_jb'),
            'view_item'     => __('View', 'th_jb'),
            'search_items'  => __('Search Jobs', 'th_jb'),
            'view_items'    => __('View Jobs', 'th_jb'),
            'attributes'    => __('Attributes', 'th_jb'),
        ],
        'show_in_nav_menus'     => true,
        'hierarchical'          => false,
        'exclude_from_search'   => false,
        'rewrite'               => ['slug' => 'jobs', 'with_front' => false]
    ]);

    register_post_type('job-alerts', [
        'public'                => true,
        'publicly_queryable'    => false,
        'supports'              => ['title', 'author'],
        'menu_position'         => null,
        'menu_icon'             => 'dashicons-email-alt2',
        'labels'                => [
            'name'          => __('Job Alerts', 'th_jb'),
            'singular_name' => __('Job Alert', 'th_jb'),
            'add_new'       => _x('Add New', 'job-alerts'),
            'add_new_item'  => __('Add new', 'th_jb'),
            'edit_item'     => __('Edit Job Alert', 'th_jb'),
            'new_item'      => __('New', 'th_jb'),
            'view_item'     => __('View', 'th_jb'),
            'search_items'  => __('Search Job Alerts', 'th_jb'),
            'view_items'    => __('View Job Alerts', 'th_jb'),
            'attributes'    => __('Attributes', 'th_jb'),
        ],
        'show_in_nav_menus'     => false,
        'hierarchical'          => false,
        'exclude_from_search'   => true,
        'capability_type'       => ['job_alert', 'job_alerts'],
        'map_meta_cap'          => true,
    ]);

}

function thjb_register_taxonomies()
{
    register_taxonomy('states', [ 'jobs' ], [
        'labels'            => [
            'name'          => _x('States', 'taxonomy general name'),
            'singular_name' => _x('State', 'taxonomy singular name'),
            'search_items'  => __('Search State'),
            'all_items'     => __('All States'),
            'edit_item'     => __('Edit State'),
            'update_item'   => __('Update State'),
            'add_new_item'  => __('Add New State'),
            'new_item_name' => __('State Name'),
            'menu_name'     => __('States'),
        ],
        'show_ui'           => true,
        'show_in_nav_menus' => false,
        'show_admin_column' => true,
        'query_var'         => true,
        'hierarchical'      => true,
        'public'            => false,
    ]);

    register_taxonomy('job-type', [ 'jobs' ], [
        'labels'            => [
            'name'          => _x('Job Type', 'taxonomy general name'),
            'singular_name' => _x('Job Type', 'taxonomy singular name'),
            'search_items'  => __('Search Job Types'),
            'all_items'     => __('All Job Types'),
            'edit_item'     => __('Edit Job Type'),
            'update_item'   => __('Update Job Type'),
            'add_new_item'  => __('Add Job Type'),
            'new_item_name' => __('Job Type'),
            'menu_name'     => __('Job Types'),
        ],
        'show_ui'           => true,
        'show_in_nav_menus' => false,
        'show_admin_column' => true,
        'query_var'         => true,
        'hierarchical'      => false,
        'public'            => false,
    ]);

    register_taxonomy('industry', [ 'jobs' ], [
        'labels'            => [
            'name'          => _x('Industry', 'taxonomy general name'),
            'singular_name' => _x('Industry', 'taxonomy singular name'),
            'search_items'  => __('Search Industries'),
            'all_items'     => __('All Industries'),
            'edit_item'     => __('Edit Industry'),
            'update_item'   => __('Update Industry'),
            'add_new_item'  => __('Add Industry'),
            'new_item_name' => __('Industry'),
            'menu_name'     => __('Industry'),
        ],
        'show_ui'           => true,
        'show_in_nav_menus' => false,
        'show_admin_column' => true,
        'query_var'         => true,
        'hierarchical'      => false,
        'public'            => false,
    ]);

}

function thjb_register_custom_post_status()
{
    register_post_status( 'expired', [
        'label'                     => __('Expired'),
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>' ),
        'post_type'                 => ['jobs'],
    ] );
}


function thjb_add_custom_job_status_to_edit_post_page()
{

    global $post;
    $complete = '';
    $label = '';

    if ($post->post_type == 'jobs') {
        if ( $post->post_status == 'expired' ) {
            $complete = ' selected=\"selected\"';
            $label    = 'Expired';
        }

        $script = <<<SD
       jQuery(document).ready(function($){
           $("select#post_status").append("<option value=\"expired\" '.$complete.'>Expired</option>");
           
           if( "{$post->post_status}" == "expired" ){
                $("span#post-status-display").html("$label");
                $("input#save-post").val("Save expired");
           }
           var jSelect = $("select#post_status");
           $("a.save-post-status").on("click", function(){
                if( jSelect.val() == "expired" ){
                    $("input#save-post").val("Save expired");
                }
           });
      });
SD;

        echo '<script type="text/javascript">' . $script . '</script>';
    }

}

function thjb_add_custom_job_status_to_quick_edit()
{
    global $post;
    if ( is_object($post) && 'jobs' == $post->post_type ) {
        echo "<script>
        jQuery(document).ready( function() {
            jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"expired\">Expired</option>' );
        });
        </script>";
    }
}
