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

  function toggleSlotPublicFields() {
    $('.eg-slot-public-fields').prop('hidden', $('#eg-slot-visibility').val() !== 'public');
  }

  $('#eg-slot-visibility').on('change', toggleSlotPublicFields);

  function resetSlotForm() {
    $('#eg-add-slot-form')[0].reset();
    $('#eg-slot-id').val('');
    $('#eg-slot-visibility').val('private');
    $('#eg-slot-image').val('').trigger('change');
    toggleSlotPublicFields();
    $('#eg-slot-form-title').text('Add Time Slot');
    $('#eg-slot-submit').text('Add Slot');
    $('#eg-slot-cancel').prop('hidden', true);
  }

  $('#eg-slot-cancel').on('click', function () {
    resetSlotForm();
    $('#eg-add-slot-msg').text('');
  });

  $(document).on('click', '.eg-edit-slot', function () {
    const msg = $('#eg-add-slot-msg');
    msg.removeClass('eg-msg--success eg-msg--error').text('Loading...');
    $.get(ajaxUrl, { action: 'eggplant_admin_get_slots', nonce: nonce, id: $(this).data('id') })
      .done(function (res) {
        if (!res.success) {
          msg.addClass('eg-msg--error').text(res.data || 'Could not load time slot.');
          return;
        }
        const slot = res.data;
        $('#eg-slot-id').val(slot.id);
        $('#eg-slot-date').val(slot.slot_date);
        $('#eg-slot-start').val(slot.start_time.slice(0, 5));
        $('#eg-slot-end').val(slot.end_time.slice(0, 5));
        $('#eg-slot-floor').val(slot.floor);
        $('#eg-slot-label').val(slot.label);
        $('#eg-slot-status').val(slot.status);
        $('#eg-slot-visibility').val(slot.visibility === 'public' ? 'public' : 'private');
        $('#eg-slot-image').val(slot.image_url || '').trigger('change');
        toggleSlotPublicFields();
        $('#eg-slot-form-title').text('Edit Time Slot');
        $('#eg-slot-submit').text('Save Changes');
        $('#eg-slot-cancel').prop('hidden', false);
        msg.text('');
        document.getElementById('eg-add-slot-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).fail(function () {
        msg.addClass('eg-msg--error').text('Could not load time slot. Network error.');
      });
  });

  // Add or edit slot.
  $('#eg-add-slot-form').on('submit', function (e) {
    e.preventDefault();
    const msg = $('#eg-add-slot-msg');
    msg.removeClass('eg-msg--success eg-msg--error').text('Saving…');

    const id = $('#eg-slot-id').val();
    const submit = $('#eg-slot-submit').prop('disabled', true);
    $.post(ajaxUrl, {
      action:     id ? 'eggplant_admin_update_slot' : 'eggplant_admin_add_slot',
      nonce:      nonce,
      id:         id,
      slot_date:  $('#eg-slot-date').val(),
      start_time: $('#eg-slot-start').val(),
      end_time:   $('#eg-slot-end').val(),
      floor:      $('#eg-slot-floor').val(),
      label:      $('#eg-slot-label').val(),
      status:     $('#eg-slot-status').val(),
      visibility: $('#eg-slot-visibility').val(),
      image_url:  $('#eg-slot-image').val()
    }).done(function (res) {
      if (res.success) {
        msg.addClass('eg-msg--success').text(id ? 'Slot updated!' : 'Slot added!');
        resetSlotForm();
        reloadSlotsTable();
        $(document).trigger('eggplant:slots-changed');
      } else {
        msg.addClass('eg-msg--error').text('Error: ' + (res.data || 'Unknown error'));
      }
    }).fail(function () {
      msg.addClass('eg-msg--error').text('Network error.');
    }).always(function () {
      submit.prop('disabled', false);
    });
  });

  // Update slot status, floor or event type inline.
  $(document).on('change', '.eg-slot-status-select, .eg-slot-floor-select, .eg-slot-visibility-select', function () {
    const select = $(this);
    const field = select.hasClass('eg-slot-floor-select') ? 'floor'
      : (select.hasClass('eg-slot-visibility-select') ? 'visibility' : 'status');
    const payload = { action: 'eggplant_admin_update_slot', nonce: nonce, id: select.data('id') };
    payload[field] = select.val();
    select.prop('disabled', true);
    $.post(ajaxUrl, payload)
      .done(function (res) {
        if (!res.success) {
          alert(res.data || 'Could not update slot.');
        } else {
          if ($('#eg-slot-id').val() === String(payload.id)) {
            $('#eg-slot-' + field).val(payload[field]);
            toggleSlotPublicFields();
          }
          $(document).trigger('eggplant:slots-changed');
        }
      }).fail(function () {
        alert('Could not update slot. Network error.');
      }).always(function () {
        select.prop('disabled', false);
        reloadSlotsTable();
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
          if ($('#eg-slot-id').val() === String(id)) resetSlotForm();
          $(document).trigger('eggplant:slots-changed');
        } else {
          alert('Could not delete slot.');
        }
      }).fail(function () {
        alert('Could not delete slot. Network error.');
      });
  });

  function reloadSlotsTable() {
    $.get(ajaxUrl, { action: 'eggplant_admin_get_slots', nonce: nonce }).done(function (res) {
      if (!res.success) {
        $('#eg-add-slot-msg').addClass('eg-msg--error').text(res.data || 'Could not reload time slots.');
        return;
      }
      const wrap = $('#eg-slots-table-wrap');
      if (!res.data || !res.data.length) {
        wrap.html('<p>No time slots yet.</p>');
        return;
      }
      let html = '<table class="widefat striped eg-slots-table"><thead><tr>' +
        '<th>Date</th><th>Start</th><th>End</th><th>Floor</th><th>Label</th><th>Status</th><th>Event Type</th><th>Actions</th>' +
        '</tr></thead><tbody>';
      res.data.forEach(function (s) {
        html += '<tr id="eg-slot-row-' + s.id + '">' +
          '<td>' + esc(s.slot_date) + '</td>' +
          '<td>' + esc(s.start_time.slice(0,5)) + '</td>' +
          '<td>' + esc(s.end_time.slice(0,5)) + '</td>' +
          '<td><select class="eg-slot-floor-select" data-id="' + s.id + '" aria-label="Floor">' +
            Object.keys(EggplantAdmin.floors).map(function (floor) {
              return opt(floor, s.floor, EggplantAdmin.floors[floor]);
            }).join('') +
          '</select></td>' +
          '<td>' + esc(s.label) + '</td>' +
          '<td><select class="eg-slot-status-select" data-id="' + s.id + '">' +
            opt('available', s.status, 'Available') +
            opt('held',      s.status, 'Hold') +
            opt('booked',    s.status, 'Booked') +
          '</select></td>' +
          '<td><select class="eg-slot-visibility-select" data-id="' + s.id + '" aria-label="Event Type">' +
            opt('private', s.visibility === 'public' ? 'public' : 'private', 'Private Event') +
            opt('public',  s.visibility === 'public' ? 'public' : 'private', 'Public Event') +
          '</select>' +
          (/^https?:\/\//i.test(s.image_url || '') ? ' <img class="eg-slot-thumb" src="' + esc(s.image_url) + '" alt="">' : '') +
          '</td>' +
          '<td><button type="button" class="button eg-edit-slot" data-id="' + s.id + '">Edit</button> ' +
          '<button class="button eg-delete-slot" data-id="' + s.id + '">Delete</button></td>' +
          '</tr>';
      });
      html += '</tbody></table>';
      wrap.html(html);
    }).fail(function () {
      $('#eg-add-slot-msg').addClass('eg-msg--error').text('Could not reload time slots. Network error.');
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
          if (res.data && res.data.floor_message) alert(res.data.floor_message);
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

  function escAttr(str) {
    return String(str || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function opt(value, current, label) {
    return '<option value="' + value + '"' + (value === current ? ' selected' : '') + '>' + esc(label) + '</option>';
  }

}(jQuery));
