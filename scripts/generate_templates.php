<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;

// Create templates directory if it doesn't exist
$templatesDir = __DIR__ . '/../public/templates';
if (!file_exists($templatesDir)) {
    mkdir($templatesDir, 0755, true);
}

// Function to create a new PHPWord instance with default settings
function createPhpWord() {
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName('Arial');
    $phpWord->setDefaultFontSize(11);
    return $phpWord;
}

// Function to add standard identification section
function addIdentificationSection($section, $title) {
    $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => 'center']);
    $section->addTextBreak();
    
    $section->addText('Identificação:', ['bold' => true, 'size' => 12]);
    $section->addText('Nome: ${name}');
    $section->addText('Município: ${municipality}');
    $section->addText('Instituição: ${institution}');
    $section->addText('Endereço: ${address}');
    $section->addTextBreak();
    
    if ($title !== 'ORIENTAÇÕES DE USO') {
        $section->addText('Objeto: ${objectDescription}', ['bold' => true]);
        $section->addTextBreak();
    }
}

// Function to add standard signature section
function addSignatureSection($section) {
    $section->addTextBreak(2);
    $section->addText('Local e data: ${municipality}, ${date}', null, ['spacing' => 150]);
    $section->addTextBreak();
    $section->addText('_______________________', null, ['spacing' => 150]);
    $section->addText('${name}', null, ['spacing' => 150]);
}

// Create Guidelines Template
function createGuidelinesTemplate() {
    $phpWord = createPhpWord();
    $section = $phpWord->addSection();
    
    addIdentificationSection($section, 'ORIENTAÇÕES DE USO');
    
    $section->addText('Este documento fornece orientações para o uso correto dos documentos gerados.', null, ['spacing' => 150]);
    $section->addTextBreak();
    
    $section->addText('1. Revise todos os documentos gerados antes de usá-los oficialmente.');
    $section->addText('2. Adapte-os conforme necessário às especificidades do seu processo licitatório.');
    $section->addText('3. Consulte a Lei 14.133/2021 para garantir conformidade legal.');
    
    addSignatureSection($section);
    $section->addText('Responsável', null, ['spacing' => 150]);
    
    return $phpWord;
}

// Create Demand Document Template
function createDemandTemplate() {
    $phpWord = createPhpWord();
    $section = $phpWord->addSection();
    
    addIdentificationSection($section, 'DOCUMENTO DE FORMALIZAÇÃO DE DEMANDA');
    
    $headers = [
        '1. Identificação da Necessidade',
        '2. Justificativa da Contratação',
        '3. Quantidade Necessária',
        '4. Previsão de Data para Início do Fornecimento',
        '5. Responsável pelo Recebimento'
    ];
    
    foreach ($headers as $header) {
        $section->addText($header, ['bold' => true, 'size' => 12]);
        $section->addTextBreak();
        $section->addText('${' . strtolower(str_replace(['. ', ' '], ['_', '_'], $header)) . '}');
        $section->addTextBreak();
    }
    
    addSignatureSection($section);
    $section->addText('Responsável pela formalização da demanda', null, ['spacing' => 150]);
    
    return $phpWord;
}

// Create Risk Matrix Template
function createRiskMatrixTemplate() {
    $phpWord = createPhpWord();
    $section = $phpWord->addSection();
    
    addIdentificationSection($section, 'MATRIZ DE RISCO');
    
    $section->addText('1. Riscos Potenciais', ['bold' => true, 'size' => 12]);
    $section->addTextBreak();
    
    for ($i = 1; $i <= 5; $i++) {
        $section->addText("Risco $i:", ['bold' => true]);
        $section->addText('${risk_' . $i . '_description}');
        $section->addText('Probabilidade: ${risk_' . $i . '_probability}');
        $section->addText('Impacto: ${risk_' . $i . '_impact}');
        $section->addText('Mitigação: ${risk_' . $i . '_mitigation}');
        $section->addTextBreak();
    }
    
    $section->addText('2. Observações Adicionais', ['bold' => true, 'size' => 12]);
    $section->addText('${additional_observations}');
    
    addSignatureSection($section);
    $section->addText('Responsável pela elaboração da matriz', null, ['spacing' => 150]);
    
    return $phpWord;
}

// Create Preliminary Study Template
function createPreliminaryStudyTemplate() {
    $phpWord = createPhpWord();
    $section = $phpWord->addSection();
    
    addIdentificationSection($section, 'ESTUDO TÉCNICO PRELIMINAR');
    
    $headers = [
        '1. Descrição da Necessidade',
        '2. Análise das Alternativas',
        '3. Alinhamento Estratégico',
        '4. Requisitos da Contratação',
        '5. Estimativa das Quantidades',
        '6. Estimativa de Preços',
        '7. Descrição da Solução',
        '8. Resultados Pretendidos',
        '9. Providências para Adequação',
        '10. Contratações Correlatas',
        '11. Declaração de Viabilidade'
    ];
    
    foreach ($headers as $header) {
        $section->addText($header, ['bold' => true, 'size' => 12]);
        $section->addTextBreak();
        $section->addText('${' . strtolower(str_replace(['. ', ' '], ['_', '_'], $header)) . '}');
        $section->addTextBreak();
    }
    
    addSignatureSection($section);
    $section->addText('Responsável pelo Estudo Técnico Preliminar', null, ['spacing' => 150]);
    
    return $phpWord;
}

// Create Reference Terms Template
function createReferenceTermsTemplate() {
    $phpWord = createPhpWord();
    $section = $phpWord->addSection();
    
    addIdentificationSection($section, 'TERMO DE REFERÊNCIA');
    
    $headers = [
        '1. Definição do Objeto',
        '2. Fundamentação e Justificativa',
        '3. Descrição da Solução',
        '4. Requisitos da Contratação',
        '5. Modelo de Execução',
        '6. Modelo de Gestão',
        '7. Critérios de Medição',
        '8. Forma de Seleção',
        '9. Estimativas de Preços',
        '10. Adequação Orçamentária'
    ];
    
    foreach ($headers as $header) {
        $section->addText($header, ['bold' => true, 'size' => 12]);
        $section->addTextBreak();
        $section->addText('${' . strtolower(str_replace(['. ', ' '], ['_', '_'], $header)) . '}');
        $section->addTextBreak();
    }
    
    addSignatureSection($section);
    $section->addText('Responsável pela elaboração do Termo de Referência', null, ['spacing' => 150]);
    
    return $phpWord;
}

// Generate all templates
$templates = [
    'guidelines_template.docx' => createGuidelinesTemplate(),
    'demand_template.docx' => createDemandTemplate(),
    'risk_matrix_template.docx' => createRiskMatrixTemplate(),
    'preliminary_study_template.docx' => createPreliminaryStudyTemplate(),
    'reference_terms_template.docx' => createReferenceTermsTemplate(),
];

// Save all templates
foreach ($templates as $filename => $phpWord) {
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($templatesDir . '/' . $filename);
    echo "Generated: $filename\n";
}

echo "\nAll templates have been generated successfully!\n"; 