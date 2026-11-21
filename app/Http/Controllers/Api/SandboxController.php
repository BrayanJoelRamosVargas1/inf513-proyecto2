<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\Selection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SandboxController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user()->id;
        $plans = DB::table('sandbox_payment_plans')->where('user_id', $u)->orderByDesc('id')->get();
        $installments = DB::table('sandbox_installments')->whereIn('plan_id', $plans->pluck('id'))->orderBy('number')->get();

        return response()->json(['notice' => 'Simulación académica — no se procesa dinero real', 'methods' => DB::table('sandbox_payment_methods')->where('user_id', $u)->get(), 'payments' => DB::table('sandbox_payments')->where('user_id', $u)->orderByDesc('id')->get(), 'plans' => $plans, 'installments' => $installments]);
    }

    public function method(Request $r)
    {
        $d = $r->validate(['label' => ['required', Rule::in(['Método de práctica A', 'Método de práctica B'])], 'card_number' => 'prohibited', 'cvv' => 'prohibited']);
        $id = Access::transaction($r->user(), function () use ($r, $d) {
            $id = DB::table('sandbox_payment_methods')->insertGetId($d + ['user_id' => $r->user()->id, 'token' => 'DEMO-'.Str::uuid(), 'created_at' => now()]);
            Selection::audit($r->user(), null, 'sandbox.method_created', 'sandbox_method:'.$id);

            return $id;
        });

        return response()->json(['id' => $id], 201);
    }

    public function remove(Request $r, int $id)
    {
        Access::transaction($r->user(), function () use ($r, $id) {
            $method = DB::table('sandbox_payment_methods')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            abort_unless($method->user_id === $r->user()->id, 403);
            DB::table('sandbox_payment_methods')->where('id', $id)->update(['active' => false]);
            Selection::audit($r->user(), null, 'sandbox.method_disabled', 'sandbox_method:'.$id);
        });

        return response()->noContent();
    }

    public function plan(Request $r)
    {
        $d = $r->validate(['total' => 'required|decimal:0,2|numeric|min:0.01|max:99999', 'description' => ['required', Rule::in(['Plan de práctica'])], 'count' => 'required|integer|min:2|max:12', 'key' => 'required|uuid']);
        $id = Access::transaction($r->user(), function () use ($r, $d) {
            $existing = DB::table('sandbox_payment_plans')->where('user_id', $r->user()->id)->where('key', $d['key'])->first();
            if ($existing) {
                abort_unless(bccomp($existing->total, $d['total'], 2) === 0 && DB::table('sandbox_installments')->where('plan_id', $existing->id)->count() === (int) $d['count'], 409, 'Reintento con datos distintos.');

                return $existing->id;
            }
            $cents = (int) bcmul($d['total'], '100', 0);
            abort_if($cents < $d['count'], 422, 'El total no alcanza para distribuir cuotas de al menos un centavo.');
            $id = DB::table('sandbox_payment_plans')->insertGetId(['user_id' => $r->user()->id, 'total' => $d['total'], 'description' => $d['description'], 'key' => $d['key'], 'created_at' => now()]);
            for ($i = 1; $i <= $d['count']; $i++) {
                $amount = intdiv($cents, $d['count']) + ($i <= $cents % $d['count'] ? 1 : 0);
                DB::table('sandbox_installments')->insert(['plan_id' => $id, 'number' => $i, 'amount' => bcdiv((string) $amount, '100', 2), 'due_at' => now()->addMonthsNoOverflow($i)->toDateString()]);
            }
            Selection::audit($r->user(), null, 'sandbox.plan_created', 'sandbox_plan:'.$id);

            return $id;
        });

        return response()->json(['id' => $id], 201);
    }

    public function pay(Request $r)
    {
        $d = $r->validate(['method_id' => 'required|integer|min:1', 'amount' => 'required|decimal:0,2|numeric|min:0.01|max:99999', 'installment_id' => 'nullable|integer|min:1', 'outcome' => ['required', Rule::in(['paid', 'failed'])], 'key' => 'required|uuid', 'confirmed' => 'accepted']);
        $id = Access::transaction($r->user(), function () use ($r, $d) {
            $existing = DB::table('sandbox_payments')->where('user_id', $r->user()->id)->where('key', $d['key'])->first();
            if ($existing) {
                abort_unless($existing->method_id === (int) $d['method_id'] && bccomp($existing->amount, $d['amount'], 2) === 0 && $existing->status === $d['outcome'] && $existing->installment_id === (isset($d['installment_id']) ? (int) $d['installment_id'] : null), 409, 'Reintento con datos distintos.');

                return $existing->id;
            }
            $method = DB::table('sandbox_payment_methods')->where('id', $d['method_id'])->first() ?? abort(404);
            abort_unless($method->user_id === $r->user()->id, 403);
            abort_unless($method->active, 422, 'El método de práctica está desactivado.');
            if (isset($d['installment_id'])) {
                $part = DB::table('sandbox_installments')->where('id', $d['installment_id'])->first() ?? abort(404);
                $plan = DB::table('sandbox_payment_plans')->where('id', $part->plan_id)->first();
                abort_unless($plan->user_id === $r->user()->id, 403);
                abort_unless(bccomp($part->amount, $d['amount'], 2) === 0, 422, 'Importe distinto de la cuota.');
                abort_if(DB::table('sandbox_payments')->where('installment_id', $part->id)->where('status', 'paid')->exists(), 409, 'Esta cuota ya se pagó en la simulación.');
            }
            $id = DB::table('sandbox_payments')->insertGetId(['user_id' => $r->user()->id, 'method_id' => $method->id, 'amount' => $d['amount'], 'installment_id' => $d['installment_id'] ?? null, 'key' => $d['key'], 'status' => $d['outcome'], 'created_at' => now()]);
            Selection::audit($r->user(), null, 'sandbox.payment_'.$d['outcome'], 'sandbox_payment:'.$id, null, ['amount' => $d['amount']]);

            return $id;
        });

        return response()->json(['id' => $id, 'simulated' => true], 201);
    }
}
