<?php
/**
* Pulseem Groups API Integration
* 
* Handles all group-related API operations with Pulseem including:
* - Adding new clients to groups
* - Managing client data and products in groups
* - Retrieving group information
* - Handling both SOAP and REST API communications
* - Detailed error logging and response handling
* Supports multiple integration methods and complex data structures.
*
* @since      1.0.0
* @version    1.0.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PulseemGroups {

    /**
     * Adds a new client to a group.
     *
     * @param int $intGroupID Group ID.
     * @param string $strEmail Recipient's email.
     * @param string $firstName First name.
     * @param string $lastName Last name.
     * @param string $birthday Date of Birth.
     * @param string $city City name.
     * @param string $address Recipient's address.
     * @param string $zip Recipient's zip code.
     * @param string $country Recipient's country.
     * @param string $state Recipient's state.
     * @param string $company Company name.
     * @param string $telephone Recipient's phone number.
     * @param string $cellphone Recipient's cell phone.
     * @param bool $needOptin Requires opt-in approval.
     *
     * @return bool|string
     */
    public static function addNewClient(
        $intGroupID,
        $strEmail,
        $firstName = '',
        $lastName = '',
        $birthday = '',
        $city = '',
        $address = '',
        $zip = '',
        $country = '',
        $state = '',
        $company = '',
        $telephone = '',
        $cellphone = '000-0000000',
        $needOptin = false
    ) {
        $pulseem_admin_model = new WooPulseemAdminModel();
        $apikey = $pulseem_admin_model->getApiKey();

        $params = [
            'password' => $pulseem_admin_model->getPassword(),
            'intGroupID' => $intGroupID,
            'strEmail' => $strEmail,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'birthday' => $birthday,
            'city' => $city,
            'address' => $address,
            'company' => $company,
            'telephone' => $telephone,
            'needOptin' => (bool)$needOptin,
        ];

        $api_url = 'https://ui-api.pulseem.com/api/v1/ClientsApi/AddClients';
        $request_body = ['clientsData' => [$params]];

        $response = wp_remote_post($api_url, [
            'body' => wp_json_encode($request_body),
            'headers' => [
                'apiKey' => $apikey,
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_API,
                'API Error: AddClients failed - ' . $response->get_error_message(),
                [
                    'api_url' => $api_url,
                    'method' => 'POST',
                    'request_body' => $request_body,
                    'error' => $response->get_error_message(),
                ],
                $strEmail
            );
            return $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        PulseemLogger::info(
            PulseemLogger::CONTEXT_API,
            'API Response: AddClients',
            [
                'api_url' => $api_url,
                'method' => 'POST',
                'http_code' => $http_code,
                'request_body' => $request_body,
                'response_body' => json_decode($body, true),
            ],
            $strEmail
        );

        return $body;
    }

    /**
     * Posts a new client to Pulseem.
     *
     * @param array $args Client data.
     *
     * @return string|bool
     * @version 1.3.4
     * @since 1.0.0
     */
    public static function postNewClient($args, $need_optin = true) {
        $pulseem_admin_model = new WooPulseemAdminModel();
        $apikey = $pulseem_admin_model->getApiKey();
        $url = $pulseem_admin_model->getEnvironmentUrl();

        $default_params = [
            "groupID" => 0,
            "email" => '',
            "firstName" => '',
            "lastName" => '',
            "birthday" => '1990-01-01',
            "city" => '',
            "address" => '',
            "zip" => '00000',
            "country" => '',
            "state" => '',
            "company" => '',
            "telephone" => '',
            "cellphone" => '',
            "needOptin" => $need_optin,
            "optinType" => "EmailAndSms",
            "overwrite" => true,
            "overwriteOption" => "OverwriteWithNotEmptyValuesOnly",
        ];

        /*
        "needOptin": true,
        "overwrite": true,
        "overwriteOption": "OverwriteWithNotEmptyValuesOnly",
        */

        $params = wp_parse_args($args, $default_params);

        $request_body = [
            'clientsData' => [$params],
            'groupIds' => [$params['groupID']],
        ];

        $api_url = "$url/api/v1/ClientsApi/AddClients";

        $response = wp_remote_post($api_url, [
            'body' => wp_json_encode($request_body),
            'headers' => [
                'apiKey' => $apikey,
                'Content-Type' => 'application/json',
            ],
        ]);

        $email = isset($params['email']) ? $params['email'] : '';

        if (is_wp_error($response)) {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_API,
                'API Error: postNewClient failed - ' . $response->get_error_message(),
                [
                    'api_url' => $api_url,
                    'method' => 'POST',
                    'request_body' => $request_body,
                    'error' => $response->get_error_message(),
                ],
                $email
            );
            return $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        $log_data = [
            'api_url' => $api_url,
            'method' => 'POST',
            'http_code' => $http_code,
            'request_body' => $request_body,
            'response_body' => json_decode($body, true),
        ];

        if ($http_code >= 200 && $http_code < 300) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_API,
                'API Response: postNewClient to group ' . $params['groupID'],
                $log_data,
                $email
            );
        } else {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_API,
                'API Error: postNewClient to group ' . $params['groupID'] . ' returned HTTP ' . $http_code,
                $log_data,
                $email
            );
        }

        return $body;
    }

    /**
     * Posts a new client with product data.
     *
     * @param array $args Client and product data.
     *
     * @return string|bool
     */
    public static function postNewClientProduct($args) {
        $pulseem_admin_model = new WooPulseemAdminModel();
        $apikey = $pulseem_admin_model->getApiKey();
        $url = $pulseem_admin_model->getEnvironmentUrl();

        $email = isset($args['clientData']['email']) ? $args['clientData']['email'] : '';
        $event_type = isset($args['eventType']) ? $args['eventType'] : 'Unknown';
        $api_url = "$url/api/v1/ClientsApi/AddClientProduct";

        $response = wp_remote_post($api_url, [
            'body' => wp_json_encode($args),
            'timeout' => 60,
            'headers' => [
                'apiKey' => $apikey,
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_API,
                'API Error: AddClientProduct (' . $event_type . ') failed - ' . $response->get_error_message(),
                [
                    'api_url' => $api_url,
                    'method' => 'POST',
                    'event_type' => $event_type,
                    'request_body' => $args,
                    'error' => $response->get_error_message(),
                ],
                $email
            );
            return $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        $log_data = [
            'api_url' => $api_url,
            'method' => 'POST',
            'http_code' => $http_code,
            'event_type' => $event_type,
            'request_body' => $args,
            'response_body' => json_decode($body, true),
        ];

        if ($http_code >= 200 && $http_code < 300) {
            PulseemLogger::info(
                PulseemLogger::CONTEXT_API,
                'API Response: AddClientProduct (' . $event_type . ')',
                $log_data,
                $email
            );
        } else {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_API,
                'API Error: AddClientProduct (' . $event_type . ') returned HTTP ' . $http_code,
                $log_data,
                $email
            );
        }

        return $body;
    }

    /**
     * Gets all Pulseem groups.
     *
     * @param string $group_name Group name (optional).
     *
     * @return array|string
     */
    public static function getGroups($group_name = '') {
        $pulseem_admin_model = new WooPulseemAdminModel();
        $apikey = $pulseem_admin_model->getApiKey();

        $response = wp_remote_post('https://ui-api.pulseem.com/api/v1/GroupsApi/GetAllGroups', [
            'body' => wp_json_encode(['groupType' => 0]),
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json-patch+json',
                'apiKey' => $apikey,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['groups'])) {
            return array_map(function ($group) {
                return [
                    'name' => $group['groupName'],
                    'id' => $group['groupId'],
                ];
            }, $body['groups']);
        }

        return [];
    }
}
