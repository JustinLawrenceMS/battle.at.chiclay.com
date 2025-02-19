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

        $input = $request->input("chatgpt_prompt");
        $chatgptResponse = $chatgpt->send($input['prompt']);

        return response()->json($chatgptResponse);
    }

    public function geminiPlay(Request $request): JsonResponse
    {
        $input = $request->input('gemini_prompt')['prompt'] ?? "";
        if (is_null($input)) {
            return response()->json(['error' => 'input was null']);
        }
        $response = (new GeminiPlayer)->generateGeminiResponse($input);

        return response()->json(['response' => $response]);
    }
}

