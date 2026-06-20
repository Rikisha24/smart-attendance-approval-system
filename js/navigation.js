/* ============================================================
   navigation.js
   Handles: switching between pages, updating topbar title,
            loading backend data when pages become active
   ============================================================ */

const pageTitles = {
  'dashboard':        'Dashboard',
  'apply-leave':      'Apply for Leave',
  'upload-proof':     'Upload Proof',
  'track-status':     'Track Status',
  'mentor-dashboard': 'Mentor Dashboard',
  'hod-dashboard':    'HOD Dashboard',
  'notifications':    'Notifications'
};

function showPage(id) {
  // Hide all pages
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));

  // Show requested page
  const pg = document.getElementById('page-' + id);
  if (pg) pg.classList.add('active');

  // Update active state on nav links
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.toggle('active', n.dataset.page === id);
  });

  // Update topbar title
  document.getElementById('topbarTitle').textContent = pageTitles[id] || id;

  // ---- Load backend data when entering each page ----------
  if (id === 'track-status' || id === 'dashboard') {
    if (typeof loadStudentRequests === 'function') loadStudentRequests();
  }
  if (id === 'mentor-dashboard') {
    if (typeof loadMentorPending === 'function') loadMentorPending();
  }
  if (id === 'hod-dashboard') {
    if (typeof loadHODPending === 'function') loadHODPending();
  }
  if (id === 'notifications') {
    if (typeof loadNotifications === 'function') loadNotifications();
  }
}
