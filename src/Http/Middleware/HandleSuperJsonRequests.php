<?php

namespace SulimanBenhalim\LaravelSuperJson\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SulimanBenhalim\LaravelSuperJson\Facades\SuperJson;

class HandleSuperJsonRequests
{
    /**
     * Handle an incoming request with SuperJSON content using secure attribute storage
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @return mixed Response from next middleware
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request contains SuperJSON
        if ($this->isSuperjsonRequest($request)) {
            $content = $request->getContent();

            if (! empty($content)) {
                try {
                    // Deserialize SuperJSON content
                    $data = SuperJson::deserialize($content);

                    // SECURITY: Do not merge directly into request to avoid parameter pollution
                    // Instead, store the deserialized data in attributes for safe access
                    $request->attributes->set('superjson_data', $data);
                    $request->attributes->set('superjson_original', json_decode($content, true));

                } catch (\Exception $e) {
                    // Log error but continue processing - sanitize logged content
                    \Log::warning('SuperJSON deserialization failed', [
                        'error' => $e->getMessage(),
                        'content_length' => strlen($content),
                        'content_type' => $request->header('Content-Type'),
                        // Don't log actual content for security
                    ]);
                }
            }
        }

        $response = $next($request);

        // Auto-transform response if client accepts SuperJSON
        if ($this->shouldTransformResponse($request, $response)) {
            $data = $response->getData(true);

            if ($data !== null) {
                $serialized = SuperJson::serialize($data);
                $response->setData($serialized);
                $response->header('Content-Type', 'application/superjson');
            }
        }

        return $response;
    }

    /**
     * Check if request contains SuperJSON content by examining headers and parameters
     *
     * @param  Request  $request  The HTTP request to check
     * @return bool True if request contains SuperJSON content
     */
    protected function isSuperjsonRequest(Request $request): bool
    {
        $contentType = $request->header('Content-Type', '');

        return str_contains($contentType, 'application/superjson')
            || str_contains($contentType, 'application/sjson')
            || $request->has('_superjson');
    }

    /**
     * Check if response should be automatically transformed to SuperJSON format
     *
     * @param  Request  $request  The HTTP request
     * @param  mixed  $response  The response to check
     * @return bool True if response should be transformed to SuperJSON
     */
    protected function shouldTransformResponse(Request $request, $response): bool
    {
        if (! method_exists($response, 'getData')) {
            return false;
        }

        $accept = $request->header('Accept', '');

        return str_contains($accept, 'application/superjson')
            || str_contains($accept, 'application/sjson')
            || $request->has('_superjson');
    }
}
