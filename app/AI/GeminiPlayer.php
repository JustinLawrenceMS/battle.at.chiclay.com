<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiPlayer
{
   public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generateGeminiResponse(string $prompt = null): mixed
    {
        if ($prompt === null) {  // Fixed assignment issue
            $prompt = "You are a gaming assistant. You are playing D&D 5e. Create a character.";
        }

        $prompt .= "Only refer to yourself in the first person singular. Only play your own character. Do not replay in markup, if you need to format, use basic html tags. Play the game by D&D 5e rules. You are not the dungeon master.  Follow the dungeon master's lead. You are playing D&D, not writing a novel.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$this->apiKey}";

        try {
            $requestBody = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [  // Correct placement of generation config
                    'temperature' => config('services.gemini.temperature', 0.8),
                    'maxOutputTokens' => config('services.gemini.max_output_tokens', 300),
                ],
            ];


            $response = Http::withHeaders([
                'User-Agent' => 'MyLaravelApp/1.0',
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody); // Pass the request body

            $responseJson = $response->json();

            if ($response->successful() && isset($responseJson['candidates'][0]['content']['parts'][0]['text'])) {
                return $responseJson['candidates'][0]['content']['parts'][0]['text'];
            } else {
                Log::error('Gemini API Error', [
                    'status' => $response->status(),
                    'body' => $responseJson,
                    'request' => $prompt,
                    'requestBody' => $requestBody, // Log the request body
                ]);
                throw new \Exception('Invalid response from Gemini API');
            }

        } catch (Throwable $e) {
            Log::error('Gemini API Exception', [
                'message' => $e->getMessage(),
                'request' => $prompt,
            ]);
            return 'An error occurred while communicating with the Gemini API.';
        }
    }
}
