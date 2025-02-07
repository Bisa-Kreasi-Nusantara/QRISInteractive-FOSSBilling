<?php
namespace Box\Mod\QrisPayment;

class QrisPaymentGateway
{
    private $config;
    private $di;
    private $baseUrl = 'https://qris.interactive.co.id/restapi/qris/';

    public function __construct($config)
    {
        $this->config = $config;
        $this->di = $di;
    }

    public function getConfig()
    {
        return [
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  false,
            'description'                  =>  'Pay with QRIS Interactive. Scan QR code to complete payment.',
            'form'  => [
                'merchant_id' => ['text', [
                    'label' => 'Merchant ID',
                    'description' => 'Your QRIS Interactive merchant ID',
                ]],
                'api_key' => ['password', [
                    'label' => 'API Key',
                    'description' => 'Your QRIS Interactive API key',
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

    public function createInvoice($invoice)
    {
        $amount = number_format($invoice->getTotalWithTax(), 2, '.', '');
        $merchantId = $this->config['merchant_id'];
        $apiKey = $this->config['api_key'];
        
        $params = [
            'merchant_id' => $merchantId,
            'api_key' => $apiKey,
            'amount' => $amount,
            'merchant_trx_id' => $invoice->getId(),
            'customer_name' => $invoice->getBuyer()->getFullName(),
            'customer_email' => $invoice->getBuyer()->getEmail()
        ];

        $response = $this->makeRequest('show_qris.php', $params);
        
        if (!isset($response['qris_url'])) {
            throw new \Exception('Failed to generate QRIS code');
        }

        return [
            'status' => 'pending',
            'type' => 'html',
            'service_url' => $response['qris_url'],
            'title' => 'QRIS Payment'
        ];
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $invoice = $api_admin->invoice_get(['id' => $id]);
        $merchantId = $this->config['merchant_id'];
        $apiKey = $this->config['api_key'];

        $params = [
            'merchant_id' => $merchantId,
            'api_key' => $apiKey,
            'merchant_trx_id' => $invoice['id']
        ];

        $response = $this->makeRequest('checkpaid_qris.php', $params);

        if (isset($response['status']) && $response['status'] === 'paid') {
            $api_admin->invoice_transaction_process([
                'id' => $id,
                'gateway_id' => $gateway_id,
                'amount' => $invoice['total'],
                'currency' => $invoice['currency'],
                'type' => 'payment',
                'txn_id' => $response['transaction_id'],
                'status' => 'complete',
            ]);
            return true;
        }

        return false;
    }

    private function makeRequest($endpoint, $params)
    {
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new \Exception('CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from QRIS Interactive');
        }
        
        return $result;
    }
}

