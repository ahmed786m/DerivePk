
(function initFilters() {

  const carsGrid  = document.getElementById('carsGrid');
  const carCount  = document.getElementById('carCount');
  const priceRange = document.getElementById('priceRange');
  const priceLabel = document.getElementById('priceLabel');

  if (!carsGrid) return; // not on cars page

  const allCards = Array.from(carsGrid.querySelectorAll('.car-card'));

  let maxPrice     = parseInt(priceRange ? priceRange.value : 25000);
  let activeTypes  = new Set();   // sedan, suv, hatchback, luxury
  let activeFuels  = new Set();   // petrol, diesel, hybrid
  let activeTrans  = new Set();   // auto, manual, cvt
  let availFilter  = 'all';       // 'all' | 'available'
  let sortMode     = 'default';

  function formatPKR(n) {
    return 'Rs. ' + Number(n).toLocaleString('en-PK');
  }

  function getCardPrice(card) {
    return parseInt(card.dataset.price) || 0;
  }

  function getCardStatus(card) {
    return (card.dataset.status || '').toLowerCase();
  }

  function getCardCategory(card) {
    return (card.dataset.category || '').toLowerCase();
  }

  function getCardFuel(card) {
    const specs = card.querySelectorAll('.car-spec');
    for (const s of specs) {
      const t = s.textContent.toLowerCase();
      if (t.includes('petrol'))  return 'petrol';
      if (t.includes('diesel'))  return 'diesel';
      if (t.includes('hybrid'))  return 'hybrid';
    }
    return '';
  }

  function getCardTrans(card) {
    const specs = card.querySelectorAll('.car-spec');
    for (const s of specs) {
      const t = s.textContent.toLowerCase();
      if (t.includes('auto'))   return 'auto';
      if (t.includes('manual')) return 'manual';
      if (t.includes('cvt'))    return 'cvt';
    }
    return '';
  }

  function applyFilters() {
    let visible = allCards.filter(card => {
      const price    = getCardPrice(card);
      const category = getCardCategory(card);
      const fuel     = getCardFuel(card);
      const trans    = getCardTrans(card);
      const status   = getCardStatus(card);

      if (price > maxPrice) return false;
      if (activeTypes.size  && !activeTypes.has(category))  return false;
      if (activeFuels.size  && !activeFuels.has(fuel))      return false;
      if (activeTrans.size  && !activeTrans.has(trans))      return false;
      if (availFilter === 'available' && status !== 'available') return false;

      return true;
    });

    if (sortMode === 'price-asc')  visible.sort((a,b) => getCardPrice(a) - getCardPrice(b));
    if (sortMode === 'price-desc') visible.sort((a,b) => getCardPrice(b) - getCardPrice(a));
    if (sortMode === 'name') {
      visible.sort((a,b) => {
        const nameA = a.querySelector('.car-name')?.textContent || '';
        const nameB = b.querySelector('.car-name')?.textContent || '';
        return nameA.localeCompare(nameB);
      });
    }

    allCards.forEach(c => c.style.display = 'none');
    visible.forEach(c => {
      c.style.display = '';
      carsGrid.appendChild(c); // re-insert in sorted order
    });

    if (carCount) carCount.textContent = visible.length;

    let noResults = document.getElementById('noResults');
    if (visible.length === 0) {
      if (!noResults) {
        noResults = document.createElement('div');
        noResults.id = 'noResults';
        noResults.style.cssText = 'grid-column:1/-1; text-align:center; padding:60px 20px; color:var(--gray); font-size:16px;';
        noResults.innerHTML = '<div style="font-size:40px; margin-bottom:16px;">🚗</div><div>No cars match your filters.</div><div style="margin-top:8px; font-size:13px;">Try adjusting or resetting the filters.</div>';
        carsGrid.appendChild(noResults);
      }
    } else if (noResults) {
      noResults.remove();
    }
  }

  if (priceRange && priceLabel) {
    priceRange.addEventListener('input', () => {
      maxPrice = parseInt(priceRange.value);
      priceLabel.textContent = formatPKR(maxPrice);
    });
  }

  document.querySelectorAll('input[name="type"]').forEach(cb => {
    cb.addEventListener('change', () => {
      cb.checked ? activeTypes.add(cb.value) : activeTypes.delete(cb.value);
    });
  });

  document.querySelectorAll('input[id="petrol"], input[id="diesel"], input[id="hybrid"]').forEach(cb => {
    cb.addEventListener('change', () => {
      cb.checked ? activeFuels.add(cb.id) : activeFuels.delete(cb.id);
    });
  });

  document.querySelectorAll('input[id="auto"], input[id="manual"], input[id="cvt"]').forEach(cb => {
    cb.addEventListener('change', () => {
      const map = { auto: 'auto', manual: 'manual', cvt: 'cvt' };
      cb.checked ? activeTrans.add(map[cb.id]) : activeTrans.delete(map[cb.id]);
    });
  });

  document.querySelectorAll('input[name="avail"]').forEach(radio => {
    radio.addEventListener('change', () => {
      availFilter = radio.value;
    });
  });

  window.applyFilters = function() {
    applyFilters();
  };

  window.resetFilters = function() {
    document.querySelectorAll('.sidebar input[type="checkbox"]').forEach(cb => cb.checked = false);
    const allRadio = document.querySelector('input[name="avail"][value="all"]');
    if (allRadio) allRadio.checked = true;
    if (priceRange) {
      priceRange.value = priceRange.max;
      priceLabel.textContent = formatPKR(priceRange.max);
    }
    maxPrice = parseInt(priceRange ? priceRange.max : 25000);
    activeTypes.clear();
    activeFuels.clear();
    activeTrans.clear();
    availFilter = 'all';
    sortMode    = 'default';

    applyFilters();
  };

  window.sortCars = function(value) {
    sortMode = value;
    applyFilters();
  };

  window.updatePrice = function(value) {
    maxPrice = parseInt(value);
    if (priceLabel) priceLabel.textContent = formatPKR(value);
  };

  (function readURLParams() {
    const params = new URLSearchParams(window.location.search);

    const typeParam = params.get('type');
    if (typeParam) {
      const cb = document.querySelector(`input[name="type"][value="${typeParam}"]`);
      if (cb) { cb.checked = true; activeTypes.add(typeParam); }
    }

    if (typeParam) applyFilters();
  })();

  if (carCount) carCount.textContent = allCards.length;

})();