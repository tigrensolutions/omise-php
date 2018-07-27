<?php

namespace OmiseApi\Res;

use OmiseApi\Exception\OmiseException;
use OmiseApi\Res\Obj\OmiseObject;

define('OMISE_PHP_LIB_VERSION', '2.6.0');
define('OMISE_API_URL', 'https://api.omise.co/');
define('OMISE_VAULT_URL', 'https://vault.omise.co/');

class OmiseApiResource extends OmiseObject
{
    // Request methods
    const REQUEST_GET = 'GET';
    const REQUEST_POST = 'POST';
    const REQUEST_DELETE = 'DELETE';
    const REQUEST_PATCH = 'PATCH';

    // Timeout settings
    private $OMISE_CONNECTTIMEOUT = 30;
    private $OMISE_TIMEOUT = 60;

    /**
     * Returns an instance of the class given in $clazz or raise an error.
     *
     * @param  string $clazz
     * @param  string $publickey
     * @param  string $secretkey
     *
     * @throws Exception
     *
     * @return OmiseResource
     */
    protected static function getInstance($clazz, $publickey = null, $secretkey = null)
    {
        if (class_exists($clazz)) {
            return new $clazz($publickey, $secretkey);
        }

        throw new Exception('Undefined class.');
    }

    /**
     * Retrieves the resource.
     *
     * @param  string $clazz
     * @param  string $publickey
     * @param  string $secretkey
     *
     * @throws Exception|OmiseException
     *
     * @return OmiseAccount|OmiseBalance|OmiseCharge|OmiseCustomer|OmiseToken|OmiseTransaction|OmiseTransfer
     */
    protected static function g_retrieve($clazz, $url, $publickey = null, $secretkey = null)
    {
        $resource = call_user_func(array($clazz, 'getInstance'), $clazz, $publickey, $secretkey);
        $result = $resource->execute($url, self::REQUEST_GET, $resource->getResourceKey());
        var_dump($result);
        die();
        $resource->refresh($result);

        return $resource;
    }

    /**
     * Creates the resource with given parameters.in an associative array.
     *
     * @param  string $clazz
     * @param  string $url
     * @param  array $params
     * @param  string $publickey
     * @param  string $secretkey
     *
     * @throws Exception|OmiseException
     *
     * @return OmiseAccount|OmiseBalance|OmiseCharge|OmiseCustomer|OmiseToken|OmiseTransaction|OmiseTransfer
     */
    protected static function g_create($clazz, $url, $params, $publickey = null, $secretkey = null)
    {
        $resource = call_user_func(array($clazz, 'getInstance'), $clazz, $publickey, $secretkey);
        $result = $resource->execute($url, self::REQUEST_POST, $resource->getResourceKey(), $params);
        $resource->refresh($result);

        return $resource;
    }

    protected static function g_retrieve_dtac($clazz, $url, $isproxy, $proxyurl, $key)
    {
        $resource = call_user_func(array($clazz, 'getInstance'), $clazz);
        $result = $resource->execute_retrieve($url, self::REQUEST_GET, $isproxy, $proxyurl, $key);
        $resource->refresh($result);

        return $resource;
    }

    protected static function dtac_create($clazz, $params, $url, $isproxy, $proxyurl)
    {
        $resource = call_user_func(array($clazz, 'getInstance'), $clazz);
        $result = $resource->executeDtac($params, $url, $isproxy, $proxyurl);
        $resource->refresh($result);

        return $resource;
    }

    /**
     * Updates the resource with the given parameters in an associative array.
     *
     * @param  string $url
     * @param  array $params
     *
     * @throws Exception|OmiseException
     */
    protected function g_update($url, $params)
    {
        $result = $this->execute($url, self::REQUEST_PATCH, $this->getResourceKey(), $params);
        $this->refresh($result);
    }

    /**
     * Makes a request and returns a decoded JSON data as an associative array.
     *
     * @param  string $url
     * @param  string $requestMethod
     * @param  array $params
     *
     * @throws OmiseException
     *
     * @return array
     */
    protected function execute($url, $requestMethod, $key, $params = null)
    {
        // If this class is execute by phpunit > get test mode.
        if (preg_match('/phpunit/', $_SERVER['SCRIPT_NAME'])) {
            $result = $this->_executeTest($url, $requestMethod, $key, $params);
        } else {
            $result = $this->_executeCurl($url, $requestMethod, $key, $params);
        }

        // Decode the JSON response as an associative array.
        $array = json_decode($result, true);

        // If response is invalid or not a JSON.
        if (count($array) === 0 || !isset($array['object'])) {
            throw new Exception('Unknown error. (Bad Response)');
        }

        // If response is an error object.
        if ($array['object'] === 'error') {
            throw OmiseException::getInstance($array);
        }

        return $array;
    }

    /**
     * @param  string $url
     * @param  string $requestMethod
     * @param  array $params
     *
     * @throws OmiseException
     *
     * @return string
     */
    private function _executeTest($url, $requestMethod, $key, $params = null)
    {
        // Remove Http, Https protocal from $url (string).
        $request_url = preg_replace('#^(http|https)://#', '', $url);

        // Remove slash if it had in last letter.
        $request_url = rtrim($request_url, '/');

        // Finally.
        $request_url = dirname(__FILE__) . '/../../../tests/fixtures/' . $request_url . '-' . strtolower($requestMethod) . '.json';

        // Make a request from Curl if json file was not exists.
        if (!file_exists($request_url)) {
            // Get a directory that's file should contain.
            $request_dir = explode('/', $request_url);
            unset($request_dir[count($request_dir) - 1]);
            $request_dir = implode('/', $request_dir);

            // Create directory if it not exists.
            if (!file_exists($request_dir)) {
                mkdir($request_dir, 0777, true);
            }

            $result = $this->_executeCurl($url, $requestMethod, $key, $params);

            $f = fopen($request_url, 'w');
            if ($f) {
                fwrite($f, $result);

                fclose($f);
            }
        } else { // Or get response from json file.
            $result = file_get_contents($request_url);
        }

        return $result;
    }

    /**
     * @param  string $url
     * @param  string $requestMethod
     * @param  array $params
     *
     * @throws OmiseException
     *
     * @return string
     */
    private function _executeCurl($url, $requestMethod, $key, $params = null)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, $this->genOptions($requestMethod, $key . ':', $params));

        // Make a request or thrown an exception.
        if (($result = curl_exec($ch)) === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception($error);
        }

        // Close.
        curl_close($ch);

        return $result;
    }

    /**
     * Creates an option for php-curl from the given request method and parameters in an associative array.
     *
     * @param  string $requestMethod
     * @param  array $params
     *
     * @return array
     */
    private function genOptions($requestMethod, $userpwd, $params)
    {
        $user_agent = "OmisePHP/" . OMISE_PHP_LIB_VERSION;
        $omise_api_version = defined('OMISE_API_VERSION') ? OMISE_API_VERSION : null;

        $options = array(
            // Set the HTTP version to 1.1.
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            // Set the request method.
            CURLOPT_CUSTOMREQUEST => $requestMethod,
            // Make php-curl returns the data as string.
            CURLOPT_RETURNTRANSFER => true,
            // Do not include the header in the output.
            CURLOPT_HEADER => false,
            // Track the header request string and set the referer on redirect.
            CURLINFO_HEADER_OUT => true,
            CURLOPT_AUTOREFERER => true,
            // Make HTTP error code above 400 an error.
            // CURLOPT_FAILONERROR => true,
            // Time before the request is aborted.
            CURLOPT_TIMEOUT => $this->OMISE_TIMEOUT,
            // Time before the request is aborted when attempting to connect.
            CURLOPT_CONNECTTIMEOUT => $this->OMISE_CONNECTTIMEOUT,
            // Authentication.
            CURLOPT_USERPWD => $userpwd,
            // CA bundle.
            CURLOPT_CAINFO => dirname(__FILE__) . '/../../../data/ca_certificates.pem'
        );

        // Config Omise API Version
        if ($omise_api_version) {
            $options += array(CURLOPT_HTTPHEADER => array("Omise-Version: " . $omise_api_version));

            $user_agent .= ' OmiseAPI/' . $omise_api_version;
        }

        // Config UserAgent
        if (defined('OMISE_USER_AGENT_SUFFIX')) {
            $options += array(CURLOPT_USERAGENT => $user_agent . " " . OMISE_USER_AGENT_SUFFIX);
        } else {
            $options += array(CURLOPT_USERAGENT => $user_agent);
        }

        // Also merge POST parameters with the option.
        if (count($params) > 0) {
            $options += array(CURLOPT_POSTFIELDS => http_build_query($params));
        }

        return $options;
    }

    /**
     * Returns the secret key.
     *
     * @return string
     */
    protected function getResourceKey()
    {
        return $this->_secretkey;
    }

    /**
     * Destroys the resource.
     *
     * @param  string $url
     *
     * @throws Exception|OmiseException
     *
     * @return OmiseApiResource
     */
    protected function g_destroy($url)
    {
        $result = $this->execute($url, self::REQUEST_DELETE, $this->getResourceKey());
        $this->refresh($result, true);
    }

    /**
     * Reloads the resource with latest data.
     *
     * @param  string $url
     *
     * @throws Exception|OmiseException
     */
    protected function g_reload($url)
    {
        $result = $this->execute($url, self::REQUEST_GET, $this->getResourceKey());
        $this->refresh($result);
    }

    protected function execute_retrieve($url, $method, $isproxy, $proxyurl, $key)
    {

        $result = $this->_executeCurl_Retrieve($url, $isproxy, $proxyurl, $key);
        // Decode the JSON response as an associative array.
        $array = json_decode($result, true);

        // If response is invalid or not a JSON.
        if (count($array) === 0 || !isset($array['object'])) {
            throw new Exception('Unknown error. (Bad Response)');
        }

        // If response is an error object.
        if ($array['object'] === 'error') {
            throw OmiseException::getInstance($array);
        }

        return $array;
    }

    private function _executeCurl_Retrieve($url, $isproxy, $proxyurl, $key)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            // Do not include the header in the output.
            CURLOPT_HEADER => false,
            // Track the header request string and set the referer on redirect.
            CURLINFO_HEADER_OUT => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => true,
            // Make HTTP error code above 400 an error.
            // CURLOPT_FAILONERROR => true,
            // Time before the request is aborted.
            CURLOPT_TIMEOUT => $this->OMISE_TIMEOUT,
            // Time before the request is aborted when attempting to connect.
            CURLOPT_CONNECTTIMEOUT => $this->OMISE_CONNECTTIMEOUT,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));

        if ($isproxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyurl);
        }
        curl_setopt($ch, CURLOPT_USERPWD, "$key:");

        // Make a request or thrown an exception.
        if (($result = curl_exec($ch)) === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception($error);
        }

        $json = json_decode($result, true);
        $json['curl_info'] = curl_getinfo($ch);
        $result = json_encode($json, true);

        // Close.
        curl_close($ch);

        return $result;
    }

    protected function executeDtac($params = null, $url, $isproxy, $proxyurl)
    {
        $result = $this->_executeCurlDtac($params, $url, $isproxy, $proxyurl);

        // Decode the JSON response as an associative array.
        $array = json_decode($result, true);
        // If response is invalid or not a JSON.
        if (count($array) === 0) {
            throw new Exception('Unknown error. (Bad Response)');
        }
        // If response is an error object.
        if ($array['status'] === 'error') {
            throw OmiseException::getInstance($array);
        }

        return $array;
    }

    private function _executeCurlDtac($params = null, $url, $isproxy = false, $proxyurl)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->genOptionsDtac($params));

        if ($isproxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyurl);
        }

        // Make a request or thrown an exception.
        if (($result = curl_exec($ch)) === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception($error);
        }

        $json = json_decode($result, true);
        $json['curl_info'] = curl_getinfo($ch);
        $result = json_encode($json, true);

        curl_close($ch);

        return $result;
    }

    private function genOptionsDtac($params)
    {
        $authorization = "Authorization: Bearer " . $params['token_dtac'];
        $options = array(
            // Set the HTTP version to 1.1.
            // CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            // Set the request method.
            CURLOPT_CUSTOMREQUEST => 'POST',
            // Make php-curl returns the data as string.
            CURLOPT_RETURNTRANSFER => true,
            // Do not include the header in the output.
            CURLOPT_HEADER => false,
            // Track the header request string and set the referer on redirect.
            CURLINFO_HEADER_OUT => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => true,
            // Make HTTP error code above 400 an error.
            // CURLOPT_FAILONERROR => true,
            // Time before the request is aborted.
            CURLOPT_TIMEOUT => $this->OMISE_TIMEOUT,
            // Time before the request is aborted when attempting to connect.
            CURLOPT_CONNECTTIMEOUT => $this->OMISE_CONNECTTIMEOUT,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', $authorization),
            // CA bundle.
            // CURLOPT_CAINFO         => dirname(__FILE__).'/../../../data/ca_certificates.pem'
        );

        // Also merge POST parameters with the option.
        if (count($params) > 0) {
            $datas = array(
                'pay_ref1' => $params['pay_ref1'],
                'pay_ref2' => $params['pay_ref2'],
                'name' => $params['name'],
                'pay_channel' => $params['pay_channel'],
                'card_token' => $params['card'],
                'channel' => $params['channel'],
                'amt' => $params['amt'],
                'lang' => $params['lang'],
                'pay_method' => $params['pay_method'],
                '3ds_status' => $params['3ds_status'],
                'callback_url' => $params['return_uri'],
                'bank_code' => $params['bank_code'],
                'terms' => $params['terms'],
                'description' => $params['description'],
            );
            $options += array(CURLOPT_POSTFIELDS => json_encode($datas));
        }
        return $options;
    }

    /**
     * Checks whether the resource has been destroyed.
     *
     * @return OmiseApiResource
     */
    protected function isDestroyed()
    {
        return $this['deleted'];
    }
}
