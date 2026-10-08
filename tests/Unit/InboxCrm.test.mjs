import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

function inbox(axios = {}) {
  let factory;
  vm.runInNewContext(readFileSync(new URL('../../resources/js/components/inbox.js', import.meta.url), 'utf8').replace('import Alpine from "alpinejs";', ''), {
    Alpine: { data: (_name, value) => { factory = value; } },
    window: { axios },
  });
  const state = factory({ routes: { crmAssign: '/leads/__LEAD__/assign', crmTask: '/tasks' } });
  state.crm = { owner_id: 7, contact: { id: 3 }, current_lead: { id: 2, assigned_to: null } };
  state.openCrmPanel = () => {};
  state.crmRequestConfig = () => ({});
  state.loadCrm = async () => {};
  return state;
}

test('assignment starts unselected while tasks default to owner or lead assignee', () => {
  const state = inbox();
  state.crmError = 'Old error';
  state.sendError = 'Message error';
  state.openCrmAction('assign');
  assert.equal(state.crmForm.assigned_to, '');
  assert.equal(state.crmError, '');
  assert.equal(state.sendError, 'Message error');
  state.openCrmAction('task');
  assert.equal(state.crmForm.assigned_to, 7);
  state.crm.current_lead.assigned_to = 9;
  state.openCrmAction('assign');
  assert.equal(state.crmForm.assigned_to, 9);
  state.openCrmAction('task');
  assert.equal(state.crmForm.assigned_to, 9);
});

test('owner choice sends an explicit id for assignment and task creation', async () => {
  const requests = [];
  const state = inbox({ patch: async (url, data) => requests.push([url, data]), post: async (url, data) => requests.push([url, data]) });
  for (const action of ['assign', 'task']) {
    state.openCrmAction(action);
    state.crmForm.assigned_to = '7';
    await state.saveCrmAction();
    assert.equal(state.crmAction, '');
  }
  assert.equal(requests[0][0], '/leads/2/assign');
  assert.equal(requests[0][1].assigned_to, '7');
  assert.equal(requests[1][0], '/tasks');
  assert.equal(requests[1][1].assigned_to, '7');
});

test('failed CRM saves keep the form open and leave message errors unchanged', async () => {
  const state = inbox({ patch: async () => { throw { response: { data: { message: 'Assignment rejected' } } }; } });
  state.sendError = 'Message error';
  state.openCrmAction('assign');
  state.crmForm.assigned_to = '7';
  await state.saveCrmAction();
  assert.equal(state.crmAction, 'assign');
  assert.equal(state.crmError, 'Assignment rejected');
  assert.equal(state.sendError, 'Message error');
  assert.equal(state.crmSaving, false);
});

test('empty assignment is rejected before a request is sent', async () => {
  let sent = false;
  const state = inbox({ patch: async () => { sent = true; } });
  state.openCrmAction('assign');
  await state.saveCrmAction();
  assert.equal(sent, false);
  assert.equal(state.crmAction, 'assign');
  assert.equal(state.crmError, 'Choose an agent.');
});
