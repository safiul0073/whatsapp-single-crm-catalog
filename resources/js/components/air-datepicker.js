/**
 * Air Datepicker Initialization
 * Documentation: https://air-datepicker.com/examples
 */

import AirDatepicker from 'air-datepicker';
import 'air-datepicker/air-datepicker.css';

const localeEn = {
  days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
  daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
  daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
  months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
  monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  today: 'Today',
  clear: 'Clear',
  dateFormat: 'MM/dd/yyyy',
  timeFormat: 'HH:mm',
  firstDay: 0,
};

// Store instances for cleanup
window.airDatepickers = [];

function initPickers() {
  // Destroy existing instances
  if (window.airDatepickers.length > 0) {
    window.airDatepickers.forEach((instance) => instance.destroy());
    window.airDatepickers = [];
  }

  const isRtl = document.documentElement.dir === 'rtl';
  const commonOptions = {
    locale: localeEn,
    isRtl: isRtl,
  };

  // Basic Date Picker
  document.querySelectorAll('.datepicker').forEach((el) => {
    if (el._adp) return;
    window.airDatepickers.push(
      new AirDatepicker(el, {
        ...commonOptions,
        autoClose: true,
      }),
    );
  });

  // Range Picker
  document.querySelectorAll('.datepicker-range').forEach((el) => {
    if (el._adp) return;
    window.airDatepickers.push(
      new AirDatepicker(el, {
        ...commonOptions,
        range: true,
        multipleDatesSeparator: ' - ',
        autoClose: true,
      }),
    );
  });

  // Date & Time Picker
  document.querySelectorAll('.datetime-picker').forEach((el) => {
    if (el._adp) return;
    window.airDatepickers.push(
      new AirDatepicker(el, {
        ...commonOptions,
        timepicker: true,
        dateTimeSeparator: ' ',
        autoClose: false,
      }),
    );
  });

  // Time Only Picker
  document.querySelectorAll('.time-picker').forEach((el) => {
    if (el._adp) return;
    window.airDatepickers.push(
      new AirDatepicker(el, {
        ...commonOptions,
        timepicker: true,
        onlyTimepicker: true,
      }),
    );
  });

  // Inline Calendar
  document.querySelectorAll('.datepicker-inline').forEach((el) => {
    if (el._adp) return;
    window.airDatepickers.push(
      new AirDatepicker(el, {
        ...commonOptions,
      }),
    );
  });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
  initPickers();
});

// Re-initialize on RTL toggle
document.addEventListener('rtl-toggled', initPickers);

// Re-initialize after datatable content swap
document.addEventListener('datatable:updated', initPickers);

export { initPickers };
