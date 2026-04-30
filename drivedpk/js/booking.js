function formatPKR(amount) {
  return 'Rs. ' + Number(amount).toLocaleString('en-PK');
}

function daysBetween(dateA, dateB) {
  const a = new Date(dateA);
  const b = new Date(dateB);
  const diff = Math.ceil((b - a) / (1000 * 60 * 60 * 24));
  return diff > 0 ? diff : 0;
}

(function initBookingPage() {
  const carSelect = document.getElementById('carSelect');
  const pickupDate = document.getElementById('pickupDate');
  const dropoffDate = document.getElementById('dropoffDate');
  const bookingForm = document.getElementById('bookingForm');

  const summaryCarName = document.getElementById('summaryCarName');
  const summaryRate = document.getElementById('summaryRate');
  const summaryDays = document.getElementById('summaryDays');
  const summarySubtotal = document.getElementById('summarySubtotal');
  const summaryTotal = document.getElementById('summaryTotal');
  const totalPriceInput = document.getElementById('totalPriceInput');

  const SERVICE_FEE = 500;

  if (!carSelect || !pickupDate || !dropoffDate) return;

  function splitName(fullName) {
    const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    return {
      first: parts.shift() || '',
      last: parts.join(' ')
    };
  }

  function prefillCustomerDetails() {
    fetch('php/auth.php?action=status', { cache: 'no-store' })
      .then(response => response.json())
      .then(session => {
        if (!session.logged_in || session.role !== 'customer') return;

        const names = splitName(session.name);
        const firstName = bookingForm?.querySelector('[name="first_name"]');
        const lastName = bookingForm?.querySelector('[name="last_name"]');
        const email = bookingForm?.querySelector('[name="email"]');
        const phone = bookingForm?.querySelector('[name="phone"]');
        const cnic = bookingForm?.querySelector('[name="cnic"]');

        if (firstName && !firstName.value) firstName.value = names.first;
        if (lastName && !lastName.value) lastName.value = names.last;
        if (email && !email.value) email.value = session.email || '';
        if (phone && !phone.value) phone.value = session.phone || '';
        if (cnic && !cnic.value) cnic.value = session.cnic || '';
      })
      .catch(() => {});
  }

  function selectedCarName(option) {
    return option.textContent.replace(/\s+-\s+Rs\..*$/i, '').replace(/\s+Rs\..*$/i, '').trim();
  }

  function updatePrice() {
    const selectedOption = carSelect.options[carSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      if (summaryCarName) summaryCarName.textContent = 'No car selected';
      if (summaryRate) summaryRate.textContent = formatPKR(0);
      if (summaryDays) summaryDays.textContent = '0 days';
      if (summarySubtotal) summarySubtotal.textContent = formatPKR(0);
      if (summaryTotal) summaryTotal.textContent = formatPKR(0);
      if (totalPriceInput) totalPriceInput.value = 0;
      return;
    }

    const pricePerDay = parseInt(selectedOption.dataset.price, 10) || 0;
    const days = daysBetween(pickupDate.value, dropoffDate.value);
    const subtotal = pricePerDay * days;
    const total = subtotal + SERVICE_FEE;

    if (summaryCarName) summaryCarName.textContent = selectedCarName(selectedOption);
    if (summaryRate) summaryRate.textContent = formatPKR(pricePerDay);
    if (summaryDays) summaryDays.textContent = days + (days === 1 ? ' day' : ' days');
    if (summarySubtotal) summarySubtotal.textContent = formatPKR(subtotal);
    if (summaryTotal) summaryTotal.textContent = formatPKR(total);
    if (totalPriceInput) totalPriceInput.value = total;
  }

  function applyCarFromURL() {
    const params = new URLSearchParams(window.location.search);
    const carId = params.get('car') || params.get('id');
    if (!carId) return;

    const option = Array.from(carSelect.options).find(item => item.value === carId);
    if (option) {
      carSelect.value = carId;
    }
  }

  function loadCarsFromDatabase() {
    fetch('php/cars.php?available=1', { cache: 'no-store' })
      .then(response => response.json())
      .then(result => {
        if (!result.success || !Array.isArray(result.cars) || !result.cars.length) {
          carSelect.innerHTML = '<option value="">No free cars available right now</option>';
          updatePrice();
          return;
        }

        carSelect.innerHTML = result.cars.map(car => {
          const label = `${car.brand} ${car.model} - ${formatPKR(car.price_per_day)}/day`;
          return `<option value="${car.id}" data-price="${car.price_per_day}">${label}</option>`;
        }).join('');

        applyCarFromURL();
        updatePrice();
      })
      .catch(() => {});
  }

  pickupDate.addEventListener('change', () => {
    if (dropoffDate.value && dropoffDate.value <= pickupDate.value) {
      dropoffDate.value = '';
    }
    dropoffDate.min = pickupDate.value;
    updatePrice();
  });

  dropoffDate.addEventListener('change', updatePrice);
  carSelect.addEventListener('change', updatePrice);

  window.updatePrice = updatePrice;
  window.calculatePrice = updatePrice;

  applyCarFromURL();
  updatePrice();
  loadCarsFromDatabase();
  prefillCustomerDetails();

  if (bookingForm) {
    bookingForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      const days = daysBetween(pickupDate.value, dropoffDate.value);

      if (!pickupDate.value || !dropoffDate.value) {
        showAlert('alertBox', 'Please select both pick-up and drop-off dates.', 'error');
        return;
      }

      if (!carSelect.value) {
        showAlert('alertBox', 'Please choose a free car before sending the request.', 'error');
        return;
      }

      if (days <= 0) {
        showAlert('alertBox', 'Drop-off date must be after pick-up date.', 'error');
        return;
      }

      const submitButton = bookingForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
      }

      try {
        const response = await fetch(bookingForm.action, {
          method: 'POST',
          body: new FormData(bookingForm)
        });
        const result = await response.json();

        if (!result.success) {
          if (result.login_required) {
            window.location.href = `login.html?role=customer&error=${encodeURIComponent(result.message || 'Please login first.')}`;
            return;
          }
          showAlert('alertBox', result.message || 'Booking failed.', 'error');
          return;
        }

        showAlert(
          'alertBox',
          `${result.message} Reference: ${result.booking_ref}. Total: ${formatPKR(result.total_price)}.`,
          'success'
        );

        bookingForm.reset();
        updatePrice();
        setTimeout(() => {
          window.location.href = `dashboard.html?success=${encodeURIComponent('Booking request sent. Watch your dashboard for owner approval.')}`;
        }, 1200);
      } catch (error) {
        showAlert('alertBox', 'Could not connect to the server. Make sure XAMPP Apache and MySQL are running.', 'error');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Send Booking Request';
        }
      }
    });
  }
})();

(function initDetailPage() {
  const pickupDate = document.getElementById('pickupDate');
  const dropoffDate = document.getElementById('dropoffDate');
  const numDaysEl = document.getElementById('numDays');
  const totalPriceEl = document.getElementById('totalPrice');

  if (!numDaysEl || !totalPriceEl) return;

  const PRICE_PER_DAY = 8000;
  const SERVICE_FEE = 500;

  function calculatePrice() {
    if (!pickupDate || !dropoffDate) return;
    const days = daysBetween(pickupDate.value, dropoffDate.value);
    const subtotal = PRICE_PER_DAY * days;
    const total = days > 0 ? subtotal + SERVICE_FEE : 0;

    numDaysEl.textContent = days;
    totalPriceEl.textContent = days > 0 ? formatPKR(total) : formatPKR(0);

    if (dropoffDate) dropoffDate.min = pickupDate ? pickupDate.value : '';
  }

  if (pickupDate) pickupDate.addEventListener('change', calculatePrice);
  if (dropoffDate) dropoffDate.addEventListener('change', calculatePrice);

  window.calculatePrice = window.calculatePrice || calculatePrice;
})();
