<?php

namespace Werk365\LaravelJsonApi\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class JsonApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->{config("jsonapi.resources.{$this->type()}.primaryKeyName")},
            'type' => $this->type(),
            'attributes' => $this->allowedAttributes(),
            'relationships' => $this->prepareRelationships(),
        ];
    }

    private function prepareRelationships()
    {
        $collection = collect(config("jsonapi.resources.{$this->type()}.relationships"))->flatMap(function ($related) {
            $relatedType = $related['type'];
            $relationship = $related['method'];

            return [
                $relatedType => [
                    'links' => [
                        'self'    => route(
                            "{$this->type()}.relationships.{$relatedType}",
                            [config("jsonapi.resources.{$this->type()}.object_name") => $this->{config("jsonapi.resources.{$this->type()}.primaryKeyName")}]
                        ),
                        'related' => route(
                            "{$this->type()}.{$relatedType}",
                            [config("jsonapi.resources.{$this->type()}.object_name") => $this->{config("jsonapi.resources.{$this->type()}.primaryKeyName")}]
                        ),
                    ],
                    'data' => $this->prepareRelationshipData($relatedType, $relationship),
                ],
            ];
        });

        return $collection->count() > 0 ? $collection : new MissingValue();
    }

    private function prepareRelationshipData($relatedType, $relationship)
    {
        if ($this->whenLoaded($relationship) instanceof MissingValue) {
            // return new MissingValue();
        }

        if ($this->$relationship() instanceof BelongsTo) {
            return new JsonApiIdentifierResource($this->$relationship);
        }

        return JsonApiIdentifierResource::collection($this->$relationship);
    }

    public function with($request)
    {
        $with = [];

        if (strstr($_SERVER['REQUEST_URI'], 'include')) {
            if ($this->included($request)->isNotEmpty()) {
                $with['included'] = $this->included($request);
            }
        }

        return $with;
    }

    public function included($request)
    {
        return collect($this->relations())
            ->filter(function ($resource) {
                return $resource->collection !== null;
            })->flatMap->toArray($request);
    }

    private function relations()
    {
        return collect(config("jsonapi.resources.{$this->type()}.relationships"))->map(function ($relation) {
            $modelOrCollection = $this->whenLoaded($relation['method']);

            if ($modelOrCollection instanceof Model) {
                $modelOrCollection = collect([new JsonApiResource($modelOrCollection)]);
            }

            return JsonApiResource::collection($modelOrCollection);
        });
    }
}
