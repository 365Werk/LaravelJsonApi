<?php

namespace Werk365\LaravelJsonApi\Controllers;

use App\Http\Controllers\Controller;
use App\Users\Models\Address;
use Werk365\LaravelJsonApi\Requests\JsonApiRequest;
use Werk365\LaravelJsonApi\Services\JsonApiService;
use Werk365\LaravelJsonApi\Services\ModelResolvingService;

/**
 * @group Addresses
 * APIs for addresses and entries
 * Routes for all addresses points
 */
class JsonApiController extends Controller
{
    protected function resourceMethodsWithoutModels()
    {
        return ['index', 'show'];
    }

    /**
     * @var JSONAPIService
     */
    private $service;
    private $model;

    public function __construct(JsonApiService $service, ModelResolvingService $resolve)
    {
        $this->service = $service;
        $this->model = $resolve;
    }

    /**
     * Display a list of addresses.
     *
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function index(string $type)
    {
        return $this->service->fetchResources($this->model->resolve($type), $type);
    }

    /**
     * Display the specified address.
     *
     * @param  \App\Users\Models\Address $address
     * @return \Illuminate\Http\Response
     */
    public function show($type, $id)
    {
        return $this->service->fetchResource($this->model->resolve($type, $id));
    }

    /**
     * Store a newly created address in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @bodyParam data object required The address details
     * @bodyParam data.type string required The address type (address)
     * @bodyParam data.attributes object required The address attributes
     * @bodyParam data.attributes.uuid string required The user uuid
     * @bodyParam data.attributes.street string required The street name
     * @bodyParam data.attributes.house_number string required The house number
     * @bodyParam data.attributes.house_number_addition string optional The house number addition
     * @bodyParam data.attributes.postal_code string required The postal code
     * @bodyParam data.attributes.city string required The city name
     * @bodyParam data.attributes.municipality string required The municipality name
     * @bodyParam data.attributes.country string required The country name
     */
    public function store(JSONAPIRequest $request, $type)
    {
        return $this->service->createResource($this->model->resolve($type), $request->input('data.attributes'), $request->input('data.relationships'));
    }

    /**
     * Update the specified address in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Users\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function update(JSONAPIRequest $request, $type, $id)
    {
        return $this->service->updateResource($this->model->resolve($type, $id), $request->input('data.attributes'), $request->input('data.relationships'));
    }

    /**
     * Remove the specified address from storage.
     *
     * @param  \App\Users\Models\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function destroy($type, $id)
    {
        return $this->service->deleteResource($this->model->resolve($type, $id));
    }
}
