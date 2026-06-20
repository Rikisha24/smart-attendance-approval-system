/* ============================================================
   approvals.js  —  CONNECTED TO BACKEND
   Handles: mentor/HOD pending lists, approve/reject, verify proof,
            notifications
   API:
     GET  /api/mentor/get_pending.php
     POST /api/mentor/approve_reject.php
     POST /api/mentor/verify_proof.php
     GET  /api/hod/get_pending.php
     POST /api/hod/approve_reject.php
     POST /api/hod/verify_proof.php
     GET  /api/auth/notifications.php
   ============================================================ */

let rejectTarget = null;     // { req_id, role, kind: 'approval'|'proof' }

// -----------------------------------------------------------------
// loadMentorPending()  —  fills mentor dashboard tables
// -----------------------------------------------------------------
async function loadMentorPending() {
  try {
    const res  = await fetch(`${API}/mentor/get_pending.php`, { credentials: 'include' });
    const json = await res.json();
    if (!json.success) return;

    renderPendingTable('pending', json.data.pending_requests, 'mentor');
    renderProofTable('proof', json.data.proof_queue, 'mentor');
  } catch (err) {
    console.error('Failed to load mentor data', err);
  }
}

// -----------------------------------------------------------------
// loadHODPending()  —  fills HOD dashboard tables
// -----------------------------------------------------------------
async function loadHODPending() {
  try {
    const res  = await fetch(`${API}/hod/get_pending.php`, { credentials: 'include' });
    const json = await res.json();
    if (!json.success) return;

    renderPendingTable('pending', json.data.pending_requests, 'hod');
    renderProofTable('proof', json.data.proof_queue, 'hod');
  } catch (err) {
    console.error('Failed to load HOD data', err);
  }
}

// -----------------------------------------------------------------
// renderPendingTable(tableKey, rows, role)
// Looks for <tbody id="{role}PendingBody"> in the dashboard HTML
// -----------------------------------------------------------------
function renderPendingTable(tableKey, rows, role) {
  const bodyId  = role === 'mentor' ? 'mentorPendingBody' : 'hodPendingBody';
  const tbody   = document.getElementById(bodyId);
  if (!tbody) return;

  const badgeId = role === 'mentor' ? 'mentorPendingCount' : 'hodPendingCount';
  setText(badgeId, `${rows.length} Pending`);

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:var(--text-muted);">No pending requests</td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(r => `
    <tr data-req-id="${r.req_id}">
      <td><strong>${r.req_code}</strong></td>
      <td>${r.student_name}</td>
      <td>${r.usn}</td>
      <td>${formatDateRange(r.leave_start_date, r.leave_end_date)}</td>
      <td>${r.category}</td>
      <td>${r.subject_count}</td>
      <td><button class="btn btn-outline btn-sm" onclick="viewDoc('${r.permission_file}')">📄 View</button></td>
      <td class="flex-gap">
        <button class="btn btn-success btn-sm" onclick="approveReq(this,'${role}',${r.req_id})">✓ Approve</button>
        <button class="btn btn-danger btn-sm" onclick="rejectReq(this,'${role}',${r.req_id},'approval')">✗ Reject</button>
      </td>
    </tr>`).join('');
}

// -----------------------------------------------------------------
// renderProofTable(tableKey, rows, role)
// Looks for <tbody id="{role}ProofBody">
// -----------------------------------------------------------------
function renderProofTable(tableKey, rows, role) {
  const bodyId  = role === 'mentor' ? 'mentorProofBody' : 'hodProofBody';
  const tbody   = document.getElementById(bodyId);
  if (!tbody) return;

  const badgeId = role === 'mentor' ? 'mentorProofCount' : 'hodProofCount';
  setText(badgeId, `${rows.length} To Verify`);

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Nothing to verify</td></tr>`;
    return;
  }

  const verifyLabel = role === 'hod' ? '✓ Verify & Update' : '✓ Verify';

  tbody.innerHTML = rows.map(r => `
    <tr data-req-id="${r.req_id}">
      <td><strong>${r.req_code}</strong></td>
      <td>${r.student_name}${r.usn ? ' (' + r.usn + ')' : ''}</td>
      <td>${r.proof_type}</td>
      <td>${r.reason || ''}</td>
      <td>${r.uploaded_at ? r.uploaded_at.split(' ')[0] : ''}</td>
      <td><button class="btn btn-outline btn-sm" onclick="viewProof('${r.file_path}')">🖼 View Proof</button></td>
      <td class="flex-gap">
        <button class="btn btn-success btn-sm" onclick="verifyProof(this,'${role}',${r.req_id})">${verifyLabel}</button>
        <button class="btn btn-danger btn-sm" onclick="rejectReq(this,'${role}',${r.req_id},'proof')">✗ Reject</button>
      </td>
    </tr>`).join('');
}

// -----------------------------------------------------------------
// formatDateRange()
// -----------------------------------------------------------------
function formatDateRange(start, end) {
  if (!start) return '';
  if (start === end || !end) return start;
  return `${start} – ${end}`;
}

// -----------------------------------------------------------------
// approveReq(btn, role, reqId)  —  POST approve_reject.php { action: 'approve' }
// -----------------------------------------------------------------
async function approveReq(btn, role, reqId) {
  const url = role === 'mentor'
    ? `${API}/mentor/approve_reject.php`
    : `${API}/hod/approve_reject.php`;

  btn.disabled = true;
  btn.textContent = 'Processing…';

  try {
    const res  = await fetch(url, {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ req_id: reqId, action: 'approve' })
    });
    const json = await res.json();

    if (json.success) {
      const row = btn.closest('tr');
      const actionCell = row.querySelector('td:last-child');
      actionCell.innerHTML = `<span class="badge approved">${role === 'mentor' ? 'Mentor Approved' : 'HOD Approved'}</span>`;
      showToast(json.message, '✅');
      setTimeout(() => row.remove(), 1200);
    } else {
      showToast(json.message || 'Action failed', '❌');
      btn.disabled = false;
      btn.textContent = '✓ Approve';
    }
  } catch (err) {
    showToast('Server error. Check XAMPP is running.', '🔌');
    btn.disabled = false;
    btn.textContent = '✓ Approve';
  }
}

// -----------------------------------------------------------------
// verifyProof(btn, role, reqId)  —  POST verify_proof.php { action: 'verify' }
// -----------------------------------------------------------------
async function verifyProof(btn, role, reqId) {
  const url = role === 'mentor'
    ? `${API}/mentor/verify_proof.php`
    : `${API}/hod/verify_proof.php`;

  btn.disabled = true;
  btn.textContent = 'Processing…';

  try {
    const res  = await fetch(url, {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ req_id: reqId, action: 'verify' })
    });
    const json = await res.json();

    if (json.success) {
      const row = btn.closest('tr');
      const actionCell = row.querySelector('td:last-child');
      actionCell.innerHTML = `<span class="badge verified">Verified ✓</span>`;
      showToast(json.message, '🎉');
      setTimeout(() => row.remove(), 1200);
    } else {
      showToast(json.message || 'Action failed', '❌');
      btn.disabled = false;
    }
  } catch (err) {
    showToast('Server error. Check XAMPP is running.', '🔌');
    btn.disabled = false;
  }
}

// -----------------------------------------------------------------
// rejectReq(btn, role, reqId, kind)  —  opens reject modal
// -----------------------------------------------------------------
function rejectReq(btn, role, reqId, kind) {
  rejectTarget = { row: btn.closest('tr'), role, reqId, kind };
  document.getElementById('rejectModal').classList.add('open');
}

// -----------------------------------------------------------------
// confirmReject()  —  POST approve_reject.php / verify_proof.php { action: 'reject' }
// -----------------------------------------------------------------
async function confirmReject() {
  if (!rejectTarget) { closeModal('rejectModal'); return; }

  const reasonEl = document.getElementById('rejectReason');
  const reason   = reasonEl ? reasonEl.value.trim() : '';

  if (!reason) {
    showToast('Please enter a rejection reason', '⚠️');
    return;
  }

  const { row, role, reqId, kind } = rejectTarget;

  let url;
  if (kind === 'proof') {
    url = role === 'mentor' ? `${API}/mentor/verify_proof.php` : `${API}/hod/verify_proof.php`;
  } else {
    url = role === 'mentor' ? `${API}/mentor/approve_reject.php` : `${API}/hod/approve_reject.php`;
  }

  try {
    const res  = await fetch(url, {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ req_id: reqId, action: 'reject', rejection_reason: reason })
    });
    const json = await res.json();

    closeModal('rejectModal');
    if (reasonEl) reasonEl.value = '';

    if (json.success) {
      const actionCell = row.querySelector('td:last-child');
      actionCell.innerHTML = '<span class="badge rejected">Rejected</span>';
      showToast(json.message || 'Request rejected. Student has been notified.', '✗');
      setTimeout(() => row.remove(), 1200);
    } else {
      showToast(json.message || 'Action failed', '❌');
    }
  } catch (err) {
    closeModal('rejectModal');
    showToast('Server error. Check XAMPP is running.', '🔌');
  }

  rejectTarget = null;
}

// -----------------------------------------------------------------
// viewDoc(filePath)  —  opens permission letter in modal
// -----------------------------------------------------------------
function viewDoc(filePath) {
  const modal = document.getElementById('docModal');
  const body  = modal.querySelector('.modal-body, [id$="DocBody"]') || modal;
  const url   = `http://localhost/smartattend-backend/uploads/${filePath}`;

  const frame = modal.querySelector('img, iframe, #docPreview');
  if (frame) {
    if (filePath && filePath.toLowerCase().endsWith('.pdf')) {
      frame.outerHTML = `<iframe id="docPreview" src="${url}" style="width:100%;height:400px;border:1px solid var(--border);"></iframe>`;
    } else {
      frame.outerHTML = `<img id="docPreview" src="${url}" style="max-width:100%;border-radius:8px;">`;
    }
  }
  modal.classList.add('open');
}

// -----------------------------------------------------------------
// viewProof(filePath)  —  opens proof document in modal
// -----------------------------------------------------------------
function viewProof(filePath) {
  const modal = document.getElementById('proofModal');
  const url   = `http://localhost/smartattend-backend/uploads/${filePath}`;

  const frame = modal.querySelector('img, iframe, #proofPreview');
  if (frame) {
    if (filePath && filePath.toLowerCase().endsWith('.pdf')) {
      frame.outerHTML = `<iframe id="proofPreview" src="${url}" style="width:100%;height:400px;border:1px solid var(--border);"></iframe>`;
    } else {
      frame.outerHTML = `<img id="proofPreview" src="${url}" style="max-width:100%;border-radius:8px;">`;
    }
  }
  modal.classList.add('open');
}

// -----------------------------------------------------------------
// loadNotifications()  —  GET /api/auth/notifications.php
// -----------------------------------------------------------------
async function loadNotifications() {
  try {
    const res  = await fetch(`${API}/auth/notifications.php`, { credentials: 'include' });
    const json = await res.json();
    if (!json.success) return;

    // Update bell badge
    const bell = document.querySelector('.topbar-notif .badge, .topbar-notif');
    if (bell) {
      let badge = bell.querySelector('.badge');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge';
        bell.appendChild(badge);
      }
      badge.textContent = json.data.unread_count;
      badge.style.display = json.data.unread_count > 0 ? '' : 'none';
    }

    // Render notification list
    const list = document.getElementById('notificationsList');
    if (list) {
      if (!json.data.notifications.length) {
        list.innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:20px;">No notifications yet</p>`;
      } else {
        list.innerHTML = json.data.notifications.map(n => `
          <div class="notification-item ${n.is_read ? '' : 'unread'}" style="padding:12px 14px;border-bottom:1px solid var(--border);${!n.is_read ? 'background:rgba(37,99,235,0.04);' : ''}">
            <p style="margin:0;font-weight:${n.is_read ? '400' : '600'};">${n.message}</p>
            <span style="font-size:12px;color:var(--text-muted);">${n.created_at}</span>
          </div>`).join('');
      }
    }
  } catch (err) {
    console.error('Failed to load notifications', err);
  }
}

// -----------------------------------------------------------------
// markAllNotificationsRead()  —  POST /api/auth/notifications.php { mark_all: true }
// -----------------------------------------------------------------
async function markAllNotificationsRead() {
  try {
    await fetch(`${API}/auth/notifications.php`, {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ mark_all: true })
    });
    loadNotifications();
  } catch (err) {
    console.error('Failed to mark notifications read', err);
  }
}
