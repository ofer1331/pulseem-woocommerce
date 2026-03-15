<?php
/**
 * Elementor Custom Action for Pulseem Integration
 *
 * Provides integration with Pulseem via Elementor forms, including:
 * - Fetching groups from Pulseem.
 * - User data submission to Pulseem.
 * - Dynamic group and field mapping.
 * - Acceptance field support for opt-in control.
 *
 * @since      1.0.0
 * @version    1.4.0
 */

use ElementorPro\Modules\Forms\Classes\Action_Base;
use pulseem\WooPulseemAdminModel;
use pulseem\PulseemGroups;
use pulseem\PulseemLogger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Pulseem_Elementor_Custom_Action extends Action_Base {

    /**
     * Get action name
     *
     * Returns the unique name of the action for Elementor integration.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return string
     */
    public function get_name() {
        return 'pulseem_action';
    }

    /**
     * Get action label
     *
     * Returns the label of the action for Elementor UI.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return string
     */
    public function get_label() {
        return __( 'Pulseem Integration', 'pulseem' );
    }

    /**
     * Register settings section
     *
     * Adds a settings section to the Elementor form widget, allowing
     * selection of groups and mapping of user fields.
     *
     * @since 1.0.0
     * @version 1.3.4
     * @param object $widget The Elementor form widget instance.
     */
    public function register_settings_section( $widget ) {
        $groups = $this->fetch_pulseem_groups();
        $options = [];

        foreach ( $groups as $group ) {
            $options[ $group['id'] ] = $group['name'];
        }

        $widget->start_controls_section(
            'pulseem_integration_section',
            [
                'label' => __( 'Pulseem Integration', 'pulseem' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Help text for acceptance field
        $widget->add_control(
            'pulseem_acceptance_help',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background: #f1f1f1; color:black; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    <strong>' . __( 'שדה הסכמה (Acceptance Field):', 'pulseem' ) . '</strong><br>
                    ' . __( 'אם תגדיר שדה הסכמה, המערכת תבדוק אם המשתמש סימן אותו. אם כן - הלקוח יישלח ללא צורך באישור נוסף (needOptin=false). אם לא תגדיר שדה הסכמה, הלקוח יישלח עם דרישה לאישור (needOptin=true).', 'pulseem' ) . '
                </div>',
            ]
        );

        $widget->add_control(
            'pulseem_group',
            [
                'label' => __( 'Select Group', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $options,
                'default' => '',
            ]
        );

        $widget->add_control(
            'pulseem_email',
            [
                'label' => __( 'Email Field ID', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( 'email', 'pulseem' ),
            ]
        );

        $widget->add_control(
            'pulseem_first_name',
            [
                'label' => __( 'First Name Field ID', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( 'first_name', 'pulseem' ),
            ]
        );

        $widget->add_control(
            'pulseem_last_name',
            [
                'label' => __( 'Last Name Field ID', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( 'last_name', 'pulseem' ),
            ]
        );

        $widget->add_control(
            'pulseem_phone',
            [
                'label' => __( 'Phone Field ID', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( 'phone', 'pulseem' ),
            ]
        );

        $widget->add_control(
            'pulseem_acceptance',
            [
                'label' => __( 'Acceptance Field ID', 'pulseem' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( 'acceptance', 'pulseem' ),
                'description' => __( 'ID של שדה הסכמה (Acceptance) - אופציונלי', 'pulseem' ),
            ]
        );

        $widget->end_controls_section();
    }

    /**
     * Fetch Pulseem groups
     *
     * Retrieves the list of groups from the Pulseem API for use in forms.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return array List of groups with 'id' and 'name' keys.
     */
    private function fetch_pulseem_groups() {
        $groups_data = [];
        $pulseem_admin_model = new WooPulseemAdminModel();
        $api_key = $pulseem_admin_model->getApiKey();
        $url = $pulseem_admin_model->getEnvironmentUrl();

        if ( ! empty( $api_key ) ) {
            $response = wp_remote_post(
                "$url/api/v1/GroupsApi/GetAllGroups",
                [
                    'body' => wp_json_encode( [ 'groupType' => 0 ] ),
                    'headers' => [
                        'accept' => 'application/json',
                        'Content-Type' => 'application/json-patch+json',
                        'apiKey' => $api_key,
                    ],
                ]
            );

            if ( ! is_wp_error( $response ) ) {
                $response_body = wp_remote_retrieve_body( $response );
                $groups_list = json_decode( $response_body, true );

                if ( isset( $groups_list['groups'] ) && is_array( $groups_list['groups'] ) ) {
                    foreach ( $groups_list['groups'] as $group ) {
                        $groups_data[] = [
                            'id' => $group['groupId'],
                            'name' => $group['groupName'],
                        ];
                    }
                }
            }
        }

        return $groups_data;
    }

    /**
     * Check if user has given acceptance
     *
     * Checks if the acceptance field is marked (on/1) or not.
     *
     * @since 1.3.4
     * @version 1.3.4
     * @param array $fields Form fields data.
     * @param array $settings Form settings.
     * @return bool True if acceptance field is checked, false otherwise.
     */
    private function has_user_acceptance( $fields, $settings ) {
        if ( empty( $settings['pulseem_acceptance'] ) ) {
            return false;
        }

        $acceptance_value = $fields[ $settings['pulseem_acceptance'] ] ?? '';
        
        // בודק אם השדה מסומן (on/1)
        return !empty( $acceptance_value ) && ( $acceptance_value === 'on' || $acceptance_value === '1' );
    }

    /**
     * Run the action
     *
     * Processes the form submission, extracts field values, and submits data to Pulseem.
     *
     * @since 1.0.0
     * @version 1.3.4
     * @param object $record The form record object.
     * @param object $ajax_handler The AJAX handler instance.
     */
    public function run( $record, $ajax_handler ) {
        $raw_fields = $record->get( 'fields' );
        $settings = $record->get( 'form_settings' );
        $fields = [];

        foreach ( $raw_fields as $id => $field ) {
            $fields[ $id ] = $field['value'];
        }

        $group_id = $settings['pulseem_group'] ?? null;

        if ( empty( $group_id ) ) {
            $ajax_handler->add_error_message( __( 'No group selected for registration.', 'pulseem' ) );
            return;
        }

        $user_data = [
            'email'     => $fields[ $settings['pulseem_email'] ] ?? '',
            'firstName' => $fields[ $settings['pulseem_first_name'] ] ?? '',
            'lastName'  => $fields[ $settings['pulseem_last_name'] ] ?? '',
            'cellphone' => $fields[ $settings['pulseem_phone'] ] ?? '',
        ];

        if ( empty( $user_data['email'] ) || empty( $user_data['firstName'] ) ) {
            $ajax_handler->add_error_message( __( 'Required fields are missing.', 'pulseem' ) );
            return;
        }

        // בדיקת הסכמה
        $has_acceptance = $this->has_user_acceptance( $fields, $settings );

        $result = $this->send_to_pulseem( $group_id, $user_data, $has_acceptance );

        $api_log_data = [
            'group_id' => $group_id,
            'has_acceptance' => $has_acceptance,
        ];

        if ( $result ) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_ELEMENTOR,
                'Elementor form submission sent to Pulseem successfully',
                $api_log_data,
                $user_data['email']
            );
            $ajax_handler->add_success_message( __( 'User successfully added to Pulseem.', 'pulseem' ) );
        } else {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_ELEMENTOR,
                'Failed to send Elementor form submission to Pulseem',
                $api_log_data,
                $user_data['email']
            );
            $ajax_handler->add_error_message( __( 'Failed to add user to Pulseem.', 'pulseem' ) );
        }
    }

    /**
     * Send data to Pulseem API
     *
     * Submits user data to the Pulseem API for group registration.
     *
     * @since 1.0.0
     * @version 1.3.4
     * @param int $group_id The group ID.
     * @param array $user_data User details to be sent.
     * @param bool $has_acceptance Whether user has given acceptance.
     * @return bool True on success, false on failure.
     */
    private function send_to_pulseem( $group_id, $user_data, $has_acceptance = false ) {
        $data = [
            "groupID"       => (int) $group_id,
            "email"         => $user_data['email'],
            "firstName"     => $user_data['firstName'],
            "lastName"      => $user_data['lastName'],
            "cellphone"     => $user_data['cellphone'],
            "arrivalSource" => "ELEMENTOR_FORM",
        ];

        // אם יש הסכמה - לא צריך opt-in 
        // אם אין הסכמה - צריך opt-in
        $need_optin = !$has_acceptance;

        try {
            return PulseemGroups::postNewClient( $data, $need_optin );
        } catch ( Exception $e ) {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_ELEMENTOR,
                'Pulseem API error: ' . $e->getMessage(),
                ['group_id' => $group_id],
                $user_data['email']
            );
            return false;
        }
    }

    /**
     * Export handler
     *
     * Adds form data to the export functionality.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param array $element The element data.
     * @return array
     */
    public function on_export( $element ) {
        return $element;
    }
}