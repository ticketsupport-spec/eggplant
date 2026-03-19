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
     CALENDAR
  ================================================================ */
  const calGrid    = document.getElementById('eg-calendar-grid');
  const calHeading = document.getElementById('eg-cal-heading');
  const slotDetail = document.getElementById('eg-slot-detail');

  if (!calGrid) return;

  const MONTHS = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December'
  ];

  let   calYear  = new Date().getFullYear();
  let   calMonth = new Date().getMonth() + 1; // 1-based
  let   calData  = {};
  let   selectedDate = null;

  function padZ(n) { return String(n).padStart(2, '0'); }

  function loadCalendar(year, month) {
    calHeading.textContent = MONTHS[month - 1] + ' ' + year;
    const ym = year + '-' + padZ(month);

    $.get(ajaxUrl, {
      action: 'eggplant_get_calendar',
      nonce: nonce,
      month: ym
    }).done(function (res) {
      if (res.success) {
        calData = res.data || {};
        renderCalendar(year, month);
      }
    });
  }

  function renderCalendar(year, month) {
    calGrid.innerHTML = '';
    const today  = new Date();
    const todayS = today.getFullYear() + '-' + padZ(today.getMonth() + 1) + '-' + padZ(today.getDate());

    // First day-of-week for the month (0=Sun).
    const firstDow = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    // Empty cells before first day
    for (let i = 0; i < firstDow; i++) {
      const empty = document.createElement('div');
      empty.className = 'eg-calendar__day eg-day--empty';
      calGrid.appendChild(empty);
    }

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = year + '-' + padZ(month) + '-' + padZ(d);
      const cell    = document.createElement('div');
      cell.className = 'eg-calendar__day';
      cell.textContent = d;
      cell.dataset.date = dateStr;

      if (dateStr === todayS) cell.classList.add('eg-day--today');

      const info = calData[dateStr];
      if (info) {
        cell.classList.add('eg-day--' + info.status);
        cell.addEventListener('click', function () { showSlotDetail(dateStr, info); });
      }

      if (selectedDate === dateStr) cell.classList.add('eg-day--selected');

      calGrid.appendChild(cell);
    }
  }

  function showSlotDetail(dateStr, info) {
    selectedDate = dateStr;

    // Highlight selected cell
    calGrid.querySelectorAll('.eg-calendar__day').forEach(function (c) {
      c.classList.toggle('eg-day--selected', c.dataset.date === dateStr);
    });

    document.getElementById('eg-slot-detail-date').textContent = dateStr;
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

    // Fill booking form date if present
    const dateInput = document.getElementById('eg-event-date');
    if (dateInput) {
      dateInput.value = dateStr;
      loadSlotsForDate(dateStr, info.slots);
    }

    // Scroll to booking form
    const bookBtn = document.getElementById('eg-slot-book-btn');
    if (bookBtn) {
      bookBtn.onclick = function () {
        const form = document.getElementById('eg-booking-section');
        if (form) form.scrollIntoView({ behavior: 'smooth' });
      };
    }
  }

  function loadSlotsForDate(dateStr, slots) {
    const sel = document.getElementById('eg-time-slot');
    if (!sel) return;
    sel.innerHTML = '<option value="">' + (slots && slots.length ? '— Select a time slot —' : '— No slots available —') + '</option>';
    if (!slots) return;
    slots.forEach(function (s) {
      if (s.status !== 'available') return; // only show available
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.start.slice(0,5) + ' – ' + s.end.slice(0,5) + (s.label ? ' (' + s.label + ')' : '');
      sel.appendChild(opt);
    });
  }

  // Calendar nav
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

  // Watch date input to reload slots
  const dateInput = document.getElementById('eg-event-date');
  if (dateInput) {
    dateInput.addEventListener('change', function () {
      const v = this.value;
      if (!v) return;
      const info = calData[v];
      if (info) {
        loadSlotsForDate(v, info.slots);
      } else {
        // Date not in calendar data – load fresh
        const ym = v.slice(0, 7);
        $.get(ajaxUrl, { action: 'eggplant_get_calendar', nonce: nonce, month: ym })
          .done(function (res) {
            if (res.success && res.data[v]) {
              loadSlotsForDate(v, res.data[v].slots);
            } else {
              const sel = document.getElementById('eg-time-slot');
              if (sel) sel.innerHTML = '<option value="">— No slots available —</option>';
            }
          });
      }
    });
  }

  // Initial load
  loadCalendar(calYear, calMonth);

  /* ================================================================
     BOOKING FORM
  ================================================================ */
  const bookingForm = document.getElementById('eg-booking-form');
  if (!bookingForm) return;

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

}(jQuery));
