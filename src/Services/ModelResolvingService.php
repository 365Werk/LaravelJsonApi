<?php

namespace Werk365\LaravelJsonApi\Services;

class ModelResolvingService
{
    public function resolve($type, $id = null)
    {
        $modelName = config("jsonapi.resources.$type.model");

        return ($id) ? (new $modelName)->find($id) : new $modelName;
    }
}
