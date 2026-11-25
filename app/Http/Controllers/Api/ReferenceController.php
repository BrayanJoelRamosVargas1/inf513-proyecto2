<?php

namespace App\Http\Controllers\Api;

use App\Domain\InstitutionalCatalogue;
use App\Http\Controllers\Controller;

final class ReferenceController extends Controller
{
    public function download(string $key, InstitutionalCatalogue $catalogue)
    {
        [$source, $bytes] = $catalogue->original($key);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$source['file'].'"', 'Content-Length' => strlen($bytes), 'X-Content-Type-Options' => 'nosniff', 'X-Document-SHA256' => $source['sha256'], 'Cache-Control' => 'private, no-store']);
    }
}
