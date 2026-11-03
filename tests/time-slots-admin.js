/* Run with: node tests/time-slots-admin.js */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = new Map();
const handlers = new Map();
const document = { getElementById: () => ({ scrollIntoView() {} }) };
let slot = {
  id: '1', slot_date: '2026-11-03', start_time: '18:00:00', end_time: '23:00:00',
  floor: 'floor_1', label: 'Original', status: 'booked', visibility: 'public', image_url: 'https://example.com/a.jpg'
};
let calendarRefreshes = 0;
let failSave = false;

function $(selector) {
  if (typeof selector === 'object' && selector !== document) return selector;
  if (selector === '<div>') return {
    text(value) {
      return { html: () => String(value).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])) };
    }
  };
  if (elements.has(selector)) return elements.get(selector);
  const element = {
    value: '', content: '', properties: {}, classes: new Set(), dataValues: {},
    0: { reset() {
      ['id', 'date', 'start', 'end', 'label', 'image'].forEach(key => $('#eg-slot-' + key).val(''));
      $('#eg-slot-visibility').val('private');
      $('#eg-slot-floor').val('floor_1');
      $('#eg-slot-status').val('available');
    } },
    on(event, target, callback) {
      handlers.set(event + ':' + (typeof target === 'string' ? target : selector), callback || target);
      return this;
    },
    trigger(event) { if (event === 'eggplant:slots-changed') calendarRefreshes++; return this; },
    val(value) { if (value === undefined) return this.value; this.value = value; return this; },
    text(value) { this.content = value; return this; },
    html(value) { this.content = value; return this; },
    prop(key, value) { this.properties[key] = value; return this; },
    data(key) { return this.dataValues[key]; },
    hasClass(key) { return this.classes.has(key); },
    addClass() { return this; },
    removeClass() { return this; },
    fadeOut(duration, callback) { callback(); },
    remove() { this.removed = true; }
  };
  elements.set(selector, element);
  return element;
}

function response(result) {
  return { done(callback) { callback(result); return this; }, fail() { return this; }, always(callback) { callback(); return this; } };
}
$.get = (url, payload) => response({ success: true, data: payload.id ? { ...slot } : (slot ? [{ ...slot }] : []) });
$.post = (url, payload) => {
  if (failSave) return response({ success: false, data: 'Update failed.' });
  if (payload.action === 'eggplant_admin_delete_slot') slot = null;
  else slot = { ...slot, ...payload };
  return response({ success: true });
};

vm.runInNewContext(fs.readFileSync(__dirname + '/../eggplant/admin/js/admin.js', 'utf8'), {
  jQuery: $, document, EggplantAdmin: {
    ajaxUrl: '/ajax', nonce: 'test', floors: { floor_1: 'Main & Floor', both: 'Both Floors', floor_2: 'Top Floor' }
  },
  alert(message) { throw new Error(message); },
  confirm: () => true
});

const editButton = $('edit-button');
editButton.dataValues.id = '1';
handlers.get('click:.eg-edit-slot').call(editButton);
assert.equal($('#eg-slot-id').val(), '1');
assert.equal($('#eg-slot-start').val(), '18:00');
assert.equal($('#eg-slot-submit').content, 'Save Changes');
assert.equal($('#eg-slot-cancel').properties.hidden, false);
assert.equal($('#eg-slot-visibility').val(), 'public');
assert.equal($('#eg-slot-image').val(), 'https://example.com/a.jpg');
assert.equal($('.eg-slot-public-fields').properties.hidden, false, 'Public slots must show the image field.');

$('#eg-slot-date').val('2026-11-10');
$('#eg-slot-start').val('09:30');
$('#eg-slot-end').val('12:45');
$('#eg-slot-floor').val('both');
$('#eg-slot-label').val('<Updated>');
$('#eg-slot-status').val('held');
$('#eg-slot-image').val('https://example.com/"b".jpg');
handlers.get('submit:#eg-add-slot-form')({ preventDefault() {} });
assert.equal(slot.visibility, 'public');
assert.equal(slot.image_url, 'https://example.com/"b".jpg');
assert.equal($('#eg-slot-visibility').val(), 'private', 'Reset must default to private events.');
assert.equal($('.eg-slot-public-fields').properties.hidden, true);
assert.match($('#eg-slots-table-wrap').content, /eg-slot-visibility-select/);
assert.match($('#eg-slots-table-wrap').content, /src="https:\/\/example.com\/&quot;b&quot;.jpg"/, 'Thumbnail src must be attribute-escaped.');
assert.equal(slot.action, 'eggplant_admin_update_slot');
assert.equal(slot.slot_date, '2026-11-10');
assert.equal(slot.start_time, '09:30');
assert.equal(slot.end_time, '12:45');
assert.equal(slot.floor, 'both');
assert.equal(slot.status, 'held');
assert.equal($('#eg-slot-id').val(), '');
assert.equal($('#eg-slot-submit').content, 'Add Slot');
assert.equal($('#eg-slot-submit').properties.disabled, false);
assert.equal($('#eg-slot-cancel').properties.hidden, true);
assert.match($('#eg-slots-table-wrap').content, /eg-edit-slot/);
assert.match($('#eg-slots-table-wrap').content, /&lt;Updated&gt;/);
assert.match($('#eg-slots-table-wrap').content, /Main &amp; Floor/);
assert.equal(calendarRefreshes, 1);

handlers.get('click:.eg-edit-slot').call(editButton);
failSave = true;
handlers.get('submit:#eg-add-slot-form')({ preventDefault() {} });
assert.equal($('#eg-slot-id').val(), '1', 'Failed saves must retain edit state.');
assert.match($('#eg-add-slot-msg').content, /Update failed/);
assert.equal(calendarRefreshes, 1, 'Failed saves must not refresh as if saved.');
handlers.get('click:#eg-slot-cancel')();
assert.equal($('#eg-slot-id').val(), '');

failSave = false;
$('#eg-slot-date').val('2026-11-12');
$('#eg-slot-start').val('14:00');
$('#eg-slot-end').val('16:00');
handlers.get('submit:#eg-add-slot-form')({ preventDefault() {} });
assert.equal(slot.action, 'eggplant_admin_add_slot', 'Cancel must return the form to adding slots.');
const visSelect = $('vis-select');
visSelect.dataValues.id = '1';
visSelect.classes.add('eg-slot-visibility-select');
visSelect.val('private');
handlers.get('change:.eg-slot-status-select, .eg-slot-floor-select, .eg-slot-visibility-select').call(visSelect);
assert.equal(slot.visibility, 'private', 'Inline event type changes must be saved.');
handlers.get('click:.eg-delete-slot').call(editButton);
assert.equal(slot, null);
assert.equal(calendarRefreshes, 4);
console.log('Time-slot admin interactions passed.');
