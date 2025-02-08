# QRIS Payment Adapter for FOSSBilling

QRIS Interactive payment gateway integration for FOSSBilling. This adapter allows your customers to pay using QRIS QR codes through various mobile banking and e-wallet apps in Indonesia.

## Requirements

- FOSSBilling >= 0.5.0
- PHP >= 8.1
- PHP cURL extension
- QRIS Interactive merchant account

## Installation

1. Upload the following files to your FOSSBilling installation:
   - `Payment_Adapter_QRIS.php` → `/library/Payment/Adapter/`

2. Change file name
   - `Payment_Adapter_QRIS.php` → `QRIS.php`

3. Enable and configure the payment gateway in FOSSBilling admin area:
   - Go to Configuration → Payment Gateways
   - Find "QRIS Interactive" in the list
   - Click "Edit" and enter your credentials

## Configuration

Required settings:
- **Merchant ID**: Your QRIS Interactive merchant ID
- **API Key**: Your QRIS Interactive API key
- **Sandbox Mode**: Enable for testing, disable for production

## Testing

To test the payment gateway:

1. Enable Sandbox Mode in the gateway configuration
2. Create a test invoice
3. Select QRIS as the payment method
4. Scan the QR code with a test account

## Support

If you encounter any issues:
1. Check the FOSSBilling error logs
2. Verify your QRIS Interactive credentials
3. Ensure your server meets the requirements
4. Create an issue in the GitHub repository

## License

This project is licensed under the Apache-2.0 License - see the LICENSE file for details.

