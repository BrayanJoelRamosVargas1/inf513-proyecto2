<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Selection;
use App\Domain\Workspace;
use App\Http\Controllers\Api\SessionController;
use App\Http\Operations\AdministrationOperations;
use App\Http\Operations\ApplicationOperations;
use App\Http\Operations\CallOperations;
use App\Http\Operations\ResultsOperations;
use App\Models\User;
use App\ReadModels\ApplicationView;
use App\ReadModels\CallView;
use App\ReadModels\HomeView;
use App\ReadModels\ResultsView;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class PortalController extends Controller
{
    public function __construct(private Selection $service) {}

    public function loginForm()
    {
        return view('auth', ['register' => false]);
    }

    public function registerForm()
    {
        return view('auth', ['register' => true]);
    }

    public function login(Request $r)
    {
        $response = app(SessionController::class)->login($r);
        if ($response->getStatusCode() !== 200) {
            return back()->withErrors($response->getData(true)['errors'])->onlyInput('email');
        }

        return redirect('/');
    }

    public function logout(Request $r)
    {
        app(SessionController::class)->logout($r);

        return redirect('/login');
    }

    public function register(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users',
            'password' => 'required|string|min:12|confirmed',
            'faculty' => 'required|string|max:80',
            'career' => 'required|string|max:80',
            'role' => 'prohibited',
            'active' => 'prohibited',
        ]);
        $user = DB::transaction(function () use ($d) {
            $u = new User;
            foreach (['name', 'email', 'password', 'faculty', 'career'] as $key) {
                $u->$key = $d[$key];
            }$u->role = 'student';
            $u->save();
            Selection::audit($u, null, 'user.registered', 'user:'.$u->id);

            return $u;
        });
        // Load database defaults before recording the session's permission version.
        $user->refresh();
        Auth::login($user);
        $r->session()->regenerate();
        $r->session()->put('security_version', $r->user()->security_version);

        return redirect('/');
    }

    public function guide(Request $r)
    {
        return view('role-guide', ['roles' => Workspace::roles($r->user())]);
    }

    public function home(Request $r, HomeView $view)
    {
        return view('home', $view->data($r));
    }

    public function call(Request $r, int $id, CallView $view)
    {
        return view('call', $view->data($r, $id));
    }

    public function createCall(Request $r, CallOperations $ops)
    {
        return redirect('/convocatorias/'.$ops->createCall($r))->with('success', 'Convocatoria DEMO creada en borrador.');
    }

    public function transition(Request $r, int $id, CallOperations $ops)
    {
        $ops->transition($r, $id);

        return back()->with('success', 'Etapa actualizada con auditoría.');
    }

    public function editCall(Request $r, int $id, CallOperations $ops)
    {
        $ops->editCall($r, $id);

        return back()->with('success', 'Borrador actualizado con nueva versión y auditoría.');
    }

    public function saveOffer(Request $r, int $id, CallOperations $ops)
    {
        $ops->saveOffer($r, $id);

        return back()->with('success', 'Oferta configurada. Pesos validados.');
    }

    public function apply(Request $r, int $id)
    {
        return redirect('/postulaciones/'.$this->service->apply($r->user(), $id))->with('success', 'Borrador creado. Complete el expediente para enviarlo.');
    }

    public function application(Request $r, int $id, ApplicationView $view)
    {
        return view('application', $view->data($r, $id));
    }

    public function action(Request $r, int $id, string $action, ApplicationOperations $ops)
    {
        $ops->action($r, $id, $action);

        return back()->with('success', 'Operación registrada correctamente.');
    }

    public function assign(Request $r, int $id, CallOperations $ops)
    {
        $ops->assign($r, $id);

        return back()->with('success', 'Responsable asignado.');
    }

    public function results(Request $r, int $id, ResultsView $view)
    {
        return view('results', $view->data($r, $id));
    }

    public function openTie(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->openTie($r, $id);

        return back()->with('success', 'Nuevo examen de desempate registrado para los aspirantes empatados.');
    }

    public function resolveTie(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->resolveTie($r, $id);

        return back()->with('success', 'Resultados y decisión de comisión conservados. Falta aprobar la selección.');
    }

    public function approve(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->approve($r, $id);

        return back()->with('success', 'Snapshot aprobado en DEMO; no constituye nombramiento oficial.');
    }

    public function audit(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);
        $call = Selection::row('calls', $id);
        $events = DB::table('audit_events')->where('call_id', $id)->orderByDesc('id')->paginate(40);

        return view('audit', compact('events', 'call'));
    }

    /**
     * Reporte plano heredado, conservado por compatibilidad (scripts y pruebas de la versión anterior).
     * El reporte vigente y completo es ConsolidatedResultsController::export.
     */
    public function report(Request $r, int $id, string $format)
    {
        Access::require($r->user(), $id, ['authority']);
        $call = Selection::row('calls', $id);
        $filePrefix = match ($call->mode) {
            'HISTORICAL' => 'reporte-referencia-'.$id,
            'OFICIAL' => 'reporte-'.$id,
            default => 'reporte-demo',
        };
        $rows = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')->where('offers.call_id', $id)->orderBy('offers.id')->orderByDesc('applications.final')->get([
            'applications.id',
            'users.name',
            'offers.title',
            'applications.status',
            'applications.eligibility',
            'applications.merit',
            'applications.exam',
            'applications.final',
            'applications.selection',
            'offers.result_revision',
            'offers.needs_publication_review',
            'applications.participation',
            'offers.held_vacancies',
        ]);
        $headers = [
            'Postulación',
            'Postulante',
            'Oferta',
            'Expediente',
            'Habilitación',
            'Mérito /40',
            'Examen /60',
            'Final /100',
            'Selección',
            'Revisión',
            'Publicación pendiente',
            'Participación',
            'Vacantes retenidas',
        ];
        $labels = [
            'active' => 'Activa',
            'withdrawn' => 'Retirada',
            'resigned' => 'Renunciada',
            'under_review' => 'En revisión: requiere nueva publicación',
            'draft' => 'Borrador',
            'submitted' => 'Enviada',
            'pending' => 'Pendiente',
            'enabled' => 'Habilitado',
            'disabled' => 'Inhabilitado',
            'selected' => 'Seleccionado DEMO',
            'not_selected' => 'Fuera de cupo',
        ];
        foreach ($rows as $row) {
            $row->needs_publication_review = $row->needs_publication_review ? 'Sí: sin reasignación automática' : 'No';
            foreach (['status', 'eligibility', 'selection', 'participation'] as $field) {
                $row->$field = $labels[$row->$field] ?? $row->$field;
            }
        }
        Selection::audit($r->user(), $id, 'report.exported', 'call:'.$id, null, ['format' => $format, 'rows' => $rows->count()]);
        if ($format === 'pdf') {
            $pdf = new Dompdf(['isRemoteEnabled' => false]);
            $pdf->loadHtml(view('report', compact('call', 'rows', 'headers'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filePrefix.'.pdf"',
            ]);
        }
        $sheet = new Spreadsheet;
        $page = $sheet->getActiveSheet();
        $page->setCellValue('A1', Selection::reportNotice($call));
        $page->setCellValueExplicit('A2', $call->title, DataType::TYPE_STRING);
        $page->fromArray($headers, null, 'A3');
        foreach ($rows as $i => $row) {
            $col = 1;
            foreach ((array) $row as $value) {
                $page->setCellValueExplicit([$col++, $i + 4], (string) ($value ?? 'Pendiente'), DataType::TYPE_STRING);
            }
        }
        foreach (range('A', 'M') as $col) {
            $page->getColumnDimension($col)->setAutoSize(true);
        }$page->getStyle('A3:M3')->getFont()->setBold(true);
        $page->setAutoFilter('A3:M'.max(3, $rows->count() + 3));

        return response()->streamDownload(fn () => (new Xlsx($sheet))->save('php://output'), $filePrefix.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function users(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return view('users', [
            'users' => User::orderBy('id')->get(),
            'calls' => DB::table('calls')->get(),
            'memberships' => DB::table('memberships')->get(),
            'history' => DB::table('audit_events')->whereIn('action', [
                'permission.granted',
                'permission.revoked',
                'user.profile_changed',
                'assignment.reassigned',
            ])->orderByDesc('id')->limit(100)->get(),
            'categories' => DB::table('categories')->get(),
        ]);
    }

    public function saveUser(Request $r, AdministrationOperations $ops)
    {
        $ops->saveUser($r);

        return back()->with('success', 'Cuenta creada. Asigne permisos por convocatoria.');
    }

    public function membership(Request $r, AdministrationOperations $ops)
    {
        $ops->membership($r);

        return back()->with('success', 'Permiso asignado en el ámbito indicado.');
    }

    public function editCategory(Request $r, int $id, AdministrationOperations $ops)
    {
        $ops->editCategory($r, $id);

        return back()->with('success', 'Tipo actualizado con auditoría. Las ofertas existentes conservan su configuración.');
    }

    public function category(Request $r, AdministrationOperations $ops)
    {
        $ops->category($r);

        return back()->with('success', 'Tipo de auxiliatura registrado para configuración.');
    }
}
