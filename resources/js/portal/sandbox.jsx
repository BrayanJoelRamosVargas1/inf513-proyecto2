import React, { useState } from 'react';
import { api, base, label, message } from './api';
import { Alert, Form, Loading, useData, uuid } from './shared';

export function Sandbox() {
  const [d, error, reload] = useData('/sandbox');
  const [actionError, setActionError] = useState(''),
    [deactivating, setDeactivating] = useState(null);
  const [key, setKey] = useState(() => uuid());
  if (!d) return <Loading error={error} />;
  const methods = d.methods.filter(m => m.active);
  return <>
    <h1>Laboratorio de pagos simulados</h1>
    <Alert text={actionError} />
    <div className="notice">Simulación académica — no se procesa dinero real. No condiciona postulación, examen ni plaza. No introduzcas tarjetas ni datos bancarios.</div>
    <section className="card">
      <h2>Métodos ficticios</h2>
      <Form
        button="Registrar método de práctica"
        onSubmit={async f => {
          await api.post('/sandbox/methods', Object.fromEntries(f));
          reload();
        }}
      >
        <label>Método<select name="label">
            <option>Método de práctica A</option>
            <option>Método de práctica B</option>
          </select></label>
      </Form>
      {methods.map(m => <p key={m.id}>{m.label} <button
          type="button"
          className="secondary"
          disabled={deactivating !== null}
          onClick={async () => {
            if (deactivating !== null) return;
            setActionError('');
            setDeactivating(m.id);
            try {
              await api.delete('/sandbox/methods/' + m.id);
              reload();
            } catch (e) {
              setActionError(message(e));
            } finally {
              setDeactivating(null);
            }
          }}
        >
          {deactivating === m.id ? 'Desactivando…' : `Desactivar #${m.id}`}
        </button></p>)}
    </section>
    {methods.length > 0 && <section className="card">
      <h2>Pago único de prueba</h2>
      <Form
        button="Procesar simulación"
        onSubmit={async f => {
          await api.post('/sandbox/payments', {
            ...Object.fromEntries(f),
            key
          });
          setKey(uuid());
          reload();
        }}
      >
        <label>Método<select name="method_id">
            {methods.map(m => <option key={m.id} value={m.id}>{m.label} #{m.id}</option>)}
          </select></label>
        <label>Importe ficticio (Bs)<input name="amount" type="number" min="0.01" max="99999" step="0.01" required /></label>
        <label>Resultado a simular<select name="outcome">
            <option value="paid">Éxito</option>
            <option value="failed">Fallo</option>
          </select></label>
        <label className="check"><input name="confirmed" type="checkbox" value="1" required />Confirmo que es una simulación sin dinero real</label>
      </Form>
    </section>}
    <section className="card">
      <h2>Plan de cuotas ficticias</h2>
      <Form
        button="Crear plan"
        onSubmit={async f => {
          await api.post('/sandbox/plans', {
            ...Object.fromEntries(f),
            description: 'Plan de práctica',
            key
          });
          setKey(uuid());
          reload();
        }}
      >
        <label>Total ficticio (Bs)<input name="total" type="number" min="0.02" max="99999" step="0.01" required /></label>
        <label>Número de cuotas<input name="count" type="number" min="2" max="12" required /></label>
      </Form>
      {d.plans.map(p => <article key={p.id}>
        <h3>Plan #{p.id} · Total {p.total} Bs ficticios</h3>
        {d.installments.filter(i => i.plan_id === p.id).map(i => {
          const paid = d.payments.some(pay => pay.installment_id === i.id && pay.status === 'paid');
          return <p key={i.id}>Cuota {i.number} · {i.amount} · Vence {i.due_at} · {paid ? 'Pagada en simulación' : <button
              disabled={!methods.length}
              onClick={async () => {
                try {
                  await api.post('/sandbox/payments', {
                    method_id: methods[0].id,
                    amount: i.amount,
                    installment_id: i.id,
                    outcome: 'paid',
                    key: uuid(),
                    confirmed: true
                  });
                  reload();
                } catch (e) {
                  setActionError(message(e));
                }
              }}
            >Simular pago de cuota</button>}</p>;
        })}
      </article>)}
    </section>
    <section className="card">
      <h2>Resumen de la simulación</h2>
      <p>Éxitos registrados: {d.summary.paid} Bs ficticios · Intentos fallidos: {d.summary.failed} Bs ficticios · Cuotas pendientes: {d.summary.pending_installments} Bs ficticios</p>
      <a href={`${base}/api/v1/sandbox/report`}>Descargar mi reporte CSV de simulación</a>
    </section>
    <section className="card">
      <h2>Historial de transacciones</h2>
      <ul>
        {d.payments.map(p => <li key={p.id}>#{p.id} · {p.amount} Bs ficticios · {label(p.status)} · {p.created_at}</li>)}
      </ul>
      {!d.payments.length && <p>Sin transacciones.</p>}
    </section>
  </>;
}
