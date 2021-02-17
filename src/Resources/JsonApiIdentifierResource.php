<?php

namespace Werk365\LaravelJsonApi\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JsonApiIdentifierResource extends JsonResource
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
            'id' => (string) $this->{config("jsonapi.resources.{$this->type()}.primaryKeyName")},
            'type' => $this->type(),
        ];
    }
}
