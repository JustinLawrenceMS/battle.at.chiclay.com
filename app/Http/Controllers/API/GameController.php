<?php

namespace App\Http\Controllers\API;

use App\AI\ChatGPTPlayer;
use App\AI\GeminiPlayer;
use App\AI\LlamaPlayer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function llamaPlay(Request $request): JsonResponse
    {
        $llama = new LlamaPlayer();
        $llamaResponse = $llama->getLlamaResponse($request->input("llama_prompt.prompt"));

        return response()->json($llamaResponse);
    }

    public function chatgptPlay(Request $request): JsonResponse
    {
        $chatgpt = new ChatGPTPlayer();
	    $chatgptResponse = $chatgpt->send($request->input("chatgpt_prompt.prompt"));

        return response()->json($chatgptResponse);
    }

        public function play(Request $request): JsonResponse
    {
        $playerAction = $request->input('action');
        $character = $request->input('character', 'an AI-controlled adventurer');

        $prompt = "In a Dungeons & Dragons game, {$character} is about to act. The situation is: {$playerAction}. What does the character do next? Provide a concise, roleplay-friendly response.";

        $response = (new GeminiPlayer)->generateGeminiResponse($prompt);

        return response()->json(['response' => $response]);
    }
}
