<?php

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    /**
     * The model used when a tenant has not picked one.
     *
     * Google retires a generation for new keys before it stops answering for
     * old ones: gemini-2.5-flash kept working on existing projects while any
     * newly created key got a flat 404 telling it to move to 3.6. Keep this on
     * the current generation — anyone who wants an older one names it in
     * settings.
     */
    public const DEFAULT_MODEL = 'gemini-3.6-flash';

    public function __construct(
        protected string $apiKey,
        protected string $model = self::DEFAULT_MODEL,
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
            throw new \RuntimeException($this->readableError($response->status(), $response->json('error.message') ?: $response->body()));
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

    /**
     * Turn a Gemini failure into something the person reading it can act on.
     *
     * The raw body was being printed straight onto the page — a wall of JSON
     * whose actual instruction ("use models/gemini-3.6-flash") was buried in
     * it. The common failures all have one obvious next step, so say it.
     */
    protected function readableError(int $status, string $message): string
    {
        $where = __('Settings → AI');

        return match (true) {
            $status === 404 => __(':model is not available for this API key — pick another model under :where. (:message)', [
                'model' => $this->model,
                'where' => $where,
                'message' => $message,
            ]),
            in_array($status, [401, 403], true) => __('Google rejected this API key. Check it under :where. (:message)', [
                'where' => $where,
                'message' => $message,
            ]),
            $status === 429 => __('Gemini rate limit or quota reached. Wait a moment, or check your Google AI Studio plan. (:message)', [
                'message' => $message,
            ]),
            default => __('Gemini API error: :message', ['message' => $message]),
        };
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
            ['id' => self::DEFAULT_MODEL, 'name' => 'Gemini 3.6 Flash'],
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (older keys only)'],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro (older keys only)'],
        ];
    }
}
