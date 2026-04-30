
const hamburger  = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');

if (hamburger && mobileMenu) {
  hamburger.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('open');
    hamburger.classList.toggle('open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen);
  });

  mobileMenu.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
    });
  });

  document.addEventListener('click', (e) => {
    if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
    }
  });
}

const scrollTopBtn = document.getElementById('scrollTop');

if (scrollTopBtn) {
  window.addEventListener('scroll', () => {
    scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
  });

  scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

const filterBtns = document.querySelectorAll('.filter-btn');
const homeGrid   = document.getElementById('carsGrid');

if (filterBtns.length && homeGrid) {
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // update active button
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;

      homeGrid.querySelectorAll('.car-card').forEach(card => {
        const match = filter === 'all' || card.dataset.category === filter;
        card.style.display = match ? '' : 'none';
      });
    });
  });
}

const today = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(input => {
  if (!input.min) input.min = today;
});

/* ---------- Alert helper (used by booking.js and contact forms) ----------
   Usage: showAlert('alertBox', 'Your message here', 'success' | 'error')
   Your HTML just needs: <div id="alertBox"></div>
---------------------------------------------------------------- */
window.showAlert = function(boxId, message, type = 'success') {
  const box = document.getElementById(boxId);
  if (!box) return;
  box.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  // auto-dismiss after 5 seconds
  setTimeout(() => { box.innerHTML = ''; }, 5000);
};

(function showURLFlashMessage() {
  const params = new URLSearchParams(window.location.search);
  const box = document.getElementById('alertBox');
  if (!box) return;

  const error = params.get('error');
  const success = params.get('success');
  if (error) showAlert('alertBox', error, 'error');
  if (success) showAlert('alertBox', success, 'success');
})();

(function markActiveLink() {
  const page = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (href === page) {
      document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
    }
  });
})();

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

const fadeTargets = document.querySelectorAll(
  '.car-card, .feature-item, .step-item, .testimonial-card, .stat-card'
);

if (fadeTargets.length && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('fade-in');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  fadeTargets.forEach(el => {
    el.style.opacity  = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });
}

document.addEventListener('animationend', () => {}, false); // keep listener alive

document.documentElement.style.setProperty('--fade-ready', '1');

(function applyFadeClass() {
  const style = document.createElement('style');
  style.textContent = `.fade-in { opacity: 1 !important; transform: translateY(0) !important; }`;
  document.head.appendChild(style);
})();
