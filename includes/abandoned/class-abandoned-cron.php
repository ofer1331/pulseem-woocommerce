<?php
/**
* Cart Abandonment Cron Handler
* 
* Manages scheduled tasks for processing abandoned carts. Features include:
* - Setting up custom cron schedules for cart checking
* - Processing abandoned carts based on configured time intervals
* - Syncing abandoned cart data to Pulseem groups
* - Handling both logged-in and guest user carts
* - Detailed logging of sync operations and errors
*
* @since      1.0.0
* @version    1.4.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WC_Customer;
use pulseem\PulseemLogger;


class WooPulseemAbandonedCron {

    /**
     * Constructor for WooPulseemAbandonedCron.
     */
    public function __construct() {
        $pulseem_admin_model = new WooPulseemAdminModel();

        // Initialize schedules and cron job hooks
        add_action('init', [$this, 'initSchedules']);

        // Clear scheduled hooks to prevent duplicate entries
        wp_clear_scheduled_hook('cronSchedules');

        // Check if cart abandonment is enabled
        if ($pulseem_admin_model->getIsCartAbandoned()) {
            add_filter('cron_schedules', [$this, 'cronSchedules']);
            add_action('pulseem_abandoned_cron_hook', [$this, 'startSchedule']);
        } else {
            $this->deactivateSchedule();
        }

        // Hook to update cron job when admin settings change
        add_action('update_option_pulseem_settings', [$this, 'updateCronJobOnSettingsChange'], 10, 2);
    }

    /**
     * Initialize schedules for cart abandonment cron job.
     */
    public function initSchedules() {
        if (!wp_next_scheduled('pulseem_abandoned_cron_hook')) {
            wp_schedule_event(time(), 'pulseem_abandoned_min', 'pulseem_abandoned_cron_hook');
            PulseemLogger::debug(
                PulseemLogger::CONTEXT_CRON,
                'Cron schedule initialized'
            );
        }
    }

    /**
     * Add custom cron schedules dynamically based on settings.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Updated cron schedules.
     */
    public function cronSchedules($schedules) {
        $pulseem_admin_model = new WooPulseemAdminModel();
        $time_duration = $pulseem_admin_model->getUserCartAbandonedAftertimeDuration();
        $time_type = $pulseem_admin_model->getUserCartAbandonedAftertimeTypes();
        $interval = (int) $time_duration * (int) $time_type;

        if ($interval === 0) {
            $interval = 60; // Default to 1 minute if invalid
        }

        $schedules['pulseem_abandoned_min'] = [
            'interval' => $interval,
            'display' => __('Woo Pulseem Abandoned Cart Interval', 'pulseem'),
        ];

        return $schedules;
    }

    /**
     * Execute the cron job and process abandoned carts.
     *
     * @throws \Exception
     */
    public function startSchedule() {
        PulseemLogger::debug(PulseemLogger::CONTEXT_CRON, 'Cron job started');
        $pulseem_admin_model = new WooPulseemAdminModel();
    
        $time_duration = $pulseem_admin_model->getUserCartAbandonedAftertimeDuration();
        $time_type = $pulseem_admin_model->getUserCartAbandonedAftertimeTypes();
        $interval = $time_duration * $time_type;
    
        if ($interval === 0) {
            $interval = 1; // Fallback
        }
    
        $group_id = $pulseem_admin_model->getUserCartAbandonedGroupId();
        $is_pending_enabled = $pulseem_admin_model->getIsCartAbandonedPending(); // בדוק אם האפשרות "Pending" פעילה
    
        if ($group_id == -1) return;
    
        $users = WooPulseemAbandonedModel::getAllByDate(time() - $interval);
        if ($users) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_ABANDONED_CART,
                'Processing ' . count($users) . ' abandoned cart(s)',
                ['interval' => $interval, 'group_id' => $group_id]
            );

            foreach ($users as $user) {
                $customer_data = json_decode($user->customer_data, true);
                if ( ! is_array( $customer_data ) ) {
                    $customer_data = [];
                }
                $products = isset($customer_data['cart']) ? $customer_data['cart'] : [];
                $user_agree = $user->user_agree ?? 1; // ברירת מחדל ל-true אם העמודה ריקה
    
                // אם משתמש לא אישר דיוור והאפשרות "Pending" לא פעילה
                if ($user_agree == 0 && !$is_pending_enabled) {
                    // מחק את הרשומה ללא שליחת POST
                    WooPulseemAbandonedModel::deleteById($user->id);
                    continue; // עבור למשתמש הבא
                }
    
                $request_data = [
                    'clientData' => [
                        'email' => $customer_data['email'] ?? '',
                        'firstName' => $customer_data['first_name'] ?? '',
                        'lastName' => $customer_data['last_name'] ?? '',
                        'telephone' => '', // שדה נוסף
                        'cellphone' => $customer_data['phone'] ?? '',
                        'address' => $customer_data['shipping_address'] ?? '',
                        'city' => $customer_data['shipping_city'] ?? '',
                        'state' => $customer_data['state'] ?? '',
                        'country' => $customer_data['country'] ?? '', // שדה חדש
                        'zip' => $customer_data['postcode'] ?? '',
                        'birthDate' => '', // שדה חדש
                        'reminderDate' => '', // שדה חדש
                        'company' => $customer_data['company'] ?? '',
                        'extraDate1' => '', // שדות נוספים
                        'extraDate2' => '',
                        'extraDate3' => '',
                        'extraDate4' => '',
                        'extraField1' => '',
                        'extraField2' => '',
                        'extraField3' => '',
                        'extraField4' => '',
                        'extraField5' => '',
                        'extraField6' => '',
                        'extraField7' => '',
                        'extraField8' => '',
                        'extraField9' => '',
                        'extraField10' => '',
                        'extraField11' => '',
                        'extraField12' => '',
                        'extraField13' => '',
                        // הפוך את הערך בעמודת `user_agree`
                        'needOptin' => $user_agree == 1 ? false : true,
                        'overwrite' => true,
                        'overwriteOption' => 'OverwriteWithNotEmptyValuesOnly',
                        'optinType' => 'NewEmailAndSms',
                    ],
                    'groupIds' => is_array($group_id) ? array_filter(array_map('strval', $group_id)) : [(string) $group_id],
                    'products' => array_map(function ($product) {
                        return [
                            'productID' => $product['productID'] ?? 0,
                            'productCategoryName' => $product['productCategoryName'] ?? '',
                            'imageURL' => $product['imageURL'] ?? '',
                            'name' => $product['name'] ?? '',
                            'description' => $product['description'] ?? '',
                            'quantity' => $product['quantity'] ?? 0,
                            'price' => $product['price'] ?? 0,
                            'code' => $product['code'] ?? '',
                            'hrefUrl' => $product['hrefUrl'] ?? '',
                        ];
                    }, $products),
                    'eventType' => 'Abandonment',
                    'eventSource' => 'WOOCOMMERCE',
                ];
    
                $result = PulseemGroups::postNewClientProduct($request_data);

                $cart_email = isset($customer_data['email']) ? $customer_data['email'] : '';

                $api_log_data = [
                    'email' => $cart_email,
                    'products_count' => count($products),
                    'group_id' => $group_id,
                ];

                // Pulseem logging
                if (!empty($result)) {
                    PulseemLogger::info(
                        PulseemLogger::CONTEXT_ABANDONED_CART,
                        'Abandoned cart synced successfully',
                        $api_log_data,
                        $cart_email
                    );
                } else {
                    PulseemLogger::error(
                        PulseemLogger::CONTEXT_ABANDONED_CART,
                        'Failed to sync abandoned cart',
                        $api_log_data,
                        $cart_email
                    );
                }


                WooPulseemAbandonedModel::deleteById($user->id);
            }
        }
    }
    

    /**
     * Deactivate the schedule for the abandoned cart cron job.
     */
    public function deactivateSchedule() {
        wp_clear_scheduled_hook('pulseem_abandoned_cron_hook');
    }

    /**
     * Update the cron job when settings are changed in the admin panel.
     *
     * @param string $old_value The old value of the settings.
     * @param string $new_value The new value of the settings.
     */
    public function updateCronJobOnSettingsChange($old_value, $new_value) {
        $pulseem_admin_model = new WooPulseemAdminModel();

        // Deactivate existing schedule
        $this->deactivateSchedule();

        // Reinitialize schedules if cart abandonment is enabled
        if ($pulseem_admin_model->getIsCartAbandoned()) {
            PulseemLogger::info(PulseemLogger::CONTEXT_CRON, 'Cron updated after settings change');
            $this->initSchedules();
        }
    }
}


