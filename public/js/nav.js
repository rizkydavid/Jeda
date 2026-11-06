/* Navigasi mobile: buka/tutup sidebar, Esc, klik latar, dan fokus keyboard. */
(() => {
  'use strict';
  const nav = document.getElementById('siteNav');
  const toggle = document.getElementById('navToggle');
  const backdrop = document.getElementById('navBackdrop');
  if (!nav || !toggle) return;

  const closeBtn = nav.querySelector('.nav-close');
  const mq = window.matchMedia('(max-width: 900px)');
  const isOpen = () => nav.classList.contains('is-open');

  function setOpen(open, restoreFocus = true) {
    nav.classList.toggle('is-open', open);
    backdrop?.classList.toggle('is-open', open);
    document.documentElement.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    if (open) (nav.querySelector('a.on') || nav.querySelector('a'))?.focus();
    else if (restoreFocus) toggle.focus();
  }

  toggle.addEventListener('click', () => setOpen(!isOpen()));
  closeBtn?.addEventListener('click', () => setOpen(false));
  backdrop?.addEventListener('click', () => setOpen(false));
  nav.addEventListener('click', e => { if (e.target.closest('a')) setOpen(false, false); });

  document.addEventListener('keydown', e => {
    if (!isOpen()) return;
    if (e.key === 'Escape') { setOpen(false); return; }
    if (e.key !== 'Tab') return;
    // Fokus tetap berputar di dalam sidebar selama terbuka.
    const items = [...nav.querySelectorAll('a[href], button')].filter(el => el.offsetParent !== null);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  // Kalau layar melebar ke desktop saat menu terbuka, rapikan keadaannya.
  const onChange = () => { if (!mq.matches && isOpen()) setOpen(false, false); };
  mq.addEventListener ? mq.addEventListener('change', onChange) : mq.addListener(onChange);
})();