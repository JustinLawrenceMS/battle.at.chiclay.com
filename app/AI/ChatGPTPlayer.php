<?php

namespace App\AI;

use OpenAI\Laravel\Facades\OpenAI;

class ChatGPTPlayer
{
    private string $systemMessage = "You are a dungeon master assistant leading a game of D&D 5e.  You are playing a text based D&D 5e adventure of your own creation with up to two other players, who will preface each message with 'this is Player 1' or 'this is Player 2'. Player 1 is another AI.  Player 2 is a human.  When you address one of them, address them as Player one or Player 2.  When you address both of them, say 'To both players'.  Do not say 'To both players' unless player 2 appears in the message history.  Only be the dungeon master.  Do not speak for anyone else. Use only HTML for line breaks and formatting";

    protected array $messages = [];

    public function systemMessage(string $message = null): static
    {
        $this->messages[] = [
            'role' => 'system',
            'content' => $this->systemMessage
        ];

        $this->setSession("chatgpt");

        return $this;
    }

    public function send(string $message = ""): ?string
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        $this->systemMessage();

        $response = OpenAI::chat()->create([
            "model" => "gpt-4-turbo",
            'max_tokens' => 4096,
            "messages" => $this->messages
        ])->choices[0]->message->content;

        if ($response) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $response,
            ];
        }

        $this->setSession('chatgpt');

        return $response;
    }

    public function reply(string $message): ?string
    {
        $this->setSession('chatgpt');

        return $this->send($message);
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function setMessages(string $role, string $message): void
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $message,
        ];
    }

    public function setSession(string $llm): void
    {
        if (!session($llm)) {
            session(
                [
                    $llm => [
                        'messages' => json_encode(
                            $this->messages,
                            JSON_PRETTY_PRINT
                        )
                    ]
                ]
            );
        } else {
            $sess = json_decode(session($llm . '.messages'), true);
            $merge = array_merge($sess, $this->messages);

            // Remove duplicates from the multidimensional array
            $unique = array_map(
                'unserialize',
                array_unique(
                    array_map(
                        'serialize',
                        $merge
                    )
                )
            );

            session(
                [
                    $llm => [
                        'messages' => json_encode(
                            $unique,
                            JSON_PRETTY_PRINT
                        )
                    ]
                ]
            );

            $this->messages = json_decode(
                session(
                    $llm
                )['messages'],
                true
            );
        }
    }
}
