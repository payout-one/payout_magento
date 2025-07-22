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
use Payout\Payment\Model\Payout;

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
    const string API_URL_PROD = 'https://app.payout.one/api/v1/';
    const string API_URL_SANDBOX = 'https://sandbox.payout.one/api/v1/';
    const string API_URL_TEST = 'https://test.payout.one/api/v1/';

    /**
     * @var array $config API client configuration
     * @var Connection $connection Connection instance
     */
    private array $config;
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
            throw new Exception(__('Payout needs the CURL PHP extension'));
        }
        if (!function_exists('json_decode')) {
            throw new Exception(__('Payout needs the JSON PHP extension'));
        }

        $this->config = array_merge(
            [
                'client_id' => '',
                'client_secret' => '',
                'sandbox' => false
            ],
            $config
        );

        if (empty($this->config['client_id'] || empty($this->config['client_secret']))) {
            throw new Exception(__('Client id or secret is not filled in payout plugin configuration'));
        }
    }

    /**
     * Verify signature obtained in API response.
     *
     * @param array $message to be signed
     * @param string $secret
     * @param string $signature from response
     *
     * @return bool
     */
    public static function verifySignature(array $message, string $secret, string $signature): bool
    {
        $message[] = $secret;

        if (strcmp(self::getSignature($message), $signature) == 0) {
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

        $signature = self::getSignature($message);
        $prepared_checkout['signature'] = $signature;

        $prepared_checkout = json_encode($prepared_checkout);

        $headers = [];
        if (isset($data['idempotency_key'])) {
            $headers['Idempotency-Key'] = $data['idempotency_key'];
        }

        $response = $this->connection()->post('checkouts', $prepared_checkout, $headers, Payout::CREATE_CHECKOUT_TIMEOUT);

        if (!self::verifySignature(
            array($response->amount, $response->currency, $response->external_id, $response->nonce),
            $this->config['client_secret'],
            $response->signature
        )) {
            throw new Exception(__('Payout error') . ': ' . __('Invalid signature in API response'));
        }

        return $response;
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
            $api_url = $this->config['internal_payout_test_override'] ? self::API_URL_TEST : ($this->config['sandbox'] ? self::API_URL_SANDBOX : self::API_URL_PROD);
            $this->connection = new Connection($api_url);
            $this->connection->authenticate(
                'authorize',
                $this->config['client_id'],
                $this->config['client_secret'],
                Payout::AUTHENTICATE_TIMEOUT,
            );
        }

        return $this->connection;
    }

    /**
     * Get checkout details from API.
     *
     * @param integer $checkout_id
     *
     * @return mixed
     * @throws Exception
     */
    public function retrieveCheckout(int $checkout_id): mixed
    {
        $url = 'checkouts/' . $checkout_id;
        $response = $this->connection()->get($url, Payout::RETRIEVE_CHECKOUT_TIMEOUT);

        if (
            !$this->verifySignature(
                [$response->amount, $response->currency, $response->external_id, $response->nonce],
                $this->config['client_secret'],
                $response->signature,
            )
        ) {
            throw new Exception(__('Payout error') . ': ' . __('Invalid signature in API response'));
        }

        return $response;
    }

    /**
     * Create signature as SHA256 hash of message.
     *
     * @param $message
     *
     * @return string
     */
    private static function getSignature($message): string
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
