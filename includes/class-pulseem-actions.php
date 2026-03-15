<?php
/**
 * WooCommerce Action Handler
 *
 * Manages core WooCommerce integration actions with Pulseem including:
 * - User registration synchronization
 * - Order processing and purchase tracking
 * - Customer data management
 * - Abandoned cart recovery
 * - Logging and error handling
 * Provides the main integration point between WooCommerce events and Pulseem.
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

class WooPulseemActions {

    /**
     * Pulseem admin model instance
     *
     * @var WooPulseemAdminModel
     * @since 1.0.0
     * @version 1.0.0
     */
    private $pulseem_admin_model;

    /**
     * Constructor
     *
     * Initializes the class and sets up hooks for WooCommerce actions.
     *
     * @since 1.0.0
     * @version 1.0.0
     */
    public function __construct() {
        $this->pulseem_admin_model = new WooPulseemAdminModel();
        add_action('user_register', [$this, 'userRegister']);
        //add_action('woocommerce_thankyou', [$this, 'wooCheckoutOrderProcessed'], 40);
        add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChange'], 10, 4);
    }

    /**
         * Handle order status change
         *
         * Processes order status changes and triggers order processed actions when needed.
         *
         * @since 1.0.0
         * @version 1.0.0
         * @param int $order_id The ID of the order.
         * @param string $old_status The old order status.
         * @param string $new_status The new order status.
         * @param WC_Order $order The order object.
         */
        public function handleOrderStatusChange($order_id, $old_status, $new_status, $order) {
            if (in_array($new_status, ['processing', 'completed'])) {
                $this->wooCheckoutOrderProcessed($order_id);
            }
        }

    /**
     * Handle user registration
     *
     * Synchronizes new user registrations with Pulseem groups.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param int $user_id The ID of the registered user.
     */
    public function userRegister($user_id) {
        // בדוק אם הרישום פעיל והאם יש מזהה קבוצה
        if ($this->pulseem_admin_model->getIsUserRegister() && $this->pulseem_admin_model->getUserRegisterGroupId()) {
            $user = get_userdata($user_id);

            PulseemLogger::info(
                PulseemLogger::CONTEXT_USER_REGISTER,
                'Processing user registration for user ID: ' . $user_id,
                null,
                $user ? $user->user_email : null,
                $user_id
            );
    
            // בדוק אם המשתמש קיים
            if (!$user) {
                PulseemLogger::error(
                    PulseemLogger::CONTEXT_USER_REGISTER,
                    'Failed to get user data for user ID: ' . $user_id,
                    null,
                    null,
                    $user_id
                );
                return;
            }
    
            $is_enabled_user_registration_agree = $this->pulseem_admin_model->getIsEnableUserRegisterAgreement();
            $is_user_registration_agree = UserModel::get_user_pulseem_registration_agreement($user_id);
            $user_agreement_checked = ($is_enabled_user_registration_agree == 1 && $is_user_registration_agree == 1);
    
            // אם המשתמש לא הסכים והצ'קבוקס של pending לא מסומן, לא לשלוח
            if (!$user_agreement_checked && !$this->pulseem_admin_model->getIsUserRegisterPending()) {
                PulseemLogger::info(
                    PulseemLogger::CONTEXT_USER_REGISTER,
                    'User not sent due to lack of agreement and pending setting',
                    null,
                    $user->user_email,
                    $user_id
                );
                return;
            }
    
            // קבל את הקבוצות
            $groups = $this->pulseem_admin_model->getUserRegisterGroupId();
            if (!is_array($groups) || empty($groups)) {
                PulseemLogger::error(
                    PulseemLogger::CONTEXT_USER_REGISTER,
                    'No valid groups found for user ID: ' . $user_id,
                    null,
                    $user->user_email,
                    $user_id
                );
                return;
            }
    
            // לולאה על הקבוצות
            foreach ($groups as $group) {
                // הבטחת המרה למספר שלם
                $group = (int)$group;
    
                if ($group > 0) {
                    $payload = [
                        "groupID" => $group,
                        "email" => $user->user_email,
                        "firstName" => $user->user_firstname ?? '',
                        "lastName" => $user->user_lastname ?? '',
                        "cellphone" => $user->user_phone ?? '',
                        "birthday" => '',
                        "city" => '',
                        "address" => '',
                        "zip" => '',
                        "country" => '',
                        "state" => '',
                        "company" => '',
                        "arrivalSource" => "WOOCOMMERCE",
                    ];
    
                    // שליחת הלקוח לקבוצה
                    $result = PulseemGroups::postNewClient($payload, !$user_agreement_checked);

                    $api_log_data = [
                        'group' => $group,
                        'needOptin' => !$user_agreement_checked,
                    ];

                    if ($result) {
                        PulseemLogger::info(
                            PulseemLogger::CONTEXT_USER_REGISTER,
                            'User added successfully to group ' . $group,
                            $api_log_data,
                            $user->user_email,
                            $user_id
                        );
                    } else {
                        PulseemLogger::error(
                            PulseemLogger::CONTEXT_USER_REGISTER,
                            'Failed to add user to group ' . $group,
                            $api_log_data,
                            $user->user_email,
                            $user_id
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Handle order processing
     *
     * Processes completed WooCommerce orders and synchronizes them with Pulseem.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param int $order_id The ID of the processed order.
     */
    public function wooCheckoutOrderProcessed($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        PulseemLogger::info(
            PulseemLogger::CONTEXT_PURCHASE,
            'Processing order #' . $order_id . ' with status: ' . $order->get_status(),
            null,
            $order->get_billing_email()
        );
    
        // בדוק אם הסטטוס הוא 'processing' או 'completed'
        if (!in_array($order->get_status(), ['processing', 'completed'])) {
            PulseemLogger::debug(
                PulseemLogger::CONTEXT_PURCHASE,
                'Order #' . $order_id . ' is not in a successful status. Skipping.',
                ['status' => $order->get_status()],
                $order->get_billing_email()
            );
            return;
        }

        // בדוק אם ההזמנה כבר טופלה
        if (get_post_meta($order_id, '_pulseem_processed', true)) {
            PulseemLogger::debug(
                PulseemLogger::CONTEXT_PURCHASE,
                'Order #' . $order_id . ' has already been processed. Skipping.',
                null,
                $order->get_billing_email()
            );
            return;
        }
    
        // Retrieve the agreement status (checkbox) from the order meta
        $pulseem_checkout_agreement = (int) get_post_meta($order_id, 'pulseem_checkout_agreement', true);
        $order_data = $order->get_data();
        $groups = $this->pulseem_admin_model->getUserPurchaseGroupId();
        $is_purchase_pending_enabled = $this->pulseem_admin_model->getIsPurchasePending();
    
        // Calculate needOptin
        $needOptin = true;
        if ($pulseem_checkout_agreement == 1) {
            $needOptin = false;
        }
    
        // Stop the function if needOptin is true and pending option is disabled
        if ($needOptin && !$is_purchase_pending_enabled) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_PURCHASE,
                'Order #' . $order_id . ' skipped - needOptin is true and pending is disabled',
                null,
                $order->get_billing_email()
            );
            return;
        }
    
        // Process groups and send data to Pulseem
        foreach ($groups as $group) {
            if ($group > 0) {
                $products = [];
                foreach ($order->get_items() as $item) {
                    $product = wc_get_product($item->get_product_id());
                    $product_categories = wp_get_post_terms($item->get_product_id(), 'product_cat', ['fields' => 'names']);
                    $products[] = [
                        "productID" => $item->get_product_id(),
                        "productCategoryName" => implode(", ", $product_categories),
                        "imageURL" => get_the_post_thumbnail_url($item->get_product_id()),
                        "name" => $item->get_name(),
                        "description" => $product->get_short_description(),
                        "quantity" => $item->get_quantity(),
                        "price" => $item->get_total(),
                        "code" => $product->get_sku(),
                        "hrefUrl" => get_permalink($item->get_product_id()),
                    ];
                }
    
                $payload = [
                    'clientData' => [
                        "groupID" => (int) $group,
                        "email" => $order_data['billing']['email'],
                        "firstName" => $order_data['billing']['first_name'],
                        "lastName" => $order_data['billing']['last_name'],
                        "cellphone" => $order_data['billing']['phone'],
                        "needOptin" => $needOptin,
                        "optinType" => "NewEmailAndSms",
                        "overwrite" => true,
                        "arrivalSource" => "WOOCOMMERCE",
                        "overwriteOption" => "OverwriteWithNotEmptyValuesOnly",
                    ],
                    'groupIds' => [$group],
                    'products' => $products,
                    'eventType' => 'Purchase',
                    'eventSource' => 'WOOCOMMERCE',
                ];
    
                $result = PulseemGroups::postNewClientProduct($payload);

                $api_log_data = [
                    'group' => $group,
                    'order_id' => $order_id,
                    'eventType' => 'Purchase',
                ];

                if ($result) {
                    PulseemLogger::info(
                        PulseemLogger::CONTEXT_PURCHASE,
                        'Order #' . $order_id . ' - Response received successfully',
                        $api_log_data,
                        $order_data['billing']['email']
                    );
                } else {
                    PulseemLogger::error(
                        PulseemLogger::CONTEXT_PURCHASE,
                        'Order #' . $order_id . ' - Failed to add user to group ' . $group,
                        $api_log_data,
                        $order_data['billing']['email']
                    );
                }
            }
        }
    
        // הגדר את ההזמנה כמעובדת על ידי עדכון Meta
        update_post_meta($order_id, '_pulseem_processed', true);

        PulseemLogger::info(
            PulseemLogger::CONTEXT_PURCHASE,
            'Order #' . $order_id . ' has been marked as processed',
            null,
            $order->get_billing_email()
        );
    }    
}