<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ZatcaProxyService;
use App\Http\Requests\ZatcaReportRequest;
use App\Http\Requests\ZatcaQrCodeRequest;
use App\Models\ZatcaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ZatcaProxyController extends Controller
{
    public function __construct(
        private ZatcaProxyService $zatcaService
    ) {}

    /**
     * Report B2C Invoice to ZATCA
     */
    public function reportInvoice(ZatcaReportRequest $request): JsonResponse
    {
        $requestId = Str::uuid();
        
        try {
            // Log incoming request
            $this->logRequest($requestId, 'report_invoice', $request->all());

            // Extract data from request
            $invoiceData = $request->validated();
            
            // Create ZATCA request record
            $zatcaRequest = ZatcaRequest::create([
                'request_id' => $requestId,
                'client_ip' => $request->ip(),
                'client_id' => $request->header('X-Client-ID'),
                'type' => 'report_invoice',
                'payload' => $invoiceData,
                'status' => 'processing',
            ]);

            // Process the invoice reporting
            $result = $this->zatcaService->reportB2CInvoice(
                $invoiceData['invoice'],
                $invoiceData['company'],
                $invoiceData['environment'] ?? 'simulation'
            );

            // Update request status
            $zatcaRequest->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'response' => $result,
                'zatca_uuid' => $result['zatca_uuid'] ?? null,
                'zatca_hash' => $result['zatca_hash'] ?? null,
                'processed_at' => now(),
            ]);

            // Log response
            $this->logResponse($requestId, $result);

            // Cache successful responses
            if ($result['success'] && config('app.cache_zatca_responses')) {
                $cacheKey = 'zatca_invoice_' . ($result['zatca_uuid'] ?? $requestId);
                Cache::put($cacheKey, $result, config('app.cache_ttl', 3600));
            }

            return response()->json([
                'success' => $result['success'],
                'request_id' => $requestId,
                'data' => $result,
                'timestamp' => now()->toISOString(),
            ], $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            // Update request status on error
            if (isset($zatcaRequest)) {
                $zatcaRequest->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            }

            Log::error('ZATCA Proxy Error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => 'Internal server error',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Generate QR Code for Invoice
     */
    public function generateQrCode(ZatcaQrCodeRequest $request): JsonResponse
    {
        $requestId = Str::uuid();
        
        try {
            // Log incoming request
            $this->logRequest($requestId, 'generate_qr', $request->all());

            $data = $request->validated();
            
            // Create ZATCA request record
            $zatcaRequest = ZatcaRequest::create([
                'request_id' => $requestId,
                'client_ip' => $request->ip(),
                'client_id' => $request->header('X-Client-ID'),
                'type' => 'generate_qr',
                'payload' => $data,
                'status' => 'processing',
            ]);

            // Generate QR code
            $qrCode = $this->zatcaService->generateQrCode(
                $data['invoice'],
                $data['company']
            );

            $result = [
                'success' => true,
                'qr_code' => $qrCode,
            ];

            // Update request status
            $zatcaRequest->update([
                'status' => 'completed',
                'response' => $result,
                'processed_at' => now(),
            ]);

            // Log response
            $this->logResponse($requestId, $result);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => $result,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            // Update request status on error
            if (isset($zatcaRequest)) {
                $zatcaRequest->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            }

            Log::error('ZATCA QR Code Error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => 'QR code generation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to generate QR code',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get request status
     */
    public function getRequestStatus(Request $request, string $requestId): JsonResponse
    {
        $zatcaRequest = ZatcaRequest::where('request_id', $requestId)->first();

        if (!$zatcaRequest) {
            return response()->json([
                'success' => false,
                'error' => 'Request not found',
                'timestamp' => now()->toISOString(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'request_id' => $requestId,
            'status' => $zatcaRequest->status,
            'type' => $zatcaRequest->type,
            'created_at' => $zatcaRequest->created_at->toISOString(),
            'processed_at' => $zatcaRequest->processed_at?->toISOString(),
            'response' => $zatcaRequest->response,
            'error_message' => $zatcaRequest->error_message,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'service' => 'ZATCA Proxy Service',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'server_location' => 'Saudi Arabia',
            'zatca_endpoints' => [
                'developer' => config('zatca.endpoints.developer'),
                'simulation' => config('zatca.endpoints.simulation'),
                'production' => config('zatca.endpoints.production'),
            ],
        ]);
    }

    /**
     * Get service statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $stats = [
            'total_requests' => ZatcaRequest::count(),
            'successful_requests' => ZatcaRequest::where('status', 'completed')->count(),
            'failed_requests' => ZatcaRequest::where('status', 'failed')->count(),
            'error_requests' => ZatcaRequest::where('status', 'error')->count(),
            'pending_requests' => ZatcaRequest::where('status', 'processing')->count(),
        ];

        // Add daily stats if requested
        if ($request->get('include_daily')) {
            $stats['daily_stats'] = ZatcaRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date', 'status')
                ->orderBy('date', 'desc')
                ->get()
                ->groupBy('date');
        }

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log incoming request
     */
    private function logRequest(string $requestId, string $type, array $data): void
    {
        if (config('app.enable_request_logging')) {
            Log::info('ZATCA Proxy Request', [
                'request_id' => $requestId,
                'type' => $type,
                'client_ip' => request()->ip(),
                'client_id' => request()->header('X-Client-ID'),
                'payload_size' => strlen(json_encode($data)),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Log response
     */
    private function logResponse(string $requestId, array $response): void
    {
        if (config('app.enable_response_logging')) {
            Log::info('ZATCA Proxy Response', [
                'request_id' => $requestId,
                'success' => $response['success'] ?? false,
                'response_size' => strlen(json_encode($response)),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }
}