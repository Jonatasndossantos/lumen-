<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Exception;

class RiskMatrixController extends Controller
{
    protected $baseDocument;

    public function __construct(BaseDocumentController $baseDocument)
    {
        $this->baseDocument = $baseDocument;
    }

    public function generate(Request $request)
    {
        try {
            // Check if template directory exists
            $templatesPath = public_path('templates');
            if (!file_exists($templatesPath)) {
                throw new Exception("Template directory not found at: " . $templatesPath);
            }

            $templatePath = $templatesPath . '/Matriz_Risco_Template.docx';
            if (!file_exists($templatePath)) {
                throw new Exception("Template file not found at: " . $templatePath);
            }

            // Ensure documents directory exists
            $documentsDir = public_path('documents');
            if (!file_exists($documentsDir)) {
                if (!mkdir($documentsDir, 0755, true)) {
                    throw new Exception("Failed to create documents directory at: " . $documentsDir);
                }
            }

            // Check if we can write to the documents directory
            if (!is_writable($documentsDir)) {
                throw new Exception("Documents directory is not writable: " . $documentsDir);
            }

            $tempPath = public_path('documents/temp_matriz_risco.docx');
            $outputFilename = 'Matriz de risco_' . time() . '.docx';
            $outputPath = public_path('documents/' . $outputFilename);

            // 1. Carregar o template
            $phpWord = IOFactory::load($templatePath, 'Word2007');
            $sections = $phpWord->getSections();
            $section = $sections[0];

            // 2. Criar e inserir a tabela de riscos + seção de assinatura
            // Adiciona espaço antes da tabela
            $section->addText('');

            // Gera os dados via IA
            //$data = $this->baseDocument->generateAiData('risco', $request);
            try{
                $data = json_decode('{
                    "processo_administrativo": "00234.2025/SSU",
                    "objeto_matriz": "Contratação de serviços contínuos vinculados à Secretaria de Serviços Urbanos, englobando manutenção de vias, iluminação pública, limpeza urbana e manejo de resíduos sólidos.",
                    "data_inicio_contratacao": "2025-06-01",
                    "unidade_responsavel": "Secretaria de Serviços Urbanos",
                    "fase_analise": "Planejamento da contratação",
                    "data_aprovacao": "2025-05-05",
                    "riscos": [
                        {
                        "seq": "1",
                        "evento": "Atraso sistemático na coleta de resíduos sólidos urbanos por parte da contratada.",
                        "dano": "Acúmulo de lixo nas vias públicas, proliferação de vetores, insalubridade ambiental e descumprimento do Plano Municipal de Gestão Integrada de Resíduos Sólidos (PMGIRS).",
                        "impacto": "alto",
                        "probabilidade": "médio",
                        "acao_preventiva": "Estabelecer cláusulas contratuais claras com cronogramas e rotas obrigatórias; exigir plano operacional detalhado antes da execução; monitoramento diário via GPS e relatórios de campo.",
                        "responsavel_preventiva": "Coordenação de Limpeza Urbana da Secretaria de Serviços Urbanos",
                        "acao_contingencia": "Aplicar sanções contratuais previstas na Lei 14.133/2021 e acionar empresa remanescente ou equipe emergencial da administração para garantir a continuidade do serviço.",
                        "responsavel_contingencia": "Diretoria de Contratos e Fiscalização de Serviços Urbanos"
                        },
                        {
                        "seq": "2",
                        "evento": "Execução deficiente de manutenção corretiva da iluminação pública.",
                        "dano": "Comprometimento da segurança pública noturna, aumento de criminalidade e insegurança dos cidadãos em áreas mal iluminadas.",
                        "impacto": "médio",
                        "probabilidade": "alto",
                        "acao_preventiva": "Impor SLA (Service Level Agreement) com prazo máximo de 48h para atendimento a falhas; exigir registro fotográfico antes/depois da manutenção; vincular pagamento à conformidade técnica da execução.",
                        "responsavel_preventiva": "Setor de Iluminação Pública da Secretaria de Serviços Urbanos",
                        "acao_contingencia": "Reorientar recursos para equipes internas ou empresa reserva via contrato emergencial; abertura de protocolo via ouvidoria para priorização de áreas críticas.",
                        "responsavel_contingencia": "Gerência de Serviços Essenciais Urbanos"
                        },
                        {
                        "seq": "3",
                        "evento": "Descumprimento contratual por abandono total ou parcial dos serviços.",
                        "dano": "Paralisação de serviços essenciais à população, como varrição de ruas, manutenção de praças e operação de ecopontos.",
                        "impacto": "alto",
                        "probabilidade": "baixo",
                        "acao_preventiva": "Análise de capacidade operacional e financeira da contratada na habilitação; exigência de garantia contratual nos moldes do art. 96 da Lei 14.133/2021; previsão de cláusulas resolutivas.",
                        "responsavel_preventiva": "Comissão Permanente de Licitação e Setor Jurídico",
                        "acao_contingencia": "Execução da garantia; aplicação de penalidades contratuais; ativação de plano emergencial de serviços mínimos por equipe interna ou nova contratação por dispensa de licitação.",
                        "responsavel_contingencia": "Gabinete da Secretaria de Serviços Urbanos"
                        },
                        {
                        "seq": "4",
                        "evento": "Exposição indevida de dados pessoais dos trabalhadores ou usuários dos serviços contratados.",
                        "dano": "Violação da Lei Geral de Proteção de Dados (LGPD), com possível responsabilização civil e administrativa do ente público, além de danos à imagem institucional.",
                        "impacto": "alto",
                        "probabilidade": "médio",
                        "acao_preventiva": "Inserir cláusulas específicas de compliance com a LGPD no contrato; exigir nomeação de Encarregado de Dados pela contratada; auditoria periódica sobre tratamento e armazenamento dos dados.",
                        "responsavel_preventiva": "Núcleo de Proteção de Dados e Controle Interno da Administração",
                        "acao_contingencia": "Notificação à Autoridade Nacional de Proteção de Dados (ANPD); bloqueio do fluxo de dados até readequação; aplicação de sanções administrativas e contratuais.",
                        "responsavel_contingencia": "Controladoria Geral do Município em conjunto com a Secretaria de Serviços Urbanos"
                        },
                        {
                        "seq": "5",
                        "evento": "Acidentes de trabalho durante a execução de serviços de manutenção urbana.",
                        "dano": "Paralisação da atividade, responsabilização da Administração por omissão na fiscalização, ações trabalhistas e indenizações por danos físicos ou morais.",
                        "impacto": "médio",
                        "probabilidade": "médio",
                        "acao_preventiva": "Exigir PCMSO e PPRA atualizados; realizar fiscalização sistemática do uso de EPIs; treinamento técnico inicial obrigatório conforme NR-35 e NR-06; inclusão de cláusula de responsabilidade exclusiva da contratada.",
                        "responsavel_preventiva": "Setor de Segurança do Trabalho da Secretaria de Serviços Urbanos",
                        "acao_contingencia": "Abertura imediata de sindicância e notificação ao Ministério do Trabalho; suspensão cautelar do contrato em caso de negligência grave; substituição da equipe por outra apta.",
                        "responsavel_contingencia": "Comissão de Fiscalização de Serviços e Segurança Operacional"
                        }
                    ]
                    }
                ', true);
            } catch(\Throwable $e){
                log:info("deu erro no json informado: MR");
            }



            // Criar e preencher a tabela
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 80
            ]);
            
            // Estilo para o texto da tabela
            $textStyle = [
                'size' => 9,
                'name' => 'Arial'
            ];
            
            // Estilo para o cabeçalho
            $headerStyle = [
                'size' => 9,
                'name' => 'Arial',
                'bold' => true
            ];
            
            // Cabeçalho da tabela
            $table->addRow();
            $headers = ['Seq', 'Evento de Risco', 'Dano', 'Impacto', 'Probabilidade', 'Ação Preventiva', 'Responsável Preventiva', 'Ação de Contingência', 'Responsável Contingência'];
            foreach ($headers as $header) {
                $cell = $table->addCell(1500, [
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'bgColor' => 'F2F2F2'
                ]);
                $cell->addText($header, $headerStyle, ['alignment' => 'center']);
            }

            // Adicionar linhas com os dados
            foreach ($data['riscos'] as $index => $risco) {
                $table->addRow();
                $risco['seq'] = $index + 1;
                foreach ($risco as $campo) {
                    $cell = $table->addCell(1500, [
                        'borderSize' => 6,
                        'borderColor' => '000000'
                    ]);
                    $cell->addText($campo, $textStyle);
                }
            }

            // Adiciona espaço após a tabela
            $section->addText('');

            // Adiciona a seção de assinaturas
            $section->addText('Assinatura:', ['bold' => true]);
            $section->addText('__________________________________________ ');
            $section->addText('${nome_autoridade}');
            $section->addText('${cargo_autoridade}');
            $section->addText('${data_aprovacao}');

            // Salvar o arquivo com a tabela
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            if (!file_exists($tempPath)) {
                throw new Exception("Failed to save temporary file with table at: " . $tempPath);
            }

            // 3. Preencher todas as variáveis
            $templateProcessor = new TemplateProcessor($tempPath);
            
            // Preenche os dados do processo
            foreach ($data as $key => $value) {
                if ($key !== 'riscos') {
                    $templateProcessor->setValue($key, $value);
                }
            }

            // Adiciona os dados institucionais e o brasão
            $this->baseDocument->setInstitutionalData($templateProcessor, $request);

            // 4. Salvar o arquivo final
            $templateProcessor->saveAs($outputPath);
            
            if (!file_exists($outputPath)) {
                throw new Exception("Failed to save final output file at: " . $outputPath);
            }

            // Limpar arquivo temporário
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return response()->json([
                'success' => true,
                'url' => url("documents/{$outputFilename}")
            ]);
        } catch (Exception $e) {
            // Log the error
            error_log("Error in RiskMatrixController: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => "Error generating risk matrix document: " . $e->getMessage()
            ], 500);
        }
    }
} 