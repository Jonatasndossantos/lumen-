<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;

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
            $data = $this->baseDocument->generateAiData('etp', $request);

            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/ETP_Estudo_Tecnico_Preliminar_Template.docx'));

            // Preenche os dados no template
            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
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
            return response()->json([
                'success' => false,
                'error' => "Error generating preliminary study document: " . $e->getMessage()
            ], 500);
        }
    }
} 