<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;

class DemandController extends Controller
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
            $data = $this->baseDocument->generateAiData('demanda', $request);

            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/DFD_Diagnostico_Unificado_Template.docx'));

            // Preenche os dados no template
            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }

            // Adiciona os dados institucionais e o brasão
            $this->baseDocument->setInstitutionalData($templateProcessor, $request);

            // Gera o nome do arquivo e salva
            $filename = 'Documento de Formalização da Demanda_' . time() . '.docx';
            $path = public_path("documents/{$filename}");
            $templateProcessor->saveAs($path);

            return response()->json([
                'success' => true,
                'url' => url("documents/{$filename}")
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => "Error generating demand document: " . $e->getMessage()
            ], 500);
        }
    }
} 