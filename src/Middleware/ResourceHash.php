<?php

namespace Werk365\LaravelJsonApi\Middleware;

use Werk365\LaravelJsonApi\Resources\JsonApiResource;
use Closure;
use Illuminate\Http\Request;

class ResourceHash extends Middleware
{
    public string $middleware = 'resourceHash';

    public function hash(array $resource): string
    {
        return '"'.md5(json_encode($resource)).'"';
    }

    public function wrap(object $resource): array
    {
        $wrap = JsonApiResource::$wrap;

        return [$wrap => $resource];
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only handle GET requests
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Skip to response, return unless statusCode is equal to 200
        $response = $next($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $content = json_decode($response->getContent());

        // Return if response does not appear to be a JSONAPIResource
        $wrap = JsonApiResource::$wrap;
        if (! isset($content->$wrap)) {
            return $response;
        }

        // Get hash of primary resource
        $resource = $this->wrap($content->$wrap);
        $meta = [
            'hash' => $this->hash($resource),
        ];

        // Add hash to metadata or create new metadata
        if (isset($content->$wrap->meta)) {
            $content->$wrap->meta = array_merge($content->$wrap->meta, $meta);
        } else {
            $content->$wrap->meta = $meta;
        }

        // Handle included resources
        $includes = $content->included ?? [];

        foreach ($includes as $key => $include) {
            $meta = [
                'hash' => $this->hash($this->wrap($include)),
            ];

            // Add hash to metadata or create new metadata
            if (isset($content->included[$key]->meta)) {
                $content->included[$key]->meta = array_merge($content->included[$key]->meta, $meta);
            } else {
                $content->included[$key]->meta = $meta;
            }
        }

        // Set modified content on resource and return
        $response->setContent(json_encode($content));

        return $response;
    }
}
