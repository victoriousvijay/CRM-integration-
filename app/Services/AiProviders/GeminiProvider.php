<?php

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gemini-2.5-flash',
    ) {}

    public function chat(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(90)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userMessage]],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 8192,
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        // Gemini 2.5 thinking models return thoughts + response in separate parts.
        // Read the last non-thought part to get the actual response.
        $parts = $response->json('candidates.0.content.parts', []);
        $text = '';
        foreach ($parts as $part) {
            if (empty($part['thought'])) {
                $text = $part['text'] ?? '';
            }
        }

        return $text;
    }

    public function testConnection(): bool
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$this->apiKey}";
            $response = Http::timeout(10)->get($url);
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listModels(): array
    {
        try {
            $models = collect();
            $pageToken = null;

            // The listing is paginated and defaults to a short page, so the
            // model you want can easily sit on page two. Walk it, with a hard
            // stop so a misbehaving response cannot loop forever.
            for ($page = 0; $page < 10; $page++) {
                $response = Http::timeout(10)->get(
                    'https://generativelanguage.googleapis.com/v1beta/models',
                    array_filter([
                        'key' => $this->apiKey,
                        'pageSize' => 200,
                        'pageToken' => $pageToken,
                    ])
                );

                if ($response->failed()) {
                    return $models->isEmpty() ? $this->fallbackModels() : $this->shape($models);
                }

                $models = $models->concat($response->json('models', []));

                $pageToken = $response->json('nextPageToken');
                if (! $pageToken) {
                    break;
                }
            }

            $usable = $this->shape($models);

            // An API key with no models listed is not a working setup — offer
            // the current model names rather than an empty box.
            return $usable === [] ? $this->fallbackModels() : $usable;
        } catch (\Throwable $e) {
            return $this->fallbackModels();
        }
    }

    /**
     * Keep the models that can actually answer a chat request, and name them.
     *
     * The generation methods a model supports live in
     * `supportedGenerationMethods`, not in `name` — `name` is only ever
     * "models/gemini-2.5-flash". Matching "generateContent" against `name`
     * discarded every model and left the settings page reporting that the key
     * had none.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $models
     * @return list<array{id: string, name: string}>
     */
    protected function shape($models): array
    {
        return $models
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(function ($m) {
                // Models come as "models/gemini-2.5-flash" — strip the prefix.
                $id = str_replace('models/', '', $m['name'] ?? '');

                return ['id' => $id, 'name' => ($m['displayName'] ?? '') ?: $id];
            })
            ->filter(fn ($m) => $m['id'] !== '')
            ->unique('id')
            ->sortBy('id', SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function fallbackModels(): array
    {
        return [
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash'],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro'],
            ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash'],
        ];
    }
}
