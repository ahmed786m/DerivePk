(function initCarDetailFromDatabase() {
  const mainImage = document.getElementById('mainImage');
  const pickupDate = document.getElementById('pickupDate');
  const dropoffDate = document.getElementById('dropoffDate');
  const numDaysEl = document.getElementById('numDays');
  const totalPriceEl = document.getElementById('totalPrice');
  const bookLink = document.querySelector('.booking-sidebar a.btn-gold, .booking-sidebar a.btn-outline');

  if (!mainImage) return;

  const params = new URLSearchParams(window.location.search);
  const carId = params.get('id') || params.get('car') || '1';
  const SERVICE_FEE = 500;
  let activeCar = null;

  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]
    ));
  }

  function formatPKR(amount) {
    return 'Rs. ' + Number(amount || 0).toLocaleString('en-PK');
  }

  function imagePath(image) {
    const value = String(image || 'corolla.jpg').trim();
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('images/')) return value;
    return `images/${value}`;
  }

  function daysBetween(dateA, dateB) {
    const a = new Date(dateA);
    const b = new Date(dateB);
    const diff = Math.ceil((b - a) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
  }

  function calculateDetailPrice() {
    if (!activeCar || !pickupDate || !dropoffDate) return;
    const days = daysBetween(pickupDate.value, dropoffDate.value);
    const subtotal = Number(activeCar.price_per_day || 0) * days;
    const total = days > 0 ? subtotal + SERVICE_FEE : 0;

    if (numDaysEl) numDaysEl.textContent = days;
    if (totalPriceEl) totalPriceEl.textContent = formatPKR(total);
    if (dropoffDate) dropoffDate.min = pickupDate ? pickupDate.value : '';
  }

  function renderCar(car) {
    activeCar = car;

    const gallery = Array.isArray(car.gallery_images) && car.gallery_images.length
      ? car.gallery_images
      : [car.image || 'corolla.jpg'];
    const images = gallery.map(imagePath);
    const image = images[0] || imagePath(car.image || 'corolla.jpg');
    const fullName = `${car.brand} ${car.model}`;
    const available = Number(car.available) === 1 || car.available === true;

    document.title = `${fullName} - DrivePK`;
    mainImage.src = image;
    mainImage.alt = fullName;

    const thumbs = document.querySelector('.car-thumbs');
    if (thumbs) {
      thumbs.innerHTML = images.map((src, index) => `
        <div class="car-thumb ${index === 0 ? 'active' : ''}" data-src="${escapeHTML(src)}">
          <img src="${escapeHTML(src)}" alt="${escapeHTML(fullName)} view ${index + 1}">
        </div>
      `).join('');

      thumbs.querySelectorAll('.car-thumb').forEach(thumb => {
        thumb.addEventListener('click', () => {
          changeImage(thumb.dataset.src, thumb);
        });
      });
    }

    const brand = document.querySelector('.car-detail-brand');
    if (brand) brand.textContent = `${car.brand} - ${car.type}`;

    const title = document.querySelector('.car-detail-title');
    if (title) title.textContent = String(car.model || '').toUpperCase();

    const status = document.querySelector('.car-detail-title + .badge');
    if (status) {
      status.textContent = available ? 'Free Now' : 'Booked';
      status.className = `badge ${available ? 'badge-available' : 'badge-booked'}`;
      status.style.marginBottom = '20px';
      status.style.display = 'inline-block';
    }

    const specValues = document.querySelectorAll('.detail-spec-value');
    const specs = ['1.6L', car.fuel_type, car.transmission, car.seats, car.year, 'A/C'];
    specValues.forEach((item, index) => {
      item.textContent = specs[index] || '-';
    });

    const description = document.querySelector('.car-description');
    if (description) {
      description.textContent = `${fullName} is available through DrivePK with ${car.seats} seats, ${car.fuel_type} fuel and ${car.transmission} transmission. Owner: ${car.owner_name || 'DrivePK admin'}. Submit a request and the owner will approve it before the car is marked booked.`;
    }

    const speedLabel = document.querySelector('.speedometer-label');
    if (speedLabel) speedLabel.textContent = Math.round(Number(car.price_per_day || 0) / 1000) + 'K';

    const sidebarPrice = document.querySelector('.booking-sidebar-price');
    if (sidebarPrice) sidebarPrice.innerHTML = `${formatPKR(car.price_per_day)} <span>/ day</span>`;

    const dailyRate = document.querySelector('.booking-sidebar .price-line strong');
    if (dailyRate) dailyRate.textContent = formatPKR(car.price_per_day);

    if (bookLink) {
      bookLink.href = available ? `booking.html?car=${encodeURIComponent(car.id)}` : 'contact.html';
      bookLink.textContent = available ? 'Request This Car' : 'Currently Booked';
      bookLink.className = available ? 'btn-gold' : 'btn-outline';
      bookLink.style.width = '100%';
      bookLink.style.textAlign = 'center';
      bookLink.style.display = 'block';
      bookLink.style.padding = '16px';
    }

    calculateDetailPrice();
  }

  if (pickupDate) pickupDate.addEventListener('change', calculateDetailPrice);
  if (dropoffDate) dropoffDate.addEventListener('change', calculateDetailPrice);
  window.calculatePrice = calculateDetailPrice;

  fetch('php/cars.php', { cache: 'no-store' })
    .then(response => response.json())
    .then(result => {
      const cars = Array.isArray(result.cars) ? result.cars : [];
      const car = cars.find(item => String(item.id) === String(carId));
      if (car) renderCar(car);
    })
    .catch(() => {});
})();
