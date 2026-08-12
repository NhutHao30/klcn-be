<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RewriteImageUrls
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_contains($response->headers->get('Content-Type') ?? '', 'application/json')) {
            $content = $response->getContent();
            
            if (str_contains($content, ':9000')) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $proxyBase = '/api/proxy-image?url=';
                    $data = $this->replaceUrls($data, $proxyBase);
                    $response->setContent(json_encode($data));
                }
            }
        }
        return $response;
    }

    private function replaceUrls($data, $proxyBase)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->replaceUrls($value, $proxyBase);
            }
        } elseif (is_string($data) && preg_match('/^https?:\/\/(127\.0\.0\.1|localhost):9000\//', $data)) {
            return $proxyBase . urlencode($data);
        }
        return $data;
    }
}
