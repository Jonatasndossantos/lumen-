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

        $jsonSchema = $this->getJsonSchema($type);
        
        Log::debug('Schema enviado:', ['schema' => $jsonSchema]);
        Log::debug('prompt enviado:', ['prompt' => $prompt]);
        Log::debug('system enviado:', ['system' => $this->getSystemRole($type)]);


        try {
            $response = $open_ai->chat([
                'model' => 'gpt-4o-2024-08-06',
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
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema
                ],
                'temperature' => 0.7,
                'max_tokens' => 8000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);
            
            Log::info("Resposta recebida: " . $response);


            $responseArray = json_decode($response, true);
            
            if (!isset($responseArray['choices'][0]['message']['content'])) {
                Log::error("Content não encontrado na resposta");
                throw new \Exception('No content in response');
            }

            $data = json_decode($responseArray['choices'][0]['message']['content'], true);

            if ($data === null) {
                Log::error("Falha ao decodificar content como JSON", [
                    'json_error' => json_last_error_msg()
                ]);
                throw new \Exception('Failed to decode content as JSON');
            }

            if (!is_array($data)) {
                Log::error("Dados decodificados não são um array", [
                    'type' => gettype($data)
                ]);
                throw new \Exception('Content did not decode to an array');
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
            'institutional' => "Para os dados institucionais de arquivos de licitações, o endereço nao deve ser informado com CEP",

            'preliminaryStudy' => "Gere um Estudo Técnico Preliminar (ETP) completo, estruturado em formato JSON conforme o schema fornecido. Use linguagem formal, técnica e precisa. Fundamente os argumentos com base legal, técnica e administrativa. O ETP será utilizado como modelo editável — portanto:\n\nREGRAS E CONDUTAS:\n- **Não invente nomes de pessoas, empresas, municípios, leis, valores, prazos ou fontes**.\n- **Use placeholders sempre que necessário**, como:\n  - `xxxxx`\n  - `[nome protegido]`\n  - `[ex: R$ 120.000,00]`\n  - `[ex: Prefeitura Municipal de ...]`\n- **Nunca preencha dados sensíveis ou simulados**.\n- **Respeite a estrutura e os campos exigidos pelo schema**.\n- **Todos os campos devem conter conteúdo completo, objetivo e técnico**.\n- **Evite linguagem genérica, duplicação de frases ou expressões vagas**.\n- O documento deve manter **coerência lógica entre as seções**.\n\nREQUISITOS DE CONTEÚDO:\n- Cada campo deve ter NO MÍNIMO o número de caracteres especificado no schema (entre 400 e 600 caracteres)\n- O conteúdo deve ser técnico, detalhado e fundamentado\n- Evite conclusões rápidas ou superficiais\n- Expanda argumentos técnicos, comparativos e justificativas\n- O total gerado deve usar aproximadamente 4000 a 6000 tokens\n- Mantenha profundidade analítica, clareza e conformidade legal\n- O conteúdo será revisado por humanos e adaptado à realidade local antes da publicação\n\nIMPORTANTE: O documento final deve ter um volume significativo de conteúdo técnico e fundamentado. Cada seção deve ser desenvolvida com profundidade e detalhamento adequados.
            
                                    O conteúdo gerado deve seguir as diretrizes abaixo:\n\n1. Use linguagem formal, técnica e impessoal, adequada a documentos oficiais da administração pública.\n\n2. O texto deve ser aplicável a qualquer tipo de contratação pública (bens, serviços, obras, locações, assinaturas, etc.), sem menções específicas a tecnologias ou setores.\n\n3. Evite repetições e generalizações. Cada campo deve tratar apenas do seu escopo, com foco claro.\n\n4. Estruture o conteúdo em parágrafos curtos e objetivos. Quando apropriado, utilize listas temáticas, tópicos numerados ou quadros conceituais — especialmente para requisitos, riscos, funções e critérios.\n\n5. Nunca invente dados. Se uma informação for incerta, inexistente ou ainda não definida, sinalize com \"informação a ser preenchida\" ou equivalente.\n\n6. Sempre observe os princípios da Lei nº 14.133/2021, como eficiência, economicidade, transparência, interesse público e inovação.\n\n7. Evite expressões subjetivas como \"muito importante\", \"altamente recomendado\", \"excelente\", entre outras. Prefira descrições técnicas e fundamentadas.\n\n8. Quando possível, relacione a informação à gestão pública, ao planejamento estratégico institucional ou à continuidade de serviços.\n\n9. Sempre que o campo comportar múltiplos elementos (requisitos, riscos, critérios, funcionalidades, etc.), organize o conteúdo em listas temáticas, tópicos numerados ou parágrafos curtos, com uso de marcadores internos, se permitido. Isso favorece a leitura, revisão e adaptação posterior.\n\n10. Cada campo deve tratar apenas do conteúdo correspondente ao seu escopo definido no schema. Evite repetir ideias, justificativas ou informações que já aparecem em outros campos do ETP.\n\n11. Utilize terminologia consistente ao longo do ETP. Sempre que um conceito, solução, alternativa ou funcionalidade for mencionado em mais de um campo, mantenha coerência na forma como ele é descrito, mesmo sem repetir textos. A narrativa deve parecer contínua e articulada.
                                    ",

            'referenceTerms' => "Gere um Termo de Referência (TR) completo e bem fundamentado em formato JSON, conforme o schema de entrada. O texto deve ser técnico, claro e detalhado, com foco nos seguintes critérios:
                    REGRAS DE CONDUTA:
                    - **Não invente dados**: nomes, empresas, valores, normas específicas ou dispositivos legais.
                    - **Use placeholders** como: `xxxxx`, `[ex: 200 unidades]`, `[nome protegido]`, etc.
                    - **Nunca simule valores ou condições específicas não fornecidas.**
                    - Todos os campos devem estar preenchidos com profundidade técnica, respeitando a função de *template editável*.

                    ORIENTAÇÕES DE CONTEÚDO:
                    - **Descrição técnica**: detalhada, específica, sem jargões genéricos como `melhorar o serviço`.
                    - **Normas legais**: citar somente quando conhecidas ou indicar `[ex: norma técnica aplicável]`.
                    - **Execução**: etapas, cronograma e controle de qualidade (para obras ou serviços).
                    - **Fornecimento de bens**: indicar lotes, quantidades, forma de entrega e exigências mínimas.
                    - **TI**: detalhar infraestrutura, linguagens, banco de dados, APIs e segurança.
                    - **Critérios de julgamento**: claros, objetivos e compatíveis com a modalidade licitatória.
                    - **Garantias e penalidades**: proporcionais, justificadas tecnicamente.
                    - **Anexos**: apenas os relevantes ao objeto e exigíveis pela norma.
                    Gere cada campo com no mínimo 1000 caracteres (ou 200 palavras). Evite respostas curtas.

                    Retorne em JSON com linguagem institucional e técnica, pronto para ser adaptado ao caso concreto.",

            'demand' => "Gere um Documento de Formalização de Demanda (DFD) completo e bem fundamentado, em formato JSON, conforme o schema definido. O conteúdo deve atender os seguintes critérios:
                        CONTEÚDO OBRIGATÓRIO:
                        - **Setor e departamento**: indicar claramente onde a demanda se origina.
                        - **Objeto da contratação**: descrição técnica clara e objetiva do que se pretende contratar.
                        - **Justificativa**: deve conter base técnica e legal, demonstrando o interesse público e a necessidade concreta.
                        - **Escopo técnico e requisitos**: detalhar as funcionalidades, exigências, normas e compatibilidades.
                        - **Alternativas consideradas**: listar opções viáveis, comparar cenários e justificar a escolha feita.
                        - **Riscos e mitigações**: identificar riscos ocupacionais, operacionais e estratégicos, propondo medidas de contenção.
                        - **ODS e sustentabilidade**: vincular a política pública aos Objetivos de Desenvolvimento Sustentável, quando aplicável.
                        - **Prazos, vigência e pagamento**: propor cronograma e modelo de pagamento compatível com a execução esperada.

                        REGRAS DE SEGURANÇA:
                        - **Não invente nomes, prazos, valores, setores, normas ou datas**.
                        - **Use placeholders sempre que necessário**, como:
                        - `xxxxx`
                        - `[ex: R$ 25.000,00]`
                        - `[nome do setor requisitante]`
                        - `[data prevista de início]`
                        - **Não simule dados**. O DFD gerado será editado por humanos depois.

                        Gere cada campo com no mínimo 1000 caracteres (ou 200 palavras). Evite respostas curtas.

                        OUTRAS ORIENTAÇÕES:
                        - Mantenha coerência e lógica entre as seções.
                        - Use linguagem formal, técnica e clara.
                        - Todos os campos definidos no schema devem ser preenchidos com profundidade e objetividade.",

            'riskMatrix' => "Gere uma Matriz de Risco completa em formato JSON, conforme o schema definido. O conteúdo deve conter os seguintes elementos:
                        CONTEÚDO ESSENCIAL:
                        - **Identificação do processo administrativo**: referência formal da contratação.
                        - **Objeto e unidade responsável**: descrição objetiva do que está sendo contratado e qual setor responde.
                        - **Lista de riscos**: pelo menos 5 riscos específicos, relevantes e mensuráveis.
                        - Incluir **pelo menos um risco de descumprimento contratual**
                        - Incluir **um risco relacionado à LGPD**, caso o objeto envolva dados pessoais
                        - **Classificação formal**:
                        - Impacto: `baixo | médio | alto`
                        - Probabilidade: `baixo | médio | alto`
                        - **Ações preventivas**: detalhadas e compatíveis com o risco identificado.
                        - **Ações contingenciais**: viáveis e coerentes, com plano de ação claro.
                        - **Responsáveis**: indicar o setor, função ou área responsável por cada ação.

                        REGRAS DE SEGURANÇA:
                        - **Nunca invente nomes de pessoas, órgãos, empresas, leis específicas ou datas**.
                        - **Use placeholders quando necessário**, como:
                        - `xxxxx`
                        - `[ex: Unidade de TI]`
                        - `[ex: vazamento de dados de cidadãos]`
                        - `[ex: multa contratual de até 5%]`
                        - **Não simule valores nem condições específicas não fornecidas.**

                        Gere cada campo com no mínimo 1000 caracteres (ou 200 palavras). Evite respostas curtas.

                        ORIENTAÇÕES FINAIS:
                        - Mantenha coerência entre riscos, ações e responsáveis.
                        - Use linguagem técnica, clara e institucional.
                        - Preencha todos os campos definidos no schema.
                        - O documento gerado deve ser completo, mas editável por humanos.",

            default => "Retorne os dados no formato JSON conforme a estrutura definida."
        };

        return $basePrompt . $typeSpecificPrompt;
    }

    protected function getSystemRole(string $type): string
    {
        $baseInstructions = "Você é um assistente especialista em licitações públicas brasileiras, com profundo conhecimento da Lei nº 14.133/2021, IN SEGES nº 05/2017 e nº 65/2021. Use linguagem formal, técnica e precisa. Use vocabulário técnico-jurídico e fundamente com base na legislação aplicável. IMPORTANTE: Você DEVE retornar sua resposta usando o tool_call especificado, preenchendo TODOS os campos obrigatórios.";

        $typeSpecificInstructions = match ($type) {
            'institutional' => "Gere dados institucionais para documentos oficiais de municípios brasileiros. O endereço deve ser realista e complementado se necessário. O nome da autoridade não pode ser fictício, use '[nome protected]'. O cargo deve ser condizente com a instituição (ex: Prefeito Municipal, Secretário de Administração).",
            
            'preliminaryStudy' => "Você é um especialista em contratações públicas, com domínio da Lei nº 14.133/2021 e da IN SEGES nº 05/2017. Seu papel é redigir Estudos Técnicos Preliminares (ETPs) detalhados, técnicos, fundamentados, objetivos e formalmente estruturados, conforme as melhores práticas da Administração Pública. Você deve sempre seguir padrões legais, evitar invenções e respeitar as instruções fornecidas. IMPORTANTE: Cada campo do ETP deve ser desenvolvido com profundidade técnica e extensão adequada, garantindo um volume significativo de conteúdo (mínimo de 400-600 caracteres por campo). O documento final deve ser completo, técnico e fundamentado, evitando respostas curtas ou superficiais.",
            
            'referenceTerms' => "Você é um especialista em contratações públicas com foco na elaboração de Termos de Referência (TR) conforme a Lei 14.133/2021. Sua função é redigir documentos técnicos e completos, alinhados ao modelo institucional da Administração Pública. Sempre considere o tipo de objeto: serviço, obra, fornecimento de bens ou solução de tecnologia da informação. Produza conteúdo estruturado, objetivo, juridicamente fundamentado e compatível com editabilidade posterior. Não invente informações.",
            
            'demand' => "Você é um especialista em planejamento de contratações públicas e elaboração de Documentos de Formalização de Demanda (DFD), com base na Lei nº 14.133/2021. Seu papel é estruturar diagnósticos claros, técnicos, objetivos e juridicamente embasados para justificar demandas da Administração Pública. A redação deve ser formal, técnica e editável. Nunca invente dados.",
            
            'riskMatrix' => "Você é um analista de riscos em contratações públicas, especializado na elaboração de matrizes de risco conforme a Lei nº 14.133/2021. Sua tarefa é identificar, classificar e estruturar riscos relevantes com linguagem técnica, objetiva e formal, com foco em contratações administrativas. O documento gerado será um template editável. Nunca invente dados ou simule informações concretas.",
            
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
            $templateProcessor->setValue('nome_elaborador', $data['nome_elaborador']);
            $templateProcessor->setValue('cargo_autoridade', $data['cargo_autoridade']);
            $templateProcessor->setValue('data_extenso', $data['data_extenso']);
            $templateProcessor->setValue('data_aprovacao', $data['data_aprovacao']);
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

    protected function getEtpFieldDescription(string $field): array
    {
        return match ($field) {
                'etp_objeto' => [
                    'type' => 'string',
                    'description' => 'Descreva tecnicamente o objeto da contratação, incluindo sua natureza, finalidade e escopo. Indique com clareza o que será adquirido, contratado ou executado, sem detalhar requisitos ou justificativas. O foco aqui é responder: “O que será contratado e com qual objetivo prático?”.',
                    'minLength' => 1000,
                ],
                'etp_justificativa' => [
                    'type' => 'string',
                    'description' => 'Explique por que a contratação é necessária, com base em fatores técnicos, operacionais ou legais. Apresente o contexto atual, os problemas enfrentados e os ganhos esperados com a solução, relacionando-os ao interesse público e aos princípios da administração. Não repita o objeto; concentre-se no motivo da contratação.',
                    'minLength' => 1000,
                ],
                'etp_requisitos_funcionais' => [
                    'type' => 'string',
                    'description' => 'Liste e explique as funções, serviços, entregas ou comportamentos esperados do objeto contratado. Indique o que ele deve obrigatoriamente fazer ou oferecer, sem tratar de compatibilidades externas ou justificativas. Priorize estrutura por tópicos quando descrever múltiplas funcionalidades.',
                    'minLength' => 1200,
                ],
                'etp_requisitos_compatibilidade' => [
                    'type' => 'string',
                    'description' => 'Informe com o que o objeto deve ser compatível: sistemas já existentes, processos administrativos, normas técnicas, formatos de integração, entre outros. Este campo foca apenas nas dependências externas e pré-condições operacionais da solução.',
                    'minLength' => 1000,
                ],
                'etp_experiencia_publica' => [
                    'type' => 'string',
                    'description' => 'Relate, de forma objetiva, experiências anteriores da administração pública com contratações similares. Destaque resultados, aprendizados, dificuldades ou boas práticas observadas. Podem ser do próprio órgão ou de outros entes, desde que relevantes como referência para a contratação atual.',
                    'minLength' => 1000,
                ],
                'etp_prazo_execucao' => [
                    'type' => 'string',
                    'description' => 'Informe o prazo estimado para execução do objeto, com justificativa técnica e administrativa. Indique o tempo necessário para entrega, implantação ou vigência do contrato, considerando a complexidade, as etapas previstas e os recursos envolvidos. Se aplicável, descreva fases, marcos intermediários ou cronograma básico.',
                    'minLength' => 800,
                ],
                'etp_forma_pagamento' => [
                    'type' => 'string',
                    'description' => 'Descreva como será feita a remuneração do contratado. Indique a periodicidade (mensal, por etapa, por entrega), as condições para o pagamento (relatórios, comprovações, medições), e quaisquer critérios de vinculação com o desempenho ou resultado. O foco é garantir clareza sobre o fluxo financeiro contratual e sua justificativa.',
                    'minLength' => 800,
                ],
                'etp_criterios_selecao' => [
                    'type' => 'string',
                    'description' => 'Apresente os critérios previstos para seleção do fornecedor, com base na natureza do objeto e na legislação vigente. Pode incluir julgamento por menor preço, técnica e preço, maior desconto, entre outros. Descreva também os critérios técnicos exigíveis (experiência, qualificação, amostras, entrevistas, etc.), desde que compatíveis com a etapa preliminar.',
                    'minLength' => 1000,
                ],
                'etp_estimativa_quantidades' => [
                    'type' => 'string',
                    'description' => 'Indique a estimativa de quantidades envolvidas na contratação, com base em dados históricos, estudos de demanda, levantamentos técnicos ou projeções da administração. Evite valores arbitrários ou genéricos e apresente os quantitativos de forma fundamentada, mesmo que sejam preliminares.',
                    'minLength' => 1000,
                ],
                'etp_alternativa_a' => [
                    'type' => 'string',
                    'description' => 'Análise técnica e comparativa da primeira alternativa de solução identificada para atender à demanda. Descreva suas vantagens, limitações, impactos operacionais e viabilidade. A abordagem deve considerar custo, eficiência, aderência à necessidade pública e eventuais riscos associados.',
                    'minLength' => 1200,
                ],
                'etp_alternativa_b' => [
                    'type' => 'string',
                    'description' => 'Análise técnica da segunda alternativa viável para resolver o problema identificado. Detalhe seus principais atributos, os possíveis ganhos em relação à alternativa anterior e os obstáculos previstos. A redação deve ser imparcial, crítica e baseada na lógica de custo-benefício.',
                    'minLength' => 1200,
                ],
                'etp_alternativa_c' => [
                    'type' => 'string',
                    'description' => 'Exposição da terceira alternativa possível para atender à necessidade da contratação. Apresente seu diferencial em relação às demais, indicando aspectos de custo, agilidade, sustentabilidade, continuidade ou adequação legal. Avalie também os riscos ou desvantagens que a tornam menos ou mais atrativa.',
                    'minLength' => 1200,
                ],
                'etp_analise_comparativa' => [
                    'type' => 'string',
                    'description' => 'Compare, de forma objetiva e técnica, as alternativas de solução analisadas. Avalie os aspectos de custo, eficácia, viabilidade, complexidade, continuidade, riscos e aderência ao interesse público. A comparação deve orientar a decisão pela alternativa mais vantajosa, sem juízo definitivo (que será feito no campo da solução total).',
                    'minLength' => 1200,
                ],
                'etp_estimativa_precos' => [
                    'type' => 'string',
                    'description' => 'Apresente a estimativa de preços da contratação, com base em fontes válidas como cotações de mercado, sistemas públicos (PNCP, Painel de Preços), orçamentos anteriores, entre outros. Justifique os valores estimados com clareza, considerando a metodologia de cálculo, unidades envolvidas e adequação à realidade do objeto. Não incluir dados fictícios — sinalizar onde for necessário preencher depois.',
                    'minLength' => 1200,
                ],
                'etp_solucao_total' => [
                    'type' => 'string',
                    'description' => 'Descreva a solução escolhida após análise das alternativas. Detalhe como ela atenderá integralmente às necessidades da administração pública, considerando escopo, abrangência, resultados esperados e integração com estruturas existentes. O conteúdo deve refletir a escolha final, justificada pela vantagem técnica e/ou econômica.',
                    'minLength' => 1200,
                ],
                'etp_parcelamento' => [
                    'type' => 'string',
                    'description' => 'Justifique, com base técnica e legal, se a contratação poderá ou não ser parcelada. Considere fatores como natureza do objeto, ampliação da competitividade, divisão de risco, viabilidade operacional e limites legais previstos na Lei nº 14.133/2021. A argumentação deve deixar claro se o parcelamento é viável, vantajoso ou desaconselhável.',
                    'minLength' => 1000,
                ],
                'etp_resultados_esperados' => [
                    'type' => 'string',
                    'description' => 'Indique os benefícios concretos que a contratação deve gerar, com metas e indicadores mensuráveis sempre que possível. Pode incluir melhoria de processos, redução de custos, aumento da transparência, impacto social, entre outros. O foco é demonstrar o valor público da contratação com base em evidências e expectativas reais.',
                    'minLength' => 1200,
                ],
                'etp_providencias_previas' => [
                    'type' => 'string',
                    'description' => 'Descreva as ações já realizadas antes da formalização da contratação, como reuniões técnicas, levantamentos, diagnósticos, estudos de viabilidade ou comunicações internas. O objetivo é demonstrar que houve planejamento prévio e que a demanda não surgiu de forma improvisada.',
                    'minLength' => 1000,
                ],
                'etp_contratacoes_correlatas' => [
                    'type' => 'string',
                    'description' => 'Informe se existem outras contratações relacionadas, simultâneas ou interdependentes que impactem a execução desta demanda. Pode incluir serviços complementares, aquisições vinculadas, manutenção de soluções já existentes, entre outros. A redação deve ser objetiva e voltada à demonstração de coerência entre as iniciativas do órgão.',
                    'minLength' => 1000,
                ],
                'etp_impactos_ambientais' => [
                    'type' => 'string',
                    'description' => 'Avalie os impactos ambientais que podem decorrer da contratação, direta ou indiretamente. Considere fatores como uso de recursos naturais, descarte de resíduos, consumo energético, logística e pegada ecológica. Sempre que possível, indique medidas para evitar, reduzir ou compensar esses impactos. A análise deve ser proporcional à natureza do objeto.',
                    'minLength' => 1000,
                ],
                'etp_viabilidade_contratacao' => [
                    'type' => 'string',
                    'description' => 'Analise a viabilidade da contratação sob os aspectos técnico, legal e orçamentário. Explique se a solução proposta pode ser executada com os recursos disponíveis, se atende aos requisitos legais e normativos, e se há compatibilidade com as capacidades operacionais do órgão. O texto deve consolidar os fatores que sustentam a decisão de contratar.',
                    'minLength' => 1200,
                ],
                'etp_previsao_pca' => [
                    'type' => 'string',
                    'description' => 'Informe se a contratação está prevista no Plano de Contratações Anual (PCA) do órgão. Indique o código ou item correspondente (se houver) e como essa previsão se alinha ao planejamento estratégico institucional. Se não estiver formalmente prevista, justifique a urgência ou a necessidade superveniente da demanda.',
                    'minLength' => 800,
                ],
                'etp_conformidade_lgpd' => [
                    'type' => 'string',
                    'description' => 'Descreva como a contratação observará os princípios e exigências da Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Indique se haverá tratamento de dados pessoais, quais medidas de segurança serão exigidas do contratado, e como será garantida a confidencialidade, integridade e acesso controlado às informações.',
                    'minLength' => 1000,
                ],
                'etp_riscos_tecnicos' => [
                    'type' => 'string',
                    'description' => 'Liste e analise os riscos técnicos identificados na futura execução contratual. Avalie as consequências potenciais de falhas técnicas, atrasos, incompatibilidades, indisponibilidades ou interrupções. A redação deve ser clara, com foco nos impactos para a continuidade dos serviços públicos e para os objetivos da contratação.',
                    'minLength' => 1200,
                ],
                'etp_riscos_mitigacao' => [
                    'type' => 'string',
                    'description' => 'Descreva as estratégias de mitigação e os planos de contingência para os riscos técnicos levantados. Indique ações preventivas, testes, cláusulas contratuais, controles operacionais ou medidas emergenciais que serão adotadas para reduzir a probabilidade e o impacto dos riscos. Preferencialmente, organizar o conteúdo por tópicos.',
                    'minLength' => 1200,
                ],
                'etp_beneficios_qualitativos' => [
                    'type' => 'string',
                    'description' => 'Apresente os benefícios não mensuráveis esperados com a contratação, como melhoria da imagem institucional, aumento da confiança dos usuários, fortalecimento da transparência, satisfação de servidores ou cidadãos, estímulo à inovação, entre outros. Mesmo sem indicadores, os efeitos esperados devem ser descritos com objetividade.',
                    'minLength' => 1000,
                ],
            
            default => [
                'type' => 'string',
                'description' => 'Campo ' . $field,
                'minLength' => 800
            ]
        };
    }

    protected function getDfdFieldDescription(string $field): array
    {
        return match ($field) {
            'setor' => [
                'type' => 'string',
                'description' => 'Nome do setor requisitante da demanda.',
                'minLength' => 100
            ],
            'departamento' => [
                'type' => 'string',
                'description' => 'Departamento ou unidade responsável.',
                'minLength' => 100
            ],
            'responsavel' => [
                'type' => 'string',
                'description' => 'Nome do responsável pela demanda.',
                'minLength' => 100
            ],
            'descricaoObjeto' => [
                'type' => 'string',
                'description' => 'Descrição do objeto da contratação.',
                'minLength' => 400
            ],
            'valor' => [
                'type' => 'string',
                'description' => 'Valor estimado da contratação.',
                'minLength' => 50
            ],
            'origem_fonte' => [
                'type' => 'string',
                'description' => 'Fonte da demanda identificada.',
                'minLength' => 100
            ],
            'unidade_nome' => [
                'type' => 'string',
                'description' => 'Unidade ou setor de origem da demanda.',
                'minLength' => 100
            ],
            'justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa da necessidade da contratação.',
                'minLength' => 400
            ],
            'impacto_meta' => [
                'type' => 'string',
                'description' => 'Meta de impacto esperada com a contratação.',
                'minLength' => 300
            ],
            'escopo' => [
                'type' => 'string',
                'description' => 'Escopo dos serviços a serem contratados.',
                'minLength' => 400
            ],
            'requisitos_tecnicos' => [
                'type' => 'string',
                'description' => 'Requisitos técnicos envolvidos na contratação.',
                'minLength' => 400
            ],
            'riscos_ocupacionais' => [
                'type' => 'string',
                'description' => 'Riscos ocupacionais identificados.',
                'minLength' => 300
            ],
            'riscos_normas' => [
                'type' => 'string',
                'description' => 'Normas de segurança ou técnicas aplicáveis.',
                'minLength' => 200
            ],
            'riscos_justificativa' => [
                'type' => 'string',
                'description' => 'Justificativa dos riscos e medidas associadas.',
                'minLength' => 300
            ],
            'alternativas' => [
                'type' => 'string',
                'description' => 'Análise de alternativas técnicas viáveis.',
                'minLength' => 400
            ],
            'alternativa_conclusao' => [
                'type' => 'string',
                'description' => 'Conclusão da análise de alternativas.',
                'minLength' => 300
            ],
            'inerciarisco' => [
                'type' => 'string',
                'description' => 'Risco decorrente da não contratação (inércia).',
                'minLength' => 300
            ],
            'inerciaplano' => [
                'type' => 'string',
                'description' => 'Plano de contingência para o risco de inércia.',
                'minLength' => 300
            ],
            'prazo_execucao' => [
                'type' => 'string',
                'description' => 'Prazo estimado para execução dos serviços.',
                'minLength' => 100
            ],
            'forma_pagamento' => [
                'type' => 'string',
                'description' => 'Forma de pagamento proposta.',
                'minLength' => 100
            ],
            'prazo_vigencia' => [
                'type' => 'string',
                'description' => 'Prazo de vigência do contrato.',
                'minLength' => 100
            ],
            'condicoes_pagamento' => [
                'type' => 'string',
                'description' => 'Condições específicas de pagamento.',
                'minLength' => 150
            ],
            'ods_vinculados' => [
                'type' => 'string',
                'description' => 'Objetivos de Desenvolvimento Sustentável (ODS) relacionados.',
                'minLength' => 100
            ],
            'acao_sustentavel' => [
                'type' => 'string',
                'description' => 'Ações sustentáveis relacionadas à contratação.',
                'minLength' => 150
            ],
            'ia_duplicidade' => [
                'type' => 'string',
                'description' => 'Análise de duplicidade identificada por IA.',
                'minLength' => 100
            ],
            'ia_validacao' => [
                'type' => 'string',
                'description' => 'Validação da contratação no PPA/LOA via IA.',
                'minLength' => 100
            ],
            'transparencia_prazo' => [
                'type' => 'string',
                'description' => 'Prazo para publicação e transparência pública.',
                'minLength' => 50
            ],
            'assinatura_formato' => [
                'type' => 'string',
                'description' => 'Formato e método da assinatura digital.',
                'minLength' => 50
            ],
            default => [
                'type' => 'string',
                'description' => 'Campo ' . $field,
                'minLength' => 100
            ]
        };
    }

    protected function getTrFieldDescription(string $field): array
    {
        return match ($field) {
            'descricao_tecnica' => [
                'type' => 'string',
                'description' => 'Descrição técnica do objeto da contratação.',
                'minLength' => 500
            ],
            'justificativa_demanda' => [
                'type' => 'string',
                'description' => 'Justificativa da demanda e necessidade da contratação.',
                'minLength' => 500
            ],
            'base_legal' => [
                'type' => 'string',
                'description' => 'Base legal que fundamenta a contratação.',
                'minLength' => 300
            ],
            'normas_aplicaveis' => [
                'type' => 'string',
                'description' => 'Normas e regulamentações aplicáveis.',
                'minLength' => 300
            ],
            'execucao_etapas' => [
                'type' => 'string',
                'description' => 'Descrição das etapas de execução do objeto.',
                'minLength' => 500
            ],
            'tolerancia_tecnica' => [
                'type' => 'string',
                'description' => 'Tolerâncias técnicas permitidas na execução.',
                'minLength' => 300
            ],
            'materiais_sustentaveis' => [
                'type' => 'string',
                'description' => 'Materiais sustentáveis e logística reversa.',
                'minLength' => 300
            ],
            'cronograma_execucao' => [
                'type' => 'string',
                'description' => 'Prazos e cronograma físico-financeiro de execução.',
                'minLength' => 400
            ],
            'execucao_similar' => [
                'type' => 'string',
                'description' => 'Exigência de execução similar anterior.',
                'minLength' => 300
            ],
            'certificacoes' => [
                'type' => 'string',
                'description' => 'Certificações obrigatórias exigidas do contratado.',
                'minLength' => 200
            ],
            'pgr_pcmso' => [
                'type' => 'string',
                'description' => 'Exigência de PGR e PCMSO conforme legislação.',
                'minLength' => 200
            ],
            'criterio_julgamento' => [
                'type' => 'string',
                'description' => 'Critério de julgamento das propostas.',
                'minLength' => 300
            ],
            'garantia_qualidade' => [
                'type' => 'string',
                'description' => 'Garantias de qualidade exigidas na contratação.',
                'minLength' => 300
            ],
            'painel_fiscalizacao' => [
                'type' => 'string',
                'description' => 'Painel ou mecanismo de fiscalização do contrato.',
                'minLength' => 300
            ],
            'kpis_operacionais' => [
                'type' => 'string',
                'description' => 'Indicadores de desempenho (KPIs) da execução.',
                'minLength' => 300
            ],
            'designacao_formal_fiscal' => [
                'type' => 'string',
                'description' => 'Designação do fiscal do contrato.',
                'minLength' => 200
            ],
            'validacao_kpis' => [
                'type' => 'string',
                'description' => 'Critérios de medição e validação dos KPIs.',
                'minLength' => 300
            ],
            'penalidades' => [
                'type' => 'string',
                'description' => 'Penalidades aplicáveis em caso de inadimplemento.',
                'minLength' => 300
            ],
            'alertas_ia' => [
                'type' => 'string',
                'description' => 'Alertas gerados por IA relacionados ao contrato.',
                'minLength' => 200
            ],
            'anexos_obrigatorios' => [
                'type' => 'string',
                'description' => 'Lista de anexos obrigatórios para o TR.',
                'minLength' => 200
            ],
            'transparencia_resumo' => [
                'type' => 'string',
                'description' => 'Resumo público para fins de transparência.',
                'minLength' => 300
            ],
            'faq_juridico' => [
                'type' => 'string',
                'description' => 'FAQ com esclarecimentos jurídicos relevantes.',
                'minLength' => 300
            ],
            'assinatura_formato' => [
                'type' => 'string',
                'description' => 'Formato da assinatura digital exigida.',
                'minLength' => 100
            ],
            'prazo_publicacao' => [
                'type' => 'string',
                'description' => 'Prazo em dias úteis para publicação do TR.',
                'minLength' => 50
            ],
            'transparencia_contato' => [
                'type' => 'string',
                'description' => 'Canal de atendimento ao cidadão para dúvidas.',
                'minLength' => 100
            ],
            default => [
                'type' => 'string',
                'description' => 'Campo ' . $field,
                'minLength' => 200
            ]
        };
    }

    protected function getRiscoFieldDescription(string $field): array
    {
        return match ($field) {
            'processo_administrativo' => [
                'type' => 'string',
                'description' => 'Número do processo administrativo relacionado.',
                'minLength' => 50
            ],
            'objeto_matriz' => [
                'type' => 'string',
                'description' => 'Objeto principal da matriz de riscos.',
                'minLength' => 300
            ],
            'data_inicio_contratacao' => [
                'type' => 'string',
                'description' => 'Data prevista para início da contratação.',
                'minLength' => 10
            ],
            'unidade_responsavel' => [
                'type' => 'string',
                'description' => 'Unidade responsável pela contratação.',
                'minLength' => 100
            ],
            'fase_analise' => [
                'type' => 'string',
                'description' => 'Fase do processo em que a análise foi realizada.',
                'minLength' => 100
            ],
            default => [
                'type' => 'string',
                'description' => 'Campo ' . $field,
                'minLength' => 50
            ]
        };
    }

    protected function getInstitutionalFieldDescription(string $field): array
    {
        return match ($field) {
            'cidade' => [
                'type' => 'string',
                'description' => 'Nome da cidade',
                'minLength' => 3
            ],
            'cidade_maiusculo' => [
                'type' => 'string',
                'description' => 'Nome da cidade em maiúsculas',
                'minLength' => 3
            ],
            'endereco' => [
                'type' => 'string',
                'description' => 'Endereço completo',
                'minLength' => 10
            ],
            'cep' => [
                'type' => 'string',
                'description' => 'CEP da cidade',
                'minLength' => 8
            ],
            'nome_autoridade' => [
                'type' => 'string',
                'description' => 'Nome da autoridade responsável',
                'minLength' => 3
            ],
            'nome_elaborador' => [
                'type' => 'string',
                'description' => 'Nome do elaborador do documento',
                'minLength' => 3
            ],
            'cargo_autoridade' => [
                'type' => 'string',
                'description' => 'Cargo da autoridade responsável',
                'minLength' => 3
            ],
            'data_extenso' => [
                'type' => 'string',
                'description' => 'Data por extenso',
                'minLength' => 10
            ],
            'data_aprovacao' => [
                'type' => 'string',
                'description' => 'Data de aprovação',
                'minLength' => 10
            ],
            'cargo_elaborador' => [
                'type' => 'string',
                'description' => 'Cargo do elaborador',
                'minLength' => 3
            ],
            'nome_autoridade_aprovacao' => [
                'type' => 'string',
                'description' => 'Nome da autoridade de aprovação',
                'minLength' => 3
            ],
            'cargo_autoridade_aprovacao' => [
                'type' => 'string',
                'description' => 'Cargo da autoridade de aprovação',
                'minLength' => 3
            ],
            default => [
                'type' => 'string',
                'description' => 'Campo ' . $field,
                'minLength' => 3
            ]
        };
    }

    protected function getJsonSchema(string $type): array
    {
        $baseSchema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false
        ];

        $fields = $this->getExpectedFields($type);
        
        foreach ($fields as $field) {
            switch ($type) {
                case 'institutional':
                    $baseSchema['properties'][$field] = $this->getInstitutionalFieldDescription($field);
                    break;
                case 'preliminaryStudy':
                    $baseSchema['properties'][$field] = $this->getEtpFieldDescription($field);
                    break;
                case 'demand':
                    $baseSchema['properties'][$field] = $this->getDfdFieldDescription($field);
                    break;
                case 'referenceTerms':
                    $baseSchema['properties'][$field] = $this->getTrFieldDescription($field);
                    break;
                case 'riskMatrix':
                    if ($field !== 'riscos') {
                        $baseSchema['properties'][$field] = $this->getRiscoFieldDescription($field);
                    }
                    break;
                default:
                    $baseSchema['properties'][$field] = [
                        'type' => 'string',
                        'description' => 'Campo ' . $field,
                    ];
                    break;
            }
            $baseSchema['required'][] = $field;
        }

        // Special handling for risk matrix which has a nested array
        if ($type === 'riskMatrix') {
            $baseSchema['properties']['riscos'] = [
                'type' => 'array',
                'description' => 'Lista de riscos identificados',
                'minItems' => 1,
                'items' => [
                    'type' => 'object',
                    'required' => [
                        'evento',
                        'dano',
                        'impacto',
                        'probabilidade',
                        'acao_preventiva',
                        'responsavel_preventiva',
                        'acao_contingencia',
                        'responsavel_contingencia'
                    ],
                    'properties' => [
                        'evento' => [
                            'type' => 'string',
                            'description' => 'Descrição do evento de risco',
                            'minLength' => 10
                        ],
                        'dano' => [
                            'type' => 'string',
                            'description' => 'Descrição do possível dano',
                            'minLength' => 10
                        ],
                        'impacto' => [
                            'type' => 'string',
                            'description' => 'Nível de impacto (baixo, médio, alto)',
                            'enum' => ['baixo', 'médio', 'alto']
                        ],
                        'probabilidade' => [
                            'type' => 'string',
                            'description' => 'Nível de probabilidade (baixo, médio, alto)',
                            'enum' => ['baixo', 'médio', 'alto']
                        ],
                        'acao_preventiva' => [
                            'type' => 'string',
                            'description' => 'Ação preventiva para mitigar o risco',
                            'minLength' => 10
                        ],
                        'responsavel_preventiva' => [
                            'type' => 'string',
                            'description' => 'Responsável pela ação preventiva',
                            'minLength' => 3
                        ],
                        'acao_contingencia' => [
                            'type' => 'string',
                            'description' => 'Ação de contingência caso o risco se materialize',
                            'minLength' => 10
                        ],
                        'responsavel_contingencia' => [
                            'type' => 'string',
                            'description' => 'Responsável pela ação de contingência',
                            'minLength' => 3
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ];
        }

        // Encapsula o schema base dentro da estrutura correta
        return [
            'name' => 'generate_document_data',
            'description' => 'Gera os dados necessários para o documento',
            'schema' => $baseSchema
        ];
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
                'nome_elaborador',
                'cargo_autoridade',
                'data_extenso',
                'data_aprovacao',
                'cargo_elaborador',
                'nome_autoridade_aprovacao',
                'cargo_autoridade_aprovacao'
            ],

            'preliminaryStudy' => [
                'etp_objeto',
                'etp_justificativa',
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
                'etp_previsao_pca',
                'etp_conformidade_lgpd',
                'etp_riscos_tecnicos',
                'etp_riscos_mitigacao',
                'etp_beneficios_qualitativos',
            ],

            'referenceTerms' => [
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
                'validacao_kpis',
                'designacao_formal_fiscal',
                'penalidades',
                'alertas_ia',
                'anexos_obrigatorios',
                'transparencia_resumo',
                'faq_juridico',
                'prazo_publicacao',
                'transparencia_contato',
                'assinatura_formato',
                'cronograma_execucao',
            ],

            'demand' => [
                'setor',
                'departamento',
                'responsavel',
                'descricaoObjeto',
                'valor',
                'origem_fonte',
                'unidade_nome',
                'justificativa',
                'impacto_meta',
                'escopo',
                'requisitos_tecnicos',
                'riscos_ocupacionais',
                'riscos_normas',
                'riscos_justificativa',
                'alternativas',
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
                'transparencia_prazo',
                'assinatura_formato',
            ],

            'riskMatrix' => [
                'processo_administrativo',
                'objeto_matriz',
                'data_inicio_contratacao',
                'unidade_responsavel',
                'fase_analise',
                'riscos', // O campo 'riscos' deve ser um array de arrays
            ],

            default => [],
        };
    }

    protected function normalizeTemplateData(array $data, string $type): array
    {
        $expectedFields = $this->getExpectedFields($type);
        $normalized = [];

        foreach ($expectedFields as $field) {
            $normalized[$field] = isset($data[$field]) ? $data[$field] : '–';
        }

        return $normalized;
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
