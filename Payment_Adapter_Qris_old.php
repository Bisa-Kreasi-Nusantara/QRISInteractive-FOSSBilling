<?php
/**
 * QRIS Payment Adapter
 *
 * @copyright Copyright (c) 2024
 * @license   Apache-2.0
 */

class Qris extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
{
    protected $_config = [];

    public function __construct($config)
    {
        if(!is_array($config)) {
            throw new Payment_Exception('Payment gateway "QRIS" is not configured properly. Please update configuration parameter at "Configuration -> Payments".');
        }
        $this->_config = $config;
    }

    public static function getConfig()
    {
        return [
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  false,
            'description'                  =>  'Pay with QRIS. Scan QR code using your mobile banking or e-wallet app.',
            'form'  => [
                'merchant_id' => ['text', [
                    'label' => 'Merchant ID',
                    'description' => 'Your QRIS Interactive merchant ID',
                    'required' => true,
                ]],
                'api_key' => ['password', [
                    'label' => 'API Key',
                    'description' => 'Your QRIS Interactive API key',
                    'required' => true,
                ]],
                'sandbox' => ['radio', [
                    'label' => 'Sandbox Mode',
                    'multiOptions' => [
                        '1' => 'Yes',
                        '0' => 'No',
                    ],
                ]],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);

        if(!$invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        $params = [
            'merchant_id' => $this->_config['merchant_id'],
            'api_key' => $this->_config['api_key'],
            'amount' => $invoice['total'],
            'merchant_trx_id' => $invoice['id'],
            'customer_name' => $invoice['client']['first_name'] . ' ' . $invoice['client']['last_name'],
            'customer_email' => $invoice['client']['email']
        ];

        try {
            $response = $this->_makeRequest('show_qris.php', $params);

            if (!isset($response['qris_url'])) {
                throw new Payment_Exception('Failed to generate QRIS code');
            }

            return $this->_generateQRHtml($response['qris_url'], $invoice_id);
        } catch (Exception $e) {
            error_log('QRIS Payment Error: ' . $e->getMessage());
            throw new Payment_Exception($e->getMessage());
        }
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $invoice = $api_admin->invoice_get(['id' => $id]);

        if(!$invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        $params = [
            'merchant_id' => $this->_config['merchant_id'],
            'api_key' => $this->_config['api_key'],
            'merchant_trx_id' => $invoice['id']
        ];

        try {
            $response = $this->_makeRequest('checkpaid_qris.php', $params);

            if (isset($response['status']) && $response['status'] === 'paid') {
                $api_admin->invoice_transaction_process([
                    'id' => $id,
                    'gateway_id' => $gateway_id,
                    'amount' => $invoice['total'],
                    'currency' => $invoice['currency'],
                    'type' => 'payment',
                    'txn_id' => $response['transaction_id'] ?? null,
                    'status' => 'complete',
                ]);
                return true;
            }
        } catch (Exception $e) {
            error_log('QRIS Payment Status Check Error: ' . $e->getMessage());
        }

        return false;
    }

    protected function _makeRequest($endpoint, $params)
    {
        $baseUrl = 'https://qris.interactive.co.id/restapi/qris/';
        $url = $baseUrl . $endpoint . '?' . http_build_query($params);
        
        if(!function_exists('curl_init')) {
            throw new Payment_Exception('CURL extension is not enabled');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Payment_Exception('CURL Error: ' . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Payment_Exception('QRIS API Error: Received HTTP code ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Payment_Exception('Invalid JSON response from QRIS Interactive');
        }
        
        return $result;
    }

    protected function _generateQRHtml($qrisUrl, $invoiceId)
    {
        return <<<HTML
        <div class="well" style="text-align: center; margin: 20px;">
            <h4>QRIS Payment</h4>
            <div style="margin: 20px;">
                <img src="{$qrisUrl}" alt="QRIS QR Code" style="max-width: 300px;">
            </div>
            <div style="margin: 20px;">
                <p>Please scan this QR code using your mobile banking or e-wallet app.</p>
                <p>The page will automatically refresh when payment is completed.</p>
            </div>
            <script>
                function checkQrisPayment() {
                    fetch(window.location.href, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            check_payment: true,
                            invoice_id: "{$invoiceId}"
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.status === "paid") {
                            window.location.reload();
                        }
                    });
                }
                setInterval(checkQrisPayment, 5000);
            </script>
        </div>
        HTML;
    }
}

