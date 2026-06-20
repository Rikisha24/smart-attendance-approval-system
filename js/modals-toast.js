/* ============================================================
   modals-toast.js
   Handles: modal open/close, document/proof preview popups,
            toast notification display
   ============================================================ */

// -----------------------------------------------------------------
// viewDoc(filePath)
// Opens the permission letter preview modal
// Called from approvals.js renderPendingTable with real file path
// -----------------------------------------------------------------
function viewDoc(filePath) {
  const modal   = document.getElementById('docModal');
  const preview = document.getElementById('docPreview');

  if (filePath && preview) {
    const url = `http://localhost/smartattend-backend/uploads/${filePath}`;
    if (filePath.toLowerCase().endsWith('.pdf')) {
      preview.innerHTML = `<iframe src="${url}" style="width:100%;height:420px;border:1px solid var(--border);border-radius:8px;"></iframe>`;
    } else {
      preview.innerHTML = `<img src="${url}" style="max-width:100%;border-radius:8px;" onerror="this.outerHTML='<p style=color:var(--text-muted)>Could not load document.</p>'">`;
    }
  }
  modal.classList.add('open');
}

// -----------------------------------------------------------------
// viewProof(filePath)
// Opens the uploaded proof preview modal
// Called from approvals.js renderProofTable with real file path
// -----------------------------------------------------------------
function viewProof(filePath) {
  const modal   = document.getElementById('proofModal');
  const preview = document.getElementById('proofPreview');

  if (filePath && preview) {
    const url = `http://localhost/smartattend-backend/uploads/${filePath}`;
    if (filePath.toLowerCase().endsWith('.pdf')) {
      preview.innerHTML = `<iframe src="${url}" style="width:100%;height:420px;border:1px solid var(--border);border-radius:8px;"></iframe>`;
    } else {
      preview.innerHTML = `<img src="${url}" style="max-width:100%;border-radius:8px;" onerror="this.outerHTML='<p style=color:var(--text-muted)>Could not load proof.</p>'">`;
    }
  }
  modal.classList.add('open');
}

// -----------------------------------------------------------------
// closeModal(id)
// -----------------------------------------------------------------
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// -----------------------------------------------------------------
// showToast(msg, icon)
// -----------------------------------------------------------------
function showToast(msg, icon = '✅') {
  const toast = document.getElementById('toast');
  document.getElementById('toastMsg').textContent  = msg;
  document.getElementById('toastIcon').textContent = icon;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}
