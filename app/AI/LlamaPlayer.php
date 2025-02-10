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

        $payload = [
            'instances' => [
                'prompt' => $prompt
            ],
            'parameters' => [
                'max_tokens' => 2048,
                "model" => "meta/llama-3.2-3b",
                "messages" => [
                    "role" => "user",
                    "content" => $prompt
                ],
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

        $response = $this->client->request('POST', '',  [
            'json' => $payload,
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

        \Log::info('Llama API Response:', ['response' => $contents]);

        $setter = new ChatGPTPlayer();
        $setter->setMessages("user", json_encode($contents));
        return $contents;
    }

    public function setSession(string $llm): void
    {
        $this->setSession("llama");
    }

    private function setToken(): void
    {
        // Authenticate using the service account
        $credentials = ApplicationDefaultCredentials::getCredentials('https://www.googleapis.com/auth/cloud-platform');
        $token = $credentials->fetchAuthToken();
        $this->accessToken = $token['access_token'];
    }
}
