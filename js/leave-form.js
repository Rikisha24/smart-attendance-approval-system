/* ============================================================
   leave-form.js  —  CONNECTED TO BACKEND
   Handles: subject rows, file upload, leave submission, proof upload
   API: POST /api/student/submit_leave.php
        POST /api/student/upload_proof.php
   ============================================================ */

let subjIdx = 1;

// Holds the actual File objects selected by user
let permissionFile = null;
let proofFile      = null;

// -----------------------------------------------------------------
// addSubjectRow()
// -----------------------------------------------------------------
function addSubjectRow() {
  subjIdx++;
  const div = document.createElement('div');
  div.className = 'subject-row';
  div.id = 'subj-' + subjIdx;
  div.innerHTML = `
    <span class="subject-row-num">Subject ${subjIdx}</span>
    <button class="remove-subject" onclick="removeSubj(this)">✕</button>
    <div class="form-row three" style="margin-top:10px;">
      <div class="form-group-light">
        <label>Subject Name *</label>
        <input type="text" class="subj-name" placeholder="e.g. Big Data Analytics">
      </div>
      <div class="form-group-light">
        <label>Faculty Email *</label>
        <input type="email" class="subj-email" placeholder="faculty@college.edu">
      </div>
      <div class="form-group-light">
        <label>Class Date *</label>
        <input type="date" class="subj-date">
      </div>
    </div>`;
  document.getElementById('subjectList').appendChild(div);
  showToast('Subject row added', '📚');
}

// -----------------------------------------------------------------
// removeSubj()
// -----------------------------------------------------------------
function removeSubj(btn) {
  btn.closest('.subject-row').remove();
}

// -----------------------------------------------------------------
// handleFileSelect(input, type)
// -----------------------------------------------------------------
function handleFileSelect(input, type) {
  const file = input.files[0];
  if (!file) return;

  if (type === 'perm') {
    permissionFile = file;
    const zone = input.closest('.form-section, .upload-section')
                      ?.querySelector('.file-upload-zone')
                 || input.parentElement.querySelector('.file-upload-zone');
    if (zone) {
      zone.innerHTML = `
        <div class="upload-icon">✅</div>
        <p style="color:var(--success);font-weight:600;">${file.name}</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
          ${(file.size / 1024).toFixed(0)} KB · Click to replace</p>`;
    }
  } else if (type === 'proof') {
    proofFile = file;
    const zone = document.querySelector('#page-upload-proof .file-upload-zone');
    if (zone) {
      zone.innerHTML = `
        <div class="upload-icon">✅</div>
        <p style="color:var(--success);font-weight:600;">${file.name}</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
          ${(file.size / 1024).toFixed(0)} KB · Click to replace</p>`;
    }
  }
}

// -----------------------------------------------------------------
// simulateUpload(zone, type)
// Opens the real file picker.
// IMPORTANT: always call with type argument: simulateUpload(this,'perm')
// -----------------------------------------------------------------
function simulateUpload(zone, type) {
  const input = document.createElement('input');
  input.type   = 'file';
  input.accept = '.pdf,.jpg,.jpeg,.png';
  input.onchange = () => {
    const file = input.files[0];
    if (!file) return;

    if (type === 'proof') {
      proofFile = file;
    } else {
      permissionFile = file;
    }

    zone.innerHTML = `
      <div class="upload-icon">✅</div>
      <p style="color:var(--success);font-weight:600;">${file.name}</p>
      <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
        ${(file.size / 1024).toFixed(0)} KB · Click to replace</p>`;
  };
  input.click();
}

// -----------------------------------------------------------------
// submitLeave()  —  POST /api/student/submit_leave.php
// -----------------------------------------------------------------
async function submitLeave() {
  // Collect subject rows
  const rows     = document.querySelectorAll('.subject-row');
  const subjects = [];

  for (const row of rows) {
    const name  = row.querySelector('.subj-name')?.value?.trim();
    const email = row.querySelector('.subj-email')?.value?.trim();
    const date  = row.querySelector('.subj-date')?.value;
    if (!name || !email || !date) {
      showToast('Fill in all subject details', '⚠️');
      return;
    }
    subjects.push({ subject_name: name, faculty_email: email, class_date: date });
  }

  const startDate   = document.getElementById('leaveStart')?.value;
  const endDate     = document.getElementById('leaveEnd')?.value;
  const reason      = document.getElementById('leaveReason')?.value?.trim();
  const category    = document.getElementById('leaveCategory')?.value;
  const description = document.getElementById('leaveDescription')?.value?.trim() || '';

  if (!startDate || !endDate) {
    showToast('Please select leave start and end dates', '⚠️');
    return;
  }
  if (!reason) {
    showToast('Please enter a reason / event name', '⚠️');
    return;
  }
  if (new Date(endDate) < new Date(startDate)) {
    showToast('End date cannot be before start date', '⚠️');
    return;
  }
  if (subjects.length === 0) {
    showToast('Add at least one subject', '⚠️');
    return;
  }
  if (!permissionFile) {
    showToast('Please upload a permission letter', '⚠️');
    return;
  }

  // Build FormData (multipart — needed for file upload)
  const fd = new FormData();
  fd.append('leave_start_date', startDate);
  fd.append('leave_end_date',   endDate);
  fd.append('reason',           reason);
  fd.append('category',         category);
  fd.append('description',      description);
  fd.append('subjects',         JSON.stringify(subjects));
  fd.append('permission_file',  permissionFile);

  const btn = document.querySelector('#page-apply-leave .btn-primary');
  if (btn) { btn.textContent = 'Submitting…'; btn.disabled = true; }

  try {
    const res  = await fetch(`${API}/student/submit_leave.php`, {
      method:      'POST',
      credentials: 'include',
      body:        fd
    });
    const json = await res.json();

    if (json.success) {
      showToast(`Request ${json.data.req_code} submitted! Awaiting mentor review.`, '📋');
      permissionFile = null;
      loadStudentRequests();
      showPage('track-status');
    } else {
      showToast(json.message || 'Submission failed', '❌');
    }
  } catch (err) {
    showToast('Server error. Check XAMPP is running.', '🔌');
  } finally {
    if (btn) { btn.textContent = 'Submit Request →'; btn.disabled = false; }
  }
}

// -----------------------------------------------------------------
// uploadProof()  —  POST /api/student/upload_proof.php
// -----------------------------------------------------------------
async function uploadProof() {
  const reqSelect = document.getElementById('uploadReqId');
  const reqId     = reqSelect ? reqSelect.value : null;

  if (!reqId) {
    showToast('Select a request to upload proof for', '⚠️');
    return;
  }
  if (!proofFile) {
    showToast('Please choose a proof file first', '⚠️');
    return;
  }

  const proofType = document.getElementById('proofType')?.value || 'certificate';
  const proofDesc = document.getElementById('proofDescription')?.value?.trim() || '';

  const fd = new FormData();
  fd.append('req_id',      reqId);
  fd.append('proof_type',  proofType);
  fd.append('description', proofDesc);
  fd.append('proof_file',  proofFile);

  const btn = document.querySelector('#page-upload-proof .btn-primary');
  if (btn) { btn.textContent = 'Uploading…'; btn.disabled = true; }

  try {
    const res  = await fetch(`${API}/student/upload_proof.php`, {
      method:      'POST',
      credentials: 'include',
      body:        fd
    });
    const json = await res.json();

    if (json.success) {
      showToast('Proof uploaded! Mentor will verify soon.', '📸');
      proofFile = null;
      loadStudentRequests();
    } else {
      showToast(json.message || 'Upload failed', '❌');
    }
  } catch (err) {
    showToast('Server error. Check XAMPP is running.', '🔌');
  } finally {
    if (btn) { btn.textContent = 'Submit Proof →'; btn.disabled = false; }
  }
}
