<?php

namespace Tests\Unit;

use App\Http\Middleware\SubpathLinks;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class SubpathLinksTest extends TestCase
{
    private function mountedRequest(): Request
    {
        return Request::create('https://example.test/curso/grupo/proyecto2/ayuda', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/curso/grupo/proyecto2/index.php',
            'PHP_SELF' => '/curso/grupo/proyecto2/index.php',
            'SCRIPT_FILENAME' => '/home/grupo/proyecto2/index.php',
        ]);
    }

    public function test_subpath_changes_only_root_relative_html_links_and_form_actions(): void
    {
        $request = $this->mountedRequest();
        $this->assertSame('/curso/grupo/proyecto2', $request->getBaseUrl());
        $html = '<a href="/ayuda">Ayuda</a><form action="/salir"></form><link href="/portal.css"><a href="/curso/grupo/proyecto2/p2">React</a><a href="//example.net/">Externo</a><a href="https://example.net/">Externo</a>';
        $response = (new SubpathLinks)->handle($request, fn () => new Response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Length' => strlen($html),
        ]));
        $this->assertStringContainsString('href="/curso/grupo/proyecto2/ayuda"', $response->getContent());
        $this->assertStringContainsString('action="/curso/grupo/proyecto2/salir"', $response->getContent());
        $this->assertStringContainsString('href="/curso/grupo/proyecto2/portal.css"', $response->getContent());
        $this->assertStringNotContainsString('/proyecto2/curso/', $response->getContent());
        $this->assertStringContainsString('href="//example.net/"', $response->getContent());
        $this->assertFalse($response->headers->has('Content-Length'));
    }

    public function test_root_installation_json_and_binary_responses_are_preserved(): void
    {
        $middleware = new SubpathLinks;
        $html = '<a href="/ayuda">Ayuda</a>';
        $response = $middleware->handle(Request::create('https://example.test/ayuda'), fn () => new Response($html, 200, ['Content-Type' => 'text/html']));
        $this->assertSame($html, $response->getContent());
        foreach (['application/json', 'application/pdf'] as $type) {
            $body = '%PDF- bytes href="/documento"';
            $response = $middleware->handle($this->mountedRequest(), fn () => new Response($body, 200, ['Content-Type' => $type]));
            $this->assertSame($body, $response->getContent());
        }
    }
}
