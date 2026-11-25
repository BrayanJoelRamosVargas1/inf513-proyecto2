<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keep the retained Blade interface usable when mounted below the teacher's URL. */
final class SubpathLinks
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $base = rtrim($request->getBaseUrl(), '/');
        if ($base === '' || ! str_contains(strtolower($response->headers->get('Content-Type', '')), 'text/html')) {
            return $response;
        }
        $content = $response->getContent();
        if (! is_string($content)) {
            return $response;
        }
        $updated = preg_replace_callback(
            '~(\b(?:href|src|action)\s*=\s*)(["\'])(/(?!/)[^"\']*)\2~i',
            function (array $match) use ($base) {
                $url = $match[3];
                if ($url === $base || str_starts_with($url, $base.'/')) {
                    return $match[0];
                }

                return $match[1].$match[2].$base.$url.$match[2];
            },
            $content
        );
        if ($updated !== null && $updated !== $content) {
            $response->setContent($updated);
            $response->headers->remove('Content-Length');
            $response->headers->remove('ETag');
        }

        return $response;
    }
}
