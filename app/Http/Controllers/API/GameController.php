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
        $llama = new LlamaPlayer();
        $llamaResponse = $llama->getLlamaResponse(in_array($request->input("llama_prompt['prompt']"), ["{}", null, "No response", "null", '%7D%7B', urldecode('%7D%7B')]) ? null : $request->input("llama_prompt['prompt']"));

        return response()->json($llamaResponse);
    }

    public function chatgptPlay(Request $request): JsonResponse
    {
        $chatgpt = new ChatGPTPlayer();
        $chatgptResponse = $chatgpt->send($request->input("chatgpt_prompt['prompt']") == "{}" ? null : $request->input("chatgpt_prompt['prompt']"));

        return response()->json($chatgptResponse);
    }
}
