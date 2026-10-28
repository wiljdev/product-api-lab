<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_a_product(): void
    {
        $this->actingAsViewer();

        $this->postJson(route('api.v1.products.store'), [
            'name' => 'Standing desk',
            'description' => 'Electric height adjustable desk.',
            'price' => 150,
            'currency_id' => Currency::factory()->create()->id,
            'tax_cost' => 28.5,
            'manufacturing_cost' => 70,
        ])->assertForbidden();

        $this->assertDatabaseEmpty('products');
    }

    public function test_viewer_cannot_update_a_product(): void
    {
        $this->actingAsViewer();
        $product = Product::factory()->create(['name' => 'Original']);

        $this->patchJson(route('api.v1.products.update', $product), ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertSame('Original', $product->fresh()->name);
    }

    public function test_viewer_cannot_delete_a_product(): void
    {
        $this->actingAsViewer();
        $product = Product::factory()->create();

        $this->deleteJson(route('api.v1.products.destroy', $product))
            ->assertForbidden()
            ->assertExactJson(['message' => 'This token does not have permission to perform this action.']);

        $this->assertModelExists($product);
    }

    public function test_viewer_cannot_create_a_price(): void
    {
        $this->actingAsViewer();
        $product = Product::factory()->create();

        $this->postJson(route('api.v1.products.prices.store', $product), [
            'currency_id' => Currency::factory()->create()->id,
            'price' => 10,
        ])->assertForbidden();

        $this->assertDatabaseEmpty('product_prices');
    }

    public function test_viewer_can_still_read_and_see_its_profile(): void
    {
        $viewer = $this->actingAsViewer();
        $product = Product::factory()->create();

        $this->getJson(route('api.v1.products.show', $product))->assertOk();
        $this->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $viewer->id)
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_token_issued_at_login_enforces_the_role_abilities(): void
    {
        User::factory()->create(['email' => 'viewer@example.com']);
        $token = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'viewer@example.com',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)
            ->deleteJson(route('api.v1.products.destroy', Product::factory()->create()))
            ->assertForbidden();
    }

    public function test_editor_can_write(): void
    {
        $this->actingAsEditor();
        $product = Product::factory()->create();

        $this->deleteJson(route('api.v1.products.destroy', $product))->assertNoContent();
    }

    /**
     * Authenticate as a viewer holding the token abilities of its role.
     */
    private function actingAsViewer(): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $user->role->abilities());

        return $user;
    }
}
