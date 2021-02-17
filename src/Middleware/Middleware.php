<?php

namespace Werk365\LaravelJsonApi\Middleware;

class Middleware
{
    public string $middleware;

    public function name(): string
    {
        return $this->middleware;
    }
}
