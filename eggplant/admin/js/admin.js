/* jshint esversion:6 */
(function ($) {
  'use strict';

  if (typeof EggplantAdmin === 'undefined') return;

  const ajaxUrl = EggplantAdmin.ajaxUrl;
  const nonce   = EggplantAdmin.nonce;

  /* ── Native date inputs are used; no datepicker initialization needed ── */

  /* ================================================================
     TIME SLOTS PAGE
  ================================================================ */

  // Add slot
  $('#eg-add-slot-form').on('submit', function (e) {
    e.preventDefault();
    const msg = $('#eg-add-slot-msg');
    msg.removeClass('eg-msg--success eg-msg--error').text('Saving…');

    $.post(ajaxUrl, {
      action:     'eggplant_admin_add_slot',
      nonce:      nonce,
      slot_date:  $('#eg-slot-date').val(),
      start_time: $('#eg-slot-start').val(),
      end_time:   $('#eg-slot-end').val(),
      label:      $('#eg-slot-label').val(),
      status:     $('#eg-slot-status').val()
    }).done(function (res) {
      if (res.success) {
        msg.addClass('eg-msg--success').text('Slot added!');
        $('#eg-add-slot-form')[0].reset();
        reloadSlotsTable();
      } else {
        msg.addClass('eg-msg--error').text('Error: ' + (res.data || 'Unknown error'));
      }
    }).fail(function () {
      msg.addClass('eg-msg--error').text('Network error.');
    });
  });

  // Update slot status (inline select)
  $(document).on('change', '.eg-slot-status-select', function () {
    const id     = $(this).data('id');
    const status = $(this).val();
    $.post(ajaxUrl, { action: 'eggplant_admin_update_slot', nonce: nonce, id: id, status: status })
      .done(function (res) {
        if (!res.success) alert('Could not update slot status.');
      });
  });

  // Delete slot
  $(document).on('click', '.eg-delete-slot', function () {
    if (!confirm('Delete this time slot? This cannot be undone.')) return;
    const id  = $(this).data('id');
    const row = $('#eg-slot-row-' + id);
    $.post(ajaxUrl, { action: 'eggplant_admin_delete_slot', nonce: nonce, id: id })
      .done(function (res) {
        if (res.success) {
          row.fadeOut(300, function () { row.remove(); });
        } else {
          alert('Could not delete slot.');
        }
      });
  });

  function reloadSlotsTable() {
    $.get(ajaxUrl, { action: 'eggplant_admin_get_slots', nonce: nonce }).done(function (res) {
      if (!res.success) return;
      const wrap = $('#eg-slots-table-wrap');
      if (!res.data || !res.data.length) {
        wrap.html('<p>No time slots yet.</p>');
        return;
      }
      let html = '<table class="widefat striped eg-slots-table"><thead><tr>' +
        '<th>Date</th><th>Start</th><th>End</th><th>Label</th><th>Status</th><th>Actions</th>' +
        '</tr></thead><tbody>';
      res.data.forEach(function (s) {
        html += '<tr id="eg-slot-row-' + s.id + '">' +
          '<td>' + esc(s.slot_date) + '</td>' +
          '<td>' + esc(s.start_time.slice(0,5)) + '</td>' +
          '<td>' + esc(s.end_time.slice(0,5)) + '</td>' +
          '<td>' + esc(s.label) + '</td>' +
          '<td><select class="eg-slot-status-select" data-id="' + s.id + '">' +
            opt('available', s.status, 'Available') +
            opt('held',      s.status, 'Hold') +
            opt('booked',    s.status, 'Booked') +
          '</select></td>' +
          '<td><button class="button eg-delete-slot" data-id="' + s.id + '">Delete</button></td>' +
          '</tr>';
      });
      html += '</tbody></table>';
      wrap.html(html);
    });
  }

  /* ================================================================
     EVENTS PAGE
  ================================================================ */

  // Save (add or update)
  $('#eg-event-form').on('submit', function (e) {
    e.preventDefault();
    const msg = $('#eg-event-msg');
    msg.removeClass('eg-msg--success eg-msg--error').text('Saving…');

    const id = $('#eg-event-id').val();
    $.post(ajaxUrl, {
      action:      'eggplant_admin_save_event',
      nonce:       nonce,
      id:          id,
      title:       $('#eg-event-title').val(),
      description: $('#eg-event-description').val(),
      image_url:   $('#eg-event-image-url').val(),
      link_url:    $('#eg-event-link-url').val(),
      start_date:  $('#eg-event-start-date').val(),
      end_date:    $('#eg-event-end-date').val(),
      sort_order:  $('#eg-event-sort').val(),
      active:      $('#eg-event-active').is(':checked') ? 1 : 0
    }).done(function (res) {
      if (res.success) {
        msg.addClass('eg-msg--success').text(id ? 'Updated!' : 'Event added!');
        resetEventForm();
        location.reload();
      } else {
        msg.addClass('eg-msg--error').text('Error: ' + (res.data || 'Unknown error'));
      }
    }).fail(function () {
      msg.addClass('eg-msg--error').text('Network error.');
    });
  });

  // Edit event – load data into form
  $(document).on('click', '.eg-edit-event', function () {
    const id = $(this).data('id');
    $.post(ajaxUrl, { action: 'eggplant_admin_get_event', nonce: nonce, id: id })
      .done(function (res) {
        if (!res.success) { alert('Could not load event.'); return; }
        const ev = res.data;
        $('#eg-event-id').val(ev.id);
        $('#eg-event-title').val(ev.title);
        $('#eg-event-description').val(ev.description);
        $('#eg-event-image-url').val(ev.image_url);
        $('#eg-event-link-url').val(ev.link_url);
        $('#eg-event-start-date').val(ev.start_date);
        $('#eg-event-end-date').val(ev.end_date);
        $('#eg-event-sort').val(ev.sort_order);
        $('#eg-event-active').prop('checked', ev.active === 1 || ev.active === '1');
        $('#eg-event-form-title').text('Edit Event / Advertisement');
        $('#eg-event-submit').text('Update Event');
        $('#eg-event-cancel').show();
        $('html, body').animate({ scrollTop: $('#eg-event-form-card').offset().top - 40 }, 300);
      });
  });

  // Cancel edit
  $('#eg-event-cancel').on('click', resetEventForm);

  function resetEventForm() {
    $('#eg-event-form')[0].reset();
    $('#eg-event-id').val('');
    $('#eg-event-form-title').text('Add Event / Advertisement');
    $('#eg-event-submit').text('Add Event');
    $('#eg-event-cancel').hide();
  }

  // Delete event
  $(document).on('click', '.eg-delete-event', function () {
    if (!confirm('Delete this event?')) return;
    const id  = $(this).data('id');
    const row = $('#eg-event-row-' + id);
    $.post(ajaxUrl, { action: 'eggplant_admin_delete_event', nonce: nonce, id: id })
      .done(function (res) {
        if (res.success) {
          row.fadeOut(300, function () { row.remove(); });
        } else {
          alert('Could not delete event.');
        }
      });
  });

  /* ================================================================
     BOOKING REQUESTS PAGE
  ================================================================ */

  $(document).on('change', '.eg-booking-status-select', function () {
    const id     = $(this).data('id');
    const status = $(this).val();
    const $this  = $(this);
    $.post(ajaxUrl, { action: 'eggplant_admin_update_booking', nonce: nonce, id: id, status: status })
      .done(function (res) {
        if (res.success) {
          $this.closest('tr').find('td:first').css('border-left', '3px solid ' +
            ({ new: '#d63638', reviewed: '#f0ad00', approved: '#46b450', declined: '#999' }[status] || '#999'));
        } else {
          alert('Could not update status.');
        }
      });
  });

  /* ================================================================
     SETTINGS: sync color picker <-> text input
  ================================================================ */

  $('input[type="color"]').on('input', function () {
    const key = $(this).attr('name');
    $('[name="' + key + '_text"]').val($(this).val());
  });

  $('input.eg-color-text').on('input', function () {
    const key = $(this).data('for');
    const val = $(this).val();
    if (/^#[0-9a-fA-F]{3,6}$/.test(val)) {
      $('input[type="color"][name="' + key + '"]').val(val);
    }
  });

  /* ── Helpers ── */
  function esc(str) {
    return $('<div>').text(str || '').html();
  }

  function opt(value, current, label) {
    return '<option value="' + value + '"' + (value === current ? ' selected' : '') + '>' + label + '</option>';
  }

}(jQuery));
