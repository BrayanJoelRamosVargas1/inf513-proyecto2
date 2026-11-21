<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class DocumentController extends Controller
{
    private function document(Request $request, int $id): array
    {
        $document = Selection::row('documents', $id);
        $application = Selection::row('applications', $document->application_id);
        Access::privateFile($request->user(), $application);
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'Archivo no disponible. Contacte al responsable; no vuelva a enviarlo sin revisión.');
        $bytes = Storage::disk('local')->get($document->path);
        abort_unless(hash_equals($document->sha256, hash('sha256', $bytes)), 409, 'La integridad del archivo no coincide con su registro. Descarga bloqueada.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['application/pdf', 'image/png', 'image/jpeg']), 415, 'Formato de archivo no admitido.');

        return [$document, $bytes, $mime];
    }

    public function show(Request $request, int $id)
    {
        [$document, $bytes, $mime] = $this->document($request, $id);
        $dimensions = str_starts_with($mime, 'image/') ? @getimagesizefromstring($bytes) : false;
        $smallImage = $dimensions && ($dimensions[0] < 100 || $dimensions[1] < 100);

        return view('document', ['document' => $document, 'mime' => $mime, 'bytes' => strlen($bytes), 'dimensions' => $dimensions, 'smallImage' => $smallImage]);
    }

    public function download(Request $request, int $id)
    {
        return $this->send($request, $id, false);
    }

    public function preview(Request $request, int $id)
    {
        return $this->send($request, $id, true);
    }

    private function send(Request $request, int $id, bool $inline)
    {
        [$document, $bytes, $mime] = $this->document($request, $id);
        $disposition = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', basename($document->name), 'documento.'.($mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg')));

        return response($bytes, 200, ['Content-Type' => $mime, 'Content-Length' => (string) strlen($bytes), 'Content-Disposition' => $disposition]);
    }

    public function sample(string $kind)
    {
        abort_unless(in_array($kind, ['carta', 'cpd', 'ci', 'academico', 'autonomia', 'cv']), 404);

        return response()->download(resource_path('demo-documents/'.$kind.'.pdf'), 'MUESTRA-'.$kind.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
