(function initCustomerBookings() {
  const body = document.getElementById('customerBookingsBody');
  if (!body) return;

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

  function statusBadge(status) {
    if (status === 'confirmed' || status === 'active') {
      return '<span class="badge badge-booked">Booked</span>';
    }
    if (status === 'completed') {
      return '<span class="badge badge-available">Completed</span>';
    }
    if (status === 'cancelled') {
      return '<span class="badge badge-booked">Cancelled</span>';
    }
    if (status === 'rejected') {
      return '<span class="badge badge-booked">Rejected</span>';
    }
    return '<span class="badge" style="background:#1a2a3a; color:#64b5f6;">Pending Owner</span>';
  }

  function renderBookings(bookings) {
    if (!bookings.length) {
      body.innerHTML = '<tr><td colspan="7">No booking requests yet.</td></tr>';
      return;
    }

    body.innerHTML = bookings.map(booking => `
      <tr>
        <td>${escapeHTML(booking.booking_ref)}</td>
        <td>
          <strong>${escapeHTML(booking.brand)} ${escapeHTML(booking.model)}</strong>
          <div style="font-size:12px; color:var(--gray);">${escapeHTML(booking.year)} - Owner: ${escapeHTML(booking.owner_name || 'DrivePK')}</div>
        </td>
        <td>${formatDate(booking.pickup_date)}</td>
        <td>${formatDate(booking.dropoff_date)}</td>
        <td style="color:var(--gold);">${formatPKR(booking.total_price)}</td>
        <td>
          ${statusBadge(booking.status)}
          ${booking.owner_note ? `<div style="font-size:12px; color:var(--gray); margin-top:4px;">${escapeHTML(booking.owner_note)}</div>` : ''}
        </td>
        <td><div class="table-actions"><a class="action-btn view" href="car-detail.html?id=${escapeHTML(booking.car_id)}">View Car</a></div></td>
      </tr>
    `).join('');
  }

  async function loadCustomerBookings() {
    body.innerHTML = '<tr><td colspan="7">Loading your bookings...</td></tr>';

    try {
      const statusResponse = await fetch('php/auth.php?action=status', { cache: 'no-store' });
      const status = await statusResponse.json();

      if (!status.logged_in || status.role !== 'customer') return;

      const response = await fetch('php/booking.php?action=list_my_bookings', { cache: 'no-store' });
      const result = await response.json();

      if (!result.success) {
        body.innerHTML = `<tr><td colspan="7">${escapeHTML(result.message || 'Could not load bookings.')}</td></tr>`;
        return;
      }

      renderBookings(result.bookings || []);
    } catch (error) {
      body.innerHTML = '<tr><td colspan="7">Could not load bookings. Check Apache, MySQL and login session.</td></tr>';
    }
  }

  loadCustomerBookings();
})();
