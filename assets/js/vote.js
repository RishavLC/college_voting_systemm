/* ============================================================
   HDC VOTES — shared UI helpers
   vote.php (and other pages) call these: openModal, closeModal,
   showToast, fireConfetti. Pure frontend, no backend calls here.
   ============================================================ */

/** Open a .modal-backdrop by id. */
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('open');
  document.body.style.overflow = 'hidden';
}

/** Close a .modal-backdrop by id. */
function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('open');
  document.body.style.overflow = '';
}

// Click on the dark backdrop (not the modal card itself) closes it.
document.addEventListener('click', function (e) {
  if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Esc closes whichever modal is open.
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(function (el) {
      el.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
});

/**
 * Show a small toast message in the top-right corner.
 * Creates the .toast-stack container on first use.
 */
function showToast(message, duration) {
  duration = duration || 3200;
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  stack.appendChild(toast);

  setTimeout(function () {
    toast.style.transition = 'opacity .25s ease, transform .25s ease';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-6px)';
    setTimeout(function () { toast.remove(); }, 250);
  }, duration);
}

/**
 * Lightweight confetti burst over a container element. No external
 * library — just a handful of absolutely-positioned divs that fall
 * and fade. Good enough for a "vote recorded" celebration moment.
 */
function fireConfetti(container) {
  if (!container) return;
  const colors = ['#B98A2E', '#D9AE55', '#14213D', '#2F7A4F', '#7C8DC7'];
  const rect = container.getBoundingClientRect();
  const layer = document.createElement('div');
  layer.style.cssText = 'position:fixed;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:9999;';
  document.body.appendChild(layer);

  const originX = rect.left + rect.width / 2;
  const originY = rect.top + Math.min(80, rect.height * 0.2);

  for (let i = 0; i < 60; i++) {
    const piece = document.createElement('span');
    const size = 6 + Math.random() * 6;
    const color = colors[Math.floor(Math.random() * colors.length)];
    const dx = (Math.random() - 0.5) * 500;
    const dy = 300 + Math.random() * 300;
    const rot = Math.random() * 720 - 360;
    const delay = Math.random() * 150;
    const dur = 900 + Math.random() * 700;

    piece.style.cssText =
      'position:absolute;left:' + originX + 'px;top:' + originY + 'px;' +
      'width:' + size + 'px;height:' + size * 0.6 + 'px;background:' + color + ';' +
      'border-radius:2px;opacity:0.95;';
    layer.appendChild(piece);

    piece.animate(
      [
        { transform: 'translate(0,0) rotate(0deg)', opacity: 1 },
        { transform: 'translate(' + dx + 'px,' + dy + 'px) rotate(' + rot + 'deg)', opacity: 0 }
      ],
      { duration: dur, delay: delay, easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'forwards' }
    );
  }

  setTimeout(function () { layer.remove(); }, 2200);
}