<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration for the OpenAI API
    | integration, used for document generation with GPT-4.
    |
    */

    'api_key' => env('OPENAI_API_KEY', ''),
    'organization' => env('OPENAI_ORGANIZATION', null),
    'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
    'temperature' => env('OPENAI_TEMPERATURE', 0.7),
    'max_tokens' => env('OPENAI_MAX_TOKENS', 2048),
]; 