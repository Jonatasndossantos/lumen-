<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;

class GuidelinesController extends Controller
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
            $data = $this->baseDocument->generateAiData('institutional', $request);

            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/guidelines_template.docx'));

            // Preenche os dados básicos
            $templateProcessor->setValue('name', $request->name);
            $templateProcessor->setValue('municipality', $request->municipality);
            $templateProcessor->setValue('institution', $request->institution);
            $templateProcessor->setValue('date', date('d/m/Y'));

            // Preenche os dados institucionais
            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }

            // Adiciona os dados institucionais e o brasão
            $this->baseDocument->setInstitutionalData($templateProcessor, $request);

            // Gera o nome do arquivo e salva
            $filename = 'orientacoes_' . time() . '.docx';
            $path = public_path("documents/{$filename}");
            $templateProcessor->saveAs($path);

            return response()->json([
                'success' => true,
                'url' => url("documents/{$filename}")
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => "Error generating guidelines document: " . $e->getMessage()
            ], 500);
        }
    }
} 