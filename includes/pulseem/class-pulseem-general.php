<?php
/**
* Pulseem API Integration Core
* 
* Handles core SOAP API communication with Pulseem services including:
* - Account and sub-account management
* - Authentication and token handling
* - Client data retrieval from Pulseem groups
* Provides base client model and general API functionality.
* Uses SOAP 1.2 protocol for API communications.
*
* @since      1.0.0
* @version    1.0.0
*/
namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SoapClient;

class Client {
	public $Email;
	public $Status;
	public $SmsStatus;
	public $FirstName;
	public $LastName;
	public $Telephone;
	public $Cellphone;
	public $Adress;
	public $City;
	public $State;
	public $Country;
	public $Zip;
	public $BirthDate;
	public $ReminderDate;
	public $Company;
	public $CreationDate;
	public $IsWebService;
}

class PulseemGeneral {

	/**
	 * @param bool $showAllSubaccounts
	 *
	 * @return mixed
	 */
	public static function getSubAccountOrAccount($showAllSubaccounts = true){
		$client = new SoapClient(PULSEEM_WSDL_URL);
		$pulseem_admin_model = new WooPulseemAdminModel();
		$params = array(
			'password' => $pulseem_admin_model->getPassword(),
			'showAllSubaccounts' => $showAllSubaccounts
		);
		$result = $client->GetSubAccountOrAccount($params);
		return $result->GetSubAccountOrAccountResult;
	}

	public static function setToken(){
		$client = new SoapClient(PULSEEM_WSDL_URL, ['soap_version' => SOAP_1_2]);
		$pulseem_admin_model = new WooPulseemAdminModel();
		$params = array(
			'apiKey' => $pulseem_admin_model->getApiKey(),
			'username' => $pulseem_admin_model->getLogin(),
			'password' => $pulseem_admin_model->getPassword()
		);
		$result = $client->SetToken($params);
		return $result->SetTokenResult;
	}

	/**
	 * @param array $groupIds
	 *
	 * @return array
	 */
	public static function getClients(array $groupIds){
		$pulseem_admin_model = new WooPulseemAdminModel();
		$client = new SoapClient(
			PULSEEM_WSDL_URL,
			[
				'classmap' => ['clients' => Client::class],
				'encoding'=>'ISO-8859-1'
			]
		);
		$params = array(
			'pwd' => $pulseem_admin_model->getPassword(),
			'groupIds' => $groupIds
		);
		$result = $client->GetClients($params);

		return $result->GetClientsResult;
	}
}
