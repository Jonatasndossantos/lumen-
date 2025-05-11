<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DocumentGenerationController;
use App\Http\Controllers\Api\GuidelinesController;
use App\Http\Controllers\Api\DemandController;
use App\Http\Controllers\Api\RiskMatrixController;
use App\Http\Controllers\Api\PreliminaryStudyController;
use App\Http\Controllers\Api\ReferenceTermsController;
use Illuminate\Support\Facades\Storage;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Document generation routes
Route::post('/documents/generate', [DocumentGenerationController::class, 'generate']);

// Individual document routes
Route::post('/documents/guidelines', [GuidelinesController::class, 'generate']);
Route::post('/documents/demand', [DemandController::class, 'generate']);
Route::post('/documents/risk-matrix', [RiskMatrixController::class, 'generate']);
Route::post('/documents/preliminary-study', [PreliminaryStudyController::class, 'generate']);
Route::post('/documents/reference-terms', [ReferenceTermsController::class, 'generate']);

Route::get('/test-openai', function() {
    try {
        $apiKey = config('openai.api_key');
        
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key não configurada. Verifique o arquivo .env'
            ]);
        }
        
        // Teste de escrita no storage
        $testFile = 'test_' . time() . '.txt';
        try {
            Storage::put('public/' . $testFile, 'Teste de escrita');
            $storageStatus = 'OK - Arquivo criado: ' . Storage::url($testFile);
        } catch (\Exception $e) {
            $storageStatus = 'ERRO: ' . $e->getMessage();
        }
        
        // Teste básico de chamada à API do OpenAI
        try {
            $openai = \OpenAI::client($apiKey);
            $result = $openai->chat()->create([
                'model' => config('openai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um assistente útil.'],
                    ['role' => 'user', 'content' => 'Diga "Olá, estou funcionando!" em português.']
                ],
                'max_tokens' => 50
            ]);
            $openaiStatus = 'OK - Resposta: ' . $result->choices[0]->message->content;
        } catch (\Exception $e) {
            $openaiStatus = 'ERRO: ' . $e->getMessage();
        }
        
        return response()->json([
            'success' => true,
            'api_key_configured' => !empty($apiKey),
            'storage_test' => $storageStatus,
            'openai_test' => $openaiStatus,
            'environment' => [
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'openai_key_length' => strlen($apiKey) > 0 ? strlen($apiKey) : 0,
                'storage_path' => storage_path('app/public'),
                'is_writable' => is_writable(storage_path('app/public'))
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erro no teste: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}); 