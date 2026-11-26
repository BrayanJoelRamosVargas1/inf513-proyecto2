import React from 'react';
import { it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { CallAudit } from './audit';
import { api } from './api';
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

it('consulta la convocatoria correcta, conserva valores y pagina sin mezclar eventos', async () => {
  const get = vi.spyOn(api, 'get').mockImplementation(async url => ({
    data: {
      events: {
        data: url.endsWith('page=1') ? [{
          id: 51,
          user_id: 9,
          action: 'grade.corrected',
          entity: 'grade:8',
          reason: 'Corrección documentada',
          before: '{"score":"70.00"}',
          after: '{"score":"81.25"}',
          created_at: '2026-11-24T10:00:00-04:00'
        }] : [{
          id: 10,
          user_id: 4,
          action: 'call.created',
          entity: 'call:12',
          before: null,
          after: '{"title":"<script>untrusted</script>"}',
          created_at: '2026-11-23T10:00:00-04:00'
        }],
        current_page: url.endsWith('page=1') ? 1 : 2,
        last_page: 2,
        total: 41
      }
    }
  }));
  render(<MemoryRouter initialEntries={['/convocatorias/12/auditoria']}>
    <Routes><Route path="/convocatorias/:id/auditoria" element={<CallAudit />} /></Routes>
  </MemoryRouter>);
  await screen.findByText(/grade\.corrected/);
  expect(screen.getByText(/70.00/)).toBeTruthy();
  expect(screen.getByText(/81.25/)).toBeTruthy();
  expect(screen.getByRole('button', {
    name: 'Página anterior'
  }).disabled).toBe(true);
  fireEvent.click(screen.getByRole('button', {
    name: 'Página siguiente'
  }));
  await screen.findByText(/call\.created/);
  await waitFor(() => expect(get).toHaveBeenLastCalledWith('/calls/12/audit?page=2'));
  expect(screen.queryByText(/grade\.corrected/)).toBeNull();
  expect(screen.getByText(/<script>untrusted<\/script>/)).toBeTruthy();
  expect(document.querySelector('script')).toBeNull();
  expect(screen.getByRole('button', {
    name: 'Página siguiente'
  }).disabled).toBe(true);
});
