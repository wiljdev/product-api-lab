<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Resources\ProductPriceResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductPriceController extends Controller
{
    /**
     * Display the prices of a product in other currencies.
     */
    public function index(Product $product): AnonymousResourceCollection
    {
        $prices = $product->prices()->with('currency')->orderBy('currency_id')->get();

        return ProductPriceResource::collection($prices);
    }

    /**
     * Store a new price of a product in another currency.
     */
    public function store(StoreProductPriceRequest $request, Product $product): ProductPriceResource
    {
        $price = $product->prices()->create($request->validated());

        return new ProductPriceResource($price->load('currency'));
    }
}
