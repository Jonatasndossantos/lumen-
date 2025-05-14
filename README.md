<<<<<<< HEAD
=======
# Sistema de Geração de Documentos Públicos

Sistema desenvolvido para automatizar a geração de documentos públicos utilizando IA, seguindo as diretrizes da Lei nº 14.133/2021 (Nova Lei de Licitações).

## Estrutura do Sistema

### Controladores

- `BaseDocumentController`: Controlador base que gerencia a comunicação com a IA e processamento de templates
- `DemandController`: Gera Documento de Formalização de Demanda (DFD)
- `PreliminaryStudyController`: Gera Estudo Técnico Preliminar (ETP)
- `ReferenceTermsController`: Gera Termo de Referência (TR)
- `RiskMatrixController`: Gera Matriz de Risco
- `GuidelinesController`: Gera Orientações Institucionais

### Templates

Localizados em `public/templates/`:
- `DFD_Diagnostico_Unificado_Template.docx`
- `ETP_Estudo_Tecnico_Preliminar_Template.docx`
- `TR_Termo_Referencia_V11_3_Template.docx`
- `Matriz_Risco_Template.docx`
- `guidelines_template.docx`

## Processos de Geração

### 1. Documento de Formalização de Demanda (DFD)
- Recebe dados básicos da requisição
- Gera conteúdo via IA
- Preenche template com dados gerados
- Adiciona dados institucionais e brasão
- Salva documento final

### 2. Estudo Técnico Preliminar (ETP)
- Recebe dados do objeto e valores
- Gera conteúdo técnico via IA
- Preenche template com dados gerados
- Adiciona dados institucionais e brasão
- Salva documento final

### 3. Termo de Referência (TR)
- Recebe dados do objeto e valores
- Gera conteúdo técnico e legal via IA
- Preenche template com dados gerados
- Adiciona dados institucionais e brasão
- Salva documento final

### 4. Matriz de Risco
- Recebe dados do processo
- Gera análise de riscos via IA
- Cria tabela dinâmica com riscos
- Preenche template com dados gerados
- Adiciona dados institucionais e brasão
- Salva documento final

### 5. Orientações Institucionais
- Recebe dados institucionais
- Gera conteúdo orientativo via IA
- Preenche template com dados gerados
- Adiciona dados institucionais e brasão
- Salva documento final

## Tratamento de Erros

O sistema implementa um robusto tratamento de erros:

1. **Recuperação de JSON Malformado**
   - Tenta recuperar dados malformados
   - Normaliza estrutura do JSON
   - Preenche campos ausentes com valores padrão

2. **Validação de Templates**
   - Verifica existência dos templates
   - Valida permissões de escrita
   - Cria diretórios necessários

3. **Cache de Dados**
   - Implementa cache para otimizar performance
   - Gera chaves únicas baseadas nos dados da requisição
   - Cache válido por 1 hora

## Integração com IA

O sistema utiliza a API da OpenAI (GPT-4) para gerar conteúdo:

1. **Prompts Especializados**
   - Cada tipo de documento tem seu prompt específico
   - Instruções detalhadas para cada contexto
   - Validação de campos obrigatórios

2. **Estrutura de Dados**
   - JSON Schema para cada tipo de documento
   - Campos obrigatórios definidos
   - Validação de tipos e formatos

3. **Processamento de Respostas**
   - Tratamento de respostas da IA
   - Normalização de dados
   - Preenchimento de campos ausentes

## Requisitos do Sistema

- PHP 8.0+
- Composer
- Extensão PHP para processamento de documentos Word
- Chave de API da OpenAI
- Permissões de escrita nos diretórios de templates e documentos

## Instalação

1. Clone o repositório
2. Execute `composer install`
3. Configure as variáveis de ambiente:
   - `OPENAI_API_KEY`
   - `OPENAI_ORG_ID`
4. Configure as permissões dos diretórios:
   - `public/templates`
   - `public/documents`
   - `public/brasoes`

## Uso

Cada controlador expõe um endpoint para geração de documentos:

```php
POST /api/demand/generate
POST /api/preliminary-study/generate
POST /api/reference-terms/generate
POST /api/risk-matrix/generate
POST /api/guidelines/generate
```

Os endpoints esperam os seguintes parâmetros:
- `municipality`: Nome do município
- `institution`: Nome da instituição
- `address`: Endereço completo
- `objectDescription`: Descrição do objeto
- `valor`: Valor estimado (opcional)
- `date`: Data de referência (opcional)

## Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## Licença

Este projeto está licenciado sob a licença MIT.




>>>>>>> 42982285fb2f3768eade067e3517a013b5e7ddaf
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development/)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
