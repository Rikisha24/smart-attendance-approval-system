/* ============================================================
   track-status.js  —  CONNECTED TO BACKEND
   Handles: fetching student's leave requests and rendering
            workflow timeline
   API: GET /api/student/get_requests.php
   ============================================================ */

// Holds requests fetched from backend: { "#SA-2024": {...}, ... }
let trackData = {};

// Status -> step index mapping (which step is "current")
const STATUS_STEP = {
  pending_mentor:      1,   // Mentor OK is current
  mentor_approved:     2,   // HOD OK is current
  hod_approved:        4,   // Proof Up is current
  proof_uploaded:      5,   // Verified is current
  mentor_verified:     5,   // still in Verified stage (HOD pending)
  hod_verified:        6,   // Att. Updated is current
  
  rejected:           -1    // special case
};

const STEP_LABELS = [
  { label: 'Submitted',    icon: '📋' },
  { label: 'Mentor OK',    icon: '✅' },
  { label: 'HOD OK',       icon: '🏛️' },
  { label: 'Provisional',  icon: '🎫' },
  { label: 'Proof Up',     icon: '📸' },
  { label: 'Verified',     icon: '🔍' },
  
];

// -----------------------------------------------------------------
// loadStudentRequests()  —  GET /api/student/get_requests.php
// Populates the request list + renders the first request's timeline
// -----------------------------------------------------------------
async function loadStudentRequests() {
  try {
    const res  = await fetch(`${API}/student/get_requests.php`, { credentials: 'include' });
    const json = await res.json();
    if (!json.success) return;

    trackData = {};
    const listEl = document.querySelector('#page-track-status .card, .track-request-list')
                   ?.querySelector('div')?.parentElement
                 || document.querySelector('.track-request-list');

    // Find the container holding the request items (siblings of .track-req-item)
    const sample = document.querySelector('.track-req-item');
    const container = sample ? sample.parentElement : null;

    const requests = json.data.requests;

    if (container) {
      if (!requests.length) {
        container.innerHTML = `<p style="color:var(--text-muted);padding:12px;">No leave requests yet</p>`;
      } else {
        container.innerHTML = requests.map((r, idx) => `
          <div onclick="selectTrack(this,'${r.req_code}')" class="track-req-item"
               style="padding:12px 14px;border-radius:8px;cursor:pointer;
                      border:${idx === 0 ? '2px solid var(--blue)' : '1px solid var(--border)'};
                      background:${idx === 0 ? 'rgba(37,99,235,0.04)' : ''};">
            <strong>${r.req_code}</strong>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
              ${r.category} · ${r.leave_start_date}${r.leave_end_date !== r.leave_start_date ? ' to ' + r.leave_end_date : ''}
            </div>
            <span class="badge ${statusBadgeClass(r.status)}" style="margin-top:4px;display:inline-block;">
              ${statusLabel(r.status)}
            </span>
          </div>`).join('');
      }
    }

    // Build trackData from requests
    requests.forEach(r => {
      trackData[r.req_code] = buildSteps(r);
    });

    // Render first request's timeline
    if (requests.length) {
      renderTrack(requests[0].req_code);
    } else {
      document.getElementById('trackReqId').textContent = '—';
      document.getElementById('workflowViz').innerHTML = '';
      document.getElementById('trackTimeline').innerHTML =
        `<p style="color:var(--text-muted);text-align:center;padding:20px;">No requests to track yet. Apply for leave first.</p>`;
    }

    // Also populate the "Upload Proof" page dropdown with hod_approved requests
    populateProofRequestDropdown(requests);

    // Populate "Recent Requests" table on student dashboard (latest 5)
    populateRecentRequests(requests);

    // Populate "All Requests — Overview" table
    populateOverviewTable(requests);

  } catch (err) {
    console.error('Failed to load student requests', err);
  }
}

// -----------------------------------------------------------------
// statusLabel() / statusBadgeClass()
// -----------------------------------------------------------------
function statusLabel(status) {
  const map = {
    pending_mentor:     'Pending Mentor',
    mentor_approved:    'Pending HOD',
    hod_approved:       'Approved · Upload Proof',
    proof_uploaded:     'Proof Submitted',
    mentor_verified:    'Mentor Verified',
    hod_verified:       'HOD Verified',
    
    rejected:           'Rejected'
  };
  return map[status] || status;
}

function statusBadgeClass(status) {
  if (status === 'rejected') return 'rejected';
  if (status === 'HOD Verified') return 'approved';
  if (['hod_approved','mentor_verified','hod_verified'].includes(status)) return 'verified';
  return 'pending';
}

// -----------------------------------------------------------------
// buildSteps(request)  —  converts a request row into 7-step data
// -----------------------------------------------------------------
function buildSteps(r) {
  const currentStep = STATUS_STEP[r.status] ?? 0;
  const isRejected  = r.status === 'rejected';

  const steps = STEP_LABELS.map((s, i) => {
    let done = false, active = false, time = '—', detail = '—';

    if (isRejected) {
      // Show progress up to rejection point greyed, last completed step shown
      done = false;
      active = false;
    } else {
      done   = i < currentStep;
      active = i === currentStep && currentStep <= 6;
    }

    switch (i) {
      case 0:
        time = formatDate(r.created_at);
        detail = `Student submitted leave request (${r.category})`;
        break;
      case 1:
        detail = done ? 'Mentor approved the request'
               : active ? 'Awaiting mentor review'
               : '—';
        break;
      case 2:
        detail = done ? 'HOD gave approval'
               : active ? 'Awaiting HOD review and approval'
               : '—';
        break;
      case 3:
        detail = done ? `Provisional approval granted. Proof deadline: ${r.proof_deadline || '—'}`
               : active ? 'Awaiting provisional approval'
               : '—';
        break;
      case 4:
        detail = done ? 'Proof document uploaded'
               : active ? 'Upload your event proof now'
               : '—';
        break;
      case 5:
        detail = done ? 'Proof verified by mentor & HOD'
               : active ? 'Awaiting mentor/HOD proof verification'
               : '—';
        break;
      case 6:
        detail = done ? 'Attendance has been updated for all subjects'
               : active ? 'Finalizing attendance update'
               : '—';
        break;
    }

    return { label: s.label, icon: s.icon, done, active, time, detail };
  });

  if (isRejected) {
    steps.push({
      label: 'Rejected', icon: '✗', done: true, active: false,
      time: '', detail: r.rejection_reason || 'Request was rejected'
    });
  }

  return { steps, status: r.status };
}

function formatDate(dt) {
  if (!dt) return '—';
  return dt.split(' ')[0];
}

// -----------------------------------------------------------------
// renderTrack(reqCode)
// -----------------------------------------------------------------
function renderTrack(reqCode) {
  document.getElementById('trackReqId').textContent = reqCode;

  const data = trackData[reqCode];
  if (!data) return;

  const wf = document.getElementById('workflowViz');
  wf.innerHTML = data.steps.map((s, i) => `
    <div class="wf-step">
      <div class="wf-circle ${s.done ? 'done' : s.active ? 'current' : ''}">
        ${s.done ? '✓' : s.active ? '⏳' : s.icon}
      </div>
      <div class="wf-label">${s.label}</div>
    </div>
    ${i < data.steps.length - 1 ? '<div class="wf-arrow">→</div>' : ''}
  `).join('');

  const tl = document.getElementById('trackTimeline');
  tl.innerHTML = data.steps.map(s => `
    <div class="timeline-item">
      <div class="timeline-dot ${s.done ? 'done' : s.active ? 'active-step' : 'waiting'}">
        ${s.done ? '✓' : s.active ? '▶' : '○'}
      </div>
      <div class="timeline-content">
        <h4>
          ${s.label}
          ${s.time && s.time !== '—' ? `<span style="font-weight:400;color:var(--text-muted);font-size:12px;">· ${s.time}</span>` : ''}
        </h4>
        <p>${s.detail}</p>
        ${s.active ? '<span class="badge pending" style="margin-top:4px;">In Progress</span>' : ''}
        ${s.done   ? '<span class="badge approved" style="margin-top:4px;">Completed</span>'  : ''}
      </div>
    </div>
  `).join('');
}

// -----------------------------------------------------------------
// selectTrack(el, reqCode)
// -----------------------------------------------------------------
function selectTrack(el, reqCode) {
  document.querySelectorAll('.track-req-item').forEach(i => {
    i.style.border     = '1px solid var(--border)';
    i.style.background = '';
  });
  el.style.border     = '2px solid var(--blue)';
  el.style.background = 'rgba(37,99,235,0.04)';
  renderTrack(reqCode);
}

// -----------------------------------------------------------------
// populateRecentRequests(requests)
// Fills <tbody id="recentRequestsBody"> on the student dashboard
// -----------------------------------------------------------------
function populateRecentRequests(requests) {
  const tbody = document.getElementById('recentRequestsBody');
  if (!tbody) return;

  if (!requests.length) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No requests yet</td></tr>`;
    return;
  }

  const recent = requests.slice(0, 5);
  tbody.innerHTML = recent.map(r => `
    <tr>
      <td><strong>${r.req_code}</strong></td>
      <td>${formatDateRange(r.leave_start_date, r.leave_end_date)}</td>
      <td>${r.reason || r.category}</td>
      <td><span class="badge ${statusBadgeClass(r.status)}">${statusLabel(r.status)}</span></td>
    </tr>`).join('');
}

// -----------------------------------------------------------------
// populateOverviewTable(requests)
// Fills <tbody id="overviewBody"> — derives mentor/HOD/proof/
// attendance column badges from the single `status` field
// -----------------------------------------------------------------
function populateOverviewTable(requests) {
  const tbody = document.getElementById('overviewBody');
  if (!tbody) return;

  if (!requests.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">No requests yet</td></tr>`;
    return;
  }

  // Order of stages — index = how far along the request is
  const STAGES = ['pending_mentor','mentor_approved','hod_approved','proof_uploaded','mentor_verified','hod_verified'];

  tbody.innerHTML = requests.map(r => {
    const isRejected = r.status === 'rejected';
    const stageIdx   = STAGES.indexOf(r.status);

    const mentorBadge = isRejected
      ? badge('rejected', 'Rejected')
      : stageIdx >= 1 ? badge('approved', 'Approved') : badge('pending', 'Pending');

    const hodBadge = isRejected
      ? badge('pending', '—')
      : stageIdx >= 2 ? badge('approved', 'Approved')
      : stageIdx === 1 ? badge('pending', 'Pending')
      : badge('pending', '—');

    const proofBadge = isRejected
      ? badge('pending', '—')
      : stageIdx >= 3 ? badge('uploaded', 'Uploaded')
      : stageIdx === 2 ? badge('hod-pending', 'Awaiting')
      : badge('pending', '—');

    const attBadge = isRejected
      ? badge('pending', '—')
      : stageIdx >= 6 ? badge('approved', 'Updated')
      : stageIdx >= 3 ? badge('hod-pending', 'Pending')
      : badge('pending', '—');

    return `
      <tr>
        <td><strong>${r.req_code}</strong></td>
        <td>${formatDateRange(r.leave_start_date, r.leave_end_date)}</td>
        <td>${r.reason || r.category}</td>
        <td>${r.subject_count}</td>
        <td>${mentorBadge}</td>
        <td>${hodBadge}</td>
        <td>${proofBadge}</td>
        <td>${attBadge}</td>
      </tr>`;
  }).join('');
}

function badge(cls, label) {
  return `<span class="badge ${cls}">${label}</span>`;
}

// -----------------------------------------------------------------
// populateProofRequestDropdown(requests)
// Fills <select id="uploadReqId"> on the upload-proof page with
// requests that are 'hod_approved' (ready for proof upload)
// -----------------------------------------------------------------
function populateProofRequestDropdown(requests) {
  const select = document.getElementById('uploadReqId');
  if (!select) return;

  const eligible = requests.filter(r => r.status === 'hod_approved');

  if (!eligible.length) {
    select.innerHTML = `<option value="">No requests awaiting proof</option>`;
    return;
  }

  select.innerHTML = eligible.map(r =>
    `<option value="${r.req_id}">${r.req_code} — ${r.category} (${r.leave_start_date})</option>`
  ).join('');
}
