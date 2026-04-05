<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZatcaProxyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public health check (no authentication required)
Route::get('/health', [ZatcaProxyController::class, 'healthCheck']);

// Protected API routes
Route::middleware(['api.key', 'ip.whitelist'])->group(function () {
    
    // ZATCA operations
    Route::post('/zatca/report', [ZatcaProxyController::class, 'reportInvoice']);
    Route::post('/zatca/qr-code', [ZatcaProxyController::class, 'generateQrCode']);
    
    // Request status and monitoring
    Route::get('/zatca/status/{requestId}', [ZatcaProxyController::class, 'getRequestStatus']);
    Route::get('/zatca/stats', [ZatcaProxyController::class, 'getStats']);
    
});

// Fallback route for undefined endpoints
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error' => 'Endpoint not found',
        'message' => 'The requested API endpoint does not exist',
        'available_endpoints' => [
            'GET /api/health' => 'Service health check',
            'POST /api/zatca/report' => 'Report invoice to ZATCA',
            'POST /api/zatca/qr-code' => 'Generate QR code for invoice',
            'GET /api/zatca/status/{requestId}' => 'Get request status',
            'GET /api/zatca/stats' => 'Get service statistics',
        ],
        'timestamp' => now()->toISOString(),
    ], 404);
});