<?php

namespace Tests\Feature;

use App\Services\AiProviders\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Fetch Models" on the AI settings page.
 *
 * It reported "No models found" for a perfectly good Gemini key, because the
 * filter asked whether the model's *name* contained "generateContent" — and a
 * name is only ever "models/gemini-2.5-flash". The supported methods live in
 * their own field. Every model failed the test, so the box came back empty and
 * the key looked broken when it was not.
 */
class AiModelListingTest extends TestCase
{
    protected function page(array $models, ?string $nextPageToken = null): array
    {
        return array_filter([
            'models' => $models,
            'nextPageToken' => $nextPageToken,
        ]);
    }

    public function test_gemini_models_that_can_chat_are_listed(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->page([
                [
                    'name' => 'models/gemini-2.5-flash',
                    'displayName' => 'Gemini 2.5 Flash',
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                ],
                [
                    'name' => 'models/gemini-2.5-pro',
                    'displayName' => 'Gemini 2.5 Pro',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ])),
        ]);

        $models = (new GeminiProvider('test-key'))->listModels();

        $this->assertSame([
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash'],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro'],
        ], $models);
    }

    public function test_models_that_cannot_chat_are_left_out(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->page([
                [
                    'name' => 'models/text-embedding-004',
                    'displayName' => 'Text Embedding 004',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ])),
        ]);

        $models = (new GeminiProvider('test-key'))->listModels();

        $this->assertSame([['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash']], $models);
    }

    public function test_a_model_on_the_second_page_is_still_offered(): void
    {
        // The listing is paginated, and the model you want is not always on
        // page one.
        Http::fakeSequence()
            ->push($this->page([[
                'name' => 'models/gemini-2.0-flash',
                'displayName' => 'Gemini 2.0 Flash',
                'supportedGenerationMethods' => ['generateContent'],
            ]], 'page-2'))
            ->push($this->page([[
                'name' => 'models/gemini-2.5-pro',
                'displayName' => 'Gemini 2.5 Pro',
                'supportedGenerationMethods' => ['generateContent'],
            ]]));

        $models = (new GeminiProvider('test-key'))->listModels();

        $this->assertSame(['gemini-2.0-flash', 'gemini-2.5-pro'], array_column($models, 'id'));
    }

    public function test_a_rejected_key_still_leaves_something_to_pick(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400),
        ]);

        $models = (new GeminiProvider('bad-key'))->listModels();

        $this->assertNotEmpty($models);
        $this->assertContains('gemini-2.5-flash', array_column($models, 'id'));
    }
}
