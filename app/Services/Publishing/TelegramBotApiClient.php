<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramBotApiClient
{
    /**
     * @return array{
     *     id: int,
     *     username: ?string,
     *     name: string
     * }
     */
    public function getMe(string $token): array
    {
        $result = $this->request($token, 'getMe');

        if (! isset($result['id'])) {
            throw new RuntimeException('Telegram Bot API response is missing bot identity data.');
        }

        $name = trim(implode(' ', array_filter([
            isset($result['first_name']) ? (string) $result['first_name'] : null,
            isset($result['last_name']) ? (string) $result['last_name'] : null,
        ])));

        return [
            'id' => (int) $result['id'],
            'username' => isset($result['username']) && trim((string) $result['username']) !== ''
                ? (string) $result['username']
                : null,
            'name' => $name !== '' ? $name : 'Telegram Bot',
        ];
    }

    /**
     * @return array{
     *     message_id: int,
     *     chat: array<string, mixed>
     * }
     */
    public function sendMessage(string $token, string $chatId, string $text): array
    {
        $result = $this->request($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if (! isset($result['message_id'])) {
            throw new RuntimeException('Telegram Bot API response is missing message_id.');
        }

        return [
            'message_id' => (int) $result['message_id'],
            'chat' => is_array($result['chat'] ?? null) ? $result['chat'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $token, string $method, array $payload = []): array
    {
        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout($this->timeoutSeconds());

        $response = $payload === []
            ? $request->get(sprintf('/bot%s/%s', $token, $method))
            : $request->post(sprintf('/bot%s/%s', $token, $method), $payload);

        $decodedPayload = $response->json();

        if (! is_array($decodedPayload)) {
            throw new RuntimeException('Telegram Bot API returned an invalid response.');
        }

        if (! $response->successful() || ($decodedPayload['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($decodedPayload['description'] ?? 'Telegram Bot API request failed.'));
        }

        $result = $decodedPayload['result'] ?? null;

        if (! is_array($result)) {
            throw new RuntimeException('Telegram Bot API response is missing result payload.');
        }

        return $result;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.telegram.bot_api_base_url', 'https://api.telegram.org'), '/');
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.telegram.bot_api_timeout', 10);
    }
}
