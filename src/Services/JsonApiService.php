<?php

namespace Werk365\LaravelJsonApi\Services;

use Werk365\LaravelJsonApi\Resources\JsonApiCollection;
use Werk365\LaravelJsonApi\Resources\JsonApiResource;
use Werk365\LaravelJsonApi\Resources\JsonApiIdentifierResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;


class JsonApiService
{

    /**
     * @param string $modelClass
     * @param string $type
     *
     * @return Response
     */
    public function fetchResources(string $modelClass, string $type)
    {

        $models = QueryBuilder::for($modelClass)
            ->allowedSorts(config("jsonapi.resources.{$type}.allowedSorts"))
            ->allowedFields(config("jsonapi.resources.{$type}.allowedFields"))
            ->allowedIncludes(config("jsonapi.resources.{$type}.allowedIncludes"))
            ->allowedFilters(config("jsonapi.resources.{$type}.allowedFilters"))
            ->jsonPaginate();


        return new JsonApiCollection($models);
    }

    /**
     * @param $model
     *
     * @param int $id
     * @param string $type
     *
     * @return JsonApiResource
     */
    public function fetchResource($model, $id = 0, $type = '')
    {
        if($model instanceof Model){
            return new JsonApiResource($model);
        }
        $pk = (new $model)->getKeyName();

        $query = QueryBuilder::for($model::where($pk, $id))
            ->allowedFields(config("jsonapi.resources.{$type}.allowedFields"))
            ->allowedIncludes(config("jsonapi.resources.{$type}.allowedIncludes"))
            ->firstOrFail();
        return new JsonApiResource($query);
    }

    /**
     * @param string $modelClass
     * @param array $attributes
     *
     * @return Response
     */
    public function createResource(string $modelClass, array $attributes, array $relationships = null)
    {
        $model = $modelClass::create($attributes);

        if ($relationships) {
            $this->handleRelationship($relationships, $model);
        }

        return (new JsonApiResource($model))
            ->response()
            ->header('Location', route("{$model->type()}.show", [
                Str::singular($model->type()) => $model,
            ], false));
    }

    public function createRelated(string $modelClass, array $attributes, array $relationships = null)
    {
        $model = $modelClass::create($attributes);

        if ($relationships) {
            $this->handleRelationship($relationships, $model);
        }

        return $model;
    }

    /**
     * @param string $modelClass
     * @param array $attributes
     *
     * @return Response
     */
    public function createResourceWithRelated(string $modelClass, array $attributes, array $relationships = null)
    {
        $model = $modelClass::create($attributes);

        if ($relationships) {
            foreach($relationships as $relationship){
                $relationship['data']['attributes']['uuid'] = $model->uuid;
                $relationship["model"] = $this->createRelated(config('jsonapi.resources.' . $relationship['data']['type'] . '.model'), $relationship['data']['attributes']);
            }
        }
        return (new JsonApiResource($model))
            ->response()
            ->header('Location', route("{$model->type()}.show", [
                Str::singular($model->type()) => $model,
            ], false));
    }

    /**
     * @param $model
     * @param $attributes
     *
     * @return Response
     */
    public function updateResource($model, $attributes, $relationships = null)
    {
        $model->update($attributes);

        if($relationships){
            $this->handleRelationship($relationships, $model);
        }

        return new JsonApiResource($model);
    }

    /**
     * @param $model
     *
     * @return Response
     */
    public function deleteResource($model)
    {
        $model->delete();
        return response(null, 204);
    }

    /**
     * @param $model
     * @param string $relationship
     *
     * @return Response
     */
    public function fetchRelationship($model, string $relationship)
    {
        if($model->$relationship instanceof Model){
            return new JsonApiIdentifierResource($model->$relationship);
        }

        return JsonApiIdentifierResource::collection($model->$relationship);
    }

    public function updateToOneRelationship($model, $relationship, $id)
    {
        $relatedModel = $model->$relationship()->getRelated();

        $model->$relationship()->dissociate();

        if($id){
            $newModel = $relatedModel->newQuery()->findOrFail($id);
            $model->$relationship()->associate($newModel);
        }

        $model->save();
        return response(null, 204);
    }

    public function updateToManyRelationships($model, $relationship, $ids)
    {
        $foreignKey = $model->$relationship()->getForeignKeyName();
        $relatedModel = $model->$relationship()->getRelated();
        $pk = $model->getKeyName();

        $relatedModel->newQuery()->findOrFail($ids);


        $relatedModel->newQuery()->where($foreignKey, $model->$pk)->update([
            $foreignKey => null,
        ]);

        $relatedModel->newQuery()->whereIn('id', $ids)->update([
            $foreignKey => $model->id,
        ]);

        return response(null, 204);
    }

    /**
     * @param $model
     * @param $relationship
     * @param $ids
     *
     * @return Response
     */
    public function updateManyToManyRelationships($model, $relationship, $ids)
    {
        $model->$relationship()->sync($ids);
        return response(null, 204);
    }

    public function fetchRelated($model, $relationship)
    {

        if($model->$relationship instanceof Model){
            return new JsonApiResource($model->$relationship);
        }
//        dd($model, $relationship);
        return new JsonApiCollection($model->$relationship);
    }

    /**
     * @param array $relationships
     * @param $model
     */
    protected function handleRelationship(array $relationships, $model): void
    {
        foreach ($relationships as $relationshipName => $contents) {
            if ($model->$relationshipName() instanceof BelongsTo) {
                $this->updateToOneRelationship($model, $relationshipName, $contents['data']['id']);
            }
            if($model->$relationshipName() instanceof BelongsToMany){
                $this->updateManyToManyRelationships($model, $relationshipName, collect($contents['data'])->pluck('id'));
            }
        }

        $model->load(array_keys($relationships));
    }


}
