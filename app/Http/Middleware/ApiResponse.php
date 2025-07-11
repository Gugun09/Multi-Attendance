<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only modify JSON responses
        if ($response->headers->get('Content-Type') === 'application/json') {
            $data = json_decode($response->getContent(), true);
            
            // Add timestamp to all API responses
            if (is_array($data)) {
                $data['timestamp'] = now()->toISOString();
                $response->setContent(json_encode($data));
            }
        }

        // Add CORS headers
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        return $response;
    }
}