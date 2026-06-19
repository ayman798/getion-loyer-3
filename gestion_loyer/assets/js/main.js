/* assets/js/main.js — Global UI helpers */

// ── Tabs ──────────────────────────────────────────────────────
function initTabs() {
  document.querySelectorAll('.tabs').forEach(tabGroup => {
    tabGroup.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        const container = tabGroup.closest('.tab-container') || document;

        tabGroup.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        container.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        btn.classList.add('active');
        const panel = container.querySelector(`#tab-${target}`);
        if (panel) panel.classList.add('active');
      });
    });
  });
}

// ── Modals ────────────────────────────────────────────────────
function openModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) overlay.classList.add('open');
}

function closeModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) overlay.classList.remove('open');
}

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
  if (e.target.dataset.closeModal) {
    closeModal(e.target.dataset.closeModal);
  }
});

// ── File Upload Label ─────────────────────────────────────────
function initFileUploads() {
  document.querySelectorAll('.file-upload-area').forEach(area => {
    const input = area.querySelector('input[type="file"]');
    const label = area.querySelector('.file-label');
    if (!input || !label) return;

    area.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      if (input.files[0]) {
        label.textContent = '📎 ' + input.files[0].name;
      }
    });

    area.addEventListener('dragover', e => {
      e.preventDefault();
      area.style.borderColor = 'var(--orange)';
    });
    area.addEventListener('dragleave', () => {
      area.style.borderColor = '';
    });
    area.addEventListener('drop', e => {
      e.preventDefault();
      area.style.borderColor = '';
      if (e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files;
        label.textContent = '📎 ' + e.dataTransfer.files[0].name;
      }
    });
  });
}

// ── Auto Retenue from MF ──────────────────────────────────────
function initRetenueAuto() {
  const mfInput = document.getElementById('mf');
  const retenueSelect = document.getElementById('retenue');
  if (!mfInput || !retenueSelect) return;

  function update() {
    retenueSelect.value = mfInput.value.trim() !== '' ? '10' : '0';
  }

  mfInput.addEventListener('input', update);
  update();
}

// ── Flash auto-close ──────────────────────────────────────────
function initFlashes() {
  document.querySelectorAll('.alert[data-auto-close]').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });
}

// ── Date display in topbar ────────────────────────────────────
function updateTopbarDate() {
  const el = document.getElementById('topbar-date');
  if (!el) return;
  const now = new Date();
  const days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
  el.textContent = `${days[now.getDay()]} ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initFileUploads();
  initRetenueAuto();
  initFlashes();
  updateTopbarDate();
});
