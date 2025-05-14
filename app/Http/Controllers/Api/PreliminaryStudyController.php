<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;
use Illuminate\Support\Facades\Log;

class PreliminaryStudyController extends Controller
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
            
            
            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/ETP_Estudo_Tecnico_Preliminar_Template.docx'));
            
            // Preenche os dados no template
            try {                
                $data = $this->baseDocument->generateAiData('preliminaryStudy', $request);
                
                foreach ($data as $key => $value) {
                    
                    $templateProcessor->setValue($key, $value);
                    
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao preencher template de ETP com dados iniciais. Tentando recuperar. Erro: " . $e->getMessage());

                // Reexecuta tentativa de recuperação diretamente do conteúdo do $data
                $raw = json_encode($data, JSON_UNESCAPED_UNICODE);
                $data = $this->baseDocument->recoverMalformedJson($raw, 'preliminaryStudy');

                if (empty($data)) {
                    $data = $this->baseDocument->recoverDelimitedKeyValue($raw);
                }

                $data = $this->baseDocument->normalizeTemplateData($data, 'preliminaryStudy');

                

            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
                }
            }

            // Adiciona os dados institucionais e o brasão
            $this->baseDocument->setInstitutionalData($templateProcessor, $request);

            // Gera o nome do arquivo e salva
            $filename = 'Estudo Técnico Preliminar_' . time() . '.docx';
            $path = public_path("documents/{$filename}");
            $templateProcessor->saveAs($path);

            return response()->json([
                'success' => true,
                'url' => url("documents/{$filename}")
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao gerar ETP: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => "Error generating preliminary study document: " . $e->getMessage()
            ], 500);
        }
    }
} 