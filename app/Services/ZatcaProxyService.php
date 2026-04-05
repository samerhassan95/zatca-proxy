<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;
use Salla\ZATCA\Tags\Timestamp;
use Salla\ZATCA\Tags\TotalAmount;
use Salla\ZATCA\Tags\TaxAmount;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Helpers\Certificate;
use Salla\ZATCA\Models\InvoiceSign;

class ZatcaProxyService
{
    /**
     * Report B2C Invoice to ZATCA
     */
    public function reportB2CInvoice(array $invoiceData, array $companyData, string $environment = 'simulation'): array
    {
        try {
            // Validate required data
            $this->validateInvoiceData($invoiceData);
            $this->validateCompanyData($companyData);

            // Generate UUID and counters
            $uuid = $invoiceData['zatca_uuid'] ?? (string) Str::uuid();
            $icv = $invoiceData['zatca_invoice_counter'] ?? 1;
            $pih = $invoiceData['zatca_previous_hash'] ?? $this->getDefaultPIH();

            // Generate base XML
            $baseXml = $this->generateBaseXml($invoiceData, $companyData, $uuid, $icv, $pih);

            // Sign the XML
            $certHelper = new Certificate($companyData['zatca_certificate'], $companyData['zatca_private_key']);
            if (!empty($companyData['zatca_secret'])) {
                $certHelper->setSecretKey($companyData['zatca_secret']);
            }

            $signer = new InvoiceSign($baseXml, $certHelper);
            $signedInvoice = $signer->sign();

            $signedXml = $signedInvoice->getSignedXml();
            $invoiceHash = $signedInvoice->getInvoiceHash();
            $qrCode = $signedInvoice->getQrCode();

            // Send to ZATCA API
            $apiResult = $this->sendToZatcaApi($uuid, $signedXml, $invoiceHash, $certHelper, $environment);

            $result = [
                'success' => $apiResult['success'],
                'zatca_uuid' => $uuid,
                'zatca_hash' => $invoiceHash,
                'zatca_xml' => $signedXml,
                'zatca_qr_code' => $qrCode,
                'zatca_invoice_counter' => $icv,
                'zatca_status' => $apiResult['success'] ? 'reported' : 'failed',
                'zatca_reported_at' => $apiResult['success'] ? now()->toISOString() : null,
                'zatca_errors' => $apiResult['errors'] ?? null,
                'api_response' => $apiResult['response'] ?? null,
            ];

            // Log the operation
            $this->logZatcaOperation('report_invoice', $result);

            return $result;

        } catch (Exception $e) {
            Log::error('ZATCA Service Error', [
                'operation' => 'report_invoice',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zatca_status' => 'failed',
                'zatca_errors' => json_encode(['exception' => $e->getMessage()]),
            ];
        }
    }

    /**
     * Generate QR Code for invoice
     */
    public function generateQrCode(array $invoiceData, array $companyData): string
    {
        try {
            $seller = new Seller($companyData['company_name']);
            $taxNumber = new TaxNumber($companyData['vat_number']);
            $invoiceDate = new Timestamp(Carbon::parse($invoiceData['created_at']));
            $invoiceTotal = new TotalAmount($invoiceData['total']);
            $taxAmount = new TaxAmount($invoiceData['total_tax_amount']);

            return GenerateQrCode::render($seller, $taxNumber, $invoiceDate, $invoiceTotal, $taxAmount);

        } catch (Exception $e) {
            Log::error('QR Code Generation Error', [
                'error' => $e->getMessage(),
                'invoice_data' => $invoiceData,
            ]);
            throw $e;
        }
    }

    /**
     * Validate invoice data
     */
    private function validateInvoiceData(array $data): void
    {
        $required = ['invoice_number', 'created_at', 'sub_total', 'total_tax_amount', 'total', 'items'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required invoice field: {$field}");
            }
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception("Invoice must have at least one item");
        }

        foreach ($data['items'] as $index => $item) {
            $itemRequired = ['name', 'quantity', 'price', 'amount'];
            foreach ($itemRequired as $field) {
                if (!isset($item[$field])) {
                    throw new Exception("Missing required item field '{$field}' in item {$index}");
                }
            }
        }
    }

    /**
     * Validate company data
     */
    private function validateCompanyData(array $data): void
    {
        $required = ['company_name', 'vat_number', 'zatca_certificate', 'zatca_private_key'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required company field: {$field}");
            }
        }
    }

    /**
     * Generate base XML for invoice
     */
    private function generateBaseXml(array $invoiceData, array $companyData, string $uuid, int $icv, string $pih): string
    {
        $issueDate = Carbon::parse($invoiceData['created_at'])->format('Y-m-d');
        $issueTime = Carbon::parse($invoiceData['created_at'])->format('H:i:s');
        $invoiceNumber = $invoiceData['invoice_number'];
        $taxPercent = 15.00; // Default VAT rate
        $currency = 'SAR';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" 
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" 
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>' . htmlspecialchars($invoiceNumber) . '</cbc:ID>
    <cbc:UUID>' . $uuid . '</cbc:UUID>
    <cbc:IssueDate>' . $issueDate . '</cbc:IssueDate>
    <cbc:IssueTime>' . $issueTime . '</cbc:IssueTime>
    <cbc:InvoiceTypeCode name="0200000">388</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>' . $currency . '</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>' . $currency . '</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference>
        <cbc:ID>ICV</cbc:ID>
        <cbc:UUID>' . $icv . '</cbc:UUID>
    </cac:AdditionalDocumentReference>
    <cac:AdditionalDocumentReference>
        <cbc:ID>PIH</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">' . $pih . '</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="CRN">' . htmlspecialchars($companyData['commercial_registration'] ?? '1010123457') . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyPostalAddress>
                <cbc:StreetName>' . htmlspecialchars($companyData['address'] ?? 'Main Street') . '</cbc:StreetName>
                <cbc:CityName>' . htmlspecialchars($companyData['city'] ?? 'Riyadh') . '</cbc:CityName>
                <cbc:PostalZone>' . htmlspecialchars($companyData['zip_code'] ?? '12345') . '</cbc:PostalZone>
                <cac:Country>
                    <cbc:IdentificationCode>SA</cbc:IdentificationCode>
                </cac:Country>
            </cac:PartyPostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>' . htmlspecialchars($companyData['vat_number']) . '</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>' . htmlspecialchars($companyData['company_name']) . '</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyPostalAddress>
                <cac:Country>
                    <cbc:IdentificationCode>SA</cbc:IdentificationCode>
                </cac:Country>
            </cac:PartyPostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:Delivery>
        <cbc:ActualDeliveryDate>' . $issueDate . '</cbc:ActualDeliveryDate>
    </cac:Delivery>
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>' . $this->getPaymentMeansCode($invoiceData['payment_method'] ?? 'cash') . '</cbc:PaymentMeansCode>
    </cac:PaymentMeans>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="' . $currency . '">' . number_format($invoiceData['total_tax_amount'], 2, '.', '') . '</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="' . $currency . '">' . number_format($invoiceData['sub_total'], 2, '.', '') . '</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="' . $currency . '">' . number_format($invoiceData['total_tax_amount'], 2, '.', '') . '</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>' . number_format($taxPercent, 2, '.', '') . '</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="' . $currency . '">' . number_format($invoiceData['sub_total'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="' . $currency . '">' . number_format($invoiceData['sub_total'], 2, '.', '') . '</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="' . $currency . '">' . number_format($invoiceData['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID="' . $currency . '">0.00</cbc:AllowanceTotalAmount>
        <cbc:PayableAmount currencyID="' . $currency . '">' . number_format($invoiceData['total'], 2, '.', '') . '</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>';

        foreach ($invoiceData['items'] as $index => $item) {
            $xml .= '
    <cac:InvoiceLine>
        <cbc:ID>' . ($index + 1) . '</cbc:ID>
        <cbc:InvoicedQuantity unitCode="PCE">' . $item['quantity'] . '</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="' . $currency . '">' . number_format($item['amount'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>' . htmlspecialchars($item['name']) . '</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>' . number_format($taxPercent, 2, '.', '') . '</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="' . $currency . '">' . number_format($item['price'], 2, '.', '') . '</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>';
        }

        $xml .= '
</Invoice>';

        return $xml;
    }

    /**
     * Send signed XML to ZATCA API
     */
    private function sendToZatcaApi(string $uuid, string $signedXml, string $hash, Certificate $certHelper, string $environment): array
    {
        $endpoint = $this->getZatcaEndpoint($environment) . '/invoices/reporting/single';
        
        $payload = [
            'invoiceHash' => $hash,
            'uuid' => $uuid,
            'invoice' => base64_encode($signedXml),
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => $certHelper->getAuthorizationHeader(),
                'Accept-Version' => 'V2',
            ])->timeout(30)->post($endpoint, $payload);

            if (config('app.log_zatca_requests')) {
                Log::info('ZATCA API Request', [
                    'endpoint' => $endpoint,
                    'uuid' => $uuid,
                    'payload_size' => strlen(json_encode($payload)),
                ]);
            }

            if (config('app.log_zatca_responses')) {
                Log::info('ZATCA API Response', [
                    'uuid' => $uuid,
                    'status_code' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'success' => $response->successful(),
                ]);
            }

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
                'status_code' => $response->status(),
                'errors' => $response->successful() ? null : $response->body(),
            ];

        } catch (Exception $e) {
            Log::error('ZATCA API Exception', [
                'uuid' => $uuid,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'errors' => json_encode(['api_exception' => $e->getMessage()]),
                'response' => null,
                'status_code' => 0,
            ];
        }
    }

    /**
     * Get ZATCA endpoint based on environment
     */
    private function getZatcaEndpoint(string $environment): string
    {
        return match ($environment) {
            'developer' => config('zatca.endpoints.developer'),
            'simulation' => config('zatca.endpoints.simulation'),
            'production' => config('zatca.endpoints.production'),
            default => config('zatca.endpoints.simulation'),
        };
    }

    /**
     * Get payment means code
     */
    private function getPaymentMeansCode(string $paymentMethod): string
    {
        return match (strtolower($paymentMethod)) {
            'cash' => '10',
            'card', 'credit_card', 'visa', 'mastercard' => '48',
            'bank_transfer' => '42',
            default => '10',
        };
    }

    /**
     * Get default PIH value
     */
    private function getDefaultPIH(): string
    {
        return "NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==";
    }

    /**
     * Log ZATCA operation
     */
    private function logZatcaOperation(string $operation, array $result): void
    {
        Log::info('ZATCA Operation Completed', [
            'operation' => $operation,
            'success' => $result['success'],
            'zatca_uuid' => $result['zatca_uuid'] ?? null,
            'zatca_status' => $result['zatca_status'] ?? null,
            'timestamp' => now()->toISOString(),
        ]);
    }
}