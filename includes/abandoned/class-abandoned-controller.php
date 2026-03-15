<?php
/**
* Cart Abandonment Controller
* 
* Handles all cart abandonment tracking functionality including:
* - Cart modifications tracking (add, update, remove items)
* - Customer data collection during checkout
* - Checkout process monitoring
* - Cart abandonment status updates
* Integrates with WooCommerce hooks to track customer behavior and 
* store relevant cart data for abandoned cart recovery.
*
* @since      1.0.0
* @version    1.4.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooPulseemAbandonedController {


	public function __construct() {

		add_action('wp_ajax_nopriv_pulseem_change_checkout_data', [$this, 'change_checkout_data_handler']);
		add_action('wp_ajax_pulseem_change_checkout_data', [$this, 'change_checkout_data_handler']);
		

		$pulseem_admin_model = new WooPulseemAdminModel();
		if($pulseem_admin_model->getIsCartAbandoned()){
			/** Cart add to cart hook */
			add_action('woocommerce_add_to_cart', [$this, 'woocommerce_add_to_cart']);
			/** Cart update hook */
			add_action('woocommerce_update_cart_action_cart_updated', [$this, 'woocommerce_change_cart']);
			/** remove from cart hook */
			add_action('woocommerce_cart_item_removed', [$this, 'woocommerce_change_cart']);
			/** restore cart item hook */
			add_action('woocommerce_restore_cart_item', [$this, 'woocommerce_change_cart']);
			/** checkout order hook */
			//add_action('woocommerce_checkout_order_processed', [$this, 'woocommerce_checkout_order_processed'], 50);
			add_action('woocommerce_thankyou', [$this, 'woocommerce_checkout_order_processed']);
		}
	}

	/**
	 *
	 */
	public function woocommerce_add_to_cart() {
		$model = new WooPulseemAbandonedModel();
		$user_id = get_current_user_id();
		$customer_id = WC()->session->get_customer_id();
		$woocommerce_session_data = WC()->session->get_session_data();
		$customer_data = isset($woocommerce_session_data['customer']) ? $woocommerce_session_data['customer'] : [];
	
		// בדיקה אם יש רשומה לפי user_id
		$result = null;
		if ($user_id) {
			$result = $model->getOneByUserId($user_id);
		}
	
		// אם לא נמצא לפי user_id, לבדוק לפי customer_id
		if (!$result && $customer_id) {
			$result = $model->getOneByCustomerId($customer_id);
	
			// איחוד רשומות אם יש user_id
			if ($result && $user_id) {
				$model->setUserId($user_id); // עדכון user_id ברשומה הקיימת
			}
		}
	
		// אם הרשומה קיימת, עדכון זמן
		if ($result) {
			$model->setTime(time());
		} else {
			// יצירת רשומה חדשה
			$model->setCustomerData($customer_data);
			if ($user_id) {
				$model->setUserId($user_id);
			} else {
				$model->setCustomerId($customer_id);
			}
		}
	
		// שמירה למסד הנתונים
		$model->save();
	}
	
	

	/**
	 *
	 */
	public function woocommerce_change_cart(){
		$user_id = get_current_user_id();
		if($user_id) {
			$model = new WooPulseemAbandonedModel();
			$result = $model->getOneByUserId($user_id);
			if ( WC()->cart->get_cart_contents_count() == 0 ) {
				if($result)
					$model->delete();
			} else {
				if($result){
					$model->setTime(time());
				}else{
					$model->setUserId($user_id);
				}
				$model->save();
			}
		}
	}

	/**
	 *
	 */
	public function woocommerce_checkout_order_processed() {
		$user_id = get_current_user_id();
		$model = new WooPulseemAbandonedModel();
	
		if ($user_id) {
			$result = $model->getOneByUserId($user_id);
			if ($result) {
				$model->delete();
			}
		} else {
			$temp_session = WC()->session->get_customer_id();
			if ($temp_session) {
				$model->deleteTemp($temp_session);
			}
		}
	}
	

	/**
	 *
	 */

	 function change_checkout_data_handler() {
		try {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pulseem_checkout_nonce' ) ) {
				\pulseem\PulseemLogger::warning(
					\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
					'Invalid nonce in checkout data handler'
				);
				wp_send_json_error( 'Invalid nonce', 403 );
			}

			// קבלת נתונים מהבקשה
			$email = isset($_POST['email']) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$phone = isset($_POST['phone']) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$first_name = isset($_POST['first_name']) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
			$last_name = isset($_POST['last_name']) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
			$user_agree = isset($_POST['user_agree']) ? intval($_POST['user_agree']) : 0;
	
			// קבלת session id ונתוני לקוח
			$customer_id = WC()->session->get_customer_id();
			if (empty($customer_id)) {
				\pulseem\PulseemLogger::warning(
					\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
					'Missing customer ID in checkout data handler'
				);
				wp_send_json_error(['message' => 'Customer ID is missing']);
				return;
			}
	
			$model = new WooPulseemAbandonedModel();
			$existing_data = $model->getOneByCustomerId($customer_id);
	
			// אם יש נתונים קיימים, שלוף אותם; אחרת, צור מבנה ריק
			if ( $existing_data && $existing_data->customer_data ) {
				$customer_data = json_decode( $existing_data->customer_data, true );
				if ( ! is_array( $customer_data ) ) {
					$customer_data = [];
				}
			} else {
				$customer_data = [];
			}
	
			// עדכון או הוספת נתונים חדשים
			$customer_data['email'] = $email;
			$customer_data['phone'] = $phone;
			$customer_data['first_name'] = $first_name;
			$customer_data['last_name'] = $last_name;
			$customer_data['user_agree'] = $user_agree;
	
			// עגלת הקניות
			$cart_items = WC()->cart->get_cart();
			$cart_data = [];
			foreach ($cart_items as $cart_item) {
				$product_id = $cart_item['product_id'];
				$product = wc_get_product($product_id);
				if ($product) {
					$cart_data[] = [
						'productID' => $product_id,
						'name' => $product->get_name(),
						'description' => $product->get_short_description(),
						'hrefUrl' => get_permalink($product->get_parent_id() ?: $product_id),
						'productCategoryName' => implode(", ", wp_get_post_terms($product->get_parent_id() ?: $product_id, 'product_cat', ['fields' => 'names'])),
						'productCategoryIDs' => implode(", ", wp_get_post_terms($product->get_parent_id() ?: $product_id, 'product_cat', ['fields' => 'ids'])),
						"imageURL" => wp_get_attachment_url($product->get_image_id()),
						'code' => $product->get_sku(),
						'price' => $product->get_price(),
						'quantity' => $cart_item['quantity'],
					];
				}
			}
			$customer_data['cart'] = $cart_data;
	
			// שמירת נתוני כתובת למשלוח
			$customer_data['shipping_first_name'] = WC()->customer->get_shipping_first_name();
			$customer_data['shipping_last_name'] = WC()->customer->get_shipping_last_name();
			$customer_data['shipping_address'] = WC()->customer->get_shipping_address();
			$customer_data['shipping_city'] = WC()->customer->get_shipping_city();
			$customer_data['shipping_state'] = WC()->customer->get_shipping_state();
			$customer_data['shipping_postcode'] = WC()->customer->get_shipping_postcode();
			$customer_data['shipping_country'] = WC()->customer->get_shipping_country();
	
			// שמירת הנתונים למסד
			$model->setCustomerData(wp_json_encode($customer_data));
			$model->setCustomerId($customer_id);
			$model->setUserAgree($user_agree); // שמירת user_agree במודל
			$model->setTime(time());
			$model->save();
	
			\pulseem\PulseemLogger::debug(
				\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
				'Checkout data updated',
				['email' => $email],
				$email
			);
			wp_send_json_success(['message' => 'Customer data updated successfully']);
		} catch (\Exception $e) {
			\pulseem\PulseemLogger::error(
				\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
				'Exception in checkout data handler: ' . $e->getMessage()
			);
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}
	
}
