<?php
/*
 * The MIT License
 *
 * Copyright (c) 2019 Payout, s.r.o. (https://payout.one/)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Payout\Payment\Api;

use Exception;

/**
 * Class Client
 *
 * The Payout API Client PHP Library.
 * https://postman.payout.one/
 *
 * @package    Payout
 * @version    1.0.0
 * @copyright  2019 Payout, s.r.o.
 * @author     Neotrendy s. r. o.
 * @link       https://github.com/payout-one/payout_php
 */
class Client
{
    const string LIB_VER = '1.0.0';
    const string API_URL = 'https://app.payout.one/api/v1/';
    const string API_URL_SANDBOX = 'https://sandbox.payout.one/api/v1/';

    /**
     * @var array $config API client configuration
     * @var string $token Obtained API access token
     * @var Connection $connection Connection instance
     */
    private array $config;
    private mixed $token;
    private Connection $connection;

    /**
     * Construct the Payout API Client.
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct(array $config = array())
    {
        if (!function_exists('curl_init')) {
            throw new Exception('Payout needs the CURL PHP extension.');
        }
        if (!function_exists('json_decode')) {
            throw new Exception('Payout needs the JSON PHP extension.');
        }

        $this->config = array_merge(
            [
                'client_id' => '',
                'client_secret' => '',
                'sandbox' => false
            ],
            $config
        );
    }

    /**
     * Get a string containing the version of the library.
     *
     * @return string
     */
    public function getLibraryVersion(): string
    {
        return self::LIB_VER;
    }

    /**
     * Verify signature obtained in API response.
     *
     * @param array $message to be signed
     * @param string $signature from response
     *
     * @return bool
     */
    public function verifySignature($message, $signature)
    {
        $message[] = $this->config['client_secret'];

        if (strcmp($this->getSignature($message), $signature) == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Verify input data and create checkout and post signed data to API.
     *
     * @param array $data
     *
     * @return mixed
     * @throws Exception
     */
    public function createCheckout(array $data): mixed
    {
        $checkout = new Checkout();

        $prepared_checkout = $checkout->create($data);

        $nonce = $this->generateNonce();
        $prepared_checkout['nonce'] = $nonce;

        $message = array(
            $prepared_checkout['amount'],
            $prepared_checkout['currency'],
            $prepared_checkout['external_id'],
            $nonce,
            $this->config['client_secret']
        );

        $signature                      = $this->getSignature($message);
        $prepared_checkout['signature'] = $signature;

        $prepared_checkout = json_encode($prepared_checkout);

        $response = $this->connection()->post('checkouts', $prepared_checkout);

        if ( ! $this->verifySignature(
            array($response->amount, $response->currency, $response->external_id, $response->nonce),
            $response->signature
        )) {
            throw new Exception('Payout error: Invalid signature in API response.');
        }

        return $response;
    }

    /**
     * Verify input data and create checkout and post signed data to API.
     *
     * @param array $data
     *
     * @return mixed
     * @throws Exception
     */
    public function getCheckout(array $data): mixed
    {
        return $this->connection()->get('https://sandbox.payout.one/api/v1/', 'checkouts/479551');
    }

    /**
     * Get an instance of the HTTP connection object. Initializes
     * the connection if it is not already active.
     * Authorize connection and obtain access token.
     *
     * @return Connection
     * @throws Exception
     */
    private function connection(): Connection
    {
        if (!isset($this->connection)) {
            $api_url = ($this->config['sandbox']) ? self::API_URL_SANDBOX : self::API_URL;
            $this->connection = new Connection($api_url);
            $this->token = $this->connection->authenticate(
                'authorize',
                $this->config['client_id'],
                $this->config['client_secret']
            );
        }

        return $this->connection;
    }

    /**
     * Create signature as SHA256 hash of message.
     *
     * @param $message
     *
     * @return string
     */
    private function getSignature($message)
    {
        $message = implode('|', $message);

        return hash('sha256', pack('A*', $message));
    }

    /**
     * Generate nonce string. In cryptography, a nonce is an arbitrary number
     * that can be used just once in a cryptographic communication.
     * https://en.wikipedia.org/wiki/Cryptographic_nonce
     *
     * @return string
     */
    private function generateNonce(): string
    {
        // TODO use more secure nonce https://secure.php.net/manual/en/function.random-bytes.php
        $bytes = openssl_random_pseudo_bytes(32);
        return base64_encode($bytes);
    }
}
