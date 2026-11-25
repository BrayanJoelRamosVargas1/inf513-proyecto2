<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Selection;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmissionReceiptController extends Controller
{
    public function download(Request $request, int $id)
    {
        $data = DB::transaction(function () use ($request, $id) {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            $application = Selection::row('applications', $id);
            Access::privateFile($request->user(), $application);
            abort_unless($application->status === 'submitted', 422, 'La constancia está disponible después del envío.');
            $snapshot = DB::table('dossier_snapshots')->where('application_id', $id)->where('phase', 'submitted')->orderByDesc('version')->first() ?? abort(409, 'No existe un envío conservado para emitir la constancia.');
            $offer = Selection::row('offers', $application->offer_id);
            return ['application' => $application, 'offer' => $offer, 'call' => Selection::row('calls', $offer->call_id), 'student' => Selection::row('users', $application->user_id), 'snapshot' => $snapshot, 'documents' => Selection::json($snapshot->snapshot)['documents'], 'snapshot_hash' => hash('sha256', $snapshot->snapshot)];
        });
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml(view('submission-receipt', $data)->render());
        $pdf->setPaper('A4');
        $pdf->render();
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="constancia-interna-'.$id.'-v'.$data['snapshot']->version.'.pdf"']);
    }
}
