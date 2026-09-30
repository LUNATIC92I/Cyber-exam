/* ==========================================================================
   CYBER EXAM — UI interactions & animations (vanilla ES2020, no dependencies)
   ========================================================================== */
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const easeOut = (t) => 1 - Math.pow(1 - t, 3);

  document.documentElement.classList.remove('no-js');

  /** Animate a number from 0 to its target. */
  function countUp(el, target, { duration = 1400, decimals = 0, onUpdate } = {}) {
    if (reduceMotion) {
      el.textContent = target.toFixed(decimals);
      onUpdate && onUpdate(target);
      return;
    }
    const start = performance.now();
    const tick = (now) => {
      const p = Math.min(1, (now - start) / duration);
      const v = target * easeOut(p);
      el.textContent = v.toFixed(decimals);
      onUpdate && onUpdate(v);
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }
  window.CyberExam = { countUp };

  // ---------------------------------------------------------------- delays
  $$('[data-delay]').forEach((el) => el.style.setProperty('--delay', `${parseInt(el.dataset.delay, 10) || 0}ms`));

  // ---------------------------------------------------------------- header
  const header = $('[data-header]');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }
  const navToggle = $('[data-nav-toggle]');
  const navMenu = $('[data-nav-menu]');
  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      const open = navMenu.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(open));
    });
    $$('a', navMenu).forEach((a) => a.addEventListener('click', () => navMenu.classList.remove('is-open')));
  }

  // ---------------------------------------------------------------- toasts
  $$('[data-toast]').forEach((toast, i) => {
    const close = () => {
      toast.classList.add('is-leaving');
      setTimeout(() => toast.remove(), 350);
    };
    $('[data-toast-close]', toast)?.addEventListener('click', close);
    setTimeout(close, 6500 + i * 600);
  });

  // ---------------------------------------------------------------- reveal on scroll
  const animateInside = (root) => {
    $$('[data-count]', root).forEach((el) => {
      if (el.dataset.counted) return;
      el.dataset.counted = '1';
      countUp(el, parseFloat(el.dataset.count) || 0, { decimals: parseInt(el.dataset.decimals || '0', 10) });
    });
    $$('[data-width]', root).forEach((el) => {
      requestAnimationFrame(() => { el.style.width = `${Math.max(0, Math.min(100, parseFloat(el.dataset.width)))}%`; });
    });
    $$('[data-height]', root).forEach((el, i) => {
      setTimeout(() => { el.style.height = `${Math.max(2, Math.min(100, parseFloat(el.dataset.height)))}%`; }, i * 90);
    });
  };

  const revealables = $$('.reveal, .hero-preview, [data-cert-paper]');
  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        animateInside(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealables.forEach((el) => io.observe(el));
  } else {
    revealables.forEach((el) => { el.classList.add('is-visible'); animateInside(el); });
  }
  // Counters / meters that are not inside a revealable block
  $$('[data-count], [data-width], [data-height]').forEach((el) => {
    if (!el.closest('.reveal, .hero-preview, [data-cert-paper]')) animateInside(el.parentElement || el);
  });

  // ---------------------------------------------------------------- card glow & tilt
  if (window.matchMedia('(hover: hover)').matches && !reduceMotion) {
    $$('[data-tilt]').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        card.style.setProperty('--mx', `${x * 100}%`);
        card.style.setProperty('--my', `${y * 100}%`);
        card.style.transform = `translateY(-6px) perspective(900px) rotateX(${(0.5 - y) * 3}deg) rotateY(${(x - 0.5) * 4}deg)`;
      });
      card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
  } else {
    $$('[data-tilt]').forEach((card) => card.classList.add('show-topics'));
  }

  // ---------------------------------------------------------------- particles (discreet)
  $$('canvas[data-particles]').forEach((canvas) => {
    if (reduceMotion) return;
    const ctx = canvas.getContext('2d');
    let w = 0; let h = 0; let points = []; let raf = 0; let visible = true;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const resize = () => {
      const rect = canvas.getBoundingClientRect();
      w = rect.width; h = rect.height;
      canvas.width = w * dpr; canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const count = Math.min(70, Math.round((w * h) / 22000));
      points = Array.from({ length: count }, () => ({
        x: Math.random() * w, y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.18, vy: (Math.random() - 0.5) * 0.18,
        r: Math.random() * 1.3 + 0.4,
      }));
    };
    const draw = () => {
      ctx.clearRect(0, 0, w, h);
      for (let i = 0; i < points.length; i++) {
        const p = points[i];
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(103, 232, 249, 0.55)';
        ctx.fill();
        for (let j = i + 1; j < points.length; j++) {
          const q = points[j];
          const dx = p.x - q.x; const dy = p.y - q.y;
          const d2 = dx * dx + dy * dy;
          if (d2 < 13000) {
            ctx.strokeStyle = `rgba(47, 107, 255, ${0.16 * (1 - d2 / 13000)})`;
            ctx.lineWidth = 0.6;
            ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
          }
        }
      }
      if (visible) raf = requestAnimationFrame(draw);
    };
    resize();
    draw();
    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(resize, 200); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        cancelAnimationFrame(raf);
        if (visible) raf = requestAnimationFrame(draw);
      }).observe(canvas);
    }
    document.addEventListener('visibilitychange', () => {
      cancelAnimationFrame(raf);
      if (!document.hidden && visible) raf = requestAnimationFrame(draw);
    });
  });

  // ---------------------------------------------------------------- landing demo timer
  const demoTimer = $('[data-demo-timer]');
  if (demoTimer && !reduceMotion) {
    let s = 1 * 3600 + 37 * 60 + 42;
    setInterval(() => {
      s = s > 0 ? s - 1 : 7200;
      const f = (n) => String(n).padStart(2, '0');
      demoTimer.textContent = `${f(Math.floor(s / 3600))}:${f(Math.floor((s % 3600) / 60))}:${f(s % 60)}`;
    }, 1000);
  }

  // ---------------------------------------------------------------- forms
  $$('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePassword);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
  $$('[data-strength]').forEach((input) => {
    const bar = input.closest('.field')?.querySelector('[data-strength-bar]');
    if (!bar) return;
    input.addEventListener('input', () => {
      const v = input.value;
      let score = 0;
      if (v.length >= 10) score++;
      if (v.length >= 14) score++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const colors = ['#f43f5e', '#f43f5e', '#f59e0b', '#f59e0b', '#22d3ee', '#10b981'];
      bar.style.width = `${(score / 5) * 100}%`;
      bar.style.background = colors[score];
    });
  });
  $$('form[data-loading]').forEach((form) => {
    form.addEventListener('submit', () => {
      const btn = $('button[type="submit"]', form);
      btn && btn.classList.add('is-loading');
    });
  });

  // ---------------------------------------------------------------- confirm before starting an exam
  function confirmModal({ title, text, confirmLabel }) {
    return new Promise((resolve) => {
      const modal = document.createElement('div');
      modal.className = 'modal';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.innerHTML = '<div class="modal-card"><h2></h2><p class="text-muted"></p><div class="modal-actions">'
        + '<button type="button" class="btn btn-ghost" data-no>Cancel</button>'
        + '<button type="button" class="btn btn-primary" data-yes></button></div></div>';
      $('h2', modal).textContent = title;
      $('p', modal).textContent = text;
      $('[data-yes]', modal).textContent = confirmLabel;
      document.body.appendChild(modal);
      requestAnimationFrame(() => modal.classList.add('is-open'));
      const done = (v) => {
        modal.classList.remove('is-open');
        setTimeout(() => modal.remove(), 250);
        document.removeEventListener('keydown', onKey);
        resolve(v);
      };
      const onKey = (e) => { if (e.key === 'Escape') done(false); };
      document.addEventListener('keydown', onKey);
      $('[data-no]', modal).addEventListener('click', () => done(false));
      $('[data-yes]', modal).addEventListener('click', () => done(true));
      modal.addEventListener('click', (e) => { if (e.target === modal) done(false); });
      $('[data-yes]', modal).focus();
    });
  }
  window.CyberExam.confirmModal = confirmModal;

  $$('form[data-confirm-start]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      if (form.dataset.confirmed) return;
      e.preventDefault();
      const ok = await confirmModal({
        title: 'Start your exam now?',
        text: 'The timer starts immediately and keeps running on our servers, even if you close the page. Make sure you have a stable connection and enough uninterrupted time.',
        confirmLabel: 'Start exam',
      });
      if (ok) {
        form.dataset.confirmed = '1';
        $('button[type="submit"]', form)?.classList.add('is-loading');
        form.submit();
      }
    });
  });

  // ---------------------------------------------------------------- payment status polling
  const statusBox = $('[data-payment-status]');
  if (statusBox && statusBox.dataset.status === 'pending') {
    const show = (state) => $$('[data-state]', statusBox).forEach((el) => { el.hidden = el.dataset.state !== state; });
    let tries = 0;
    const poll = async () => {
      tries++;
      try {
        const res = await fetch(`${statusBox.dataset.endpoint}?payment=${encodeURIComponent(statusBox.dataset.paymentStatus)}`, {
          headers: { Accept: 'application/json' }, credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'paid') return show('paid');
        if (data.status === 'failed' || data.status === 'refunded') return show('failed');
      } catch (_) { /* network hiccup: retry */ }
      if (tries < 90) setTimeout(poll, tries < 10 ? 2000 : 5000);
    };
    setTimeout(poll, 1500);
  }

  // ---------------------------------------------------------------- result page
  const result = $('[data-result]');
  if (result) {
    const score = parseFloat(result.dataset.score) || 0;
    const ring = $('[data-ring]', result);
    const text = $('[data-score-text]', result);
    const verdict = $('[data-verdict]', result);
    const circ = parseFloat(ring.dataset.circ);
    const decimals = Number.isInteger(score) ? 0 : 1;
    setTimeout(() => {
      countUp(text, score, { duration: 1800, decimals });
      requestAnimationFrame(() => { ring.style.strokeDashoffset = String(circ * (1 - score / 100)); });
      setTimeout(() => {
        verdict.classList.add('is-visible');
        if (result.dataset.passed === '1') confetti($('[data-confetti]'));
      }, reduceMotion ? 0 : 1700);
    }, 350);
  }

  function confetti(canvas) {
    if (!canvas || reduceMotion) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = window.innerWidth;
    const h = canvas.height = window.innerHeight;
    const colors = ['#2f6bff', '#22d3ee', '#67e8f9', '#ffffff', '#10b981'];
    const parts = Array.from({ length: 110 }, () => ({
      x: w / 2 + (Math.random() - 0.5) * 200, y: h * 0.35,
      vx: (Math.random() - 0.5) * 11, vy: Math.random() * -11 - 3,
      s: Math.random() * 6 + 3, r: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3,
      c: colors[Math.floor(Math.random() * colors.length)],
    }));
    const start = performance.now();
    const frame = (now) => {
      const t = now - start;
      ctx.clearRect(0, 0, w, h);
      parts.forEach((p) => {
        p.vy += 0.28; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.r += p.vr;
        ctx.save();
        ctx.globalAlpha = Math.max(0, 1 - t / 3200);
        ctx.translate(p.x, p.y); ctx.rotate(p.r);
        ctx.fillStyle = p.c;
        ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2);
        ctx.restore();
      });
      if (t < 3300) requestAnimationFrame(frame); else canvas.remove();
    };
    requestAnimationFrame(frame);
  }
})();
