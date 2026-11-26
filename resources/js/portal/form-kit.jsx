import React, { useState } from 'react';
import { api } from './api';
import { Form } from './shared';

export const reason = {
  name: 'reason',
  label: 'Motivo y evidencia',
  type: 'textarea',
  minLength: 10,
  required: true
};

export const evidence = {
  name: 'evidence',
  label: 'Referencia de evidencia',
  minLength: 10,
  required: true
};

export const select = (name, title, options, value) => ({
  name,
  label: title,
  options,
  value,
  required: true
});

export const field = (name, title, extra = {}) => ({
  name,
  label: title,
  required: true,
  ...extra
});

export const opts = items => items.map(x => [x.id, x.name ?? x.title]);

export const localDate = value => value ? new Date(value).toLocaleString('sv-SE', {
  timeZone: 'America/La_Paz',
  hour12: false
}).slice(0, 19).replace(' ', 'T') : '';

const fieldLimits = {
  name: 100,
  email: 150,
  faculty: 80,
  career: 80,
  title: 150,
  scope_value: 80,
  call_number: 40,
  source: 2000,
  documents: 8000,
  requirements: 15000,
  weights: 3000,
  reason: 3000,
  evidence: 3000,
  minutes: 8000,
  conditions: 5000,
  commission: 1500
};

export function Field({
  name,
  label: title,
  type = 'text',
  options,
  value,
  required = false,
  minLength,
  maxLength,
  min,
  max,
  step
}) {
  maxLength ??= name.startsWith('result-') ? 1000 : fieldLimits[name];
  return <label>
    {title}
    {options ? <select name={name} defaultValue={value ?? options[0]?.[0]} required={required}>
      {options.map(([k, t]) => <option key={k} value={k}>{t}</option>)}
    </select> : type === 'textarea' ? <textarea
      name={name}
      required={required}
      minLength={minLength}
      maxLength={maxLength}
      defaultValue={value ?? ''}
    /> : <input
      name={name}
      type={type}
      required={required}
      minLength={minLength}
      maxLength={maxLength}
      min={min}
      max={max}
      step={step ?? (type === 'datetime-local' ? 1 : undefined)}
      defaultValue={value ?? ''}
    />}
  </label>;
}

export function Action({
  title,
  endpoint,
  fields = [],
  after,
  transform,
  button = 'Guardar',
  data = {}
}) {
  const [ok, set] = useState('');
  return <section className="card">
    <h2>{title}</h2>
    <Form
      button={button}
      onSubmit={async f => {
        let values = {
          ...Object.fromEntries(f),
          ...data
        };
        for (const key of Object.keys(values)) if (values[key] === '') delete values[key];
        if (transform) values = transform(values);
        const r = await api.post(endpoint, values);
        set('Operación registrada.');
        if (after) after(r.data);
      }}
    >
      {fields.map(f => <Field key={f.name} {...f} />)}
    </Form>
    <p role="status">{ok}</p>
  </section>;
}
