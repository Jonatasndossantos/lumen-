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
            //$data = $this->baseDocument->generateAiData('etp', $request);
           try{
                $data = json_decode('{
                    "etp_objeto": "Trata-se da contratação de empresa especializada para o fornecimento, instalação e operação de uma estrutura de montanha-russa em praça pública, destinada a atividades de lazer e fomento ao turismo. A estrutura deverá ser desmontável, com instalação segura, homologação técnica, operação assistida por equipe capacitada e licenciamento prévio junto aos órgãos competentes, garantindo atratividade, segurança e conformidade normativa. O objeto integra o plano municipal de incentivo ao uso de espaços públicos para atividades culturais e de entretenimento, em consonância com as diretrizes de acessibilidade, inclusão social e sustentabilidade.",
                    "etp_justificativa": "A contratação justifica-se pelo interesse público em promover o uso qualificado de praças e espaços urbanos ociosos por meio de atividades de lazer de impacto positivo no bem-estar da população, com incremento da economia local. Com base na Lei nº 14.133/2021, art. 11, I e III, e nos princípios da economicidade e eficiência, tal contratação representa inovação administrativa ao transformar equipamentos públicos em polos de atração turística. A experiência exitosa em municípios que adotaram estruturas de entretenimento temporário embasa a viabilidade técnica, social e econômica da proposta.",
                    "etp_plano_contratacao": "A contratação deverá ocorrer preferencialmente por meio de concorrência, com publicação em amplo prazo e observância ao art. 28 da Lei nº 14.133/2021, sendo o critério de julgamento o menor preço por lote, considerando-se todos os elementos do objeto. O planejamento inclui o levantamento de custos via IN SEGES nº 65/2021, elaboração do Termo de Referência e posterior inclusão no Plano de Contratações Anual (PCA), além de consulta ao mercado para verificação da capacidade técnica dos fornecedores.",
                    "etp_requisitos_funcionais": "A estrutura deverá ser metálica, modular, desmontável e transportável, com capacidade mínima para 12 usuários simultâneos, operação contínua por 6 horas diárias, com controle eletrônico de segurança e dispositivos de parada automática em caso de falha. Deve permitir operação por operador externo treinado, possuir acessibilidade universal, manual técnico de operação e manutenção, e suportar condições meteorológicas moderadas, conforme normas da ABNT NBR 15926 e correlatas.",
                    "etp_requisitos_compatibilidade": "A solução contratada deve ser compatível com as condições físicas da praça (incluindo topografia, rede elétrica e circulação urbana) e com as normas técnicas da ABNT, Corpo de Bombeiros e CREA/ART. Deverá permitir integração com outras atividades de lazer e eventos temporários municipais, exigindo compatibilidade com a infraestrutura urbana existente, inclusive para acesso de veículos para montagem/desmontagem e alimentação elétrica trifásica padronizada.",
                    "etp_experiencia_publica": "Diversos municípios implementaram com sucesso estruturas itinerantes de lazer em praças públicas, como parques infláveis e brinquedos mecânicos. A adoção da montanha-russa em praças foi registrada em cidades turísticas de médio porte como Pomerode (SC) e Holambra (SP), demonstrando boa aceitação popular e incremento significativo no fluxo de visitantes e comércio local, evidenciando a viabilidade e aderência da proposta à realidade administrativa brasileira.",
                    "etp_prazo_execucao": "A contratação deverá prever a instalação, operação e desmontagem da estrutura no prazo total de 90 dias, sendo 15 dias para montagem e licenciamento, 60 dias de operação e 15 dias para desmontagem e desmobilização. O cronograma poderá ser estendido mediante justificativa e interesse público, conforme art. 107 da Lei nº 14.133/2021.",
                    "etp_forma_pagamento": "O pagamento será realizado em parcelas proporcionais às fases de implantação: 20% na entrega da estrutura montada e licenciada; 40% após 30 dias de operação; e os 40% restantes após a desmontagem, vistoria final e entrega do relatório técnico. Os pagamentos estarão condicionados à regularidade fiscal e à apresentação de nota fiscal eletrônica e atesto técnico por servidor designado, conforme art. 141 da Lei nº 14.133/2021.",
                    "etp_criterios_selecao": "Os critérios de seleção incluirão: menor preço global, experiência comprovada em estruturas de entretenimento itinerante, licenciamento prévio por órgão competente, portfólio técnico, plano de segurança operacional e proposta de operação inclusiva e sustentável. Serão aceitas soluções inovadoras, conforme art. 6º, inciso XL, da Lei nº 14.133/2021.",
                    "etp_estimativa_quantidades": "A contratação prevê a aquisição de 1 (uma) estrutura de montanha-russa com capacidade mínima para 12 passageiros por ciclo, com operação contínua por até 60 dias. Estima-se média de 1.200 usuários por semana. Será necessário o fornecimento de 2 operadores por turno, incluindo 2 turnos diários, totalizando 240 horas/homens mensais.",
                    "etp_alternativa_a": "Promoção de atividades culturais e lúdicas por meio de artistas locais e brinquedos infláveis em regime de comodato, com contratação via chamamento público. Baixo custo, mas limitada atratividade e menor controle de segurança.",
                    "etp_alternativa_b": "Instalação de brinquedos mecânicos via cessão onerosa do espaço público a operador privado, mediante concessão temporária. Sem custos para o Município, mas com menor controle da qualidade e risco de exploração comercial excessiva.",
                    "etp_alternativa_c": "Aquisição definitiva da estrutura pelo Município, com montagem, operação e manutenção por equipe própria. Alto custo inicial e necessidade de equipe técnica permanente, pouco viável financeiramente.",
                    "etp_analise_comparativa": "A alternativa A é de baixa complexidade, mas oferece atratividade limitada; a B garante economia direta, mas com riscos jurídicos na concessão e baixa capacidade de controle público; já a C apresenta alto investimento e impacto no orçamento. A contratação temporária via locação da estrutura (modelo proposto) reúne controle público, viabilidade orçamentária e alto impacto social, sendo a alternativa mais equilibrada entre custo-benefício e atendimento ao interesse público.",
                    "etp_estimativa_precos": "Com base em cotações de mercado e experiências similares, estima-se o valor total em R$ 237.000,00, englobando transporte, montagem, operação assistida, desmontagem e seguro da estrutura. Os preços foram obtidos junto a três empresas especializadas, sendo adotada a média aritmética, conforme preceitua a IN SEGES nº 65/2021.",
                    "etp_solucao_total": "A solução contempla a locação de estrutura desmontável de montanha-russa, com operação assistida, licenciamento técnico, seguro e equipe de apoio. A contratação permitirá reativar o uso social da praça, promover inclusão, turismo e atividades comunitárias, sem comprometer o orçamento público com aquisição definitiva ou encargos contínuos.",
                    "etp_parcelamento": "Não se recomenda o parcelamento do objeto, dada a natureza indivisível e técnica da estrutura, que exige fornecimento integral, com responsabilidade única sobre montagem, operação e desmontagem. O fracionamento comprometeria a segurança, o desempenho e o controle do serviço, infringindo o art. 23, §1º da Lei nº 14.133/2021.",
                    "etp_resultados_esperados": "Espera-se incremento significativo na frequência da população à praça pública, fortalecimento da economia local por meio do comércio informal e ambulante, aumento da visibilidade do município no cenário turístico regional e ampliação do acesso da população a equipamentos de lazer seguros, inclusivos e inovadores.",
                    "etp_providencias_previas": "Serão necessárias: a) vistoria técnica na praça; b) emissão de alvará de funcionamento temporário pela vigilância sanitária e corpo de bombeiros; c) obtenção de ART do responsável técnico da contratada; d) cadastramento da contratação no PCA ou solicitação formal de inclusão.",
                    "etp_contratacoes_correlatas": "Não há contratações em vigor com o mesmo objeto. Entretanto, a experiência da administração com festividades e feiras públicas, que demandaram estruturas temporárias, serve como base de referência operacional para esta contratação.",
                    "etp_impactos_ambientais": "O impacto ambiental é considerado baixo, restrito ao aumento pontual de resíduos sólidos e consumo energético, que será mitigado por meio de instalação de lixeiras seletivas, cronograma de limpeza diária e uso de equipamento com alimentação elétrica de baixo consumo. Será exigido plano de descarte correto dos materiais utilizados.",
                    "etp_viabilidade_contratacao": "A contratação mostra-se viável técnica, jurídica e economicamente, atendendo aos critérios de legalidade, planejamento, interesse público e sustentabilidade orçamentária, conforme previsto nos arts. 11, 18 e 19 da Lei nº 14.133/2021. O uso de estruturas desmontáveis permite flexibilidade, economicidade e ampliação do acesso ao lazer público.",
                    "etp_previsao_dotacao": "Os recursos financeiros serão alocados pela Secretaria de Turismo e Lazer, conforme dotação orçamentária vinculada à ação 2.001 – Fomento ao Lazer Urbano. A classificação funcional programática será identificada na fase interna da contratação.",
                    "etp_previsao_pca": "O objeto ainda não consta no Plano de Contratações Anual. Será instruída solicitação de inclusão formal junto à unidade responsável, com base neste Estudo Técnico Preliminar.",
                    "etp_plano_implantacao": "A implantação será executada em três fases: Fase I – Preparação (15 dias): vistorias, emissão de licenças, montagem; Fase II – Operação (60 dias): atendimento diário ao público; Fase III – Encerramento (15 dias): desmontagem, retirada da estrutura e relatório final de execução.",
                    "etp_conformidade_lgpd": "A contratação não envolve coleta ou tratamento de dados pessoais sensíveis. Caso ocorra cadastramento de usuários para controle de acesso, será exigida da contratada a adoção de boas práticas em conformidade com a LGPD (Lei nº 13.709/2018), mediante termo de responsabilidade e proteção dos dados tratados.",
                    "etp_riscos_tecnicos": "Os principais riscos envolvem: a) falha estrutural do equipamento; b) acidente durante a operação; c) não cumprimento de prazos contratuais. Tais riscos podem comprometer a segurança dos usuários e a reputação institucional do município.",
                    "etp_riscos_mitigacao": "Os riscos serão mitigados com: a) exigência de ART e seguro de responsabilidade civil; b) equipe operacional treinada; c) fiscalização técnica diária; d) cláusulas contratuais com penalidades por inadimplemento; e) checklists de vistoria padronizados e inspeção do CREA e Corpo de Bombeiros.",
                    "etp_beneficios_qualitativos": "Entre os benefícios qualitativos destacam-se: aumento da vitalidade urbana e da percepção de segurança da população; estímulo à convivência comunitária; inovação na gestão dos espaços públicos; fortalecimento da imagem institucional e estímulo à cidadania por meio do lazer gratuito e acessível."
                }', true);
           } catch(\Throwable $e){
            log:info("deu erro no json informado: ETP");
           }
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