import React from 'react';
import {render,screen,fireEvent,cleanup} from '@testing-library/react';
import {afterEach,test,expect} from 'vitest';
import {UserDirectory} from './user-directory';
afterEach(cleanup);
test('combines accent-insensitive search with profile/status and clears filters without changing records',()=>{
  const users=[{id:1,name:'Ángela Pérez',email:'angela@example.test',faculty:'FICCT',career:'Informática',role:'staff',active:true},{id:2,name:'Bruno',email:'bruno@example.test',role:'student',active:false}];
  render(<UserDirectory users={users}>{rows=>rows.map(user=><button key={user.id}>{user.name}</button>)}</UserDirectory>);
  fireEvent.change(screen.getByLabelText('Buscar usuarios'),{target:{value:'  ANGELA   informatica '}});
  expect(screen.queryByRole('button',{name:'Bruno'})).toBeNull();
  expect(screen.getByRole('button',{name:'Ángela Pérez'})).toBeTruthy();
  fireEvent.change(screen.getByLabelText('Filtrar por estado'),{target:{value:'0'}});
  expect(screen.getByText('No hay usuarios que coincidan con estos filtros.')).toBeTruthy();
  fireEvent.click(screen.getByRole('button',{name:'Quitar filtros de usuarios'}));
  fireEvent.change(screen.getByLabelText('Filtrar por perfil'),{target:{value:'student'}});
  expect(screen.getByRole('button',{name:'Bruno'})).toBeTruthy();
  expect(screen.queryByRole('button',{name:'Ángela Pérez'})).toBeNull();
  expect(users[0].active).toBe(true);
});
