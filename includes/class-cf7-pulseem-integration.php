<?php
/**
 * Contact Form 7 Integration with Pulseem
 *
 * Handles the submission of user data to Pulseem when a CF7 form is submitted.
 *
 * @since 1.0.0
 * @version 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use pulseem\WooPulseemAdminModel;
use pulseem\PulseemGroups;
use pulseem\PulseemLogger;

/**
 * Add Pulseem settings tab to CF7 form editor
 *
 * Adds a custom settings panel in the Contact Form 7 form editor
 * for configuring Pulseem integration settings.
 *
 * @since 1.0.0
 * @version 1.4.0
 * @param array $panels Existing editor panels.
 * @return array Updated editor panels.
 */
add_filter( 'wpcf7_editor_panels', function ( $panels ) {
    $panels['pulseem-settings'] = [
        'title'    => __( 'Pulseem Integration', 'pulseem' ),
        'callback' => 'pulseem_cf7_settings_panel',
    ];
    return $panels;
});

/**
 * Display Pulseem settings panel content
 *
 * Renders the content of the custom settings panel in the CF7 editor.
 *
 * @since 1.0.0
 * @version 1.4.0
 * @param object $post CF7 form post object.
 */

 function pulseem_cf7_settings_panel( $post ) {
    $pulseem_enabled       = get_post_meta( $post->id(), '_pulseem_enabled', true );
    $pulseem_group         = get_post_meta( $post->id(), '_pulseem_group', true );
    $pulseem_email_field   = get_post_meta( $post->id(), '_pulseem_email_field', true );
    $pulseem_first_name    = get_post_meta( $post->id(), '_pulseem_first_name', true );
    $pulseem_last_name     = get_post_meta( $post->id(), '_pulseem_last_name', true );
    $pulseem_phone_field   = get_post_meta( $post->id(), '_pulseem_phone_field', true );

    $groups = pulseem_cf7_fetch_groups();

    ?>
    <h2><?php esc_html_e( 'Pulseem Integration Settings', 'pulseem' ); ?></h2>
    <fieldset>
        <legend><?php esc_html_e( 'Configure Pulseem integration for this form.', 'pulseem' ); ?></legend>

        <p class="description">
            <label for="pulseem-enabled">
                <?php esc_html_e( 'Enable Pulseem Integration', 'pulseem' ); ?><br>
                <input type="checkbox" id="pulseem-enabled" name="pulseem_enabled" value="1" <?php checked( $pulseem_enabled, '1' ); ?>>
            </label>
        </p>

        <p class="description">
            <label for="pulseem-group">
                <?php esc_html_e( 'Select Group:', 'pulseem' ); ?><br>
                <select id="pulseem-group" name="pulseem_group" class="large-text">
                    <?php foreach ( $groups as $group ) : ?>
                        <option value="<?php echo esc_attr( $group['id'] ); ?>" <?php selected( $group['id'], $pulseem_group ); ?>>
                            <?php echo esc_html( $group['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>

        <p class="description">
            <label for="pulseem-email-field">
                <?php esc_html_e( 'Email Field ID:', 'pulseem' ); ?><br>
                <input type="text" id="pulseem-email-field" name="pulseem_email_field" class="large-text" size="70" value="<?php echo esc_attr( $pulseem_email_field ); ?>" placeholder="<?php esc_html_e( 'email', 'pulseem' ); ?>">
            </label>
        </p>

        <p class="description">
            <label for="pulseem-first-name">
                <?php esc_html_e( 'First Name Field ID:', 'pulseem' ); ?><br>
                <input type="text" id="pulseem-first-name" name="pulseem_first_name" class="large-text" size="70" value="<?php echo esc_attr( $pulseem_first_name ); ?>" placeholder="<?php esc_html_e( 'first_name', 'pulseem' ); ?>">
            </label>
        </p>

        <p class="description">
            <label for="pulseem-last-name">
                <?php esc_html_e( 'Last Name Field ID:', 'pulseem' ); ?><br>
                <input type="text" id="pulseem-last-name" name="pulseem_last_name" class="large-text" size="70" value="<?php echo esc_attr( $pulseem_last_name ); ?>" placeholder="<?php esc_html_e( 'last_name', 'pulseem' ); ?>">
            </label>
        </p>

        <p class="description">
            <label for="pulseem-phone-field">
                <?php esc_html_e( 'Phone Field ID:', 'pulseem' ); ?><br>
                <input type="text" id="pulseem-phone-field" name="pulseem_phone_field" class="large-text" size="70" value="<?php echo esc_attr( $pulseem_phone_field ); ?>" placeholder="<?php esc_html_e( 'phone', 'pulseem' ); ?>">
            </label>
        </p>
    </fieldset>

    <?php
}

/**
 * Save Pulseem settings for the CF7 form
 *
 * Saves the integration settings configured in the CF7 editor.
 *
 * @since 1.0.0
 * @version 1.4.0
 * @param object $post CF7 form post object.
 */
add_action( 'wpcf7_after_save', function ( $post ) {
    if ( ! current_user_can( 'wpcf7_edit_contact_form', $post->id() ) ) {
        return;
    }
    update_post_meta( $post->id(), '_pulseem_enabled', sanitize_text_field( wp_unslash( $_POST['pulseem_enabled'] ?? '' ) ) );
    update_post_meta( $post->id(), '_pulseem_group', sanitize_text_field( wp_unslash( $_POST['pulseem_group'] ?? '' ) ) );
    update_post_meta( $post->id(), '_pulseem_email_field', sanitize_text_field( wp_unslash( $_POST['pulseem_email_field'] ?? '' ) ) );
    update_post_meta( $post->id(), '_pulseem_first_name', sanitize_text_field( wp_unslash( $_POST['pulseem_first_name'] ?? '' ) ) );
    update_post_meta( $post->id(), '_pulseem_last_name', sanitize_text_field( wp_unslash( $_POST['pulseem_last_name'] ?? '' ) ) );
    update_post_meta( $post->id(), '_pulseem_phone_field', sanitize_text_field( wp_unslash( $_POST['pulseem_phone_field'] ?? '' ) ) );
});

/**
 * Submit data to Pulseem before email is sent
 *
 * Processes form data and sends it to Pulseem API before the CF7 email is sent.
 *
 * @since 1.0.0
 * @version 1.4.0
 * @param object $contact_form CF7 form object.
 */
add_action( 'wpcf7_before_send_mail', function ( $contact_form ) {
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }

    $form_id = $contact_form->id();
    if ( get_post_meta( $form_id, '_pulseem_enabled', true ) !== '1' ) {
        return;
    }

    $data = $submission->get_posted_data();

    $group_id = get_post_meta( $form_id, '_pulseem_group', true );

    $user_data = [
        'email'         => $data[ get_post_meta( $form_id, '_pulseem_email_field', true ) ] ?? '',
        'firstName'     => $data[ get_post_meta( $form_id, '_pulseem_first_name', true ) ] ?? '',
        'lastName'      => $data[ get_post_meta( $form_id, '_pulseem_last_name', true ) ] ?? '',
        'cellphone'     => $data[ get_post_meta( $form_id, '_pulseem_phone_field', true ) ] ?? '',
        'arrivalSource' => 'CF7_FORM',
    ];

    if ( empty( $user_data['email'] ) || empty( $user_data['firstName'] ) ) {
        return;
    }

    $data_to_send = [
        "groupID"       => (int) $group_id,
        "email"         => $user_data['email'],
        "firstName"     => $user_data['firstName'],
        "lastName"      => $user_data['lastName'],
        "cellphone"     => $user_data['cellphone'],
        "arrivalSource" => $user_data['arrivalSource'],
    ];

    try {
        $result = PulseemGroups::postNewClient( $data_to_send );

        $api_log_data = [
            'group_id' => $group_id,
            'form_id' => $form_id,
        ];

        if ( $result ) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_CF7,
                'CF7 form submission sent to Pulseem successfully',
                $api_log_data,
                $user_data['email']
            );
        } else {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_CF7,
                'Failed to send CF7 form submission to Pulseem',
                $api_log_data,
                $user_data['email']
            );
        }
    } catch ( Exception $e ) {
        PulseemLogger::error(
            PulseemLogger::CONTEXT_CF7,
            'CF7 Pulseem API exception: ' . $e->getMessage(),
            ['group_id' => $group_id, 'form_id' => $form_id],
            $user_data['email']
        );

    }
});

/**
 * Fetch Pulseem groups from the API
 *
 * Retrieves a list of groups from Pulseem's API to populate the settings panel.
 *
 * @since 1.0.0
 * @version 1.4.0
 * @return array List of groups with 'id' and 'name' keys.
 */
function pulseem_cf7_fetch_groups() {
    $pulseem_admin_model = new WooPulseemAdminModel();
    $api_key = $pulseem_admin_model->getApiKey();
    $url = $pulseem_admin_model->getEnvironmentUrl();

    $response = wp_remote_post(
        "$url/api/v1/GroupsApi/GetAllGroups",
        [
            'body'    => wp_json_encode( [ 'groupType' => 0 ] ),
            'headers' => [
                'accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'apiKey'        => $api_key,
            ],
        ]
    );

    $groups_data = [];
    if ( ! is_wp_error( $response ) ) {
        $response_body = wp_remote_retrieve_body( $response );
        $groups_list = json_decode( $response_body, true );
        if ( isset( $groups_list['groups'] ) && is_array( $groups_list['groups'] ) ) {
            foreach ( $groups_list['groups'] as $group ) {
                $groups_data[] = [
                    'id'   => $group['groupId'],
                    'name' => $group['groupName'],
                ];
            }
        }
    }

    return $groups_data;
}