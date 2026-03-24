<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\MercadoPagoWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Endpoints públicos (sin auth) para integración con agentes
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('test', function () {
        return response()->json([
            'status' => 'ok',
            'app' => 'CRM Mosley',
            'version' => '1.0',
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::get('services', function () {
        $services = \App\Models\Service::where('active', true)
            ->with('category:id,name')
            ->get(['id', 'name', 'description', 'price', 'service_category_id']);
        return response()->json(['success' => true, 'data' => $services]);
    });
});

// API para Agente (Open Claw) - protegida con Sanctum token
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Leads
    Route::get('leads/search', [LeadController::class, 'search']);
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{lead}', [LeadController::class, 'show']);
    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus']);
    Route::post('leads/{lead}/notes', [LeadController::class, 'addNote']);

    // Cotizaciones
    Route::post('quotes', [QuoteController::class, 'store']);
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus']);
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'downloadPdf']);

    // Control del Agente
    Route::get('agent/status', [AgentController::class, 'status']);

    // Configuración del negocio
    Route::get('settings', [SettingController::class, 'index']);
});

// Webhook de Mercado Pago (público, validado por firma HMAC)
Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->middleware('throttle:webhooks')
    ->name('mercadopago.webhook');
