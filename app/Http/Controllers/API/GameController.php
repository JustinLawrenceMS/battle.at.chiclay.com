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

        public function geminiPlay(Request $request): JsonResponse
    {
        $prompt = $request->input('gemini_prompt.prompt') ?? null;

        $response = (new GeminiPlayer)->generateGeminiResponse($prompt);

        return response()->json(['response' => $response]);
    }
}
