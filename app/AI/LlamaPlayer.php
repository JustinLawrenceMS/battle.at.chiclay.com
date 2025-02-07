<?php

namespace App\AI;

use GuzzleHttp\Client;

class LlamaPlayer
{
    private $apiKey;
    private $client;
    private $messages;
    public function __construct()
    {
        $this->apiKey = config('services.llama.api_key');
        $this->client = new Client([
            'base_uri' => 'https://api.llama-api.com/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);
    }

    public function getLlamaResponse(mixed $prompt = null): array
    {
        if ($prompt == null || $prompt == "null") {
            $prompt = "You are playing D&D 5e. Please create a character in D&D 5e 
            and return your response to the DM. Use only HTML for line breaks and 
            text formatting. Only play your one character.  Do not speak for anyone else.";
        }

        $payload = array_merge(
            session("llama") ?? [],
            [
                "model" => "llama3.2-3b",
                "messages" => [
                    ["role" => "user", "content" => $prompt]
                ],
            ]
        );

        $response = $this->client->post('chat/completions', [
            'json' => $payload,
            'max_tokens' => 2048,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        $setter = new ChatGPTPlayer();
        $setter->setMessages("user", json_encode($contents));
        return $contents;
    }

    public function setSession(string $llm): void    
    {
        $this->setSession("llama");
    }
}
