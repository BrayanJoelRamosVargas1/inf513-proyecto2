<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Administration;
use App\Domain\Scores;
use App\Domain\Selection;
use App\Domain\Workspace;
use App\Http\Controllers\Api\SessionController;
use App\Models\User;
use App\ReadModels\PortalScope;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users', 'password' => 'required|string|min:12|confirmed', 'faculty' => 'required|string|max:80', 'career' => 'required|string|max:80', 'role' => 'prohibited', 'active' => 'prohibited']);
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

    public function home(Request $r)
    {
        $u = $r->user();
        $ids = Access::effective(DB::table('memberships')->where('user_id', $u->id))->pluck('call_id');
        $roles = Workspace::roles($u);
        $calls = DB::table('calls')->when($u->role !== 'admin', function ($q) use ($u, $ids) {
            if ($u->role === 'student') {
                $q->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereIn('id', $ids));
            } else {
                $q->whereIn('id', $ids);
            }
        })->orderByDesc('id')->get();
        if ($u->role === 'student') {
            $calls = $calls->sortByDesc(fn ($c) => Workspace::receiving($c))->values();
        }
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('applications.user_id', $u->id)->select('applications.*', 'offers.title')->get();

        $tasks = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('calls', 'calls.id', '=', 'offers.call_id')->join('users', 'users.id', '=', 'applications.user_id')
            ->where('applications.participation', 'active')->where('applications.status', 'submitted')
            ->where(function ($q) use ($u) {
                $reviewCalls = Access::effective(DB::table('memberships')->where('user_id', $u->id)->where('role', 'reviewer'))->pluck('call_id');
                $evalCalls = Access::effective(DB::table('memberships')->where('user_id', $u->id)->where('role', 'evaluator'))->pluck('call_id');
                $q->where(fn ($q) => $q->whereIn('calls.id', $reviewCalls)->where('applications.user_id', '!=', $u->id)->whereIn('calls.status', ['published', 'closed', 'evaluation']))
                    ->orWhere(fn ($q) => $q->whereIn('calls.id', $evalCalls)->where('calls.status', 'evaluation')->where('applications.eligibility', 'enabled')->whereIn('offers.id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id')));
            })->select('applications.id', 'applications.eligibility', 'offers.title', 'users.name')->orderBy('applications.id')->limit(50)->get();

        return view('home', compact('calls', 'apps', 'roles', 'tasks'));
    }

    public function call(Request $r, int $id)
    {
        $call = Selection::row('calls', $id);
        $u = $r->user();
        $authority = Access::has($u, $id, ['authority']);
        $reviewer = Access::has($u, $id, ['reviewer']);
        $evaluator = Access::has($u, $id, ['evaluator']);
        // Misma política de lectura que la API (PortalScope): membresía, postulación propia o convocatoria publicada para postulantes.
        abort_unless(PortalScope::calls($u)->where('calls.id', $id)->exists(), 403);
        $offers = DB::table('offers')->join('categories', 'categories.id', '=', 'offers.category_id')->where('call_id', $id)->when($evaluator && ! $authority && ! $reviewer && $u->role !== 'student', fn ($q) => $q->whereIn('offers.id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id')))->select('offers.*', 'categories.name as category')->get();
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')->where('offers.call_id', $id)
            ->when(! $authority && ! $reviewer, fn ($q) => $q->where(function ($q) use ($u, $evaluator) {
                $q->where('applications.user_id', $u->id);
                if ($evaluator) {
                    $q->orWhereIn('applications.offer_id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id'));
                }
            }))
            ->select('applications.*', 'offers.title', 'users.name')->orderBy('applications.id')->paginate(30);
        $categories = DB::table('categories')->where('active', true)->get();
        $teachers = User::whereIn('id', Access::effective(DB::table('memberships')->where('call_id', $id)->where('role', 'evaluator'))->pluck('user_id'))->where('active', true)->get();

        $ownApplications = DB::table('applications')->where('user_id', $u->id)->whereIn('offer_id', $offers->pluck('id'))->get()->keyBy('offer_id');
        $subjects = $ownApplications->whereIn('offer_id', $offers->where('is_subject', true)->pluck('id'))->count();
        $applicationBlocks = $offers->mapWithKeys(fn ($o) => [$o->id => Workspace::applicationBlock($u, $call, $o, $subjects)]);

        $editOffer = null;
        if ($authority && $call->status === 'draft' && $r->filled('editar_oferta')) {
            $editOffer = $offers->firstWhere('id', (int) $r->input('editar_oferta'));
        }

        return view('call', compact('call', 'offers', 'apps', 'categories', 'teachers', 'authority', 'reviewer', 'evaluator', 'editOffer', 'ownApplications', 'applicationBlocks'));
    }

    public function createCall(Request $r)
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'opens_at' => 'required|date', 'closes_at' => 'required|date', 'exam_at' => 'required|date', 'scope' => ['required', Rule::in(['UAGRM', 'faculty', 'career'])], 'scope_value' => 'nullable|required_unless:scope,UAGRM|string|max:80', 'subject_limit' => 'nullable|integer|min:1|max:100', 'source' => 'required|string|max:2000', 'parent_id' => 'nullable|exists:calls,id']);

        $d += $r->validate(['management_year' => 'nullable|integer|digits:4', 'call_number' => 'nullable|string|max:40']);

        return redirect('/convocatorias/'.$this->service->createCall($r->user(), $d))->with('success', 'Convocatoria DEMO creada en borrador.');
    }

    public function transition(Request $r, int $id)
    {
        $d = $r->validate(['status' => 'required|string']);
        $this->service->transition($r->user(), $id, $d['status']);

        return back()->with('success', 'Etapa actualizada con auditoría.');
    }

    public function editCall(Request $r, int $id)
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'opens_at' => 'required|date', 'closes_at' => 'required|date', 'exam_at' => 'required|date', 'scope' => ['required', Rule::in(['UAGRM', 'faculty', 'career'])], 'scope_value' => 'nullable|required_unless:scope,UAGRM|string|max:80', 'subject_limit' => 'nullable|integer|min:1|max:100', 'source' => 'required|string|max:2000']);
        $d += $r->validate(['management_year' => 'nullable|integer|digits:4', 'call_number' => 'nullable|string|max:40']);
        $this->service->editCall($r->user(), $id, $d);

        return back()->with('success', 'Borrador actualizado con nueva versión y auditoría.');
    }

    private function pairs(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $parts = explode('=', trim($line), 2);
            if (count($parts) !== 2 || ! trim($parts[0]) || ! trim($parts[1])) {
                Scores::fail('Use una fila clave=valor por elemento.');
            }$key = trim($parts[0]);
            if (array_key_exists($key, $out)) {
                Scores::fail('Hay claves duplicadas.');
            }$out[$key] = trim($parts[1]);
        }

        return $out;
    }

    public function saveOffer(Request $r, int $id)
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'category_id' => 'required|integer|exists:categories,id', 'seats' => 'required|integer|min:0|max:999', 'modality' => 'required|string|max:40', 'is_subject' => 'nullable|boolean', 'requirement_profile' => ['nullable', Rule::in(['asignatura','sala','laboratorio'])], 'documents' => 'required|string|max:8000', 'requirements' => 'required|string|max:15000', 'weights' => 'required|string|max:3000', 'scheme' => ['required', Rule::in(['DEMO_AREAS_100', 'DEMO_COMPONENTS_100', 'PENDING_COMPOSITION'])], 'source' => 'required|string|max:2000']);
        foreach (['documents', 'requirements', 'weights'] as $key) {
            $d[$key] = $this->pairs($d[$key]);
        }
        if (! array_key_exists('cpd', $d['documents'])) {
            Scores::fail('Incluya el documento cpd=Formulario CPD.');
        }
        foreach (['documents', 'requirements'] as $field) {
            foreach (array_keys($d[$field]) as $key) {
                if (! preg_match('/^[a-z0-9_]{1,50}$/D', $key)) {
                    Scores::fail('Claves: minúsculas, números y guion bajo.');
                }
            }
        }
        $d['is_subject'] = $r->boolean('is_subject');
        $r->validate(['offer_id' => 'nullable|integer']);
        $this->service->saveOffer($r->user(), $id, $d, $r->filled('offer_id') ? (int) $r->input('offer_id') : null);

        return back()->with('success', 'Oferta configurada. Pesos validados.');
    }

    public function apply(Request $r, int $id)
    {
        return redirect('/postulaciones/'.$this->service->apply($r->user(), $id))->with('success', 'Borrador creado. Complete el expediente para enviarlo.');
    }

    public function application(Request $r, int $id)
    {
        $app = Selection::row('applications', $id);
        Access::viewApplication($r->user(), $app);
        $offer = Selection::row('offers', $app->offer_id);
        $call = Selection::row('calls', $offer->call_id);
        $u = $r->user();
        $owner = $app->user_id === $u->id;
        $reviewer = Access::has($u, $call->id, ['reviewer']);
        $authority = Access::has($u, $call->id, ['authority']);
        $student = User::findOrFail($app->user_id);
        $documents = ($owner || $reviewer) ? DB::table('documents')->where('application_id', $id)->orderByDesc('version')->get() : collect();
        $reviews = ($owner || $reviewer || $authority) ? DB::table('reviews')->where('application_id', $id)->get()->keyBy('criterion') : collect();
        $assignments = Access::has($u, $call->id, ['evaluator']) ? DB::table('assignments')->where('offer_id', $offer->id)->where('user_id', $u->id)->get() : collect();
        $grades = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $id)->when(! $owner && ! $authority, fn ($q) => $q->whereIn('grades.assignment_id', $assignments->pluck('id')))->select('grades.*', 'assignments.area')->get();

        $hasAmendmentHistory = DB::table('amendments')->where('application_id', $id)->exists();
        $pendingAmendment = DB::table('amendments')->where('application_id', $id)->whereIn('status', ['open', 'submitted'])->exists();
        $currentSnapshot = DB::table('dossier_snapshots')->where('application_id', $id)->orderByDesc('id')->first();
        $effectiveDocuments = $currentSnapshot ? $documents->whereIn('id', array_column(Selection::json($currentSnapshot->snapshot)['documents'], 'id')) : $documents->whereNull('amendment_id');

        $documentValidation = app(\App\Domain\ApplicantDocuments::class)->readiness($id);

        return view('application', compact('documentValidation', 'hasAmendmentHistory', 'pendingAmendment', 'effectiveDocuments', 'app', 'offer', 'call', 'student', 'documents', 'reviews', 'assignments', 'grades', 'owner', 'reviewer', 'authority'));
    }

    public function action(Request $r, int $id, string $action)
    {
        $u = $r->user();
        switch ($action) {
            case 'archivo':$d = $r->validate(['kind' => 'required|string|max:50', 'file' => 'required|file']);
                $this->service->upload($u, $id, $d['kind'], $d['file']);
                break;
            case 'enviar':$this->service->submit($u, $id);
                break;
            case 'revision':$d = $r->validate(['decisions' => 'required|array|min:1', 'decisions.*' => 'required|string', 'reason' => 'required|string|min:10|max:3000']);
                $this->service->review($u, $id, $d['decisions'], $d['reason']);
                break;
            case 'habilitacion':$d = $r->validate(['enabled' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
                $this->service->eligibility($u, $id, (bool) $d['enabled'], $d['reason']);
                break;
            case 'meritos':$d = $r->validate(['score' => 'required|string', 'reference' => 'required|string|max:150']);
                $this->service->merit($u, $id, $d['score'], $d['reference']);
                break;
            case 'nota':$d = $r->validate(['assignment_id' => 'required|integer', 'score' => 'required|string', 'intent' => ['nullable', Rule::in(['draft', 'confirm'])]]);
                $this->service->grade($u, $id, (int) $d['assignment_id'], $d['score'], ($d['intent'] ?? 'confirm') === 'confirm');
                break;
            case 'calcular':$this->service->calculate($u, $id);
                break;
        }

        return back()->with('success', 'Operación registrada correctamente.');
    }

    public function assign(Request $r, int $id)
    {
        $d = $r->validate(['user_id' => 'required|integer', 'area' => 'required|string']);
        $this->service->assign($r->user(), $id, (int) $d['user_id'], $d['area']);

        return back()->with('success', 'Responsable asignado.');
    }

    public function results(Request $r, int $id)
    {
        $offer = Selection::row('offers', $id);
        Access::require($r->user(), $offer->call_id, ['authority']);
        $call = Selection::row('calls', $offer->call_id);
        $documentary_reference = $call->mode === 'HISTORICAL' && DB::table('institutional_call_profiles')->where('call_id', $call->id)->exists();
        $ranking = null;
        $warning = null;
        try {
            $ranking = $this->service->ranking($id);
        } catch (ValidationException $e) {
            $warning = $e->validator->errors()->first();
        }
        $ties = DB::table('tie_exams')->where('offer_id', $id)->where('result_revision', $offer->result_revision)->get();
        $publications = DB::table('publications')->where('offer_id', $id)->get();

        $revisions = DB::table('result_revisions')->where('offer_id', $id)->orderByDesc('revision')->get();
        $oldTies = DB::table('tie_exams')->where('offer_id', $id)->where('result_revision', '!=', $offer->result_revision)->get();

        return view('results', compact('documentary_reference', 'revisions', 'oldTies', 'offer', 'call', 'ranking', 'warning', 'ties', 'publications'));
    }

    public function openTie(Request $r, int $id)
    {
        $d = $r->validate(['commission' => 'required|string|max:1500', 'conditions' => 'required|string|min:20|max:5000', 'scheduled_at' => 'required|date']);
        $d['commission'] = array_values(array_filter(array_map('trim', explode(',', $d['commission']))));
        $this->service->openTie($r->user(), $id, $d);

        return back()->with('success', 'Nuevo examen de desempate registrado para los aspirantes empatados.');
    }

    public function resolveTie(Request $r, int $id)
    {
        $d = $r->validate(['results' => 'required|array', 'results.*' => 'required|string|max:1000', 'winners' => 'required|array', 'winners.*' => 'required|integer', 'minutes' => 'required|string|min:20|max:8000']);
        $this->service->resolveTie($r->user(), $id, $d['results'], array_map('intval', $d['winners']), $d['minutes']);

        return back()->with('success', 'Resultados y decisión de comisión conservados. Falta aprobar la selección.');
    }

    public function approve(Request $r, int $id)
    {
        $d = $r->validate(['minutes' => 'required|string|min:20|max:8000']);
        $this->service->approve($r->user(), $id, $d['minutes']);

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
        $filePrefix = $call->mode === 'HISTORICAL' ? 'reporte-referencia-'.$id : 'reporte-demo';
        $rows = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')->where('offers.call_id', $id)->orderBy('offers.id')->orderByDesc('applications.final')->get(['applications.id', 'users.name', 'offers.title', 'applications.status', 'applications.eligibility', 'applications.merit', 'applications.exam', 'applications.final', 'applications.selection', 'offers.result_revision', 'offers.needs_publication_review', 'applications.participation', 'offers.held_vacancies']);
        $headers = ['Postulación', 'Postulante', 'Oferta', 'Expediente', 'Habilitación', 'Mérito /40', 'Examen /60', 'Final /100', 'Selección', 'Revisión', 'Publicación pendiente', 'Participación', 'Vacantes retenidas'];
        $labels = ['active' => 'Activa', 'withdrawn' => 'Retirada', 'resigned' => 'Renunciada', 'under_review' => 'En revisión: requiere nueva publicación', 'draft' => 'Borrador', 'submitted' => 'Enviada', 'pending' => 'Pendiente', 'enabled' => 'Habilitado', 'disabled' => 'Inhabilitado', 'selected' => 'Seleccionado DEMO', 'not_selected' => 'Fuera de cupo'];
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

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filePrefix.'.pdf"']);
        }
        $sheet = new Spreadsheet;
        $page = $sheet->getActiveSheet();
        $page->setCellValue('A1', $call->mode === 'HISTORICAL' ? 'Referencia histórica · Reporte interno, sin aprobación institucional' : 'DEMO ACADÉMICA · Sin validez institucional');
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

        return response()->streamDownload(fn () => (new Xlsx($sheet))->save('php://output'), $filePrefix.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function users(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return view('users', ['users' => User::orderBy('id')->get(), 'calls' => DB::table('calls')->get(), 'memberships' => DB::table('memberships')->get(), 'history' => DB::table('audit_events')->whereIn('action', ['permission.granted', 'permission.revoked', 'user.profile_changed', 'assignment.reassigned'])->orderByDesc('id')->limit(100)->get(), 'categories' => DB::table('categories')->get()]);
    }

    public function saveUser(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:150', 'password' => 'required|string|min:12', 'role' => ['required', Rule::in(['student', 'staff', 'authority'])], 'faculty' => 'required|string|max:80', 'career' => 'required|string|max:80']);
        Access::transaction($r->user(), function () use ($r, $d) {
            abort_unless($r->user()->role === 'admin', 403);
            $u = new User;
            foreach ($d as $key => $v) {
                $u->$key = $v;
            }$u->save();
            Selection::audit($r->user(), null, 'user.created', 'user:'.$u->id, null, ['role' => $u->role]);
        });

        return back()->with('success', 'Cuenta creada. Asigne permisos por convocatoria.');
    }

    public function membership(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['call_id' => 'required|integer|exists:calls,id', 'user_id' => 'required|integer|exists:users,id', 'role' => ['required', Rule::in(['reviewer', 'evaluator', 'authority'])]]);
        if ((int) $d['user_id'] === $r->user()->id) {
            Scores::fail('No puede elevar sus propios permisos. Otro administrador debe asignar el ámbito.');
        }
        $extra = $r->validate(['starts_at' => 'nullable|date', 'expires_at' => 'nullable|date', 'reason' => 'required|string|min:10|max:3000', 'version' => 'nullable|integer']);
        app(Administration::class)->grant($r->user(), (int) $d['call_id'], (int) $d['user_id'], $d['role'], $extra['starts_at'] ?? null, $extra['expires_at'] ?? null, $extra['reason'], isset($extra['version']) ? (int) $extra['version'] : null);

        return back()->with('success', 'Permiso asignado en el ámbito indicado.');
    }

    public function editCategory(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($id)],
            'regime' => ['required', Rule::in(['docencia', 'laboratorio'])], 'active' => 'required|boolean',
            'reason' => 'required|string|min:10|max:3000']);
        $reason = $d['reason'];
        unset($d['reason']);
        app(Administration::class)->editCategory($r->user(), $id, $d, $reason);

        return back()->with('success', 'Tipo actualizado con auditoría. Las ofertas existentes conservan su configuración.');
    }

    public function category(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:100|unique:categories', 'regime' => ['required', Rule::in(['docencia', 'laboratorio'])], 'active' => 'nullable|boolean']);
        Access::transaction($r->user(), function () use ($r, $d) {
            abort_unless($r->user()->role === 'admin', 403);
            $id = DB::table('categories')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            Selection::audit($r->user(), null, 'category.created', 'category:'.$id, null, $d);
        });

        return back()->with('success', 'Tipo de auxiliatura registrado para configuración.');
    }
}
