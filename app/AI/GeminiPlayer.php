<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;

class GeminiPlayer
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generateGeminiResponse(string $prompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateText?key={$this->apiKey}";

        $response = Http::post($url, [
            'prompt' => $prompt,
            'temperature' => 0.8, // Adjust for creativity
            'maxOutputTokens' => 2048,
        ]);

        return $response->json()['candidates'][0]['output'] ?? 'No response generated.';
    }
}
