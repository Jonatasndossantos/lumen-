<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Exception;

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
                //$data = $this->baseDocument->generateAiData('tr', $request);
            try{
                $data = json_decode('{
                    "descricao_tecnica": "O objeto do presente Termo de Referência consiste na contratação de serviços ou aquisição de bens demandados pela Secretaria de Serviços Urbanos, abrangendo intervenções de natureza contínua, especializada e essencial à manutenção de espaços públicos e equipamentos urbanos. Enquadra-se como serviço comum de engenharia ou fornecimento correlato, conforme art. 6º, XXII e art. 40 da Lei nº 14.133/2021, a ser detalhado conforme o escopo específico. A contratação visa atender aos padrões de qualidade, regularidade operacional e desempenho definidos por normas técnicas e regulatórias, priorizando eficiência e sustentabilidade nos processos. O valor estimado da contratação é de R$ {valor}, apurado por cotação referencial de mercado conforme exigido pela IN SEGES nº 65/2021.",
                    "justificativa_demanda": "A presente contratação justifica-se pela necessidade de garantir a continuidade e eficiência da prestação de serviços essenciais à população, atribuídos à Secretaria de Serviços Urbanos. Considerando a natureza dinâmica e recorrente das atividades, a demanda visa sanar falhas estruturais, promover a manutenção preventiva e corretiva de equipamentos e assegurar a conservação de áreas públicas. A ausência da contratação comprometeria diretamente a execução de políticas públicas e os direitos coletivos, ensejando riscos operacionais, sanitários e ambientais, além do descumprimento de obrigações legais e constitucionais vinculadas à Administração Pública.",
                    "base_legal": "A contratação fundamenta-se na Lei Federal nº 14.133/2021, que estabelece normas gerais de licitação e contratação para a Administração Pública direta e indireta, em especial seus arts. 6º, 11, 18, 19, 40 e 74. Aplica-se ainda a Instrução Normativa SEGES/ME nº 5/2017, que orienta a elaboração de Estudos Técnicos Preliminares e Termos de Referência, além das disposições da IN SEGES nº 65/2021 no tocante à estimativa de preços. O procedimento observará os princípios constitucionais da legalidade, impessoalidade, moralidade, publicidade, eficiência e transparência.",
                    "normas_aplicaveis": "Serão observadas as normas da ABNT aplicáveis ao objeto, bem como as normas regulamentadoras do Ministério do Trabalho e Emprego (NRs), em especial as NRs 10, 12, 18 e 35, conforme a natureza do serviço ou fornecimento. Aplica-se também a IN SEGES nº 5/2017 e a IN nº 65/2021 para instrução processual. Quando cabível, observar-se-á o disposto nas portarias ministeriais e resoluções técnicas emitidas pelos conselhos profissionais, como CREA e CFT.",
                    "execucao_etapas": "As etapas de execução serão organizadas conforme planejamento técnico aprovado previamente: (i) mobilização e instalação de canteiro, quando necessário; (ii) execução dos serviços conforme cronograma físico-financeiro; (iii) aplicação de materiais, insumos e equipamentos conforme especificações técnicas; (iv) controle de qualidade por meio de ensaios, relatórios e vistorias técnicas; (v) entrega formal da etapa ou fase mediante aceite provisório e definitivo; (vi) encerramento contratual com emissão de termo de recebimento final, em conformidade com o art. 140 da Lei nº 14.133/2021.",
                    "tolerancia_tecnica": "Admite-se variação técnica de até 5% nos quantitativos unitários, conforme art. 125 da Lei nº 14.133/2021, desde que tecnicamente justificável e formalmente aprovado pela fiscalização. Todos os materiais e serviços devem atender aos padrões de qualidade, resistência, segurança e durabilidade estabelecidos pelas normas técnicas e exigências do edital, não sendo tolerada substituição por equivalentes inferiores.",
                    "materiais_sustentaveis": "Priorizar-se-á o uso de materiais com certificação ambiental, recicláveis ou reutilizáveis, quando tecnicamente viável, em observância aos princípios do desenvolvimento sustentável e da eficiência dos recursos públicos, conforme art. 144 da Lei nº 14.133/2021 e Política Nacional de Resíduos Sólidos (Lei nº 12.305/2010).",
                    "execucao_similar": "Há registros de contratações similares em administrações municipais e estaduais, especialmente nas áreas de manutenção urbana, elétrica e obras civis, com execução por meio de empresas especializadas e sob fiscalização técnica contínua. Estas experiências demonstram a viabilidade da execução do objeto sob regime de empreitada por preço unitário ou global, conforme aplicável.",
                    "certificacoes": "Serão exigidas, conforme o objeto, certificações como ISO 9001 (gestão da qualidade), ISO 14001 (gestão ambiental) e NR-10/NR-35 (segurança do trabalho), além de registro no CREA/CFT para empresas e profissionais técnicos, conforme art. 67 da Lei nº 14.133/2021.",
                    "cronograma_execucao": "O cronograma de execução será distribuído em fases: planejamento inicial (até 15 dias), mobilização (até 10 dias após a ordem de serviço), execução das atividades (conforme especificação técnica por lote ou unidade), e encerramento com entrega definitiva (prazo global estimado de até 180 dias). Ajustes poderão ocorrer mediante termo aditivo, devidamente justificado.",
                    "pgr_pcmso": "É obrigatória a apresentação dos programas de gerenciamento de riscos (PGR) e de controle médico de saúde ocupacional (PCMSO), em atendimento às NRs 1 e 7 do Ministério do Trabalho. A contratada deverá apresentar ASO, exames complementares e treinamentos exigidos, como condição para a liberação da execução contratual.",
                    "criterio_julgamento": "O critério de julgamento adotado será o de menor preço por item ou global, conforme matriz de risco e impacto econômico, nos termos do art. 33, I da Lei nº 14.133/2021. Poderá ser adotado modelo de pontuação técnica para objetos com alta complexidade e grau de especialização.",
                    "garantia_qualidade": "Todos os serviços estarão sujeitos à garantia mínima de 12 (doze) meses, contados do recebimento definitivo, conforme art. 140 da Lei nº 14.133/2021. Serão exigidas ARTs, vistorias técnicas, certificações de conformidade, e relatórios de inspeção, sendo condição para aceite e liberação de pagamento.",
                    "painel_fiscalizacao": "Será disponibilizado painel de fiscalização eletrônico com registros de ordens de serviço, medições, fotos, pareceres técnicos e indicadores de desempenho, conforme práticas de controle interno e com base no art. 117 da Lei nº 14.133/2021.",
                    "kpis_operacionais": "Serão monitorados indicadores como: (i) cumprimento do prazo contratual (%), (ii) índice de não conformidade (%), (iii) índice de retrabalho (%), (iv) satisfação do usuário (%), e (v) produtividade média por equipe. Os dados alimentarão relatório mensal de desempenho.",
                    "validacao_kpis": "A validação dos KPIs será feita por meio de inspeções técnicas, relatórios da fiscalização, análise de ordens de serviço e registros no sistema oficial. A aferição será documentada com base nos critérios de medição descritos no contrato, podendo implicar glosas e sanções.",
                    "designacao_formal_fiscal": "O fiscal será formalmente designado por portaria específica do órgão requisitante, conforme art. 117 da Lei nº 14.133/2021. Sua atuação compreenderá o controle técnico, administrativo, econômico e documental da execução contratual, com registros obrigatórios em sistema próprio.",
                    "penalidades": "A contratada poderá ser penalizada por descumprimento contratual com advertência, multa de até 30% do valor do contrato, impedimento de licitar e contratar com o ente público por até 3 anos, e declaração de inidoneidade, conforme arts. 155 a 157 da Lei nº 14.133/2021.",
                    "alertas_ia": "Este Termo de Referência exige preenchimento técnico rigoroso. A ausência de dados, uso de expressões genéricas ou incongruências com a Lei nº 14.133/2021 poderão resultar em glosas, retrabalho ou invalidação do processo licitatório. Verificar se os anexos obrigatórios estão corretamente inseridos.",
                    "anexos_obrigatorios": "Deverão acompanhar este Termo de Referência: (i) Estudo Técnico Preliminar (ETP); (ii) Pesquisa de preços com metodologia aplicada (IN 65/2021); (iii) Mapa de riscos; (iv) Minuta do edital; (v) Cronograma físico-financeiro; (vi) Projeto básico, quando aplicável; (vii) ARTs e documentos técnicos de referência.",
                    "transparencia_resumo": "A presente contratação visa atender às necessidades operacionais da Secretaria de Serviços Urbanos, promovendo serviços e/ou bens com qualidade, eficiência e economicidade. A medida integra o planejamento estratégico municipal e assegura a adequada prestação de serviços públicos à população.",
                    "faq_juridico": "1. A contratação exige licitação? Sim, exceto nos casos de dispensa legal conforme art. 75 da Lei nº 14.133/2021. 2. Qual a base legal para o termo de referência? Art. 6º, inciso XXIII da Lei nº 14.133/2021 e IN SEGES nº 5/2017. 3. É obrigatória a apresentação de PGR e PCMSO? Sim, nos casos de execução de serviços com riscos ocupacionais. 4. Pode haver prorrogação contratual? Sim, desde que observados os requisitos do art. 106 da Lei nº 14.133/2021.",
                    "prazo_publicacao": "5",
                    "transparencia_contato": "ouvidoria@municipio.gov.br | (11) 99999-9999 | https://www.municipio.gov.br/ouvidoria",
                    "assinatura_formato": "Assinatura digital com certificação ICP-Brasil e carimbo do tempo",
                    "nome_elaborador": "Eng. Roberto Almeida da Silva",
                    "cargo_elaborador": "Assessor Técnico de Planejamento da Secretaria de Serviços Urbanos",
                    "nome_autoridade_aprovacao": "Ana Cláudia Ferraz Ribeiro",
                    "cargo_autoridade_aprovacao": "Secretária Municipal de Serviços Urbanos"
                    }
                ', true);
            } catch(\Throwable $e){
                log:info("deu erro no json informado: TR");
            }
            // Processa o template
            $templateProcessor = new TemplateProcessor(public_path('templates/TR_Termo_Referencia_V11_3_Template.docx'));

            // Preenche os dados no template
            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
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