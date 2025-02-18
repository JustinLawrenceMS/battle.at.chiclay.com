<?php

use App\Http\Controllers\API\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/gemini', [GameController::class,'geminiPlay'])->name('gemini.play');
Route::post('/v1/llama', [GameController::class,'llamaPlay'])->name('llama.play');
Route::post('/v1/chatgpt', [GameController::class, 'chatgptPlay'])->name('chatgpt.play');
