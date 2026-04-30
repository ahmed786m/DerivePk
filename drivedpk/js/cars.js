(function initDatabaseCars() {
  const carsGrid = document.getElementById('carsGrid');
  const carCount = document.getElementById('carCount');
  const priceRange = document.getElementById('priceRange');
  const priceLabel = document.getElementById('priceLabel');

  if (!carsGrid) return;

  let allCars = [];
  let maxPrice = parseInt(priceRange ? priceRange.value : 25000, 10);
  let sortMode = 'default';

  function formatPKR(amount) {
    return 'Rs. ' + Number(amount || 0).toLocaleString('en-PK');
  }

  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]
    ));
  }

  function checkedValues(selector) {
    return Array.from(document.querySelectorAll(selector))
      .filter(input => input.checked)
      .map(input => input.value || input.id);
  }

  function renderCars(cars) {
    carsGrid.innerHTML = '';

    if (!cars.length) {
      carsGrid.innerHTML = '<div id="noResults" style="grid-column:1/-1; text-align:center; padding:60px 20px; color:var(--gray); font-size:16px;">No cars match your filters.</div>';
      if (carCount) carCount.textContent = '0';
      return;
    }

    cars.forEach(car => {
      const status = car.available ? 'available' : 'booked';
      const badge = car.available
        ? '<span class="badge badge-available">Free</span>'
        : '<span class="badge badge-booked">Booked</span>';
      const ownerBadge = car.owner_id ? '<span class="badge badge-featured" style="margin-left:6px;">Owner Listed</span>' : '';
      const action = car.available
        ? `<a href="booking.html?car=${escapeHTML(car.id)}" class="btn-primary">Request</a>`
        : '<a href="contact.html" class="btn-outline">Booked</a>';

      const card = document.createElement('div');
      card.className = 'car-card';
      card.dataset.category = car.type;
      card.dataset.price = car.price_per_day;
      card.dataset.status = status;
      card.dataset.fuel = car.fuel_type;
      card.dataset.transmission = car.transmission;
      card.innerHTML = `
        <div class="car-card-image">
          <img src="images/${escapeHTML(car.image || 'corolla.jpg')}" alt="${escapeHTML(car.brand)} ${escapeHTML(car.model)}" loading="lazy">
          <div class="car-card-badge">${badge}${ownerBadge}</div>
        </div>
        <div class="car-card-body">
          <div class="car-brand">${escapeHTML(car.brand)}</div>
          <div class="car-name">${escapeHTML(car.model)}</div>
          <div class="car-specs">
            <div class="car-spec"><span class="car-spec-icon">Fuel</span> ${escapeHTML(car.fuel_type)}</div>
            <div class="car-spec"><span class="car-spec-icon">Gear</span> ${escapeHTML(car.transmission)}</div>
            <div class="car-spec"><span class="car-spec-icon">Seats</span> ${escapeHTML(car.seats)}</div>
          </div>
          <div class="car-card-footer">
            <div class="car-price">${formatPKR(car.price_per_day)} <span>/ day</span></div>
            ${action}
          </div>
        </div>
      `;
      carsGrid.appendChild(card);
    });

    if (carCount) carCount.textContent = cars.length;
  }

  function applyDatabaseFilters() {
    const activeTypes = checkedValues('input[name="type"]');
    const activeFuels = checkedValues('input[id="petrol"], input[id="diesel"], input[id="hybrid"]');
    const activeTrans = checkedValues('input[id="auto"], input[id="manual"], input[id="cvt"]');
    const availFilter = document.querySelector('input[name="avail"]:checked')?.value || 'all';

    let visible = allCars.filter(car => {
      if (Number(car.price_per_day) > maxPrice) return false;
      if (activeTypes.length && !activeTypes.includes(car.type)) return false;
      if (activeFuels.length && !activeFuels.includes(car.fuel_type)) return false;
      if (activeTrans.length && !activeTrans.includes(car.transmission)) return false;
      if (availFilter === 'available' && !car.available) return false;
      return true;
    });

    if (sortMode === 'price-asc') visible.sort((a, b) => a.price_per_day - b.price_per_day);
    if (sortMode === 'price-desc') visible.sort((a, b) => b.price_per_day - a.price_per_day);
    if (sortMode === 'name') visible.sort((a, b) => `${a.brand} ${a.model}`.localeCompare(`${b.brand} ${b.model}`));

    renderCars(visible);
  }

  window.applyFilters = applyDatabaseFilters;

  window.resetFilters = function() {
    document.querySelectorAll('.sidebar input[type="checkbox"]').forEach(cb => cb.checked = false);
    const allRadio = document.querySelector('input[name="avail"][value="all"]');
    if (allRadio) allRadio.checked = true;
    if (priceRange) {
      priceRange.value = priceRange.max;
      maxPrice = parseInt(priceRange.max, 10);
      if (priceLabel) priceLabel.textContent = formatPKR(priceRange.max);
    }
    sortMode = 'default';
    applyDatabaseFilters();
  };

  window.sortCars = function(value) {
    sortMode = value;
    applyDatabaseFilters();
  };

  window.updatePrice = function(value) {
    maxPrice = parseInt(value, 10);
    if (priceLabel) priceLabel.textContent = formatPKR(value);
  };

  fetch('php/cars.php', { cache: 'no-store' })
    .then(response => response.json())
    .then(result => {
      if (!result.success || !Array.isArray(result.cars)) return;
      allCars = result.cars;

      const params = new URLSearchParams(window.location.search);
      const typeParam = params.get('type');
      if (typeParam) {
        const cb = document.querySelector(`input[name="type"][value="${typeParam}"]`);
        if (cb) cb.checked = true;
      }

      applyDatabaseFilters();
    })
    .catch(() => {
      if (carCount) carCount.textContent = carsGrid.querySelectorAll('.car-card').length;
    });
})();
