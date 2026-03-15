<?php
/**
* Abandoned Cart Data Model
* 
* Core model class for handling abandoned cart data operations including:
* - CRUD operations for abandoned cart records
* - Customer and user data management
* - Session tracking and temporary data handling
* - Time-based cart retrieval and filtering
* - Logging of data operations
* Provides the data layer interface between the database and business logic.
*
* @since      1.0.0
* @version    1.4.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooPulseemAbandonedModel {

	private $id;
	private $user_id;
	private $customer_id;
	private $customer_data;
	private $time;
	private $user_agree;

	private $table_name;

	/**
	 * WooPulseemAbandonedModel constructor.
	 *
	 * @param int $id
	 */
	public function __construct($id = 0) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . "pulseem_abandoned";
		if ($id == 0) {
			$this->id = null;
			$this->user_id = 0;
			$this->customer_id = 0;
			$this->customer_data = '';
			$this->time = time();
			$this->user_agree = 0; // ערך ברירת מחדל ל-true
		} else {
			$this->init($id);
		}
	}

	private function init($id) {
		global $wpdb;
		$result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . esc_sql($this->table_name) . "` WHERE id = %d LIMIT 1", $id));
		if ($result) {
			foreach ($result as $row) {
				$this->id = $row->id;
				$this->user_id = $row->user_id;
				$this->customer_id = $row->customer_id;
				$this->customer_data = $row->customer_data;
				$this->time = $row->time;
				$this->user_agree = $row->user_agree ?? 1; // ערך ברירת מחדל
			}
		}
		return $result;
	}

	public function save() {
		global $wpdb;

		$data = [
			'user_id'      => $this->user_id,
			'customer_id'  => $this->customer_id,
			'customer_data'=> empty($this->customer_data) ? wp_json_encode([]) : $this->customer_data,
			'time'         => $this->time,
			'user_agree'   => $this->user_agree // שמירת user_agree
		];

		if ($this->id > 0) {
			$result = $wpdb->update(
				$this->table_name,
				$data,
				['id' => $this->id]
			);
			$method = 'update';
		} else {
			$result = $wpdb->insert(
				$this->table_name,
				$data
			);
			$method = 'insert';
			
			if ($result && $wpdb->insert_id) {
				$this->id = $wpdb->insert_id;
			}
		}

		if (empty($result)) {
			\pulseem\PulseemLogger::error(
				\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
				'Failed to save abandoned cart data',
				['method' => $method, 'error' => $wpdb->last_error, 'data' => $data]
			);
		} else {
			\pulseem\PulseemLogger::debug(
				\pulseem\PulseemLogger::CONTEXT_ABANDONED_CART,
				'Abandoned cart data saved to DB',
				['method' => $method, 'result' => $result]
			);
		}

		return $result;
	}

	public function deleteTemp($temp_session) {
		global $wpdb;
		$result = false;
		if (!empty($temp_session)) {
			$result = $wpdb->delete(
				$this->table_name,
				['customer_id' => $temp_session]
			);
		}

		return $result;
	}

	public function delete() {
		global $wpdb;
		$result = false;
		if ($this->id > 0) {
			$result = $wpdb->delete(
				$this->table_name,
				['id' => $this->id]
			);
		}

		return $result;
	}

	public static function deleteById($id) {
		global $wpdb;

		$result = false;

		if ($id > 0) {
			$result = $wpdb->delete(
				$wpdb->prefix . "pulseem_abandoned",
				['id' => $id]
			);
		}

		return $result;
	}

	/**
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getUserId() {
		return $this->user_id;
	}

	/**
	 * @param int $user_id
	 */
	public function setUserId($user_id) {
		$this->user_id = $user_id;
	}

	/**
	 * @return int|string
	 */
	public function getTime() {
		return $this->time;
	}

	/**
	 * @param int|string $time
	 */
	public function setTime($time) {
		$this->time = $time;
	}

	/**
	 * @return int
	 */
	public function getCustomerId() {
		return $this->customer_id;
	}

	/**
	 * @param int $customer_id
	 */
	public function setCustomerId($customer_id) {
		$this->customer_id = $customer_id;
	}

	/**
	 * @return string
	 */
	public function getCustomerData() {
		return $this->customer_data;
	}

	/**
	 * @param string $customer_data
	 */
	public function setCustomerData($customer_data) {
		$this->customer_data = $customer_data;
	}

	/**
	 * @return int
	 */
	public function getUserAgree() {
		return $this->user_agree;
	}

	/**
	 * @param int $user_agree
	 */
	public function setUserAgree($user_agree) {
		$this->user_agree = $user_agree;
	}

	/**
	 * @param $user_id
	 *
	 * @return array|null|object
	 */
	public function getOneByUserId($user_id) {
		global $wpdb;
		$result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . esc_sql($this->table_name) . "` WHERE user_id = %d LIMIT 1", $user_id));
		if ($result) {
			foreach ($result as $row) {
				$this->id = $row->id;
				$this->user_id = $row->user_id;
				$this->customer_id = $row->customer_id;
				$this->customer_data = $row->customer_data;
				$this->time = $row->time;
				$this->user_agree = $row->user_agree ?? 1; // ערך ברירת מחדל
			}
		}
		return $result;
	}

	/**
	 * @param $customer_id
	 *
	 * @return array|null|object
	 */
	public function getOneByCustomerId($customer_id) {
		global $wpdb;
		$result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . esc_sql($this->table_name) . "` WHERE customer_id = %s LIMIT 1", $customer_id));
		if ($result) {
			foreach ($result as $row) {
				$this->id = $row->id;
				$this->user_id = $row->user_id;
				$this->customer_id = $row->customer_id;
				$this->customer_data = $row->customer_data;
				$this->time = $row->time;
				$this->user_agree = $row->user_agree ?? 1; // ערך ברירת מחדל
			}
		}
		return $result;
	}

	/**
	 * @param $time
	 *
	 * @return array
	 */
	public static function getAllByDate($time) {
		global $wpdb;
		$table_name = $wpdb->prefix . "pulseem_abandoned";
		$result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . esc_sql($table_name) . "` WHERE time <= %d", $time));
		$result_array = [];
		if ($result) {
			foreach ($result as $row) {
				$result_array[] = (object) [
					'id'          => $row->id,
					'user_id'     => $row->user_id,
					'time'        => $row->time,
					'customer_id' => $row->customer_id,
					'customer_data' => $row->customer_data,
					'user_agree'  => $row->user_agree
				];
			}
		}
		return $result_array;
	}
}