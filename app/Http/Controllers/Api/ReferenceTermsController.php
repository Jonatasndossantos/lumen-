<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;
use Illuminate\Support\Facades\Log;

class ReferenceTermsController extends Controller
{
    protected $baseDocument;

    public function __construct(BaseDocumentController $baseDocument)
    {
        $this->baseDocument = $baseDocument;
    }

    public function generate(Request $request)
    {
        try {
            // Gera os dados via IA
            $data = $this->baseDocument->generateAiData('tr', $request);

            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/TR_Termo_Referencia_V11_3_Template.docx'));

            // Preenche os dados no template
            try {
                foreach ($data as $key => $value) {
                    $templateProcessor->setValue($key, $value);
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao preencher template de TR com dados iniciais. Tentando recuperar. Erro: " . $e->getMessage());

                // Reexecuta tentativa de recuperação diretamente do conteúdo do $data
                $raw = json_encode($data, JSON_UNESCAPED_UNICODE);

                $data = $this->baseDocument->recoverMalformedJson($raw, 'tr');

                if (empty($data)) {
                    $data = $this->baseDocument->recoverDelimitedKeyValue($raw);
                }

                $data = $this->baseDocument->normalizeTemplateData($data, 'tr');

                foreach ($data as $key => $value) {
                    $templateProcessor->setValue($key, $value);
                }
            }

            // Adiciona os dados institucionais e o brasão
            $this->baseDocument->setInstitutionalData($templateProcessor, $request);

            // Gera o nome do arquivo e salva
            $filename = 'Termo de Referência_' . time() . '.docx';
            $path = public_path("documents/{$filename}");
            $templateProcessor->saveAs($path);

            return response()->json([
                'success' => true,
                'url' => url("documents/{$filename}")
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => "Error generating reference terms document: " . $e->getMessage()
            ], 500);
        }
    }
} 