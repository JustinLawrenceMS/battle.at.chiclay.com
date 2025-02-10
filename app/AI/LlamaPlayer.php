<?php

namespace App\AI;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;

class LlamaPlayer
{
    private $client;
    private $endpointId;
    private $projectId;
    private string $accessToken = "";
    private array $messages = [];
    public string $url = "";
    
    public function __construct()
    {
        $this->setToken();

        $this->endpointId = config('services.vertex.0.llama.endpoint_id');
        $this->projectId = config('services.vertex.0.llama.project_id');
        $this->url = "https://us-central1-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/us-central1/endpoints/{$this->endpointId}:predict";
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type'  => 'application/json'
            ],
        ]);
    }

    public function getLlamaResponse(mixed $prompt = null): array
    {
        if ($prompt === null || $prompt === "null") {
            $prompt = "You are a chatbot and you are playing D&D 5e. Please create a character in D&D 5e
            and return your response to the DM. Use only HTML for line breaks and
            text formatting. Only play your one character. Do not speak for anyone else.";
        }

        $this->messages[] = [ 
            'role' => 'assistant',
            'prompt' => $prompt,
            'parameters' => [
                'max_tokens' => 2048,
                "model" => "meta/llama-3.2-3b",
                "temperature" => 0.7,
                "top_p" => 0.9,
                "stream" => false,
                "extra_body" => [
                    "google" => [
                        "model_safety_settings" => [
                            "enabled" => false,
                            "llama_guard_settings" => []
                        ]
                    ]
                ]
            ]
        ];

        // Using request() with method 'POST'
        $response = $this->client->request('POST', '', [
            'instances' => json_encode($this->messages),
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        \Log::info('Llama API Response:', ['response' => $contents]);

        $setter = new ChatGPTPlayer();
        $setter->setMessages("assistant", json_encode($contents));

        return $contents;
    }

    public function setSession(string $llm): void
    {
        session($llm);
    }

    private function setToken(): void
    {
        $credentials = ApplicationDefaultCredentials::getCredentials('https://www.googleapis.com/auth/cloud-platform');
        $token = $credentials->fetchAuthToken();
        $this->accessToken = $token['access_token'];
    }
}
