<?php

/** Synthetic concurrency fixtures: require the real configured scanner, never release rows by hand. */
function ficctRaceDocument(\App\Models\User $owner, int $application, ?int $amendment = null): int
{
    if (config('database.connections.pgsql.database') !== 'ficct_test' || app()->environment() !== 'testing') {
        throw new RuntimeException('Concurrency documents require isolated ficct_test.');
    }
    $file = new \Illuminate\Http\UploadedFile(resource_path('demo-documents/cpd.pdf'), 'CPD-MUESTRA.pdf', 'application/pdf', null, true);
    $document = $amendment === null
        ? app(\App\Domain\Selection::class)->upload($owner, $application, 'cpd', $file)
        : app(\App\Domain\Participation::class)->upload($owner, $amendment, 'cpd', $file);
    $processor = app(\App\Domain\DocumentProcessor::class);
    $result = $processor->runOne($processor->enqueue($document, 'applicant'));
    if (($result['state'] ?? null) !== 'clean') {
        throw new RuntimeException('Real scanner did not accept the synthetic fixture; concurrency acceptance stopped.');
    }
    return $document;
}
