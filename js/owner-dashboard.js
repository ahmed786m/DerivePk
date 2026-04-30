(function initOwnerDashboard() {
  const approvedBody = document.getElementById('approvedOwnerCarsBody');
  const requestsBody = document.getElementById('ownerRequestsStatusBody');
  const bookingRequestsBody = document.getElementById('ownerBookingRequestsBody');
  const pendingBookingCount = document.getElementById('ownerPendingBookingCount');

  if (!approvedBody || !requestsBody) return;

  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]
    ));
  }

  function formatPKR(value) {
    return 'Rs. ' + Number(value || 0).toLocaleString('en-PK');
  }

  function formatDate(value) {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return escapeHTML(value);
    return date.toLocaleDateString('en-PK', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function galleryCount(value, fallbackImage) {
    let images = [];
    if (Array.isArray(value)) {
      images = value;
    } else if (value) {
      try {
        const parsed = JSON.parse(value);
        images = Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        images = String(value).split(/[\r\n,]+/);
      }
    }
    if (!images.length && fallbackImage) images = [fallbackImage];
    return images.filter(Boolean).length;
  }

  function statusBadge(status) {
    if (status === 'approved') return '<span class="badge badge-available">Approved</span>';
    if (status === 'rejected') return '<span class="badge badge-booked">Rejected</span>';
    if (status === 'available') return '<span class="badge badge-available">Live</span>';
    if (status === 'booked') return '<span class="badge badge-booked">Hidden</span>';
    if (status === 'confirmed' || status === 'active') return '<span class="badge badge-booked">Booked</span>';
    if (status === 'completed') return '<span class="badge badge-available">Completed</span>';
    if (status === 'cancelled') return '<span class="badge badge-booked">Cancelled</span>';
    return '<span class="badge" style="background:#1a2a3a; color:#64b5f6;">Pending</span>';
  }

  function renderApprovedCars(cars) {
    if (!cars.length) {
      approvedBody.innerHTML = '<tr><td colspan="5">No approved owner cars are live yet.</td></tr>';
      return;
    }

    approvedBody.innerHTML = cars.map(car => `
      <tr>
        <td>
          <strong>${escapeHTML(car.brand)} ${escapeHTML(car.model)}</strong>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(car.year)} - ${galleryCount(car.gallery_images, car.image)} photo(s)</div>
        </td>
        <td>${escapeHTML(car.type)} / ${escapeHTML(car.fuel_type)} / ${escapeHTML(car.transmission)} / ${escapeHTML(car.seats)} seats</td>
        <td style="color:var(--gold);">${formatPKR(car.price_per_day)}</td>
        <td>${statusBadge(Number(car.available) === 1 ? 'available' : 'booked')}</td>
        <td>${formatDate(car.created_at)}</td>
      </tr>
    `).join('');
  }

  function renderRequests(requests) {
    if (!requests.length) {
      requestsBody.innerHTML = '<tr><td colspan="5">No car requests submitted yet.</td></tr>';
      return;
    }

    requestsBody.innerHTML = requests.map(request => `
      <tr>
        <td>
          <strong>${escapeHTML(request.brand)} ${escapeHTML(request.model)}</strong>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(request.year)} - ${escapeHTML(request.city || '-')}</div>
          <div style="font-size:12px; color:var(--gray);">${galleryCount(request.gallery_images, request.image)} photo(s) uploaded</div>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(request.description || '')}</div>
        </td>
        <td>${escapeHTML(request.type)} / ${escapeHTML(request.fuel_type)} / ${escapeHTML(request.transmission)} / ${escapeHTML(request.seats)} seats</td>
        <td style="color:var(--gold);">${formatPKR(request.price_per_day)}</td>
        <td>
          ${statusBadge(request.status)}
          <div style="font-size:12px; color:var(--gray); margin-top:4px;">Submitted ${formatDate(request.created_at)}</div>
        </td>
        <td>
          <div style="font-size:13px; color:var(--light-gray);">${escapeHTML(request.admin_note || 'Waiting for admin review.')}</div>
          ${request.reviewed_at ? `<div style="font-size:12px; color:var(--gray); margin-top:4px;">Reviewed ${formatDate(request.reviewed_at)}</div>` : ''}
        </td>
      </tr>
    `).join('');
  }

  function bookingDecisionActions(booking) {
    if (booking.status === 'pending') {
      return `
        <form action="php/owner.php" method="POST" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
          <input type="hidden" name="action" value="approve_booking">
          <input type="hidden" name="booking_id" value="${escapeHTML(booking.id)}">
          <input type="text" name="owner_note" placeholder="Approval note" style="min-width:150px; flex:1; background:var(--black); border:1px solid var(--border); color:var(--white); padding:8px;">
          <button class="action-btn edit" type="submit">Approve</button>
        </form>
        <form action="php/owner.php" method="POST" style="display:flex; gap:8px; flex-wrap:wrap;">
          <input type="hidden" name="action" value="reject_booking">
          <input type="hidden" name="booking_id" value="${escapeHTML(booking.id)}">
          <input type="text" name="owner_note" placeholder="Reject reason" style="min-width:150px; flex:1; background:var(--black); border:1px solid var(--border); color:var(--white); padding:8px;">
          <button class="action-btn delete" type="submit">Reject</button>
        </form>
      `;
    }

    if (booking.status === 'confirmed' || booking.status === 'active') {
      return `
        <form action="php/owner.php" method="POST" style="display:flex; gap:8px; flex-wrap:wrap;">
          <input type="hidden" name="action" value="mark_booking_free">
          <input type="hidden" name="booking_id" value="${escapeHTML(booking.id)}">
          <input type="text" name="owner_note" placeholder="Completion note" style="min-width:150px; flex:1; background:var(--black); border:1px solid var(--border); color:var(--white); padding:8px;">
          <button class="action-btn view" type="submit">Mark Free</button>
        </form>
      `;
    }

    return `<div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.owner_note || 'Reviewed')}</div>`;
  }

  function renderOwnerBookings(bookings) {
    if (!bookingRequestsBody) return;

    const pendingCount = bookings.filter(booking => booking.status === 'pending').length;
    if (pendingBookingCount) {
      pendingBookingCount.textContent = `${pendingCount} Pending`;
    }

    if (pendingCount > 0 && !sessionStorage.getItem('drivepkOwnerBookingNotice')) {
      sessionStorage.setItem('drivepkOwnerBookingNotice', 'shown');
      if (typeof showAlert === 'function') {
        showAlert('alertBox', `You have ${pendingCount} customer booking request${pendingCount === 1 ? '' : 's'} waiting for approval.`, 'success');
      }
    }

    if (!bookings.length) {
      bookingRequestsBody.innerHTML = '<tr><td colspan="6">No customer booking requests yet.</td></tr>';
      return;
    }

    bookingRequestsBody.innerHTML = bookings.map(booking => `
      <tr>
        <td>
          <strong>${escapeHTML(booking.full_name)}</strong>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.email)}</div>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.phone)}</div>
          <div style="font-size:12px; color:var(--gray);">CNIC: ${escapeHTML(booking.cnic)}</div>
        </td>
        <td>
          <strong>${escapeHTML(booking.brand)} ${escapeHTML(booking.model)}</strong>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.year)} - ${escapeHTML(booking.type)}</div>
          <div style="font-size:12px; color:var(--gray);">${Number(booking.available) === 1 ? 'Free' : 'Booked'}</div>
        </td>
        <td>
          <strong>${escapeHTML(booking.booking_ref)}</strong>
          <div style="font-size:12px; color:var(--gray);">${formatDate(booking.pickup_date)} to ${formatDate(booking.dropoff_date)}</div>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.total_days)} day(s) - ${escapeHTML(booking.pickup_city)}</div>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.notes || '')}</div>
        </td>
        <td style="color:var(--gold);">${formatPKR(booking.total_price)}</td>
        <td>${statusBadge(booking.status)}</td>
        <td>${bookingDecisionActions(booking)}</td>
      </tr>
    `).join('');
  }

  window.loadOwnerBookingRequests = async function() {
    if (!bookingRequestsBody) return;
    bookingRequestsBody.innerHTML = '<tr><td colspan="6">Loading booking requests...</td></tr>';

    try {
      const statusResponse = await fetch('php/auth.php?action=status', { cache: 'no-store' });
      const status = await statusResponse.json();

      if (!status.logged_in || status.role !== 'owner') return;

      const response = await fetch('php/owner.php?action=list_booking_requests', { cache: 'no-store' });
      const result = await response.json();

      renderOwnerBookings(result.bookings || []);
    } catch (error) {
      bookingRequestsBody.innerHTML = '<tr><td colspan="6">Could not load booking requests. Check Apache, MySQL and login session.</td></tr>';
    }
  };

  window.loadOwnerCarStatus = async function() {
    approvedBody.innerHTML = '<tr><td colspan="5">Loading approved cars...</td></tr>';
    requestsBody.innerHTML = '<tr><td colspan="5">Loading requests...</td></tr>';

    try {
      const statusResponse = await fetch('php/auth.php?action=status', { cache: 'no-store' });
      const status = await statusResponse.json();

      if (!status.logged_in || status.role !== 'owner') return;

      const response = await fetch('php/owner.php?action=list_my_cars', { cache: 'no-store' });
      const result = await response.json();

      renderApprovedCars(result.approved_cars || []);
      renderRequests(result.requests || []);
      window.loadOwnerBookingRequests();
    } catch (error) {
      approvedBody.innerHTML = '<tr><td colspan="5">Could not load approved cars.</td></tr>';
      requestsBody.innerHTML = '<tr><td colspan="5">Could not load requests. Check Apache, MySQL and login session.</td></tr>';
    }
  };

  window.loadOwnerCarStatus();
  window.loadOwnerBookingRequests();
})();
