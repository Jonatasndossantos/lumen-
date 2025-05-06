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
            return Cache::get($cacheKey);
        }

        $open_ai_key = getenv('OPENAI_API_KEY');
        $open_ai = new OpenAi($open_ai_key);
        $open_ai->setORG("org-82bPuEP0JKxx9yTNreD9Jclb");

        
        $prompt = $this->buildPrompt($type, $request);

        try {
            $response = $open_ai->chat([
                'model' => 'gpt-4-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "Você é um assistente especialista em licitações públicas brasileiras. Gere APENAS JSON válido, sem texto adicional. O JSON deve conter todos os campos solicitados, com descrições completas, detalhadas e específicas. Use como referência modelos técnicos robustos como pregões e TRs municipais. NUNCA retorne campos com respostas genéricas ou rasas. Sempre preencha os valores com o maior nível de completude possível. Se um campo exigir justificativa ou especificação técnica, forneça como se estivesse elaborando um documento oficial."
                    ],
                    [
                        "role" => "user",
                        "content" => $prompt
                    ],
                ],
                'temperature' => 1.0,
                'max_tokens' => 4000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            $apiKey = config('services.openai.key');

        
            //$response = Http::withHeaders([
            //    'Authorization' => 'Bearer ' . $apiKey,
            //])
            //->timeout(60) // Aumenta o timeout para 60 segundos
            //->retry(3, 1000) // Tenta 3 vezes com delay de 1 segundo
            //->post('https://api.openai.com/v1/chat/completions', [
            //    'model' => env('OPENAI_MODEL', 'gpt-4-turbo'), // Usa GPT-3.5 por padrão
            //    'messages' => [
            //        ['role' => 'system', 'content' => 'Você é um assistente especialista em licitações públicas brasileiras. Gere APENAS JSON válido, sem texto adicional. O JSON deve conter todos os campos solicitados, com descrições completas, detalhadas e específicas. Use como referência modelos técnicos robustos como pregões e TRs municipais. NUNCA retorne campos com respostas genéricas ou rasas. Sempre preencha os valores com o maior nível de completude possível. Se um campo exigir justificativa ou especificação técnica, forneça como se estivesse elaborando um documento oficial.'],
            //        ['role' => 'user', 'content' => $prompt],
            //    ],
            //    'temperature' => 0.7,
            //]);
        } catch (\Exception $e) {
            
            throw $e;
            
            Log::error('Erro ao se comunicar com a OpenAI: ' . $e->getMessage());
            
            return $this->returnEmptyFallback($type, $cacheKey);
        }
        
        // 2. TENTA INTERPRETAR O JSON NORMALMENTE
        $rawJson = $this->extractRawJsonFromResponse($response);
        $data = $this->tryParseJson($rawJson);

        // 3. SE FALHAR, TENTA RECUPERAR VIA MÉTODO ESPECÍFICO
        if (!is_array($data) || empty($data)) {
            Log::warning("Tentando recuperação forçada do JSON tipo '{$type}'...");
            $data = $this->recoverMalformedJson($type, $rawJson);
        }

        // 4. COMPLETA OS CAMPOS QUE FALTAM
        $expectedFields = $this->getExpectedFields($type);
        foreach ($expectedFields as $field) {
            if (!array_key_exists($field, $data)) {
                if ($type === 'risco' && $field === 'riscos') {
                    $data['riscos'] = [[
                        'seq' => '–',
                        'evento' => '–',
                        'dano' => '–',
                        'impacto' => '–',
                        'probabilidade' => '–',
                        'acao_preventiva' => '–',
                        'responsavel_preventiva' => '–',
                        'acao_contingencia' => '–',
                        'responsavel_contingencia' => '–',
                    ]];
                } else {
                    $data[$field] = '–';
                }
            }
        }

        if (!empty($data)) {
            Cache::put($cacheKey, $data, $this->cacheTime);
        }
        return $data;
    }

    

    protected function extractRawJsonFromResponse($response): string
    {
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
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }
    

    protected function recoverMalformedJson(string $raw, string $type): array
    {
        // Remove delimitadores de markdown (```json ou ```)
        $raw = preg_replace('/```(?:json)?/', '', $raw);

        // Corrige vírgulas finais antes de fechamentos }
        $raw = preg_replace('/,(\s*[\}\]])/', '$1', $raw);

        // Remove aspas duplicadas
        $raw = preg_replace('/"{2,}/', '"', $raw);

        // Remove espaços em excesso
        $raw = trim($raw);

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
            ],

            'etp' => [
                'etp_objeto',
                'etp_justificativa',
                'etp_plano_contratacao',
                'etp_requisitos_linguagens',
                'etp_requisitos_banco',
                'etp_requisitos_api',
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

    protected function buildPrompt(string $type, Request $request): string
    {
        $municipality = $request->input('municipality');
        $institution = $request->input('institution');
        $address = $request->input('address');
        $objectDescription = $request->input('objectDescription');
        $date = now()->format('d \d\e F \d\e Y');

        switch ($type) {
            case 'institutional':
                return $this->buildInstitutionalPrompt($municipality, $institution, $address, $objectDescription, $date);
            
            case 'etp':
                return $this->buildETPPrompt($objectDescription, $request->input('valor', '00'));
            
            case 'tr':
                return $this->buildTRPrompt($objectDescription, $request->input('valor', '00'));
            
            case 'demanda':
                return $this->buildDemandaPrompt($objectDescription, $request->input('valor', '00'));
            
            case 'risco':
                return $this->buildRiscoPrompt($objectDescription);
            
            default:
                throw new \InvalidArgumentException("Tipo de documento inválido: {$type}");
        }
    }

    protected function buildInstitutionalPrompt($municipality, $institution, $address, $objectDescription, $date): string
    {
        return <<<PROMPT
            Gere os dados institucionais para preencher documentos oficiais de um município brasileiro, considerando as seguintes informações fornecidas:

            - Município: {$municipality}
            - Instituição: {$institution}
            - Endereço: {$address}
            - Descrição do objeto da contratação: {$objectDescription}
            - Data atual: {$date}

            Retorne os dados exclusivamente no formato JSON, obedecendo exatamente esta estrutura:

            {
            "cidade": "<nome do município>",
            "cidade_maiusculo": "<nome do município em letras maiúsculas>",
            "endereco": "<endereço sem a cidade>",
            "cep": "<CEP do município>",
            "nome_autoridade": "<nome do principal representante legal da instituição>",
            "cargo_autoridade": "<cargo do representante>",
            "data_extenso": "<data por extenso, ex: '26 de abril de 2025'>",
            "data_aprovacao": "<data por extenso, ex: '26 de abril de 2025'>"
            }

            Instruções importantes:
            - O endereço deve ser o informado, complementado se necessário para realismo
            - O nome da autoridade não pode ser fictício, somente deixe "[nome protected]"
            - O cargo deve ser condizente com a instituição (ex: Prefeito Municipal, Secretário de Administração)
            - Não adicione textos explicativos
            - Não adicione comentários
            - Apenas o JSON puro como resposta
            PROMPT;
    }

    protected function buildETPPrompt($descricao, $valor): string
    {
        return <<<PROMPT

            Você é um assistente especializado em contratações públicas com base na Lei nº 14.133/2021, IN SEGES nº 05/2017 e nº 65/2021.

            Sua tarefa é gerar um JSON **estruturado e válido** com todos os campos necessários para compor um Estudo Técnico Preliminar (ETP), compatível com o modelo institucional adotado pela Administração Pública.

            ---
             Diretrizes:
            - Use linguagem formal, precisa e técnica.
            - Fundamente as justificativas conforme os princípios da legalidade, eficiência, economicidade, interesse público e inovação.
            - Todos os campos devem estar presentes, mesmo que estejam vazios. Use `""` ou `"–"` para indicar ausência de valor.
            - Retorne apenas o JSON puro (sem crases, markdown ou explicações adicionais).
            - O JSON deve estar **100% válido** e pronto para ser interpretado por máquina.

            Cada campo do JSON deve conter no mínimo 300 caracteres. Use vocabulário técnico-jurídico e fundamente com base na Lei nº 14.133/2021, IN SEGES nº 05/2017 e nº 65/2021. Ao tratar temas como parcelamento, alternativas e riscos, fundamente com exemplos ou justificativas robustas de políticas públicas ou experiências anteriores.

            ---
            Gere um JSON para Estudo Técnico Preliminar com:

            - Objeto: {$descricao}
            - Valor: R\$ {$valor}

            Retorne os dados no formato JSON com os seguintes campos:
            {
                "etp_objeto": "<descrição detalhada do objeto>",
                "etp_justificativa": "<justificativa técnica e legal>",
                "etp_plano_contratacao": "<plano de contratação>",
                "etp_requisitos_funcionais": "<requisitos técnicos, funcionais ou operacionais do objeto da contratação>",
                "etp_requisitos_compatibilidade": "<integrações, compatibilidades ou dependências técnicas que o objeto deve atender>",
                "etp_experiencia_publica": "<experiência com setor público>",
                "etp_prazo_execucao": "<prazo estimado em meses>",
                "etp_forma_pagamento": "<forma de pagamento>",
                "etp_criterios_selecao": "<critérios de seleção>",
                "etp_estimativa_quantidades": "<quantidades estimadas>",
                "etp_alternativa_a": "<primeira alternativa>",
                "etp_alternativa_b": "<segunda alternativa>",
                "etp_alternativa_c": "<terceira alternativa>",
                "etp_analise_comparativa": "<análise comparativa das alternativas>",
                "etp_estimativa_precos": "<estimativa de preços>",
                "etp_solucao_total": "<solução total proposta>",
                "etp_parcelamento": "<possibilidade de parcelamento>",
                "etp_resultados_esperados": "<resultados esperados>",
                "etp_providencias_previas": "<providências prévias>",
                "etp_contratacoes_correlatas": "<contratações correlatas>",
                "etp_impactos_ambientais": "<impactos ambientais>",
                "etp_viabilidade_contratacao": "<viabilidade da contratação>",                
                "etp_previsao_dotacao": "<previsão de dotação orçamentária e programa orçamentário vinculado>",
                "etp_previsao_pca": "<informar se o objeto consta no Plano de Contratações Anual (PCA) ou se será solicitada sua inclusão>",
                "etp_plano_implantacao": "<fases e cronograma de implantação da solução>",
                "etp_conformidade_lgpd": "<medidas de conformidade com a LGPD>",
                "etp_riscos_tecnicos": "<riscos técnicos envolvidos na contratação>",
                "etp_riscos_mitigacao": "<estratégias de mitigação dos riscos identificados>",
                "etp_beneficios_qualitativos": "<benefícios não mensuráveis diretamente em reais, como melhoria na transparência, atendimento ao cidadão, automação>"
            }

            Instruções:
            - Seja específico e técnico
            - Use linguagem formal
            - Apenas retorne o JSON
            PROMPT;
    }

    protected function buildTRPrompt($descricao, $valor): string
    {
        return <<<PROMPT
            Você é um assistente especializado em contratações públicas conforme a Lei nº 14.133/2021, e sua tarefa é gerar um JSON técnico e completo que represente um Termo de Referência (TR) com base no template institucional da Administração Pública.
            
            Instruções obrigatórias:
            Preencha todos os campos, mesmo que com "" ou "–" quando não houver informação.

            Use linguagem técnica e formal, como se fosse um parecer emitido por equipe de planejamento e engenharia.

            Fundamente tudo com base na Lei nº 14.133/2021, IN SEGES nº 5/2017 e boas práticas administrativas.

            Evite jargões vagos como "melhorar o serviço" sem descrição técnica clara.

            Inclua os anexos necessários para assegurar a completude do documento.
                            
            Preencha todos os campos. Onde não houver valor real, insira "–", "", ou "A preencher". Nunca deixe campos faltando ou remova campos do JSON.
            
            Se o objeto for obra ou serviço, detalhe etapas construtivas, insumos e controle de qualidade. Se for fornecimento de bens, especifique lotes, quantidades, forma de entrega e exigências mínimas. Se for solução de TI, inclua infraestrutura, linguagens, banco de dados e APIs.

            'Cada campo do JSON deve conter no mínimo 300 caracteres. Use linguagem técnica, e sempre que possível, cite normas legais ou regulamentadoras.'

            ---
            Gere um JSON para Termo de Referência com:

            - Objeto: {$descricao}
            - Valor: R\$ {$valor}

            Retorne os dados no formato JSON com os seguintes campos:
            {
                "descricao_tecnica": "<descrição técnica detalhada>",
                "justificativa_demanda": "<justificativa da necessidade>",
                "base_legal": "<base legal da contratação>",
                "normas_aplicaveis": "<normas aplicáveis>",
                "execucao_etapas": "<etapas de execução>",
                "tolerancia_tecnica": "<tolerância técnica>",
                "materiais_sustentaveis": "<materiais sustentáveis>",
                "execucao_similar": "<execução similar>",
                "certificacoes": "<certificações necessárias>",
                "cronograma_execucao": "<cronograma de execucao>",
                "pgr_pcmso": "<PGR e PCMSO>",
                "criterio_julgamento": "<critério de julgamento>",
                "garantia_qualidade": "<garantia de qualidade>",
                "painel_fiscalizacao": "<painel de fiscalização>",
                "kpis_operacionais": "<KPIs operacionais>",
                "validacao_kpis": "<validacao kpis>",
                "designacao_formal_fiscal": "<designação formal do fiscal>",
                "penalidades": "<penalidades>",
                "alertas_ia": "<alertas de IA>",
                "anexos_obrigatorios": "<anexos obrigatórios>",
                "transparencia_resumo": "<resumo para transparência>",
                "faq_juridico": "<FAQ jurídico>",
                "prazo_publicacao": "<número de dias úteis para publicação do contrato no Portal da Transparência>",
                "transparencia_contato": "<canal de atendimento ao cidadão: e-mail, telefone ou formulário eletrônico>",                
                "assinatura_formato": "<formato exigido para assinatura (ex: assinatura digital com ICP-Brasil, carimbo do tempo)>",
                "nome_elaborador": "<nome do responsável técnico pela elaboração>",
                "cargo_elaborador": "<cargo do responsável técnico>",
                "nome_autoridade_aprovacao": "<nome da autoridade competente que aprova o TR>",
                "cargo_autoridade_aprovacao": "<cargo da autoridade competente>"
            }

            Instruções:
            - Seja específico e técnico
            - Use linguagem formal
            - Apenas retorne o JSON
            PROMPT;
    }

    protected function buildDemandaPrompt($descricao, $valor): string
    {
        return <<<PROMPT
            Você é um assistente especializado em licitações públicas e contratos administrativos, com profundo conhecimento da Lei nº 14.133/2021.

            Sua tarefa é gerar um JSON estruturado e técnico que represente um Documento de Formalização de Demanda (DFD) com todos os dados necessários para instruir uma contratação pública.
            Considere que o DFD pode se referir a qualquer tipo de contratação pública — bens, serviços, obras, tecnologia da informação, consultoria, etc. Ajuste os campos de justificativa, escopo e requisitos conforme a natureza do objeto.

            ---

            Importante:

            - *Não utilize valores simulados ou fictícios.*
            - Se alguma informação não estiver disponível, insira o caractere "X", "-", para indicar que deverá ser preenchido manualmente.
            - *Não invente dados para "completar" o documento.*
            - Todos os campos devem estar presentes no JSON.

            ---

            Requisitos:

            - Utilize linguagem formal, precisa e técnica.
            - Utilize termos da administração pública e retorne os dados no formato JSON.
            - As informações devem estar completas, compatíveis com práticas de órgãos públicos e com foco em justificar tecnicamente a demanda.

            'Cada campo do JSON deve conter no mínimo 300 caracteres. Use linguagem técnica, e sempre que possível, cite normas legais ou regulamentadoras.'

            ---
            Gere um JSON para Documento de Formalização de Demanda:

            - Objeto: {$descricao}
            - Valor: R\$ {$valor}

            Retorne os dados no formato JSON com os seguintes campos:
            {
                "setor": "<setor solicitante>",
                "departamento": "<departamento>",
                "responsavel": "<responsável pela demanda>",
                "descricaoObjeto": "<descrição do objeto>",
                "valor": "<valor estimado>",
                "origem_fonte": "<origem da fonte>",
                "unidade_nome": "<nome da unidade>",
                "justificativa": "<justificativa da demanda>",
                "impacto_meta": "<impacto na meta>",
                "criterio": "<critério de seleção>",
                "priorizacao_justificativa": "<justificativa da priorização>",
                "escopo": "<escopo do projeto>",
                "requisitos_tecnicos": "<requisitos técnicos>",
                "riscos_ocupacionais": "<riscos ocupacionais>",
                "riscos_normas": "<normas de risco>",
                "riscos_justificativa": "<justificativa dos riscos>",
                "alternativa_a": "<primeira alternativa>",
                "alternativa_b": "<segunda alternativa>",
                "alternativa_conclusao": "<conclusão das alternativas>",
                "inerciarisco": "<risco de inércia>",
                "inerciaplano": "<plano de inércia>",
                "prazo_execucao": "<prazo de execução>",
                "forma_pagamento": "<forma de pagamento>",
                "prazo_vigencia": "<prazo de vigência>",
                "condicoes_pagamento": "<condições de pagamento>",
                "ods_vinculados": "<ODS vinculados>",
                "acao_sustentavel": "<ação sustentável>",
                "ia_duplicidade": "<verificação de duplicidade>",
                "ia_validacao": "<validação>",
                "transparencia_resumo": "<resumo para transparência>",
                "transparencia_faq": "<FAQ para transparência>",
                "transparencia_prazo": "<prazo de transparência>",
                "assinatura_formato": "<formato de assinatura>"
            }

            Instruções:
            - Seja específico e técnico
            - Use linguagem formal
            - Apenas retorne o JSON
            PROMPT;
    }

    protected function buildRiscoPrompt($descricao): string
    {
        return <<<PROMPT
            Você é um especialista em contratações públicas e gestão de riscos, com base na Lei nº 14.133/2021.

            Sua tarefa é gerar uma matriz de risco no formato JSON, com base no objeto da contratação informado, contendo:

            - Dados gerais do processo
            - Lista de ao menos 5 riscos relevantes
            - Classificação formal e específica de impacto e probabilidade
            - Ações preventivas e contingenciais bem detalhadas
            - Indicação de responsáveis
            
            Instruções adicionais:

            - Liste pelo menos **5 riscos reais e prováveis** ao tipo de contratação.
            - Considere **pelo menos um risco de descumprimento contratual** e **um relacionado à LGPD**, se o objeto envolver dados pessoais.
            - Use **linguagem formal, técnica e precisa**.
            - As ações devem ser detalhadas (o quê, como, por quem, quando).
            - Classifique impacto e probabilidade usando somente os valores padronizados: **baixo | médio | alto**.
            - IMPORTANTE: Mantenha a estrutura exata do JSON, incluindo todos os campos para cada risco.
            - NÃO modifique a estrutura do JSON ou adicione campos extras.

            Considere que o objeto pode envolver qualquer área da Administração Pública (obras, serviços, tecnologia, fornecimento, consultorias etc). Ajuste os riscos com base na natureza do objeto informado.
            'Cada campo do JSON deve conter no mínimo 300 caracteres. Use linguagem técnica, e sempre que possível, cite normas legais ou regulamentadoras.'

            ---
            Gere uma matriz de risco JSON com:

            - Objeto: {$descricao}

            Retorne os dados no formato JSON com a seguinte estrutura:
            {
                "processo_administrativo": "<número do processo>",
                "objeto_matriz": "<objeto da matriz>",
                "data_inicio_contratacao": "<data de início>",
                "unidade_responsavel": "<unidade responsável>",
                "fase_analise": "<fase de análise>",
                "data_aprovacao": "<data de aprovacao>",
                "riscos": [
                    {
                        "seq": "<número sequencial>",
                        "evento": "<descrição do evento de risco>",
                        "dano": "<descrição do dano>",
                        "impacto": "<impacto do risco (ex: baixo | médio | alto)>",
                        "probabilidade": "<probabilidade de ocorrência (ex: baixo | médio | alto)>",
                        "acao_preventiva": "<ação preventiva>",
                        "responsavel_preventiva": "<responsável pela ação preventiva>",
                        "acao_contingencia": "<ação de contingência>",
                        "responsavel_contingencia": "<responsável pela ação de contingência>"
                    }
                ]
            }

            Instruções:
            - Liste pelo menos 5 riscos relevantes
            - Seja específico e técnico
            - Use linguagem formal
            - Apenas retorne o JSON
            PROMPT;
    }

    public function setInstitutionalData($templateProcessor, Request $request)
    {
        try {
            $data = $this->generateAiData('institutional', $request);
            
            // Preenche os dados básicos
            $templateProcessor->setValue('cidade', $data['cidade']);
            $templateProcessor->setValue('cidade_maiusculo', strtoupper($data['cidade']));
            $templateProcessor->setValue('endereco', $data['endereco']);
            $templateProcessor->setValue('cep', $data['cep']);
            $templateProcessor->setValue('nome_autoridade', $data['nome_autoridade']);
            $templateProcessor->setValue('cargo_autoridade', $data['cargo_autoridade']);
            $templateProcessor->setValue('data_extenso', $data['data_extenso']);

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
            Log::info("Municipality recebido via request: {$municipality}");
            // Normaliza o nome do município
            $filename = $this->normalizeMunicipalityName($municipality) . '.png';

            $brasaoPath = public_path('brasoes/' . $filename);

            // Verifica se o brasão específico existe
            if (!file_exists($brasaoPath)) {
                Log::info("Brasão específico não encontrado para {$filename}, usando padrão");
                $brasaoPath = public_path('brasoes/default.png');
            }

            // Verifica se o arquivo existe antes de tentar processá-lo
            if (file_exists($brasaoPath)) {
                Log::info("Processando brasão: {$brasaoPath}");
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

} 
