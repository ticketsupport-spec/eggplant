/* jshint esversion:6 */
(function ($) {
  'use strict';

  if (typeof EggplantData === 'undefined') return;

  const ajaxUrl       = EggplantData.ajaxUrl;
  const nonce         = EggplantData.nonce;
  const carouselSpeed = EggplantData.carouselSpeed || 5000;
  const carouselAuto  = EggplantData.carouselAuto  !== false;

  /* ================================================================
     CAROUSEL
  ================================================================ */
  const track  = document.getElementById('eg-carousel-track');
  const dots   = document.querySelectorAll('.eg-carousel__dot');
  let   current = 0;
  let   timer;

  if (track) {
    const slides = track.querySelectorAll('.eg-carousel__slide');
    const total  = slides.length;

    function goTo(index) {
      current = (index + total) % total;
      track.style.transform = 'translateX(-' + (current * 100) + '%)';
      dots.forEach(function (d, i) {
        d.classList.toggle('eg-carousel__dot--active', i === current);
      });
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    if (total > 1) {
      document.querySelector('.eg-carousel__btn--prev') &&
        document.querySelector('.eg-carousel__btn--prev').addEventListener('click', function () { prev(); resetTimer(); });
      document.querySelector('.eg-carousel__btn--next') &&
        document.querySelector('.eg-carousel__btn--next').addEventListener('click', function () { next(); resetTimer(); });

      dots.forEach(function (d) {
        d.addEventListener('click', function () { goTo(parseInt(d.dataset.index, 10)); resetTimer(); });
      });

      function resetTimer() {
        clearInterval(timer);
        if (carouselAuto) timer = setInterval(next, carouselSpeed);
      }

      if (carouselAuto) timer = setInterval(next, carouselSpeed);
    }
  }

  /* ================================================================
     CALENDAR (two floors, one event per floor per day)
  ================================================================ */
  const calGrid    = document.getElementById('eg-calendar-grid');
  const calHeading = document.getElementById('eg-cal-heading');
  const slotDetail = document.getElementById('eg-slot-detail');
  const FLOORS     = EggplantData.floors || {};
  const TODAY      = EggplantData.today || '';
  const monthCache = {};

  function padZ(n) { return String(n).padStart(2, '0'); }

  function fetchMonth(ym) {
    if (monthCache[ym]) return $.Deferred().resolve(monthCache[ym]).promise();
    return $.get(ajaxUrl, { action: 'eggplant_get_calendar', nonce: nonce, month: ym })
      .then(function (res) {
        const data = (res && res.success && res.data) ? res.data : {};
        monthCache[ym] = { slots: data.slots || {}, floors: data.floors || {} };
        return monthCache[ym];
      });
  }

  function floorState(entry, isPast) {
    if (!entry) {
      return isPast
        ? { cls: 'past', text: '', short: '' }
        : { cls: 'available', text: 'Available', short: 'Open' };
    }
    if (entry.status === 'blocked') return { cls: 'blocked', text: 'Unavailable', short: 'N/A' };
    return entry.visibility === 'public'
      ? { cls: 'public',  text: 'PUBLIC EVENT',  short: 'Public' }
      : { cls: 'private', text: 'PRIVATE EVENT', short: 'Private' };
  }

  function formatDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  /* ── Public event pop-up ── */
  const modal = document.getElementById('eg-event-modal');
  let lastFocus = null;

  function openEventModal(dateStr, floor, entry) {
    if (!modal) return;
    const img  = document.getElementById('eg-modal-img');
    const link = document.getElementById('eg-modal-link');
    const time = entry.start ? (entry.start + (entry.end ? ' – ' + entry.end : '')) : '';

    document.getElementById('eg-modal-title').textContent = entry.title || 'Public Event';
    document.getElementById('eg-modal-meta').textContent  =
      formatDate(dateStr) + ' · ' + (FLOORS[floor] || '') + (time ? ' · ' + time : '');
    // Description is sanitized server-side with wp_kses_post().
    document.getElementById('eg-modal-desc').innerHTML = entry.description || '';

    if (entry.image_url) { img.src = entry.image_url; img.alt = entry.title || ''; img.hidden = false; }
    else { img.removeAttribute('src'); img.hidden = true; }

    if (entry.link_url) { link.href = entry.link_url; link.hidden = false; }
    else { link.hidden = true; }

    lastFocus = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('eg-modal-open');
    modal.querySelector('.eg-modal__dialog').focus();
  }

  function closeEventModal() {
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('eg-modal-open');
    if (lastFocus) lastFocus.focus();
  }

  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-eg-close]')) closeEventModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeEventModal();
    });
  }

  /* ── Booking form helpers ── */
  function updateFloorOptions(dateStr, floorsForDay) {
    const sel = document.getElementById('eg-floor');
    if (!sel) return;
    Array.prototype.forEach.call(sel.options, function (opt) {
      if (!opt.value) return;
      const taken = !!(floorsForDay && floorsForDay[opt.value]);
      opt.disabled = taken;
      opt.textContent = FLOORS[opt.value] + (taken ? ' (not available)' : '');
      if (taken && opt.selected) sel.value = '';
    });
  }

  function loadSlotsForDate(dateStr, slots) {
    const sel = document.getElementById('eg-time-slot');
    if (!sel) return;
    const open = (slots || []).filter(function (s) { return s.status === 'available'; });
    sel.innerHTML = '<option value="">' + (open.length ? '— Select a time slot —' : '— No slots available —') + '</option>';
    open.forEach(function (s) {
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.start.slice(0,5) + ' – ' + s.end.slice(0,5) + (s.label ? ' (' + s.label + ')' : '');
      sel.appendChild(opt);
    });
  }

  function prefillBooking(dateStr, floor) {
    const dateInput = document.getElementById('eg-event-date');
    if (!dateInput) return;
    dateInput.value = dateStr;
    const month = monthCache[dateStr.slice(0, 7)] || { slots: {}, floors: {} };
    updateFloorOptions(dateStr, month.floors[dateStr]);
    loadSlotsForDate(dateStr, (month.slots[dateStr] || {}).slots);
    const floorSel = document.getElementById('eg-floor');
    if (floorSel && floor) floorSel.value = floor;
    const form = document.getElementById('eg-booking-section');
    if (form) form.scrollIntoView({ behavior: 'smooth' });
  }

  if (calGrid && calHeading) {
    const MONTHS = [
      'January','February','March','April','May','June',
      'July','August','September','October','November','December'
    ];

    const todayParts = TODAY ? TODAY.split('-').map(Number) : [new Date().getFullYear(), new Date().getMonth() + 1];
    let calYear  = todayParts[0];
    let calMonth = todayParts[1];
    let selectedDate = null;

    function loadCalendar(year, month) {
      calHeading.textContent = MONTHS[month - 1] + ' ' + year;
      fetchMonth(year + '-' + padZ(month)).done(function (data) {
        if (year === calYear && month === calMonth) renderCalendar(year, month, data);
      });
    }

    function renderCalendar(year, month, data) {
      calGrid.innerHTML = '';
      const firstDow    = new Date(year, month - 1, 1).getDay();
      const daysInMonth = new Date(year, month, 0).getDate();

      for (let i = 0; i < firstDow; i++) {
        const empty = document.createElement('div');
        empty.className = 'eg-calendar__day eg-day--empty';
        calGrid.appendChild(empty);
      }

      for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = year + '-' + padZ(month) + '-' + padZ(d);
        const isPast  = TODAY && dateStr < TODAY;
        const cell    = document.createElement('div');
        cell.className = 'eg-calendar__day eg-day--floors';
        cell.dataset.date = dateStr;
        if (dateStr === TODAY) cell.classList.add('eg-day--today');
        if (isPast) cell.classList.add('eg-day--past');
        if (selectedDate === dateStr) cell.classList.add('eg-day--selected');

        const slotInfo = data.slots[dateStr];
        const num = document.createElement(slotInfo ? 'button' : 'span');
        num.className = 'eg-day__num';
        num.textContent = d;
        if (slotInfo && slotDetail) {
          num.type = 'button';
          num.classList.add('eg-day__num--slots');
          num.title = 'View time slots';
          num.addEventListener('click', function () { showSlotDetail(dateStr, slotInfo); });
        }
        cell.appendChild(num);

        const dayFloors = data.floors[dateStr] || {};
        Object.keys(FLOORS).forEach(function (floor) {
          const entry = dayFloors[floor];
          const state = floorState(entry, isPast);
          const clickable = state.cls === 'public' || (state.cls === 'available' && EggplantData.showBookingForm);
          const el = document.createElement(clickable ? 'button' : 'div');
          el.className = 'eg-floor eg-floor--' + state.cls;
          if (clickable) el.type = 'button';

          let label = FLOORS[floor] + ': ' + (state.text || 'Past date');
          if (state.cls === 'public' && entry.title) label += ' – ' + entry.title + ' (click for details)';
          if (state.cls === 'available' && clickable) label += ' (click to request this floor)';
          el.title = label;
          el.setAttribute('aria-label', formatDate(dateStr) + ', ' + label);

          const longTxt = document.createElement('span');
          longTxt.className = 'eg-floor__text';
          longTxt.textContent = state.text;
          const shortTxt = document.createElement('span');
          shortTxt.className = 'eg-floor__short';
          shortTxt.textContent = state.short;
          el.appendChild(longTxt);
          el.appendChild(shortTxt);

          if (state.cls === 'public') {
            el.addEventListener('click', function () { openEventModal(dateStr, floor, entry); });
          } else if (clickable) {
            el.addEventListener('click', function () { prefillBooking(dateStr, floor); });
          }
          cell.appendChild(el);
        });

        calGrid.appendChild(cell);
      }
    }

    function showSlotDetail(dateStr, info) {
      selectedDate = dateStr;
      calGrid.querySelectorAll('.eg-calendar__day').forEach(function (c) {
        c.classList.toggle('eg-day--selected', c.dataset.date === dateStr);
      });

      document.getElementById('eg-slot-detail-date').textContent = formatDate(dateStr);
      const list = document.getElementById('eg-slot-detail-list');
      list.innerHTML = '';

      (info.slots || []).forEach(function (s) {
        const li    = document.createElement('li');
        const badge = document.createElement('span');
        badge.className = 'eg-slot-badge eg-slot-badge--' + s.status;
        badge.textContent = s.status.charAt(0).toUpperCase() + s.status.slice(1);
        li.appendChild(badge);
        li.appendChild(document.createTextNode(' ' + s.start.slice(0,5) + ' – ' + s.end.slice(0,5) + (s.label ? ' (' + s.label + ')' : '')));
        li.dataset.slotId = s.id;
        list.appendChild(li);
      });

      slotDetail.hidden = false;

      const bookBtn = document.getElementById('eg-slot-book-btn');
      if (bookBtn) {
        bookBtn.onclick = function () { prefillBooking(dateStr, ''); };
      }
    }

    document.getElementById('eg-cal-prev') && document.getElementById('eg-cal-prev').addEventListener('click', function () {
      calMonth--;
      if (calMonth < 1) { calMonth = 12; calYear--; }
      loadCalendar(calYear, calMonth);
    });
    document.getElementById('eg-cal-next') && document.getElementById('eg-cal-next').addEventListener('click', function () {
      calMonth++;
      if (calMonth > 12) { calMonth = 1; calYear++; }
      loadCalendar(calYear, calMonth);
    });

    loadCalendar(calYear, calMonth);
  }

  // Watch the booking-form date input to refresh floor availability and slots.
  const dateInputEl = document.getElementById('eg-event-date');
  if (dateInputEl) {
    if (TODAY) dateInputEl.min = TODAY;
    dateInputEl.addEventListener('change', function () {
      const v = this.value;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return;
      fetchMonth(v.slice(0, 7)).done(function (data) {
        updateFloorOptions(v, data.floors[v]);
        loadSlotsForDate(v, (data.slots[v] || {}).slots);
      });
    });
  }

  /* ================================================================
     BOOKING FORM
  ================================================================ */
  const bookingForm = document.getElementById('eg-booking-form');
  if (bookingForm) {
    bookingForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const btn = document.getElementById('eg-booking-submit');
      const res = document.getElementById('eg-booking-response');

      btn.disabled = true;
      btn.innerHTML = '<span class="eg-spinner"></span> Sending…';
      res.className = '';
      res.style.display = 'none';

      $.post(ajaxUrl, {
        action:       'eggplant_submit_booking',
        nonce:        nonce,
        name:         $('#eg-name').val(),
        email:        $('#eg-email').val(),
        phone:        $('#eg-phone').val(),
        event_type:   $('#eg-event-type').val(),
        event_date:   $('#eg-event-date').val(),
        floor:        $('#eg-floor').val(),
        time_slot_id: $('#eg-time-slot').val(),
        message:      $('#eg-message').val()
      }).done(function (response) {
        btn.disabled = false;
        btn.textContent = 'Send Booking Request';
        if (response.success) {
          res.className = 'eg-response--success';
          res.textContent = response.data;
          bookingForm.reset();
          const sel = document.getElementById('eg-time-slot');
          if (sel) sel.innerHTML = '<option value="">— Choose a date first —</option>';
          updateFloorOptions('', null);
        } else {
          res.className = 'eg-response--error';
          res.textContent = response.data || 'An error occurred. Please try again.';
        }
      }).fail(function () {
        btn.disabled = false;
        btn.textContent = 'Send Booking Request';
        res.className = 'eg-response--error';
        res.textContent = 'Network error. Please try again.';
      });
    });
  }

  /* ================================================================
     OPERATIONAL SHORTCODES
  ================================================================ */
  const staffField = document.getElementById('eg-ops-staff-identifier');
  if (staffField) {
    function syncStaffIdentifier() {
      document.querySelectorAll('.eg-ops-staff-mirror').forEach(function (input) {
        input.value = staffField.value;
      });
    }

    staffField.addEventListener('input', syncStaffIdentifier);
    syncStaffIdentifier();
  }

  /* ── Tasks auto-refresh every 15 minutes ── */
  const tasksList = document.getElementById('eg-ops-tasks-list');
  if (tasksList) {
    var tasksRefreshNonce = tasksList.dataset.nonce || '';

    function refreshTasks() {
      $.post(ajaxUrl, {
        action:   'eggplant_refresh_tasks',
        nonce:    tasksRefreshNonce,
        page_url: window.location.href
      }).done(function (res) {
        if (res.success && res.data) {
          tasksList.innerHTML = res.data;
          // Re-sync staff identifier into any newly rendered hidden fields.
          if (staffField) {
            document.querySelectorAll('.eg-ops-staff-mirror').forEach(function (input) {
              input.value = staffField.value;
            });
          }
        }
      });
    }

    setInterval(refreshTasks, 15 * 60 * 1000);
  }

}(jQuery));
