<?php

namespace App\Http\Controllers\API;

use App\AI\ChatGPTPlayer;
use App\AI\LlamaPlayer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function llamaPlay(Request $request): JsonResponse
    {
        \Log::info("Llama Request: " . $request->input("llama_prompt"));
        $llama = new LlamaPlayer();
        $llamaResponse = $llama->getLlamaResponse(in_array($request->input("llama_prompt"), ["{}", null, "null"]) ? null : $request->input("llama_prompt"));

        return response()->json($llamaResponse);
    }

    public function chatgptPlay(Request $request): JsonResponse
    {
        $chatgpt = new ChatGPTPlayer();
        $chatgptResponse = $chatgpt->send($request->input("chatgpt_prompt") == "{}" ? null : $request->input("chatgpt_prompt"));

        return response()->json($chatgptResponse);
    }
}
