<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_home_redirects_to_the_api_docs(): void
    {
        $this->get('/')->assertRedirect('/docs');
    }

    public function test_swagger_ui_loads_the_openapi_specification(): void
    {
        $this->get(route('docs'))
            ->assertOk()
            ->assertSee('swagger-ui-bundle.js', false)
            ->assertSee("url: '/docs/openapi.yaml'", false);
    }

    public function test_openapi_specification_is_served_as_yaml(): void
    {
        $response = $this->get(route('docs.openapi'));

        $response->assertOk()->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');
        $this->assertSame('3.1.0', Yaml::parse($response->streamedContent())['openapi']);
    }

    public function test_every_api_route_is_documented(): void
    {
        $documented = $this->documentedOperations();

        foreach ($this->apiOperations() as $operation) {
            $this->assertContains($operation, $documented, "Route [{$operation}] is missing from docs/openapi.yaml.");
        }
    }

    public function test_every_documented_operation_exists(): void
    {
        $routes = $this->apiOperations();

        foreach ($this->documentedOperations() as $operation) {
            $this->assertContains($operation, $routes, "Documented operation [{$operation}] has no route.");
        }
    }

    public function test_every_reference_points_to_an_existing_component(): void
    {
        $specification = $this->specification();

        array_walk_recursive($specification, function (mixed $value, string|int $key) use ($specification) {
            if ($key !== '$ref') {
                return;
            }

            $path = str_replace('/', '.', Str::after($value, '#/'));

            $this->assertTrue(data_get($specification, $path) !== null, "Reference [{$value}] does not exist.");
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function specification(): array
    {
        return Yaml::parseFile(base_path('docs/openapi.yaml'));
    }

    /**
     * Operations documented in the specification, such as "GET /products/{product}".
     *
     * @return list<string>
     */
    private function documentedOperations(): array
    {
        $operations = [];

        foreach ($this->specification()['paths'] as $path => $pathItem) {
            foreach (array_keys($pathItem) as $method) {
                if ($method !== 'parameters') {
                    $operations[] = Str::upper($method).' '.$path;
                }
            }
        }

        return $operations;
    }

    /**
     * Operations registered under /api/v1, relative to the documented server URL.
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
