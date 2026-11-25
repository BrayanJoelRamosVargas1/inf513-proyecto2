<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\ApplicantDocuments;
use App\Domain\DocumentSafety;
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
        $safety = app(DocumentSafety::class);
        $before = $safety->status($id, 'applicant');
        abort_unless($before['download_allowed'], 423, 'Documento en cuarentena: análisis pendiente, vencido, fallido o bloqueado.');
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'Archivo no disponible. Contacte al responsable; no vuelva a enviarlo sin revisión.');
        $bytes = $safety->bytes($document);
        $after = $safety->status($id, 'applicant');
        abort_unless($after['download_allowed'] && $after['job_id'] === $before['job_id'], 423, 'El análisis cambió; descarga bloqueada.');
        abort_unless(hash_equals($document->sha256, hash('sha256', $bytes)), 409, 'La integridad del archivo no coincide con su registro. Descarga bloqueada.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['application/pdf', 'image/png', 'image/jpeg']), 415, 'Formato de archivo no admitido.');

        return [$document, $bytes, $mime];
    }

    public function show(Request $request, int $id)
    {
        $document = Selection::row('documents', $id);
        Access::privateFile($request->user(), Selection::row('applications', $document->application_id));
        $security = app(ApplicantDocuments::class)->status($id);
        if (! $security['download_allowed']) {
            return view('document', ['document' => $document, 'security' => $security, 'mime' => $document->mime, 'bytes' => 0, 'dimensions' => false, 'smallImage' => false]);
        }
        [$document, $bytes, $mime] = $this->document($request, $id);
        $dimensions = str_starts_with($mime, 'image/') ? @getimagesizefromstring($bytes) : false;
        $smallImage = $dimensions && ($dimensions[0] < 100 || $dimensions[1] < 100);

        return view('document', ['document' => $document, 'mime' => $mime, 'bytes' => strlen($bytes), 'dimensions' => $dimensions, 'smallImage' => $smallImage, 'security' => $security]);
    }

    public function metadata(Request $request, int $id)
    {
        [$document, $bytes, $mime] = $this->document($request, $id);
        $dimensions = str_starts_with($mime, 'image/') ? @getimagesizefromstring($bytes) : false;

        return response()->json([
            'id' => $document->id, 'name' => $document->name, 'version' => $document->version,
            'mime' => $mime, 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
            'created_at' => $document->created_at,
            'width' => $dimensions ? $dimensions[0] : null, 'height' => $dimensions ? $dimensions[1] : null,
            'small_image' => $dimensions && ($dimensions[0] < 100 || $dimensions[1] < 100),
        ]);
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
        $filename = basename($document->name);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'documento';
        $disposition = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, $fallback);

        return response($bytes, 200, ['Content-Type' => $mime, 'Content-Length' => (string) strlen($bytes), 'Content-Disposition' => $disposition]);
    }

    public function sample(string $kind)
    {
        abort_unless(config('portal.experience') === 'training' && request()->user()?->role === 'student', 404);
        abort_unless(in_array($kind, ['carta', 'cpd', 'ci', 'academico', 'autonomia', 'cv']), 404);

        return response()->download(resource_path('demo-documents/'.$kind.'.pdf'), 'MUESTRA-'.$kind.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
