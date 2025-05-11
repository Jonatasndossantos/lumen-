<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \OpenAI\Client client(string $apiKey = null, string $organization = null)
 * @method static \OpenAI\Resources\Chat chat()
 * 
 * @see \OpenAI\Client
 */
class OpenAI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'openai';
    }
} 