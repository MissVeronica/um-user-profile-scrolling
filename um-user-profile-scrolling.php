<?php
/**
 * Plugin Name:     Ultimate Member - User Profile Scrolling
 * Description:     Extension to Ultimate Member for User Profile Scrolling via ID, username, display name, first or last name, user email or random for selected Profile forms.
 * Version:         1.2.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Plugin URI:      https://github.com/MissVeronica/um-user-profile-scrolling
 * Update URI:      https://github.com/MissVeronica/um-user-profile-scrolling
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.6
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

Class UM_User_Profile_Scrolling {

    public $user_scroll_options     = array();
    public $all_targeted_user_roles = array();

    public $search_key = '';
    public $priority   = 9;
    public $limit      = '10';

    function __construct() {

        define( 'Plugin_Basename_UPS', plugin_basename( __FILE__ ));

        if ( UM()->options()->get( 'um_user_profile_scrolling_page_bottom' ) == 1 ) {
            $this->priority = 99;
        }

        add_action( 'um_profile_content_main', array( $this, 'um_profile_content_main_user_scroll' ), $this->priority, 1 );
        add_filter( 'um_settings_structure',   array( $this, 'um_settings_structure_user_scroll' ), 10, 1 );

        add_filter( 'plugin_action_links_' . Plugin_Basename_UPS, array( $this, 'scrolling_settings_link' ), 10 );

        $this->user_scroll_options =

                array(
                    ''              => '',
                    'ID'            => esc_html__( 'User ID', 'ultimate-member' ),
                    'user_login'    => esc_html__( 'Username', 'ultimate-member' ),
                    'display_name'  => esc_html__( 'User display name', 'ultimate-member' ),
                    'first_name'    => esc_html__( 'User first name', 'ultimate-member' ),
                    'last_name'     => esc_html__( 'User last name', 'ultimate-member' ),
                    'user_nicename' => esc_html__( 'User nice name', 'ultimate-member' ),
                    'user_email'    => esc_html__( 'User email', 'ultimate-member' ),
                );
    }

    public function scrolling_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&tab=appearance';
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings' ) . '</a>';

        return $links;
    }

    public function um_can_view_profile_account_status( $user_id ) {

        $can_view = false;
        $userdata = get_userdata( $user_id );

        if ( $userdata !== false ) {

            um_fetch_user( $user_id );

            $prio_role = UM()->roles()->get_priority_user_role( $user_id );

            if ( um_user( 'account_status' ) == 'approved' && in_array( $prio_role, $this->all_targeted_user_roles )) {
                $can_view = um_can_view_profile( $user_id );

            } else {

                UM()->user()->remove_cache( $user_id );
            }
        }

        return $can_view;
    }

    public function get_scroll_users() {

        global $wpdb;

        $scroll_users = array( 'left' => array(), 'right' => array() );

        $this->search_key = sanitize_text_field( UM()->options()->get( 'um_user_profile_scrolling_meta_key' ));
        if ( ! empty( $this->search_key ) && array_key_exists( $this->search_key, $this->user_scroll_options )) {

            $search_value = um_user( $this->search_key );
            if ( ! empty( $search_value )) {

                if ( ! in_array( $this->search_key, array( 'first_name', 'last_name' ))) {

                    $scroll_users['left']  = $wpdb->get_results( "SELECT ID FROM $wpdb->users WHERE $this->search_key < '$search_value' ORDER BY $this->search_key DESC LIMIT $this->limit" );
                    $scroll_users['right'] = $wpdb->get_results( "SELECT ID FROM $wpdb->users WHERE $this->search_key > '$search_value' ORDER BY $this->search_key ASC  LIMIT $this->limit" );

                } else {

                    $scroll_users['left']  = $wpdb->get_results( "SELECT user_id as 'ID' FROM $wpdb->usermeta WHERE meta_key = '$this->search_key' AND meta_value < '$search_value' ORDER BY meta_value DESC LIMIT $this->limit" );
                    $scroll_users['right'] = $wpdb->get_results( "SELECT user_id as 'ID' FROM $wpdb->usermeta WHERE meta_key = '$this->search_key' AND meta_value > '$search_value' ORDER BY meta_value ASC  LIMIT $this->limit" );
                }
            }
        }

        return $scroll_users;
    }

    public function display_button( $scroll_users, $button_text ) {

        $button_text = sprintf( $button_text, $this->user_scroll_options[$this->search_key] );

        foreach( $scroll_users as $user ) {

            if ( $this->um_can_view_profile_account_status( $user->ID ) !== false ) { 
                ?>
                    <button><a href="<?php echo esc_url( um_user_profile_url( $user->ID ));?>"><?php echo $button_text;?></a></button>
                <?php 
                break;
            }
        }
    }

    public function um_profile_content_main_user_scroll( $args ) {

        global $wpdb, $current_user;

        if ( ! um_is_on_edit_profile()) {

            $um_user_profile_scrolling_forms = UM()->options()->get( 'um_user_profile_scrolling_forms' );

            if ( is_array( $um_user_profile_scrolling_forms )) {
                $um_user_profile_scrolling_forms = array_map( 'trim', array_map( 'sanitize_text_field', $um_user_profile_scrolling_forms ));

            } else {
                $um_user_profile_scrolling_forms = array_map( 'trim', array_map( 'sanitize_text_field', explode( ',', $um_user_profile_scrolling_forms )));
            }

            if ( isset( $args['form_id'] ) && in_array( (string)$args['form_id'], $um_user_profile_scrolling_forms )) {

                $um_user_profile_scrolling_roles = UM()->options()->get( 'um_user_profile_scrolling_roles' );
                if ( ! empty( $um_user_profile_scrolling_roles )) {
                    if ( in_array( UM()->roles()->get_priority_user_role( $current_user->ID ), array_map( 'sanitize_text_field', $um_user_profile_scrolling_roles ))) {

                        $this->all_targeted_user_roles = UM()->options()->get( 'um_user_profile_scrolling_target_roles' );
                        if ( ! empty( $this->all_targeted_user_roles )) {
                            $this->all_targeted_user_roles = array_map( 'sanitize_text_field', $this->all_targeted_user_roles );

                            $um_profile_id = absint( um_profile_id());

                            if ( UM()->options()->get( 'um_user_profile_scrolling_enable' ) == 1 ) {
                                $scroll_users = $this->get_scroll_users();
                            }

                            ?><div class="um-center"><?php

                            um_fetch_user( $current_user->ID );

                            if ( UM()->options()->get( 'um_user_profile_scrolling_enable' ) == 1 ) {

                                $this->display_button( $scroll_users['left'],  esc_html__( 'Previous %s', 'ultimate-member' ));
                            }

                            if ( UM()->options()->get( 'um_user_profile_scrolling_random' ) == 1 ) {

                                $userids = $wpdb->get_results( "SELECT max( ID ) as max FROM $wpdb->users" );
                                do {
                                    $random_id = absint( rand( 1, $userids[0]->max ));

                                } while ( $this->um_can_view_profile_account_status( $random_id ) === false );

                                ?>
                                <button><a href="<?php echo esc_url( um_user_profile_url( $random_id ));?>"><?php echo esc_html__( 'Random User', 'ultimate-member' );?></a></button>
                                <?php
                            }

                            if ( UM()->options()->get( 'um_user_profile_scrolling_enable' ) == 1 ) {

                                $this->display_button( $scroll_users['right'], esc_html__( 'Next %s', 'ultimate-member' ));
                            }

                            um_fetch_user( $um_profile_id );

                            ?></div><?php
                        }
                    }
                }
            }
        }
    }

    public function um_settings_structure_user_scroll( $settings_structure ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] == 'appearance' ) {

                if ( ! isset( $settings_structure['appearance']['sections']['']['form_sections']['user_profile_scrolling']['fields'] )) {

                    $plugin_data = get_plugin_data( __FILE__ );

                    $link = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
                                                esc_url( $plugin_data['PluginURI'] ),
                                                esc_html__( 'GitHub plugin documentation and download', 'ultimate-member' ),
                                                esc_html__( 'Plugin', 'ultimate-member' )
                                    );

                    $header = array(
                                        'title'       => esc_html__( 'User Profile Scrolling', 'ultimate-member' ),
                                        'description' => sprintf( esc_html__( '%s version %s - tested with UM 2.8.6', 'ultimate-member' ),
                                                                            $link, esc_attr( $plugin_data['Version'] )),
                                    );

                    $um_profile_forms = get_posts( array(   'meta_key'    => '_um_mode',
                                                            'meta_value'  => 'profile',
                                                            'numberposts' => -1,
                                                            'post_type'   => 'um_form',
                                                            'post_status' => 'publish'
                                                        )
                                                );

                    $profile_forms = array();
                    foreach( $um_profile_forms as $um_form ) {
                        $profile_forms[$um_form->ID] = $um_form->post_title;
                    }

                    $um_user_profile_scrolling_forms = UM()->options()->get( 'um_user_profile_scrolling_forms' );

                    $warning = '';
                    if ( ! is_array( $um_user_profile_scrolling_forms )) {
                        $warning = '<br />' . esc_html__( 'Save your Profile page selections after installation or update of the plugin', 'ultimate-member' );
                    }

                    $prefix = '&nbsp; * &nbsp;';
                    $section_fields = array();

                    $section_fields[] =

                            array(
                                'id'            => 'um_user_profile_scrolling_forms',
                                'type'          => 'select',
                                'multi'         => true,
                                'size'          => 'medium',
                                'options'       => $profile_forms,
                                'label'         => $prefix . esc_html__( 'Profile Forms to include in Scrolling', 'ultimate-member' ),
                                'description'   => esc_html__( 'Select single or multiple Profile Forms for User Profile Scrolling and Random.', 'ultimate-member' ) . $warning,
                            );

                    $section_fields[] = array(
                                'id'             => 'um_user_profile_scrolling_roles',
                                'type'           => 'select',
                                'multi'          => true,
                                'label'          => $prefix . esc_html__( 'User Roles to use Scrolling', 'ultimate-member' ),
                                'description'    => esc_html__( 'Select the User Role(s) to be included in using Scrolling and Random.', 'ultimate-member' ),
                                'options'        => UM()->roles()->get_roles(),
                                'size'           => 'medium',
                            );

                    $section_fields[] = array(
                                'id'             => 'um_user_profile_scrolling_target_roles',
                                'type'           => 'select',
                                'multi'          => true,
                                'label'          => $prefix . esc_html__( 'User Roles to display', 'ultimate-member' ),
                                'description'    => esc_html__( 'Select the User Role(s) to be displayed in Previous/Next Scrolling and Random.', 'ultimate-member' ),
                                'options'        => UM()->roles()->get_roles(),
                                'size'           => 'medium',
                            );

                    $section_fields[] =

                            array(
                                'id'            => 'um_user_profile_scrolling_meta_key',
                                'type'          => 'select',
                                'size'          => 'small',
                                'options'       => $this->user_scroll_options,
                                'label'         => $prefix . esc_html__( 'meta_key', 'ultimate-member' ),
                                'description'   => esc_html__( 'Select the meta_key for User Profile Previous/Next Scrolling.', 'ultimate-member' )
                            );

                    $section_fields[] =

                            array(
                                'id'            => 'um_user_profile_scrolling_enable',
                                'type'          => 'checkbox',
                                'label'         => $prefix . esc_html__( 'Previous/Next buttons', 'ultimate-member' ),
                                'default'       => 0,
                                'description'   => esc_html__( 'Click to enable Previous/Next display of User Profiles.', 'ultimate-member' ),
                            );

                    $section_fields[] =

                            array(
                                'id'            => 'um_user_profile_scrolling_random',
                                'type'          => 'checkbox',
                                'label'         => $prefix . esc_html__( 'Random button', 'ultimate-member' ),
                                'default'       => 0,
                                'description'   => esc_html__( 'Click to enable Random display of User Profiles.', 'ultimate-member' ),
                            );

                    $section_fields[] =

                            array(
                                'id'            => 'um_user_profile_scrolling_page_bottom',
                                'type'          => 'checkbox',
                                'label'         => $prefix . esc_html__( 'Buttons at Page bottom', 'ultimate-member' ),
                                'default'       => 0,
                                'description'   => esc_html__( 'Click to display scrolling buttons at the Profile page bottom.', 'ultimate-member' ),
                            );

                    $settings_structure['appearance']['sections']['']['form_sections']['user_profile_scrolling'] = $header;
                    $settings_structure['appearance']['sections']['']['form_sections']['user_profile_scrolling']['fields'] = $section_fields;
                }
            }
        }

        return $settings_structure;
    }
}

new UM_User_Profile_Scrolling();
