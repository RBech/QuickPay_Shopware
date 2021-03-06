<?php

namespace QuickPayPayment\Components;

use Exception;
use Shopware\Components\Random;
use function Shopware;

class QuickPayService
{
    private $baseUrl = 'https://api.quickpay.net';

    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';
    const METHOD_PATCH = 'PATCH';

    /**
     * Create payment
     *
     * @param $orderId
     * @param $parameters
     * @return mixed
     */
    public function createPayment($orderId, $parameters)
    {
        $parameters['order_id'] = $orderId;
        
        //Create payment
        $payment = $this->request(self::METHOD_POST, '/payments', $parameters);

        return $payment;
    }

    /**
     * Create payment
     *
     * @param $paymentId
     * @param $parameters
     * @return mixed
     */
    public function updatePayment($paymentId, $parameters)
    {
        $resource = sprintf('/payments/%s', $paymentId);
        
        //Update payment
        $payment = $this->request(self::METHOD_PATCH, $resource, $parameters);

        return $payment;
    }
    
    /**
     * Get payment information
     * 
     * @param $paymentId
     * @return mixed
     */
    public function getPayment($paymentId)
    {
        $resource = sprintf('/payments/%s', $paymentId);
        
        //Get payment
        $payment = $this->request(self::METHOD_GET, $resource);

        return $payment;        
    }
    
    /**
     * Create payment link
     *
     * @param $id
     * @param $amount
     * @param $email
     * @param $continueUrl
     * @param $cancelUrl
     * @param $callbackUrl
     *
     * @return string
     */
    public function createPaymentLink($id, $amount, $email, $continueUrl, $cancelUrl, $callbackUrl)
    {
        $resource = sprintf('/payments/%s/link', $id);
        $paymentLink = $this->request(self::METHOD_PUT, $resource, [
            'amount'             => $amount * 100, //Convert to cents
            'continueurl'        => $continueUrl,
            'cancelurl'          => $cancelUrl,
            'callbackurl'        => $callbackUrl,
            'customer_email'     => $email,
            'language'           => $this->getLanguageCode()
        ]);

        return $paymentLink->url;
    }

    /**
     * Perform API request
     *
     * @param string $method
     * @param $resource
     * @param array $params
     * @param bool $synchronized
     */
    private function request($method = self::METHOD_POST, $resource, $params = [], $synchronized = false)
    {
        $ch = curl_init();

        $url = $this->baseUrl . $resource;

        //Set CURL options
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => http_build_query($params, '', '&'),
        ];

        curl_setopt_array($ch, $options);

        //Get response
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        //Validate reponsecode
        if (! in_array($responseCode, [200, 201, 202])) {
            throw new Exception('Invalid gateway response ' . $result);
        }

        $response = json_decode($result);

        //Check for JSON errors
        if (! $response || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new Exception('Invalid json response');
        }

        return $response;
    }

    /**
     * Get CURL headers
     *
     * @return array
     */
    private function getHeaders()
    {
        return [
            'Authorization: Basic ' . base64_encode(':' . $this->getApiKey()),
            'Accept-Version: v10',
            'Accept: application/json'
        ];
    }

    /**
     * Get API key from config
     *
     * @return mixed
     */
    private function getApiKey()
    {
        return Shopware()->Config()->getByNamespace('QuickPayPayment', 'public_key');
    }

    /**
     * Get language code
     *
     * @return string
     */
    private function getLanguageCode()
    {
        $locale = Shopware()->Shop()->getLocale()->getLocale();

        return substr($locale, 0, 2);
    }
    
    /**
     * Creates a unique order id
     * 
     * @return string
     */
    public function createOrderId()
    {
        return Random::getAlphanumericString(20);
    }
}
