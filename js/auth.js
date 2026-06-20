/* ============================================================
   auth.js  —  CONNECTED TO BACKEND
   Handles: role selection, login (real API), logout, sidebar
   API: POST /api/auth/login   GET /api/auth/logout
   ============================================================ */

const API = 'http://localhost/smartattend-backend/api';

// Stores logged-in user data (set after login)
let currentUser  = null;
let currentRole  = 'student';

// -----------------------------------------------------------------
// selectRole()
// -----------------------------------------------------------------
function selectRole(role, btn) {
  currentRole = role;
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');

  // Update placeholder hint
  const field = document.getElementById('loginUser');
  if (role === 'student') field.placeholder = 'e.g. 4NM21CS042';
  else                    field.placeholder = 'faculty@college.edu';
}

// -----------------------------------------------------------------
// doLogin()  —  calls POST /api/auth/login
// -----------------------------------------------------------------
async function doLogin() {
  const loginVal = document.getElementById('loginUser').value.trim();
  const passVal  = document.getElementById('loginPass').value;

  if (!loginVal || !passVal) {
    showToast('Please enter your login ID and password', '⚠️');
    return;
  }

  // Show loading state on button
  const btn = document.querySelector('.btn-login');
  const origText = btn.textContent;
  btn.textContent = 'Signing in…';
  btn.disabled = true;

  try {
    const res = await fetch(`${API}/auth/login.php`, {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'include',                 // send/receive PHP session cookie
      body: JSON.stringify({
        usn_or_email: loginVal,
        password:     passVal,
        role:         currentRole
      })
    });

    const json = await res.json();

    if (!json.success) {
      showToast(json.message || 'Login failed', '❌');
      btn.textContent = origText;
      btn.disabled = false;
      return;
    }

    // ---- Login succeeded ----------------------------------------
    currentUser = json.data.user;

    // Clear password field for security
    document.getElementById('loginPass').value = '';

    // Switch to app shell
    document.getElementById('loginScreen').classList.remove('active');
    document.getElementById('appScreen').classList.add('active');

    // Fill sidebar with real user data
    document.getElementById('sidebarName').textContent = currentUser.name;
    document.getElementById('sidebarRole').textContent =
      (currentRole === 'student')
        ? `Student · ${currentUser.dept_name || 'CSE'} Sem ${currentUser.semester || ''}`
        : `${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)} · ${currentUser.dept_name || 'CSE'}`;

    const av = document.getElementById('sidebarAvatar');
    av.textContent = currentUser.name.charAt(0).toUpperCase();
    av.className   = 'role-avatar ' + currentRole;

    // Fill Apply Leave page's read-only student details (if student)
    if (currentRole === 'student') {
      setVal('applyStudentName', currentUser.name);
      setVal('applyStudentUSN',  currentUser.usn);
      setVal('applyMentorName',  currentUser.mentor_name);
      setVal('applyMentorEmail', currentUser.mentor_email);
      setVal('applyDept',        currentUser.dept_name);
      setVal('applySemester',    currentUser.semester);
      setVal('applySection',     currentUser.section);
    }

    // Build nav and open first page
    const navItems = buildNav(currentRole);
    renderNav(navItems);
    const firstPage = navItems.find(n => n.id);
    showPage(firstPage.id);

    document.getElementById('topbarDate').textContent =
      new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });

    const topbarSub = document.getElementById('topbarSub');
    if (topbarSub) {
      if (currentRole === 'student') {
        topbarSub.textContent = `${currentUser.dept_name || ''} · Semester ${currentUser.semester || ''} · Section ${currentUser.section || ''}`;
      } else {
        topbarSub.textContent = currentUser.dept_name || '';
      }
    }

    // Load dashboard data for this role
    loadDashboardData();

  } catch (err) {
    showToast('Cannot reach server. Check XAMPP is running.', '🔌');
    btn.textContent = origText;
    btn.disabled = false;
  }
}

// -----------------------------------------------------------------
// doLogout()  —  calls GET /api/auth/logout.php
// -----------------------------------------------------------------
async function doLogout() {
  try {
    await fetch(`${API}/auth/logout.php`, { credentials: 'include' });
  } catch (_) { /* ignore network errors on logout */ }

  currentUser = null;
  document.getElementById('appScreen').classList.remove('active');
  document.getElementById('loginScreen').classList.add('active');
  document.getElementById('loginUser').value = '';
}

// -----------------------------------------------------------------
// buildNav()  —  returns nav items array for each role
// -----------------------------------------------------------------
function buildNav(role) {
  const navMap = {
    student: [
      { id: 'dashboard',     label: 'Dashboard',       icon: '🏠' },
      { section: 'Leave Management' },
      { id: 'apply-leave',   label: 'Apply for Leave', icon: '📝' },
      { id: 'upload-proof',  label: 'Upload Proof',    icon: '📎' },
      { id: 'track-status',  label: 'Track Status',    icon: '🔍' },
      { section: 'Other' },
      { id: 'notifications', label: 'Notifications',   icon: '🔔' },
    ],
    mentor: [
      { id: 'mentor-dashboard', label: 'Dashboard',          icon: '🏠' },
      { section: 'Actions' },
      { id: 'mentor-dashboard', label: 'Pending Requests',   icon: '⏳' },
      { id: 'mentor-dashboard', label: 'Proof Verification', icon: '📸' },
      { section: 'Other' },
      { id: 'notifications',    label: 'Notifications',      icon: '🔔' },
    ],
    hod: [
      { id: 'hod-dashboard', label: 'Dashboard',         icon: '🏠' },
      { section: 'Actions' },
      { id: 'hod-dashboard', label: 'Pending Approvals', icon: '⏳' },
      { id: 'hod-dashboard', label: 'Final Verification', icon: '🏛️' },
      { section: 'Other' },
      { id: 'notifications', label: 'Notifications',     icon: '🔔' },
    ]
  };
  return navMap[role] || navMap.student;
}

// -----------------------------------------------------------------
// renderNav()
// -----------------------------------------------------------------
function renderNav(items) {
  const nav = document.getElementById('sidebarNav');
  nav.innerHTML = items.map(it => {
    if (it.section) return `<div class="nav-section-label">${it.section}</div>`;
    return `
      <div class="nav-item" onclick="showPage('${it.id}')" data-page="${it.id}">
        <span class="nav-icon">${it.icon}</span>
        ${it.label}
        ${it.badge ? `<span class="badge">${it.badge}</span>` : ''}
      </div>`;
  }).join('');
}

// -----------------------------------------------------------------
// loadDashboardData()  —  called after login; loads real data
// -----------------------------------------------------------------
function loadDashboardData() {
  loadDashboardStats();              // common stat cards, all roles
  if (currentRole === 'student') {
    loadStudentRequests();          // from track-status.js
    loadNotifications();            // from approvals.js
  } else if (currentRole === 'mentor') {
    loadMentorPending();            // from approvals.js
    loadNotifications();
  } else if (currentRole === 'hod') {
    loadHODPending();               // from approvals.js
    loadNotifications();
  }
}

// -----------------------------------------------------------------
// loadDashboardStats()  —  GET /api/common/stats.php
// Fills the stat-card numbers on each role's dashboard
// -----------------------------------------------------------------
async function loadDashboardStats() {
  try {
    const res  = await fetch(`${API}/common/stats.php`, { credentials: 'include' });
    const json = await res.json();
    if (!json.success) return;
    const d = json.data;

    if (currentRole === 'student') {
      setText('statTotalRequests',    d.total_requests);
      setText('statApproved',         d.approved);
      setText('statPending',          d.pending);
      setText('statProofRequired',    d.proof_required);
      setText('statClassesProtected', d.classes_protected);
    } else if (currentRole === 'mentor') {
      setText('mentorStatPending',  d.pending_reviews);
      setText('mentorStatApproved', d.approved_total);
      setText('mentorStatRejected', d.rejected);
      setText('mentorStatProofs',   d.proofs_to_verify);
    } else if (currentRole === 'hod') {
      setText('hodStatAwaiting', d.awaiting_review);
      setText('hodStatApproved', d.approved_total);
      setText('hodStatProofs',   d.proofs_to_verify);
      setText('hodStatRate',     d.approval_rate + '%');
    }
  } catch (err) {
    console.error('Failed to load dashboard stats', err);
  }
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function setVal(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value ?? '';
}
