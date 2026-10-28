<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostmanCollectionTest extends TestCase
{
    public function test_collection_uses_the_postman_v2_1_schema(): void
    {
        $this->assertSame(
            'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            $this->collection()['info']['schema'],
        );
    }

    public function test_collection_covers_every_api_route(): void
    {
        $covered = $this->collectionOperations();

        foreach ($this->apiOperations() as $operation) {
            $this->assertContains($operation, $covered, "Route [{$operation}] has no request in docs/postman_collection.json.");
        }
    }

    public function test_collection_does_not_contain_real_tokens(): void
    {
        $contents = file_get_contents(base_path('docs/postman_collection.json'));

        $this->assertDoesNotMatchRegularExpression('/\d+\|[A-Za-z0-9]{20,}/', $contents);
    }

    public function test_every_request_checks_its_status_code(): void
    {
        foreach ($this->requests() as $item) {
            $script = implode("\n", $item['event'][0]['script']['exec'] ?? []);

            $this->assertStringContainsString('pm.response.to.have.status(', $script, "Request [{$item['name']}] has no status test.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collection(): array
    {
        return json_decode(file_get_contents(base_path('docs/postman_collection.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requests(): array
    {
        return collect($this->collection()['item'])->flatMap(fn (array $folder) => $folder['item'])->all();
    }

    /**
     * Operations in the collection, such as "GET /products/{product}".
     *
     * @return list<string>
     */
    private function collectionOperations(): array
    {
        return collect($this->requests())
            ->map(function (array $item) {
                $path = collect($item['request']['url']['path'])
                    ->map(fn (string $segment) => $segment === '{{productId}}' || ctype_digit($segment) ? '{product}' : $segment)
                    ->implode('/');

                return $item['request']['method'].' /'.$path;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Operations registered under /api/v1, relative to the collection base URL.
     *
     * @return list<string>
     */
    private function apiOperations(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => Str::startsWith($route->uri(), 'api/v1/'))
            ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
                ->reject(fn (string $method) => $method === 'HEAD')
                ->map(fn (string $method) => $method.' '.Str::after($route->uri(), 'api/v1')))
            ->values()
            ->all();
    }
}
