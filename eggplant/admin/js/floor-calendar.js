/* jshint esversion:6 */
(function ($) {
  'use strict';

  if (typeof EggplantFloorCal === 'undefined') return;

  const cfg    = EggplantFloorCal;
  const floors = cfg.floors || {};
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  const grid    = document.getElementById('eg-fc-grid');
  const heading = document.getElementById('eg-fc-heading');
  const editor  = document.getElementById('eg-fc-editor');

  const todayParts = cfg.today.split('-').map(Number);
  let year  = todayParts[0];
  let month = todayParts[1];
  let data  = {};

  function pad(n) { return String(n).padStart(2, '0'); }
  function ymd(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
  function fromDate(dt) { return ymd(dt.getFullYear(), dt.getMonth() + 1, dt.getDate()); }

  function statusInfo(entry) {
    if (!entry) return { cls: 'available', text: 'Available' };
    if (entry.status === 'blocked') return { cls: 'blocked', text: 'Unavailable' };
    return entry.visibility === 'public'
      ? { cls: 'public',  text: 'PUBLIC EVENT' }
      : { cls: 'private', text: 'PRIVATE EVENT' };
  }

  function load() {
    heading.textContent = MONTHS[month - 1] + ' ' + year;
    $.post(cfg.ajaxUrl, { action: 'eggplant_fc_get_month', nonce: cfg.nonce, month: year + '-' + pad(month) })
      .done(function (res) {
        data = (res.success && res.data && !Array.isArray(res.data)) ? res.data : {};
        render();
      });
  }

  function render() {
    grid.innerHTML = '';
    const firstDow    = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    for (let i = 0; i < firstDow; i++) {
      const empty = document.createElement('div');
      empty.className = 'eg-fc-day eg-fc-day--empty';
      grid.appendChild(empty);
    }

    for (let d = 1; d <= daysInMonth; d++) {
      const date = ymd(year, month, d);
      const cell = document.createElement('div');
      cell.className = 'eg-fc-day';
      if (date === cfg.today) cell.classList.add('eg-fc-day--today');
      if (date < cfg.today) cell.classList.add('eg-fc-day--past');

      const num = document.createElement('div');
      num.className = 'eg-fc-day__num';
      num.textContent = d;
      cell.appendChild(num);

      Object.keys(floors).forEach(function (floor) {
        const entry = (data[date] || {})[floor];
        const info  = statusInfo(entry);
        const btn   = document.createElement('button');
        btn.type = 'button';
        btn.className = 'eg-fc-floor eg-fc--' + info.cls;
        btn.title = floors[floor] + ': ' + info.text + (entry && entry.title ? ' – ' + entry.title : '') + (entry && entry.note ? ' (' + entry.note + ')' : '');

        const name = document.createElement('span');
        name.className = 'eg-fc-floor__name';
        name.textContent = floors[floor];
        const status = document.createElement('span');
        status.className = 'eg-fc-floor__status';
        status.textContent = info.text;
        btn.appendChild(name);
        btn.appendChild(status);
        if (entry && entry.title) {
          const t = document.createElement('span');
          t.className = 'eg-fc-floor__title';
          t.textContent = entry.title;
          btn.appendChild(t);
        }

        btn.addEventListener('click', function () { openEditor(date, floor); });
        cell.appendChild(btn);
      });

      grid.appendChild(cell);
    }
  }

  /* ── Day editor ── */
  function toggleFields() {
    const status = $('#eg-fc-status').val();
    $('.eg-fc-booked-fields').toggle(status === 'booked');
    $('.eg-fc-public-fields').toggle(status === 'booked' && $('#eg-fc-visibility').val() === 'public');
  }

  function fillEditor() {
    const date  = $('#eg-fc-date').val();
    const floor = $('#eg-fc-floor').val();
    const entry = (data[date] || {})[floor] || null;

    $('#eg-fc-status').val(entry ? entry.status : 'available');
    $('#eg-fc-visibility').val(entry && entry.visibility ? entry.visibility : 'private');
    $('#eg-fc-title').val(entry ? entry.title : '');
    $('#eg-fc-start').val(entry ? entry.start : '');
    $('#eg-fc-end').val(entry ? entry.end : '');
    $('#eg-fc-description').val(entry ? entry.description : '');
    $('#eg-fc-image').val(entry ? entry.image_url : '');
    $('#eg-fc-link').val(entry ? entry.link_url : '');
    $('#eg-fc-note').val(entry ? entry.note : '');
    $('#eg-fc-editor-msg').removeClass('eg-msg--success eg-msg--error').text('');
    toggleFields();
  }

  function openEditor(date, floor) {
    $('#eg-fc-date').val(date);
    $('#eg-fc-floor').val(floor);
    const dt = new Date(date + 'T00:00:00');
    $('#eg-fc-editor-title').text(dt.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
    fillEditor();
    editor.hidden = false;
    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  $('#eg-fc-status, #eg-fc-visibility').on('change', toggleFields);
  $('#eg-fc-floor').on('change', fillEditor);
  $('#eg-fc-editor-close').on('click', function () { editor.hidden = true; });

  $('#eg-fc-editor-form').on('submit', function (e) {
    e.preventDefault();
    const msg = $('#eg-fc-editor-msg');
    msg.removeClass('eg-msg--success eg-msg--error').text('Saving…');
    $.post(cfg.ajaxUrl, {
      action:      'eggplant_fc_save_day',
      nonce:       cfg.nonce,
      date:        $('#eg-fc-date').val(),
      floor:       $('#eg-fc-floor').val(),
      status:      $('#eg-fc-status').val(),
      visibility:  $('#eg-fc-visibility').val(),
      title:       $('#eg-fc-title').val(),
      start_time:  $('#eg-fc-start').val(),
      end_time:    $('#eg-fc-end').val(),
      description: $('#eg-fc-description').val(),
      image_url:   $('#eg-fc-image').val(),
      link_url:    $('#eg-fc-link').val(),
      note:        $('#eg-fc-note').val()
    }).done(function (res) {
      if (res.success) {
        msg.addClass('eg-msg--success').text('Saved!');
        load();
      } else {
        msg.addClass('eg-msg--error').text('Error: ' + (res.data || 'Unknown error'));
      }
    }).fail(function () {
      msg.addClass('eg-msg--error').text('Network error.');
    });
  });

  /* ── Navigation ── */
  $('#eg-fc-prev').on('click', function () { month--; if (month < 1) { month = 12; year--; } load(); });
  $('#eg-fc-next').on('click', function () { month++; if (month > 12) { month = 1; year++; } load(); });
  $('#eg-fc-today').on('click', function () { year = todayParts[0]; month = todayParts[1]; load(); });

  /* ── Bulk block ── */
  $('.eg-fc-presets').on('click', 'button', function () {
    const today = new Date(todayParts[0], todayParts[1] - 1, todayParts[2]);
    let start, end;
    switch ($(this).data('preset')) {
      case 'week':
        start = new Date(today); start.setDate(today.getDate() - today.getDay());
        end   = new Date(start); end.setDate(start.getDate() + 6);
        break;
      case 'month':
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end   = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        break;
      case 'shown':
        start = new Date(year, month - 1, 1);
        end   = new Date(year, month, 0);
        break;
      case 'year':
        start = today;
        end   = new Date(today.getFullYear(), 11, 31);
        break;
      case 'next-year':
        start = new Date(today.getFullYear() + 1, 0, 1);
        end   = new Date(today.getFullYear() + 1, 11, 31);
        break;
      default:
        return;
    }
    $('#eg-fc-bulk-start').val(fromDate(start));
    $('#eg-fc-bulk-end').val(fromDate(end));
  });

  let bulkMode = 'block';
  $('#eg-fc-bulk-form button[type="submit"]').on('click', function () { bulkMode = $(this).data('mode'); });

  $('#eg-fc-bulk-form').on('submit', function (e) {
    e.preventDefault();
    const msg      = $('#eg-fc-bulk-msg');
    const weekdays = $('#eg-fc-bulk-form input[name="weekdays[]"]:checked').map(function () { return this.value; }).get();
    msg.removeClass('eg-msg--success eg-msg--error');

    if (!weekdays.length) {
      msg.addClass('eg-msg--error').text('Choose at least one day of the week.');
      return;
    }

    msg.text('Working…');
    $.post(cfg.ajaxUrl, {
      action:   'eggplant_fc_bulk',
      nonce:    cfg.nonce,
      mode:     bulkMode,
      weekdays: weekdays,
      floor:    $('#eg-fc-bulk-floor').val(),
      start:    $('#eg-fc-bulk-start').val(),
      end:      $('#eg-fc-bulk-end').val(),
      note:     $('#eg-fc-bulk-note').val()
    }).done(function (res) {
      if (res.success) {
        const verb = bulkMode === 'block' ? 'blocked' : 'unblocked';
        msg.addClass('eg-msg--success').text(res.data.changed + ' floor-day(s) ' + verb + (res.data.skipped ? ', ' + res.data.skipped + ' skipped (already booked or blocked).' : '.'));
        load();
      } else {
        msg.addClass('eg-msg--error').text('Error: ' + (res.data || 'Unknown error'));
      }
    }).fail(function () {
      msg.addClass('eg-msg--error').text('Network error.');
    });
  });

  load();

}(jQuery));
