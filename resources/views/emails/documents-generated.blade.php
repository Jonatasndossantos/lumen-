<!DOCTYPE html>
<html>
<head>
    <title>Documentos Gerados</title>
</head>
<body>
    <h2>Olá {{ $name }},</h2>
    
    <p>Seus documentos foram gerados com sucesso. Abaixo estão os links para download:</p>

    <ul>
        @if(isset($documents['guidelines']))
            <li><a href="{{ $documents['guidelines'] }}">Diretrizes</a></li>
        @endif
        
        @if(isset($documents['demand']))
            <li><a href="{{ $documents['demand'] }}">Demanda</a></li>
        @endif
        
        @if(isset($documents['riskMatrix']))
            <li><a href="{{ $documents['riskMatrix'] }}">Matriz de Risco</a></li>
        @endif
        
        @if(isset($documents['preliminaryStudy']))
            <li><a href="{{ $documents['preliminaryStudy'] }}">Estudo Preliminar</a></li>
        @endif
        
        @if(isset($documents['referenceTerms']))
            <li><a href="{{ $documents['referenceTerms'] }}">Termos de Referência</a></li>
        @endif
    </ul>

    <p>Recomendamos que você faça o download dos documentos o quanto antes, pois eles podem expirar após um período.</p>

    <p>Atenciosamente,<br>Equipe de Suporte</p>
</body>
</html> 