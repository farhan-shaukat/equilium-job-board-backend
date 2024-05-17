<?php
/**
 * Plugin settings
 */
add_action('admin_menu', 'thjb_add_plugin_settings_page_function', 9);
add_action('admin_init', 'thjb_register_plugin_settings_fields');

add_action('thjb_general_section_register_fields', 'thjb_general_section_jobdiva_fields_function', 10, 2);
add_action('acf/init', 'thjb_add_dashboard_settings');

function thjb_add_plugin_settings_page_function()
{
    add_menu_page(  __('Job Board Settings', 'th_jb'), 'Job Board', 'administrator', 'talenthero_jb_settings', 'thjb_plugin_settings_gerenal_display', 'dashicons-admin-generic', 40 );
}

function thjb_plugin_settings_gerenal_display()
{
    require_once THJB_PLUGIN_DIR_PATH . 'partials/admin/settings-general.php';
}

function thjb_register_plugin_settings_fields()
{
    add_settings_section(
        'thjb_general_section',
        __('General settings', 'th_jb'),
        '',
        'thjb_general_settings'
    );

    add_settings_section(
        'thjb_job_alerts_section',
        __('Alerts settings', 'th_jb'),
        '',
        'thjb_job_alerts_settings'
    );

    add_settings_field(
        'thjb_send_alerts',
        __('Send alerts email', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_job_alerts_settings',
        'thjb_job_alerts_section',
        [
            'type'              => 'input',
            'subtype'           => 'checkbox',
            'id'                => 'thjb_send_alerts',
            'name'              => 'thjb_send_alerts',
            'required'          => '',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    add_settings_field(
        'thjb_job_alerts_email_from',
        __('Send emails from', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_job_alerts_settings',
        'thjb_job_alerts_section',
        [
            'type'              => 'input',
            'subtype'           => 'email',
            'id'                => 'thjb_job_alerts_email_from',
            'name'              => 'thjb_job_alerts_email_from',
            'required'          => '',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    add_settings_field(
        'thjb_job_alerts_email_subject',
        __('Alert emails subject', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_job_alerts_settings',
        'thjb_job_alerts_section',
        [
            'type'              => 'input',
            'subtype'           => 'text',
            'id'                => 'thjb_job_alerts_email_subject',
            'name'              => 'thjb_job_alerts_email_subject',
            'required'          => '',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    add_settings_field(
        'thjb_job_alerts_unsubscribe_url',
        __('Unsubscribe page url', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_job_alerts_settings',
        'thjb_job_alerts_section',
        [
            'type'              => 'input',
            'subtype'           => 'text',
            'id'                => 'thjb_job_alerts_unsubscribe_url',
            'name'              => 'thjb_job_alerts_unsubscribe_url',
            'required'          => '',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    register_setting(
        'thjb_job_alerts_settings',
        'thjb_send_alerts'
    );

    register_setting(
        'thjb_job_alerts_settings',
        'thjb_job_alerts_email_from'
    );

    register_setting(
        'thjb_job_alerts_settings',
        'thjb_job_alerts_email_subject'
    );

    register_setting(
        'thjb_job_alerts_settings',
        'thjb_job_alerts_unsubscribe_url'
    );

    do_action('thjb_general_section_register_fields');

}

function thjb_general_section_jobdiva_fields_function()
{

    add_settings_field(
        'thjb_jobdiva_api_import_enabled',
        __('Enable api import', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_general_settings',
        'thjb_general_section',
        [
            'type'              => 'input',
            'subtype'           => 'checkbox',
            'id'                => 'thjb_jobdiva_api_import_enabled',
            'name'              => 'thjb_jobdiva_api_import_enabled',
            'required'          => '',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );
    
    add_settings_field(
        'thjb_jobdiva_username',
        __('JobDiva username', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_general_settings',
        'thjb_general_section',
        [
            'type'              => 'input',
            'subtype'           => 'text',
            'id'                => 'thjb_jobdiva_username',
            'name'              => 'thjb_jobdiva_username',
            'required'          => 'true',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    add_settings_field(
        'thjb_jobdiva_password',
        __('JobDiva password', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_general_settings',
        'thjb_general_section',
        [
            'type'              => 'input',
            'subtype'           => 'password',
            'id'                => 'thjb_jobdiva_password',
            'name'              => 'thjb_jobdiva_password',
            'required'          => 'true',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    add_settings_field(
        'thjb_jobdiva_clientid',
        __('JobDiva Client ID', 'th_jb'),
        'thjb_render_settings_field',
        'thjb_general_settings',
        'thjb_general_section',
        [
            'type'              => 'input',
            'subtype'           => 'text',
            'id'                => 'thjb_jobdiva_clientid',
            'name'              => 'thjb_jobdiva_clientid',
            'required'          => 'true',
            'get_options_list'  => '',
            'value_type'        => 'normal',
        ]
    );

    register_setting(
        'thjb_general_settings',
        'thjb_jobdiva_username'
    );

    register_setting(
        'thjb_general_settings',
        'thjb_jobdiva_password'
    );

    register_setting(
        'thjb_general_settings',
        'thjb_jobdiva_clientid'
    );

    register_setting(
        'thjb_general_settings',
        'thjb_jobdiva_api_import_enabled'
    );

}

function thjb_render_settings_field($args)
{
    $wp_data_value = get_option($args['name']);

    switch ($args['type']) {

        case 'input':
            $value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
            if ($args['subtype'] != 'checkbox') {
                $prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
                $prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
                $step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
                $min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
                $max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';

                if ( isset($args['disabled']) ) {
                    // hide the actual input bc if it was just a disabled input the informaiton saved in the database would be wrong - bc it would pass empty values and wipe the actual information
                    echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                } else {
                    echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                }
                /*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/

            } else {
                $checked = ($value) ? 'checked' : '';
                echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$args['name'].'" size="40" value="1" '.$checked.' />';
            }
            break;
        case 'select':

            $options = call_user_func($args['get_options_list']);

            echo '<select id="'.$args['id'].'" '.$args['required'].' name="'.$args['name'].'">';
                foreach ($options as $value => $label) {
                    echo '<option value="' . $value . '"' . ( ($value == $wp_data_value) ? 'selected' : '' ) . '>';
                    echo $label;
                    echo '</option>';
                }
            echo '</select>';

            break;
        default:
            # code...
            break;
    }
}

function thjb_add_dashboard_settings()
{
    if( function_exists('acf_add_options_page') ) {

        acf_add_options_page([
            'page_title'    => __('Dashboard Settings'),
            'menu_title'    => __('Dashboard'),
            'menu_slug'     => 'thjb-dashboard',
            'parent_slug'   => 'talenthero_jb_settings',
            'capability'    => 'administrator',
            'redirect'      => false
        ]);

        if( function_exists('acf_add_local_field_group') ):

            acf_add_local_field_group(array(
                'key' => 'group_62b2cc4607a05',
                'title' => 'Dashboard Settings',
                'fields' => array(
                    array(
                        'key' => 'field_62b2cc637e35c',
                        'label' => 'My consents',
                        'name' => 'my_consents',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'collapsed' => '',
                        'min' => 0,
                        'max' => 0,
                        'layout' => 'block',
                        'button_label' => 'Add Link',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_62b2cc787e35d',
                                'label' => 'Link Title',
                                'name' => 'link_title',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                            array(
                                'key' => 'field_62b2cc837e35e',
                                'label' => 'Url',
                                'name' => 'url',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'maxlength' => '',
                            ),
                        ),
                    ),
                    array(
                        'key' => 'field_62b2cced7e35f',
                        'label' => 'Request RTBF',
                        'name' => 'request_rtbf',
                        'type' => 'group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'layout' => 'block',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_62b2ccfc7e360',
                                'label' => 'Send email from',
                                'name' => 'send_email_from',
                                'type' => 'email',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ),
                            array(
                                'key' => 'field_62b2ccfc7e399',
                                'label' => 'Send email to',
                                'name' => 'send_email_to',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => array(
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ),
                                'default_value' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ),
                        ),
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'thjb-dashboard',
                        ),
                    ),
                ),
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'seamless',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
            ));

        endif;

    }
}
