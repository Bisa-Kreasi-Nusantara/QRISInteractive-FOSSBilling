<?php

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * QRIS Interactive Payment Adapter for FOSSBilling.
 */
class Payment_Adapter_QRIS extends Payment_AdapterAbstract
{
    private HttpClientInterface $httpClient;

    public function __construct(array $_config)
    {
        parent::__construct($_config);
        $this->httpClient = HttpClient::create();
    }

    public static function getConfig()
    {
        return [
            'label' => 'QRIS Interactive Payment Gateway',
            'description' => 'Process payments using QRIS Interactive Open API.',
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'parameters' => [
                'merchant_id' => [
                    'label' => 'Merchant ID',
                    'required' => true,
                    'description' => 'Your QRIS Interactive Merchant ID.',
                ],
                'api_key' => [
                    'label' => 'API Key',
                    'required' => true,
                    'description' => 'Your QRIS Interactive API Key.',
                ],
            ],
            'form' => [
                'api_key' => [
                    'text', [
                        'label' => 'API Key',
                        'description' => 'Your API key from QRIS Interactive',
                        'required' => true
                    ]
                ],
                'merchant_id' => [
                    'text', [
                        'label' => 'Merchant ID',
                        'description' => 'Your Merchant ID from QRIS Interactive',
                        'required' => true
                    ]
                ],
            ],
        ];
    }

    public function getType(): string
    {
        return self::TYPE_API;
    }

    public function getServiceUrl(): string
    {
        return 'https://qris.interactive.co.id/restapi/qris/';
    }

    /**
     * Creates a QRIS payment request.
     *
     * @param string $invoiceId Unique invoice identifier from your application.
     * @param float  $amount    The total transaction amount.
     *
     * @return array The response from the QRIS API.
     *
     * @throws Payment_Exception If the API request fails.
     */
    public function createPaymentRequest(string $invoiceId, float $amount): array
    {
        $endpoint = $this->getServiceUrl() . 'show_qris.php';
        $params = [
            'do' => 'create-invoice',
            'apikey' => $this->getParam('api_key'),
            'mID' => $this->getParam('merchant_id'),
            'cliTrxNumber' => $invoiceId,
            'cliTrxAmount' => (int) $amount,
            'useTip' => 'no',
        ];

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => $params,
            ]);

            $data = $response->toArray(false); // Ensure it does not throw an exception

            // Check if response is valid
            if (!is_array($data)) {
                throw new Payment_Exception('Invalid response from QRIS API. Expected an array, got: ' . gettype($data));
            }

            if (!isset($data['status'])) {
                throw new Payment_Exception('QRIS API response does not contain a status field.');
            }

            if ($data['status'] !== 'success') {
                throw new Payment_Exception('Failed to create QRIS invoice: ' . json_encode($data));
            }

            return $data['data'];
        } catch (\Exception $e) {
            throw new Payment_Exception('QRIS API error: ' . $e->getMessage());
        }
    }


    public function singlePayment(Payment_Invoice $invoice)
    {
        // Extract necessary details from the invoice object
        $invoiceId = $invoice->getId(); 
        $amount = $invoice->getTotalWithTax(); 
        $currency = $invoice->getCurrency();
        $clientEmail = $invoice->getBuyer()->getEmail();

        // Call the createPaymentRequest function to get the QR code URL
        try {
            $paymentData = $this->createPaymentRequest($invoiceId, $amount);
            
            return [
                'redirect' => $paymentData['qris_content'], // Adjust based on API response
            ];
        } catch (Exception $e) {
            throw new Payment_Exception('QRIS Payment failed: ' . $e->getMessage());
        }
    }


    /**
     * Checks the status of a QRIS payment with retry logic to avoid API key blocking.
     *
     * @param int    $invoiceId The QRIS invoice ID.
     * @param float  $amount    The transaction amount.
     * @param string $date      The transaction date in 'YYYY-mm-dd' format.
     *
     * @return array The response from the QRIS API.
     *
     * @throws Payment_Exception If the API request fails after maximum retries.
     */
    public function checkPaymentStatus(int $invoiceId, float $amount, string $date): array
    {
        $endpoint = $this->getServiceUrl() . 'checkpaid_qris.php';
        $params = [
            'do' => 'checkStatus',
            'apikey' => $this->getParam('api_key'),
            'mID' => $this->getParam('merchant_id'),
            'invid' => $invoiceId,
            'trxvalue' => (int) $amount,
            'trxdate' => $date,
        ];

        $maxRetries = 3; // If using direct calls, retry up to 3 times (15 sec each)
        $retryInterval = 15; // Wait 15 seconds between retries
        $cronRetries = 30; // If using a cron job, retry up to 30 times (1 per minute)

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'query' => $params,
                    'timeout' => 15, // Set timeout to 15 seconds
                ]);

                $data = $response->toArray();

                if ($data['status'] === 'success' && $data['data']['qris_status'] === 'PAID') {
                    return $data['data']; // Payment successful, return result
                }
                
                // If status is "failed", wait and retry
                if ($data['data']['qris_status'] === 'failed') {
                    if ($attempt < $maxRetries) {
                        sleep($retryInterval);
                    }
                }
            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    sleep($retryInterval);
                }
            }
        }

        // If still failed after 3 direct retries, return a response indicating further actions
        return [
            'status' => 'failed',
            'message' => 'Payment not confirmed after multiple attempts. Please upload proof of payment or contact customer support.',
            'support' => [
                'instructions' => 'If your payment was deducted but not confirmed, please provide a screenshot of the successful payment details.',
                'required_details' => [
                    'transaction_id' => 'Transaction Number from E-Wallet/Bank',
                    'payer_phone' => 'Your Phone Number',
                    'amount' => 'Amount Paid',
                    'date_time' => 'Date & Time of Payment',
                ],
                'customer_support' => [
                    'email' => 'support@bisakreasi.com', // Replace with actual support email
                    'working_hours' => 'Monday-Friday, 9 AM - 5 PM',
                ],
            ],
        ];
    }

}
