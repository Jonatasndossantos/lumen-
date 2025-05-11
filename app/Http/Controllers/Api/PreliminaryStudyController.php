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
            $data = $this->baseDocument->generateAiData('etp', $request);

            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/ETP_Estudo_Tecnico_Preliminar_Template.docx'));

            // Preenche os dados no template
            try {
                // Garante que campos críticos existam
                if (!isset($data['etp_estimativa_quantidades']) || empty($data['etp_estimativa_quantidades'])) {
                    Log::warning("Campo etp_estimativa_quantidades ausente ou vazio");
                    $data['etp_estimativa_quantidades'] = 'Não especificado';
                }
                
                if (!isset($data['etp_viabilidade_contratacao']) || empty($data['etp_viabilidade_contratacao'])) {
                    Log::warning("Campo etp_viabilidade_contratacao ausente ou vazio");
                    $data['etp_viabilidade_contratacao'] = 'Não especificado';
                }

                foreach ($data as $key => $value) {
                    // Garante que os campos críticos sejam substituídos corretamente
                    if ($key === 'etp_estimativa_quantidades' || $key === 'etp_viabilidade_contratacao') {
                        Log::info("Preenchendo campo {$key}", ['length' => strlen($value)]);
                        $templateProcessor->setValue($key, $value);
                        // Dupla verificação da substituição
                        try {
                            $templateProcessor->setValue($key, $value);
                        } catch (\Exception $e) {
                            Log::error("Erro ao substituir {$key}: " . $e->getMessage());
                        }
                    } else {
                        $templateProcessor->setValue($key, $value);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao preencher template de ETP com dados iniciais. Tentando recuperar. Erro: " . $e->getMessage());

                // Reexecuta tentativa de recuperação diretamente do conteúdo do $data
                $raw = json_encode($data, JSON_UNESCAPED_UNICODE);
                $data = $this->baseDocument->recoverMalformedJson($raw, 'etp');

                if (empty($data)) {
                    $data = $this->baseDocument->recoverDelimitedKeyValue($raw);
                }

                $data = $this->baseDocument->normalizeTemplateData($data, 'etp');

                // Garante novamente os campos críticos após recuperação
                if (!isset($data['etp_estimativa_quantidades'])) {
                    $data['etp_estimativa_quantidades'] = 'Não especificado';
                }
                if (!isset($data['etp_viabilidade_contratacao'])) {
                    $data['etp_viabilidade_contratacao'] = 'Não especificado';
                }

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