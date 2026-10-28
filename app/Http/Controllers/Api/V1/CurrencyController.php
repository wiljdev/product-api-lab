<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    /**
     * Display the available currencies, needed to create products and prices.
     */
    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(Currency::orderBy('name')->get());
    }
}
