<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use PhpOffice\PhpWord\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Orhanerday\OpenAi\OpenAi;

class BaseDocumentController extends Controller
{
    protected $templatesPath;
    protected $cacheTime = 3600; // 1 hora
    
    public function __construct()
    {
        set_time_limit(300); // Aumenta para 5 minutos
        $this->templatesPath = public_path('templates');
        Settings::setOutputEscapingEnabled(true);
    }

    public function generateAiData(string $type, Request $request): array
    {
        // Gera uma chave única para o cache baseada no tipo e nos dados da requisição
        $cacheKey = $this->generateCacheKey($type, $request);
        
        // Tenta recuperar do cache primeiro
        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            return $cachedData;
        }

        $open_ai_key = getenv('OPENAI_API_KEY');
        $open_ai = new OpenAi($open_ai_key);
        $open_ai->setORG("org-82bPuEP0JKxx9yTNreD9Jclb");

        $prompt = $this->getPrompt($type, $request);
        $toolSchema = $this->getToolSchema($type);

        try {
            $response = $open_ai->chat([
                'model' => 'gpt-4-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => $this->getSystemRole($type)
                    ],
                    [
                        "role" => "user",
                        "content" => $prompt
                    ],
                ],
                'tools' => [$toolSchema],
                'tool_choice' => [
                    'type' => 'function',
                    'function' => ['name' => $toolSchema['function']['name']]
                ],
                'temperature' => 1.0,
                'max_tokens' => 4000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);
            log:info("resposta '{$response}'");

            $responseArray = json_decode($response, true);
            
            if (!isset($responseArray['choices'][0]['message']['tool_calls'][0])) {
                Log::error("Tool calls não encontrado na resposta");
                throw new \Exception('No tool call in response');
            }


            $toolCall = $responseArray['choices'][0]['message']['tool_calls'][0];
            $arguments = $toolCall['function']['arguments'];
            $data = json_decode($arguments, true);

            

            if ($data === null) {
                Log::error("Falha ao decodificar arguments como JSON", [
                    'json_error' => json_last_error_msg()
                ]);
                throw new \Exception('Failed to decode tool call arguments as JSON');
            }

            if (!is_array($data)) {
                Log::error("Dados decodificados não são um array", [
                    'type' => gettype($data)
                ]);
                throw new \Exception('Tool call arguments did not decode to an array');
            }

            // Cache the result
            Cache::put($cacheKey, $data, $this->cacheTime);
            
            return $data;

        } catch (\Exception $e) {
            Log::error('Erro ao se comunicar com a OpenAI: ' . $e->getMessage());
            return $this->returnEmptyFallback($type, $cacheKey);
        }
    }

    protected function extractRawJsonFromResponse($response): string
    {
        Log::warning("processando: '{$response}'");
        $array = json_decode($response, true);
        if (!isset($array['choices'][0]['message']['content'])) {
            Log::error('Campo "content" ausente na resposta da IA.');
            return '';
        }

        $raw = $array['choices'][0]['message']['content'];
        $raw = preg_replace('/```(json)?/', '', $raw);
        $raw = preg_replace('/,(\s*[\}\]])/', '$1', trim($raw));

        return $raw;
    }
    

    protected function tryParseJson(string $raw): array
    {
        Log::info("tryParseJson");
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }
    

    protected function recoverMalformedJson(string $raw, string $type): array
    {
        log::info("esse: '{$raw}'");
        // Remove delimitadores de markdown (```json ou ```)
        $raw = preg_replace('/```(?:json)?/', '', $raw);

        // Corrige vírgulas finais antes de fechamentos }
        $raw = preg_replace('/,(\s*[\}\]])/', '$1', $raw);

        // Remove aspas duplicadas
        $raw = preg_replace('/"{2,}/', '"', $raw);

        // Remove espaços em excesso
        $raw = trim($raw);
        log::info("passa: '{$raw}'");
        // Tentativa direta
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            // Tenta forçar por regex extraindo o que parece ser JSON
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $raw, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }

        if (!is_array($parsed)) {
            Log::warning("Falha na tentativa de recuperação de JSON bruto.");
            $parsed = [];
        }

        // Completa com fallback
        $expected = $this->getExpectedFields($type);
        $final = [];

        foreach ($expected as $field) {
            $final[$field] = array_key_exists($field, $parsed) && !empty($parsed[$field])
                ? $parsed[$field]
                : '–';
        }

        return $final;
    }

    

    protected function returnEmptyFallback(string $type, string $cacheKey): array
    {
        $expectedFields = $this->getExpectedFields($type);
        $data = [];

        foreach ($expectedFields as $field) {
            $data[$field] = '–';
        }

        Cache::put($cacheKey, $data, $this->cacheTime);
        return $data;
    }


    protected function getExpectedFields(string $type): array
    {
        return match ($type) {
            'institutional' => [
                'cidade',
                'cidade_maiusculo',
                'endereco',
                'cep',
                'nome_autoridade',
                'cargo_autoridade',
                'data_extenso',
                'data_aprovacao',
                'nome_elaborador',
                'cargo_elaborador',
                'nome_autoridade_aprovacao',
                'cargo_autoridade_aprovacao'
            ],

            'etp' => [
                'etp_objeto',
                'etp_justificativa',
                'etp_plano_contratacao',
                'etp_requisitos_linguagens',
                'etp_requisitos_banco',
                'etp_requisitos_api',
                'etp_requisitos_funcionais',
                'etp_requisitos_compatibilidade',
                'etp_experiencia_publica',
                'etp_prazo_execucao',
                'etp_forma_pagamento',
                'etp_criterios_selecao',
                'etp_estimativa_quantidades',
                'etp_alternativa_a',
                'etp_alternativa_b',
                'etp_alternativa_c',
                'etp_analise_comparativa',
                'etp_estimativa_precos',
                'etp_solucao_total',
                'etp_parcelamento',
                'etp_resultados_esperados',
                'etp_providencias_previas',
                'etp_contratacoes_correlatas',
                'etp_impactos_ambientais',
                'etp_viabilidade_contratacao',
                'etp_previsao_dotacao',
                'etp_previsao_pca',
                'etp_plano_implantacao',
                'etp_conformidade_lgpd',
                'etp_riscos_tecnicos',
                'etp_riscos_mitigacao',
                'etp_beneficios_qualitativos',
            ],

            'tr' => [
                'descricao_tecnica',
                'justificativa_demanda',
                'base_legal',
                'normas_aplicaveis',
                'execucao_etapas',
                'tolerancia_tecnica',
                'materiais_sustentaveis',
                'execucao_similar',
                'certificacoes',
                'pgr_pcmso',
                'criterio_julgamento',
                'garantia_qualidade',
                'painel_fiscalizacao',
                'kpis_operacionais',
                'designacao_formal_fiscal',
                'penalidades',
                'alertas_ia',
                'anexos_obrigatorios',
                'transparencia_resumo',
                'faq_juridico',
                'prazo_publicacao',
                'transparencia_contato',
                'assinatura_formato',
                'nome_elaborador',
                'cargo_elaborador',
                'nome_autoridade_aprovacao',
                'cargo_autoridade_aprovacao',

            ],

            'demanda' => [
                'setor',
                'departamento',
                'responsavel',
                'descricaoObjeto',
                'valor',
                'origem_fonte',
                'unidade_nome',
                'justificativa',
                'impacto_meta',
                'criterio',
                'priorizacao_justificativa',
                'escopo',
                'requisitos_tecnicos',
                'riscos_ocupacionais',
                'riscos_normas',
                'riscos_justificativa',
                'alternativa_a',
                'alternativa_b',
                'alternativa_conclusao',
                'inerciarisco',
                'inerciaplano',
                'prazo_execucao',
                'forma_pagamento',
                'prazo_vigencia',
                'condicoes_pagamento',
                'ods_vinculados',
                'acao_sustentavel',
                'ia_duplicidade',
                'ia_validacao',
                'transparencia_resumo',
                'transparencia_faq',
                'transparencia_prazo',
                'assinatura_formato',
            ],

            'risco' => [
                'processo_administrativo',
                'objeto_matriz',
                'data_inicio_contratacao',
                'unidade_responsavel',
                'fase_analise',
                'data_aprovacao',
                'riscos', // O campo 'riscos' deve ser um array de arrays
            ],

            default => [],
        };
    }




    protected function generateCacheKey(string $type, Request $request): string
    {
        // Cria uma chave única baseada no tipo e nos dados relevantes da requisição
        $relevantData = [
            'type' => $type,
            'municipality' => $request->input('municipality'),
            'institution' => $request->input('institution'),
            'objectDescription' => $request->input('objectDescription'),
            'valor' => $request->input('valor'),
        ];
        
        return 'ai_data_' . md5(json_encode($relevantData));
    }

    protected function getPrompt(string $type, Request $request): string
    {
        $municipio = $request->input('municipality');
        $instituicao = $request->input('institution');
        $address = $request->input('address');
        $descricao = $request->input('objectDescription');
        $date = now()->format('d \d\e F \d\e Y');
        $valor = $request->input('valor', '00');

        $basePrompt = "Gere os dados para o documento com as seguintes informações:\n\n";
        $basePrompt .= "- Município: {$municipio}\n";
        $basePrompt .= "- Instituição: {$instituicao}\n";
        $basePrompt .= "- Endereço: {$address}\n";
        $basePrompt .= "- Descrição do objeto: {$descricao}\n";
        $basePrompt .= "- Valor: R$ {$valor}\n";
        $basePrompt .= "- Data: {$date}\n\n";

        $typeSpecificPrompt = match ($type) {
            'institutional' => "Para os dados institucionais, considere:\n" .
                "- O endereço deve ser completo e realista\n" .
                "- O nome da autoridade deve ser '[nome protected]'\n" .
                "- O cargo deve ser apropriado para a instituição\n" .
                "- A data deve estar no formato correto para documentos oficiais\n\n" .
                "Retorne os dados no formato JSON conforme a estrutura definida.",

            'etp' => "Para o Estudo Técnico Preliminar, considere:\n" .
                "1. Objeto e Justificativa:\n" .
                "   - Descreva o objeto de forma clara e técnica\n" .
                "   - Fundamente a necessidade com base na Lei 14.133/2021\n" .
                "   - Demonstre o interesse público\n\n" .
                
                "2. Requisitos e Compatibilidade:\n" .
                "   - Detalhe os requisitos funcionais com casos de uso\n" .
                "   - Especifique compatibilidade com sistemas existentes\n" .
                "   - Liste experiências similares na administração pública\n\n" .
                
                "3. Prazos e Pagamentos:\n" .
                "   - Justifique o prazo de execução\n" .
                "   - Detalhe a forma de pagamento\n" .
                "   - Especifique critérios de seleção\n\n" .
                
                "4. Análise de Alternativas:\n" .
                "   - Apresente três alternativas viáveis\n" .
                "   - Compare custos e benefícios\n" .
                "   - Justifique a escolha final\n\n" .
                
                "5. Impactos e Riscos:\n" .
                "   - Avalie impactos ambientais\n" .
                "   - Identifique riscos técnicos\n" .
                "   - Proponha medidas de mitigação\n\n" .
                
                "6. Aspectos Legais:\n" .
                "   - Confirme previsão orçamentária\n" .
                "   - Verifique inclusão no PCA\n" .
                "   - Garanta conformidade com LGPD\n\n" .
                
                "Retorne os dados no formato JSON conforme a estrutura definida, garantindo que cada campo tenha o número mínimo de caracteres especificado e mantenha coerência com os demais campos.",

            'tr' => "Para o Termo de Referência, considere:\n" .
                "- A descrição técnica deve ser detalhada e específica\n" .
                "- As normas aplicáveis devem ser citadas corretamente\n" .
                "- As etapas de execução devem ser claras e sequenciais\n" .
                "- Os critérios de julgamento devem ser objetivos\n" .
                "- As garantias e penalidades devem ser proporcionais\n" .
                "- Os anexos devem ser relevantes e necessários\n\n" .
                "Retorne os dados no formato JSON conforme a estrutura definida.",

            'demanda' => "Para o Documento de Formalização de Demanda, considere:\n" .
                "- O setor e departamento devem ser precisos\n" .
                "- A justificativa deve ser técnica e legal\n" .
                "- O escopo deve ser claro e delimitado\n" .
                "- Os requisitos técnicos devem ser específicos\n" .
                "- As alternativas devem ser viáveis\n" .
                "- Os prazos devem ser realistas\n\n" .
                "Retorne os dados no formato JSON conforme a estrutura definida.",

            'risco' => "Para a Matriz de Risco, considere:\n" .
                "- O processo administrativo deve ser identificado\n" .
                "- Os riscos devem ser específicos e mensuráveis\n" .
                "- O impacto e probabilidade devem ser classificados\n" .
                "- As ações preventivas devem ser detalhadas\n" .
                "- Os responsáveis devem ser claramente definidos\n" .
                "- As ações contingenciais devem ser viáveis\n\n" .
                "Retorne os dados no formato JSON conforme a estrutura definida.",

            default => "Retorne os dados no formato JSON conforme a estrutura definida."
        };

        return $basePrompt . $typeSpecificPrompt;
    }

    protected function getSystemRole(string $type): string
    {
        $baseInstructions = "Você é um assistente especialista em licitações públicas brasileiras, com profundo conhecimento da Lei nº 14.133/2021, IN SEGES nº 05/2017 e nº 65/2021. Use linguagem formal, técnica e precisa. Use vocabulário técnico-jurídico e fundamente com base na legislação aplicável. IMPORTANTE: Você DEVE retornar sua resposta usando o tool_call especificado, preenchendo TODOS os campos obrigatórios.";

        $typeSpecificInstructions = match ($type) {
            'institutional' => "Gere dados institucionais para documentos oficiais de municípios brasileiros. O endereço deve ser realista e complementado se necessário. O nome da autoridade não pode ser fictício, use '[nome protected]'. O cargo deve ser condizente com a instituição (ex: Prefeito Municipal, Secretário de Administração).",
            
            'etp' => "Gere um Estudo Técnico Preliminar (ETP) detalhado e completo, seguindo rigorosamente a IN SEGES nº 05/2017.\n\n" .
                "CAMPOS OBRIGATÓRIOS (todos devem ser preenchidos):\n" .
                "1. etp_estimativa_precos (mín. 300 caracteres):\n" .
                "   - Use fontes oficiais (PNCP, Painel de Preços)\n" .
                "   - Detalhe metodologia de pesquisa\n" .
                "   - Justifique valores encontrados\n\n" .
                
                "2. etp_solucao_total (mín. 400 caracteres):\n" .
                "   - Descreva solução completa\n" .
                "   - Justifique escolha técnica\n" .
                "   - Demonstre aderência aos requisitos\n\n" .
                
                "3. etp_parcelamento (mín. 250 caracteres):\n" .
                "   - Cite art. 40 da Lei 14.133/21\n" .
                "   - Analise viabilidade técnica\n" .
                "   - Justifique decisão\n\n" .
                
                "4. etp_resultados_esperados (mín. 350 caracteres):\n" .
                "   - Liste benefícios esperados\n" .
                "   - Inclua métricas mensuráveis\n" .
                "   - Descreva impactos positivos\n\n" .
                
                "5. etp_providencias_previas (mín. 300 caracteres):\n" .
                "   - Liste ações necessárias\n" .
                "   - Inclua cronograma\n" .
                "   - Detalhe recursos necessários\n\n" .
                
                "6. etp_contratacoes_correlatas (mín. 250 caracteres):\n" .
                "   - Liste contratos relacionados\n" .
                "   - Explique interdependências\n" .
                "   - Analise impactos mútuos\n\n" .
                
                "REGRAS IMPORTANTES:\n" .
                "- TODOS os campos acima são OBRIGATÓRIOS\n" .
                "- Respeite o número mínimo de caracteres\n" .
                "- Mantenha coerência entre as seções\n" .
                "- Use exemplos específicos\n" .
                "- Evite texto genérico\n" .
                "- Retorne via tool_call\n\n" .
                
                "ESTRUTURA DA RESPOSTA:\n" .
                "- Use o tool_call 'generate_document_data'\n" .
                "- Inclua todos os campos obrigatórios\n" .
                "- Mantenha formato JSON válido\n" .
                "- Não omita nenhum campo requerido",
            
            'tr' => "Gere um Termo de Referência (TR) com base no template institucional da Administração Pública. Evite jargões vagos como 'melhorar o serviço' sem descrição técnica clara. Se o objeto for obra ou serviço, detalhe etapas construtivas, insumos e controle de qualidade. Se for fornecimento de bens, especifique lotes, quantidades, forma de entrega e exigências mínimas. Se for solução de TI, inclua infraestrutura, linguagens, banco de dados e APIs.",
            
            'demanda' => "Gere um Documento de Formalização de Demanda (DFD) completo.\n\n" .
                "CAMPOS OBRIGATÓRIOS:\n" .
                "1. justificativa (mín. 300 caracteres):\n" .
                "   - Fundamente necessidade\n" .
                "   - Demonstre benefícios\n" .
                "   - Cite legislação\n\n" .
                
                "2. alternativas (mín. 400 caracteres):\n" .
                "   - Descreva opções consideradas\n" .
                "   - Compare alternativas\n" .
                "   - Justifique escolha\n\n" .
                
                "3. riscos (mín. 300 caracteres):\n" .
                "   - Identifique riscos principais\n" .
                "   - Proponha mitigações\n" .
                "   - Avalie impactos\n\n" .
                
                "REGRAS IMPORTANTES:\n" .
                "- TODOS os campos são OBRIGATÓRIOS\n" .
                "- Use o tool_call 'generate_document_data'\n" .
                "- Mantenha formato JSON válido\n" .
                "- Não omita campos requeridos",
            
            'risco' => "Gere uma matriz de risco contendo dados gerais do processo, lista de ao menos 5 riscos relevantes, classificação formal e específica de impacto e probabilidade, ações preventivas e contingenciais bem detalhadas, e indicação de responsáveis. Liste pelo menos um risco de descumprimento contratual e um relacionado à LGPD, se o objeto envolver dados pessoais. Classifique impacto e probabilidade usando somente: baixo | médio | alto.",
            
            default => ""
        };

        return $baseInstructions . "\n\n" . $typeSpecificInstructions;
    }

    public function setInstitutionalData($templateProcessor, Request $request)
    {
        try {
            $data = $this->generateAiData('institutional', $request);
            
            // Preenche os dados básicos
            $templateProcessor->setValue('cidade', $data['cidade']);
            $templateProcessor->setValue('cidade_maiusculo', strtoupper($data['cidade_maiusculo']));
            $templateProcessor->setValue('endereco', $data['endereco']);
            $templateProcessor->setValue('cep', $data['cep']);
            $templateProcessor->setValue('nome_autoridade', $data['nome_autoridade']);
            $templateProcessor->setValue('cargo_autoridade', $data['cargo_autoridade']);
            $templateProcessor->setValue('data_extenso', $data['data_extenso']);
            $templateProcessor->setValue('cargo_elaborador', $data['cargo_elaborador']);
            $templateProcessor->setValue('nome_autoridade_aprovacao', $data['nome_autoridade_aprovacao']);
            $templateProcessor->setValue('cargo_autoridade_aprovacao', $data['cargo_autoridade_aprovacao']);

            // Processa o brasão de forma otimizada
            $this->processBrasao($templateProcessor, $request->input('municipality'));
        } catch (\Exception $e) {
            Log::error('Error setting institutional data: ' . $e->getMessage());
            throw $e;
        }
    }

    public function processBrasao($templateProcessor, $municipality)
    {
        try {
            // Normaliza o nome do município
            $filename = $this->normalizeMunicipalityName($municipality) . '.png';
            $brasaoPath = public_path('brasoes/' . $filename);

            // Verifica se o brasão específico existe
            if (!file_exists($brasaoPath)) {
                $brasaoPath = public_path('brasoes/default.png');
            }

            // Verifica se o arquivo existe antes de tentar processá-lo
            if (file_exists($brasaoPath)) {
                $templateProcessor->setImageValue('brasao', [
                    'path' => $brasaoPath,
                    'width' => 80,
                    'ratio' => true
                ]);
            } else {
                Log::warning("Nenhum brasão encontrado para {$filename}");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao processar brasão: " . $e->getMessage());
            throw $e;
        }
    }

    protected function normalizeMunicipalityName($municipality)
    {
        // Remove "-SP", traços, espaços
        $municipality = str_replace(['-SP', '–SP', '–', ' '], '', $municipality);

        // Remove acentos
        $municipality = iconv('UTF-8', 'ASCII//TRANSLIT', $municipality);

        // Remove caracteres especiais
        $municipality = preg_replace('/[^a-zA-Z0-9]/', '', $municipality);

        // Converte para minúsculo
        return strtolower($municipality);
    }

    protected function getToolSchema(string $type): array
    {
        return match ($type) {
            'institutional' => $this->getInstitutionalSchema(),
            'etp' => $this->getETPSchema(),
            'tr' => $this->getTRSchema(),
            'demanda' => $this->getDFDSchema(),
            'risco' => $this->getRiscoSchema(),
            default => $this->getBaseSchema(),
        };
    }

    protected function getBaseSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'generate_document_data',
                'description' => 'Gera os dados necessários para o documento',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => []
                ]
            ]
        ];
    }

    protected function getInstitutionalSchema(): array
    {
        $schema = $this->getBaseSchema();
        $fields = $this->getExpectedFields('institutional');
        
        foreach ($fields as $field) {
            $schema['function']['parameters']['properties'][$field] = [
                'type' => 'string',
                'description' => 'Campo ' . $field
            ];
            $schema['function']['parameters']['required'][] = $field;
        }

        return $schema;
    }

    protected function getETPSchema(): array
    {
        $schema = $this->getBaseSchema();
        $schema['function']['description'] = 'Gera os dados necessários para o Estudo Técnico Preliminar (ETP)';
        
        $schema['function']['parameters']['properties'] = [
            'etp_objeto' => [
                'type' => 'string',
                'description' => 'Descrição detalhada do objeto da contratação, incluindo especificações técnicas e requisitos'
            ],
            'etp_justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa fundamentada da contratação conforme Lei nº 14.133/2021'
            ],
            'etp_requisitos_funcionais' => [
                'type' => 'string',
                'description' => 'Detalhamento dos requisitos funcionais, casos de uso e regras de negócio'
            ],
            'etp_requisitos_compatibilidade' => [
                'type' => 'string',
                'description' => 'Requisitos de compatibilidade com sistemas existentes e padrões tecnológicos'
            ],
            'etp_experiencia_publica' => [
                'type' => 'string',
                'description' => 'Análise de experiências similares na administração pública'
            ],
            'etp_prazo_execucao' => [
                'type' => 'string',
                'description' => 'Definição e justificativa dos prazos de execução'
            ],
            'etp_forma_pagamento' => [
                'type' => 'string',
                'description' => 'Detalhamento da forma de pagamento e cronograma financeiro'
            ],
            'etp_criterios_selecao' => [
                'type' => 'string',
                'description' => 'Critérios técnicos para seleção do fornecedor'
            ],
            'etp_estimativa_quantidades' => [
                'type' => 'string',
                'description' => 'Memória de cálculo das quantidades necessárias'
            ],
            'etp_alternativa_a' => [
                'type' => 'string',
                'description' => 'Descrição detalhada da primeira alternativa de solução'
            ],
            'etp_alternativa_b' => [
                'type' => 'string',
                'description' => 'Descrição detalhada da segunda alternativa de solução'
            ],
            'etp_alternativa_c' => [
                'type' => 'string',
                'description' => 'Descrição detalhada da terceira alternativa de solução'
            ],
            'etp_analise_comparativa' => [
                'type' => 'string',
                'description' => 'Análise comparativa das alternativas, incluindo custos e benefícios'
            ],
            'etp_estimativa_precos' => [
                'type' => 'string',
                'description' => 'Estimativa detalhada de preços com base em pesquisa de mercado'
            ],
            'etp_solucao_total' => [
                'type' => 'string',
                'description' => 'Descrição da solução como um todo, considerando todos os elementos'
            ],
            'etp_parcelamento' => [
                'type' => 'string',
                'description' => 'Análise sobre o parcelamento ou não da solução'
            ],
            'etp_resultados_esperados' => [
                'type' => 'string',
                'description' => 'Descrição dos resultados pretendidos com a contratação'
            ],
            'etp_providencias_previas' => [
                'type' => 'string',
                'description' => 'Providências necessárias para adequação do ambiente'
            ],
            'etp_contratacoes_correlatas' => [
                'type' => 'string',
                'description' => 'Análise de contratações correlatas e/ou interdependentes'
            ],
            'etp_impactos_ambientais' => [
                'type' => 'string',
                'description' => 'Análise dos impactos ambientais e medidas de sustentabilidade'
            ],
            'etp_viabilidade_contratacao' => [
                'type' => 'string',
                'description' => 'Declaração da viabilidade ou não da contratação'
            ],
            'etp_previsao_dotacao' => [
                'type' => 'string',
                'description' => 'Informações sobre a previsão de dotação orçamentária'
            ],
            'etp_previsao_pca' => [
                'type' => 'string',
                'description' => 'Detalhamento da previsão no Plano de Contratações Anual (PCA)'
            ],
            'etp_conformidade_lgpd' => [
                'type' => 'string',
                'description' => 'Análise de conformidade com a LGPD e medidas de proteção de dados'
            ],
            'etp_riscos_tecnicos' => [
                'type' => 'string',
                'description' => 'Identificação e análise dos riscos técnicos'
            ],
            'etp_riscos_mitigacao' => [
                'type' => 'string',
                'description' => 'Plano de mitigação dos riscos identificados'
            ],
            'etp_beneficios_qualitativos' => [
                'type' => 'string',
                'description' => 'Análise dos benefícios qualitativos esperados'
            ]
        ];
        
        $schema['function']['parameters']['required'] = array_keys($schema['function']['parameters']['properties']);
        
        return $schema;
    }

    protected function getTRSchema(): array
    {
        $schema = $this->getBaseSchema();
        $schema['function']['description'] = 'Gera os dados necessários para o Termo de Referência (TR)';
        
        $schema['function']['parameters']['properties'] = [
            'natureza_objeto' => [
                'type' => 'string',
                'description' => 'Natureza do objeto (serviço, obra, compra, etc)'
            ],
            'modalidade_contratacao' => [
                'type' => 'string',
                'description' => 'Modalidade de contratação conforme Lei nº 14.133/2021'
            ],
            'descricao_tecnica' => [
                'type' => 'string',
                'description' => 'Descrição técnica detalhada do objeto, incluindo especificações e requisitos'
            ],
            'justificativa_demanda' => [
                'type' => 'string',
                'description' => 'Justificativa fundamentada da necessidade da contratação'
            ],
            'base_legal' => [
                'type' => 'string',
                'description' => 'Fundamentação legal específica para a contratação'
            ],
            'normas_aplicaveis' => [
                'type' => 'string',
                'description' => 'Normas técnicas e regulamentações aplicáveis'
            ],
            'cronograma_execucao' => [
                'type' => 'string',
                'description' => 'Cronograma detalhado de execução com marcos e entregas'
            ],
            'execucao_etapas' => [
                'type' => 'string',
                'description' => 'Detalhamento das etapas de execução do objeto'
            ],
            'tolerancia_tecnica' => [
                'type' => 'string',
                'description' => 'Níveis de tolerância técnica aceitáveis'
            ],
            'materiais_sustentaveis' => [
                'type' => 'string',
                'description' => 'Especificação de materiais e práticas sustentáveis'
            ],
            'execucao_similar' => [
                'type' => 'string',
                'description' => 'Análise de execuções similares anteriores'
            ],
            'certificacoes' => [
                'type' => 'string',
                'description' => 'Certificações e qualificações necessárias'
            ],
            'pgr_pcmso' => [
                'type' => 'string',
                'description' => 'Requisitos de PGR e PCMSO quando aplicáveis'
            ],
            'criterio_julgamento' => [
                'type' => 'string',
                'description' => 'Critérios objetivos para julgamento das propostas'
            ],
            'garantia_qualidade' => [
                'type' => 'string',
                'description' => 'Requisitos de garantia e qualidade'
            ],
            'painel_fiscalizacao' => [
                'type' => 'string',
                'description' => 'Definição do painel de fiscalização e controle'
            ],
            'kpis_operacionais' => [
                'type' => 'string',
                'description' => 'Indicadores chave de desempenho operacional'
            ],
            'validacao_kpis' => [
                'type' => 'string',
                'description' => 'Metodologia de validação e medição dos KPIs'
            ],
            'designacao_formal_fiscal' => [
                'type' => 'string',
                'description' => 'Critérios para designação formal do fiscal'
            ],
            'penalidades' => [
                'type' => 'string',
                'description' => 'Definição das penalidades e sanções aplicáveis'
            ],
            'alertas_ia' => [
                'type' => 'string',
                'description' => 'Alertas e recomendações específicas'
            ],
            'anexos_obrigatorios' => [
                'type' => 'string',
                'description' => 'Lista de anexos obrigatórios'
            ],
            'transparencia_resumo' => [
                'type' => 'string',
                'description' => 'Resumo para portal da transparência'
            ],
            'faq_juridico' => [
                'type' => 'string',
                'description' => 'Perguntas e respostas jurídicas frequentes'
            ],
            'prazo_publicacao' => [
                'type' => 'string',
                'description' => 'Prazo para publicação do edital'
            ],
            'transparencia_contato' => [
                'type' => 'string',
                'description' => 'Informações de contato para transparência'
            ],
            'assinatura_formato' => [
                'type' => 'string',
                'description' => 'Formato e requisitos de assinatura'
            ]
        ];
        
        $schema['function']['parameters']['required'] = array_keys($schema['function']['parameters']['properties']);
        
        return $schema;
    }

    protected function getDFDSchema(): array
    {
        $schema = $this->getBaseSchema();
        $schema['function']['description'] = 'Gera os dados necessários para o Documento de Formalização da Demanda (DFD)';
        
        $schema['function']['parameters']['properties'] = [
            'setor' => [
                'type' => 'string',
                'description' => 'Setor requisitante da contratação'
            ],
            'departamento' => [
                'type' => 'string',
                'description' => 'Departamento específico do setor requisitante'
            ],
            'responsavel' => [
                'type' => 'string',
                'description' => 'Responsável pela demanda'
            ],
            'descricaoObjeto' => [
                'type' => 'string',
                'description' => 'Descrição detalhada do objeto da contratação'
            ],
            'valor' => [
                'type' => 'string',
                'description' => 'Valor estimado da contratação'
            ],
            'origem_fonte' => [
                'type' => 'string',
                'description' => 'Origem dos recursos e fonte orçamentária'
            ],
            'unidade_nome' => [
                'type' => 'string',
                'description' => 'Nome da unidade requisitante'
            ],
            'justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa detalhada e fundamentada da necessidade da contratação'
            ],
            'impacto_meta' => [
                'type' => 'string',
                'description' => 'Impacto nas metas institucionais'
            ],
            'criterio' => [
                'type' => 'string',
                'description' => 'Critérios técnicos específicos'
            ],
            'priorizacao_justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa para priorização da demanda'
            ],
            'escopo' => [
                'type' => 'string',
                'description' => 'Escopo detalhado da contratação'
            ],
            'requisitos_tecnicos' => [
                'type' => 'string',
                'description' => 'Requisitos técnicos específicos'
            ],
            'riscos_ocupacionais' => [
                'type' => 'string',
                'description' => 'Análise de riscos ocupacionais'
            ],
            'riscos_normas' => [
                'type' => 'string',
                'description' => 'Conformidade com normas de segurança'
            ],
            'riscos_justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa da análise de riscos'
            ],
            'alternativas' => [
                'type' => 'string',
                'description' => 'Análise completa das alternativas consideradas, incluindo descrição das opções, comparação e conclusão'
            ],
            'riscos' => [
                'type' => 'string',
                'description' => 'Análise detalhada dos riscos envolvidos na contratação e suas mitigações'
            ],
            'inerciarisco' => [
                'type' => 'string',
                'description' => 'Riscos da não realização da contratação'
            ],
            'inerciaplano' => [
                'type' => 'string',
                'description' => 'Plano de contingência para não contratação'
            ],
            'prazo_execucao' => [
                'type' => 'string',
                'description' => 'Prazo de execução previsto'
            ],
            'forma_pagamento' => [
                'type' => 'string',
                'description' => 'Forma de pagamento proposta'
            ],
            'prazo_vigencia' => [
                'type' => 'string',
                'description' => 'Prazo de vigência do contrato'
            ],
            'condicoes_pagamento' => [
                'type' => 'string',
                'description' => 'Condições específicas de pagamento'
            ],
            'ods_vinculados' => [
                'type' => 'string',
                'description' => 'Objetivos de Desenvolvimento Sustentável vinculados'
            ],
            'acao_sustentavel' => [
                'type' => 'string',
                'description' => 'Ações de sustentabilidade previstas'
            ],
            'ia_duplicidade' => [
                'type' => 'string',
                'description' => 'Análise de duplicidade por IA'
            ],
            'ia_validacao' => [
                'type' => 'string',
                'description' => 'Validação por inteligência artificial'
            ],
            'transparencia_resumo' => [
                'type' => 'string',
                'description' => 'Resumo para portal da transparência'
            ],
            'transparencia_faq' => [
                'type' => 'string',
                'description' => 'Perguntas frequentes para transparência'
            ],
            'transparencia_prazo' => [
                'type' => 'string',
                'description' => 'Prazo para publicação na transparência'
            ],
            'assinatura_formato' => [
                'type' => 'string',
                'description' => 'Formato de assinatura do documento'
            ],
        ];
        
        $schema['function']['parameters']['required'] = array_merge(
            ['justificativa', 'alternativas', 'riscos'],
            array_keys($schema['function']['parameters']['properties'])
        );
        
        return $schema;
    }

    protected function getRiscoSchema(): array
    {
        $schema = $this->getBaseSchema();
        $schema['function']['description'] = 'Gera os dados necessários para a Matriz de Risco';
        
        $schema['function']['parameters']['properties'] = [
            'processo_administrativo' => [
                'type' => 'string',
                'description' => 'Número do processo administrativo'
            ],
            'objeto_matriz' => [
                'type' => 'string',
                'description' => 'Descrição do objeto da matriz de risco'
            ],
            'data_inicio_contratacao' => [
                'type' => 'string',
                'description' => 'Data de início da contratação'
            ],
            'unidade_responsavel' => [
                'type' => 'string',
                'description' => 'Unidade responsável pela contratação'
            ],
            'fase_analise' => [
                'type' => 'string',
                'description' => 'Fase atual da análise'
            ],
            'data_aprovacao' => [
                'type' => 'string',
                'description' => 'Data de aprovação'
            ],
            'riscos' => [
                'type' => 'array',
                'description' => 'Lista de riscos identificados',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'evento' => [
                            'type' => 'string',
                            'description' => 'Descrição do evento de risco'
                        ],
                        'dano' => [
                            'type' => 'string',
                            'description' => 'Descrição do possível dano'
                        ],
                        'impacto' => [
                            'type' => 'string',
                            'description' => 'Nível de impacto (baixo, médio, alto)'
                        ],
                        'probabilidade' => [
                            'type' => 'string',
                            'description' => 'Nível de probabilidade (baixo, médio, alto)'
                        ],
                        'acao_preventiva' => [
                            'type' => 'string',
                            'description' => 'Ação preventiva para mitigar o risco'
                        ],
                        'responsavel_preventiva' => [
                            'type' => 'string',
                            'description' => 'Responsável pela ação preventiva'
                        ],
                        'acao_contingencia' => [
                            'type' => 'string',
                            'description' => 'Ação de contingência caso o risco se materialize'
                        ],
                        'responsavel_contingencia' => [
                            'type' => 'string',
                            'description' => 'Responsável pela ação de contingência'
                        ]
                    ],
                    'required' => [
                        'evento',
                        'dano',
                        'impacto',
                        'probabilidade',
                        'acao_preventiva',
                        'responsavel_preventiva',
                        'acao_contingencia',
                        'responsavel_contingencia'
                    ]
                ]
            ]
        ];
        
        $schema['function']['parameters']['required'] = array_keys($schema['function']['parameters']['properties']);
        
        return $schema;
    }

} 
