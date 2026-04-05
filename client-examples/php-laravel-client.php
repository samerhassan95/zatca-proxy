<?php

/**
 * ZATCA Proxy Client for Laravel Projects
 * 
 * استخدم هذا الكلاس في مشاريعك المصرية للتواصل مع خدمة ZATCA Proxy
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZatcaProxyClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $clientId;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.zatca_proxy.url');
        $this->apiKey = config('services.zatca_proxy.api_key');
        $this->clientId = config('services.zatca_proxy.client_id', config('app.name'));
        $this->timeout = config('services.zatca_proxy.timeout', 60);
    }

    /**
     * تقرير فاتورة إلى هيئة الزكاة
     */
    public function reportInvoice(
        array $invoiceData, 
        array $companyData, 
        string $environment = 'simulation'
    ): array {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/api/zatca/report', [
                    'invoice' => $invoiceData,
                    'company' => $companyData,
                    'environment' => $environment,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('ZATCA Invoice Reported Successfully', [
                    'request_id' => $result['request_id'] ?? null,
                    'zatca_uuid' => $result['data']['zatca_uuid'] ?? null,
                    'invoice_number' => $invoiceData['invoice_number'] ?? null,
                ]);

                return $result;
            }

            $error = $response->json();
            Log::error('ZATCA Report Failed', [
                'status' => $response->status(),
                'error' => $error,
                'invoice_number' => $invoiceData['invoice_number'] ?? null,
            ]);

            throw new Exception('ZATCA Report Failed: ' . ($error['message'] ?? $response->body()));

        } catch (Exception $e) {
            Log::error('ZATCA Proxy Connection Error', [
                'error' => $e->getMessage(),
                'invoice_number' => $invoiceData['invoice_number'] ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * توليد QR Code للفاتورة
     */
    public function generateQrCode(array $invoiceData, array $companyData): string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/api/zatca/qr-code', [
                    'invoice' => $invoiceData,
                    'company' => $companyData,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return $result['data']['qr_code'];
            }

            $error = $response->json();
            throw new Exception('QR Code Generation Failed: ' . ($error['message'] ?? $response->body()));

        } catch (Exception $e) {
            Log::error('ZATCA QR Code Error', [
                'error' => $e->getMessage(),
                'invoice_number' => $invoiceData['invoice_number'] ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * فحص حالة الطلب
     */
    public function getRequestStatus(string $requestId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get($this->baseUrl . '/api/zatca/status/' . $requestId);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get request status: ' . $response->body());

        } catch (Exception $e) {
            Log::error('ZATCA Status Check Error', [
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);
            throw $e;
        }
    }

    /**
     * فحص صحة الخدمة
     */
    public function healthCheck(): array
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/api/health');
            return $response->json();
        } catch (Exception $e) {
            throw new Exception('ZATCA Proxy Service is not available: ' . $e->getMessage());
        }
    }

    /**
     * الحصول على إحصائيات الخدمة
     */
    public function getStats(bool $includeDailyStats = false): array
    {
        try {
            $url = $this->baseUrl . '/api/zatca/stats';
            if ($includeDailyStats) {
                $url .= '?include_daily=true';
            }

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get stats: ' . $response->body());

        } catch (Exception $e) {
            Log::error('ZATCA Stats Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * تحضير بيانات الفاتورة من نموذج Order
     */
    public function prepareInvoiceData($order): array
    {
        return [
            'invoice_number' => $order->order_number,
            'created_at' => $order->created_at->toISOString(),
            'sub_total' => (float) $order->sub_total,
            'total_tax_amount' => (float) $order->total_tax_amount,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method ?? 'cash',
            'zatca_uuid' => $order->zatca_uuid,
            'zatca_invoice_counter' => $order->zatca_invoice_counter,
            'zatca_previous_hash' => $this->getLastZatcaHash($order->restaurant_id),
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->menuItem->item_name ?? $item->name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'amount' => (float) $item->amount,
                    'tax_amount' => (float) ($item->tax_amount ?? 0),
                    'tax_percentage' => 15.00,
                ];
            })->toArray(),
        ];
    }

    /**
     * تحضير بيانات الشركة من نموذج Restaurant
     */
    public function prepareCompanyData($restaurant): array
    {
        return [
            'company_name' => $restaurant->restaurant_name,
            'vat_number' => $restaurant->vat_number,
            'commercial_registration' => $restaurant->commercial_registration,
            'address' => $restaurant->address,
            'city' => $restaurant->city,
            'zip_code' => $restaurant->zip_code,
            'zatca_certificate' => $restaurant->zatca_certificate,
            'zatca_private_key' => $restaurant->zatca_private_key,
            'zatca_secret' => $restaurant->zatca_secret,
        ];
    }

    /**
     * معالجة نتيجة تقرير ZATCA وحفظها في قاعدة البيانات
     */
    public function processZatcaResult($order, array $result): void
    {
        if ($result['success'] && isset($result['data'])) {
            $data = $result['data'];
            
            $order->update([
                'zatca_uuid' => $data['zatca_uuid'] ?? null,
                'zatca_hash' => $data['zatca_hash'] ?? null,
                'zatca_xml' => $data['zatca_xml'] ?? null,
                'zatca_qr_code' => $data['zatca_qr_code'] ?? null,
                'zatca_status' => $data['zatca_status'] ?? 'failed',
                'zatca_reported_at' => $data['zatca_reported_at'] ? now() : null,
                'zatca_errors' => null,
                'zatca_invoice_counter' => $data['zatca_invoice_counter'] ?? null,
            ]);

            Log::info('ZATCA Data Saved Successfully', [
                'order_id' => $order->id,
                'zatca_uuid' => $data['zatca_uuid'] ?? null,
            ]);
        } else {
            $order->update([
                'zatca_status' => 'failed',
                'zatca_errors' => json_encode($result['data']['zatca_errors'] ?? $result),
            ]);

            Log::error('ZATCA Report Failed', [
                'order_id' => $order->id,
                'errors' => $result['data']['zatca_errors'] ?? $result,
            ]);
        }
    }

    /**
     * الحصول على آخر hash من ZATCA للمطعم
     */
    private function getLastZatcaHash(int $restaurantId): ?string
    {
        // استبدل هذا بالكود المناسب لمشروعك
        $lastOrder = \App\Models\Order::where('restaurant_id', $restaurantId)
            ->where('zatca_status', 'reported')
            ->whereNotNull('zatca_hash')
            ->orderBy('zatca_invoice_counter', 'desc')
            ->first();

        return $lastOrder?->zatca_hash;
    }

    /**
     * الحصول على headers المطلوبة للطلبات
     */
    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-Key' => $this->apiKey,
            'X-Client-ID' => $this->clientId,
        ];
    }
}

/**
 * مثال على الاستخدام في Controller
 */
class OrderController extends Controller
{
    public function reportToZatca(Order $order)
    {
        try {
            $zatcaClient = new ZatcaProxyClient();
            
            // تحضير البيانات
            $invoiceData = $zatcaClient->prepareInvoiceData($order);
            $companyData = $zatcaClient->prepareCompanyData($order->restaurant);
            
            // تقرير الفاتورة
            $result = $zatcaClient->reportInvoice(
                $invoiceData, 
                $companyData, 
                config('zatca.environment', 'simulation')
            );
            
            // معالجة النتيجة
            $zatcaClient->processZatcaResult($order, $result);
            
            return response()->json([
                'success' => true,
                'message' => 'تم تقرير الفاتورة بنجاح',
                'zatca_uuid' => $result['data']['zatca_uuid'] ?? null,
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في تقرير الفاتورة: ' . $e->getMessage(),
            ], 500);
        }
    }
}

/**
 * إعداد الـ Service Provider
 * أضف هذا في config/services.php
 */
/*
'zatca_proxy' => [
    'url' => env('ZATCA_PROXY_URL', 'https://your-zatca-proxy.sa'),
    'api_key' => env('ZATCA_PROXY_API_KEY'),
    'client_id' => env('ZATCA_PROXY_CLIENT_ID', config('app.name')),
    'timeout' => env('ZATCA_PROXY_TIMEOUT', 60),
],
*/

/**
 * متغيرات البيئة المطلوبة في .env
 */
/*
ZATCA_PROXY_URL=https://your-zatca-proxy.sa
ZATCA_PROXY_API_KEY=your-api-key-here
ZATCA_PROXY_CLIENT_ID=your-project-name
ZATCA_PROXY_TIMEOUT=60
*/