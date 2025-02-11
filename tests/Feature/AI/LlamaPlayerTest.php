<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Tests\TestCase;

class LlamaPlayerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_llama_responds_with_200(): void
    {
        $llama = Mockery::mock(LlamaPlayer::class);
        $llama->messages = [];
        $llama->shouldReceive("getLlamaResponse")
            ->andReturnUsing(function ($prompt) use ($llama) {
                $response = [
                    "predictions" => [
                        [
                            "content" => [
                                "role" => "assistant",
                                "content" => [
                                    "content_type" => "text",
                                    "parts" => ["Hello, world!"]
                                ]
                            ]
                        ]
                    ]
                ];
                // Update messages property with the response payload.
                $llama->messages[] = $response;
                return $response;
            });

        $this->app->instance(LlamaPlayer::class, $llama);

        $result = $llama->getLlamaResponse("Hello, world!");
        $this->assertEquals("Hello, world!", $result['predictions'][0]['content']['content']['parts'][0]);
    }
}
