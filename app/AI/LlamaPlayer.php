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
                'Content-Type' => 'application/json'
            ],
            'http_errors' => false,
        ]);
    }

    public function getLlamaResponse(mixed $prompt = null): array
    {
        if ($prompt === null || $prompt === "null") {
            $prompt = "You are an assistant and you are playing D&D 5e. Please create a character in D&D 5e
            and return your response to the DM. Use only HTML for line breaks and
	    text formatting. Only play your one character. Do not speak for anyone else.
	    Your answer should be between 150 and 350 words.";
        }


        $message = [
            'instances' => [
                ['prompt' => $prompt],
            ],
            'parameters' => [
                "temperature" => 0.7,
                "top_p" => 0.9,
                "top_k" => 40,
                "max_tokens" => 2048,
                "role" => "assistant",
            ]
        ];

        // Using request() with method 'POST'
        $response = $this->client->request('POST', '', [
            'json' => $message
        ]);

        $contents = json_decode($response->getBody()->getContents(), true);

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
