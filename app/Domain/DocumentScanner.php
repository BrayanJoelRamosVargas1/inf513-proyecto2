<?php

namespace App\Domain;

interface DocumentScanner
{
    /** Return state (clean/blocked/error), engine and a non-sensitive reason code. */
    public function scan(string $path): array;
}
