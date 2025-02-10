<?php

namespace App\AI;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;

class LlamaPlayer
{
    private $apiKey;
    private $client;
    private string $endpointId = "";
    private string $projectId = "";

    public function __construct()
    {
        // Authenticate using the service account
        $credentials = ApplicationDefaultCredentials::getCredentials('https://www.googleapis.com/auth/cloud-platform');
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'];

        $this->endpointId = config('services.vertex.llama.endpoint_id');
        $this->projectId = config('services.vertex.llama.project_id');
        $this->client = new Client([
            'base_uri' => "https://us-central1-aiplatform.googleapis.com/v1/projects/{ $this->projectId }/locations/us-central1/endpoints/{ $this->endpointId }:predict",
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
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
