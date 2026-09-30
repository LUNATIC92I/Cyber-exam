/* ==========================================================================
   CYBER EXAM — Exam room client
   The server is authoritative for time, questions and answers. This script
   only renders state, saves answers and keeps the countdown in sync.
   ========================================================================== */
(() => {
  'use strict';

  const app = document.getElementById('exam-app');
  if (!app) return;

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const api = app.dataset.api;
  const attempt = app.dataset.attempt;
  const csrf = $('meta[name="csrf-token"]').content;
  const flagKey = `cex-flags-${attempt}`;

  const el = {
    loading: $('[data-loading]', app), exam: $('[data-exam]', app), stage: $('[data-stage]', app),
    number: $('[data-q-number]', app), category: $('[data-q-category]', app), difficulty: $('[data-q-difficulty]', app),
    text: $('[data-q-text]', app), options: $('[data-options]', app), prev: $('[data-prev]', app), next: $('[data-next]', app),
    flag: $('[data-flag]', app), palette: $('[data-palette]', app), answered: $('[data-answered]', app),
    flagged: $('[data-flagged]', app), finish: $('[data-finish]', app), side: $('[data-side]', app),
    saveState: $('[data-save-state]', app), saveText: $('[data-save-text]', app),
    timer: $('[data-timer]'), timerText: $('[data-timer-text]'), progress: $('[data-progress]'),
    paletteToggle: $('[data-palette-toggle]'),
  };

  let questions = [];
  let index = 0;
  let deadline = 0;
  let submitting = false;
  let pendingSaves = 0;
  let flags = new Set();
  try { flags = new Set(JSON.parse(localStorage.getItem(flagKey) || '[]')); } catch (_) { /* storage unavailable */ }

  // ------------------------------------------------------------------ network
  async function request(action, body) {
    const opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = csrf;
      opts.body = JSON.stringify({ attempt, ...body });
    }
    const url = `${api}?action=${action}${body ? '' : `&attempt=${encodeURIComponent(attempt)}`}`;
    const res = await fetch(url, opts);
    let data = {};
    try { data = await res.json(); } catch (_) { /* non-JSON */ }
    if (data.redirect && (res.status === 410 || data.finished)) {
      window.location.href = data.redirect;
      throw new Error('redirecting');
    }
    if (res.status === 401) {
      alert('Your session has expired. Please sign in again — your saved answers and timer are kept on the server.');
      window.location.href = app.dataset.api.replace(/api\/exam\.php$/, 'login.php');
      throw new Error('unauthenticated');
    }
    if (!res.ok) throw Object.assign(new Error(data.error || `HTTP ${res.status}`), { status: res.status });
    return data;
  }

  // ------------------------------------------------------------------ timer
  const pad = (n) => String(n).padStart(2, '0');
  function renderTimer() {
    const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
    el.timerText.textContent = `${pad(Math.floor(left / 3600))}:${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
    el.timer.classList.toggle('is-warning', left <= 600 && left > 120);
    el.timer.classList.toggle('is-critical', left <= 120);
    if (left <= 0 && !submitting) timeUp();
  }
  function setRemaining(seconds) {
    deadline = Date.now() + seconds * 1000;
    renderTimer();
  }
  async function sync() {
    try {
      const data = await request('sync');
      if (data.redirect) { window.location.href = data.redirect; return; }
      setRemaining(data.remaining);
    } catch (_) { /* retry on next tick */ }
  }

  // ------------------------------------------------------------------ rendering
  function renderPalette() {
    el.palette.innerHTML = '';
    const frag = document.createDocumentFragment();
    questions.forEach((q, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = String(q.position);
      b.setAttribute('aria-label', `Question ${q.position}${q.selected ? ', answered' : ''}${flags.has(q.position) ? ', flagged' : ''}`);
      b.className = [q.selected ? 'is-answered' : '', flags.has(q.position) ? 'is-flagged' : '', i === index ? 'is-current' : ''].join(' ').trim();
      b.addEventListener('click', () => { go(i); closeSide(); });
      frag.appendChild(b);
    });
    el.palette.appendChild(frag);
    const answered = questions.filter((q) => q.selected).length;
    el.answered.textContent = String(answered);
    el.flagged.textContent = String(flags.size);
    el.progress.style.width = `${(answered / questions.length) * 100}%`;
  }

  function renderQuestion() {
    const q = questions[index];
    el.number.textContent = String(q.position);
    el.category.textContent = q.category;
    el.difficulty.textContent = q.difficulty.charAt(0).toUpperCase() + q.difficulty.slice(1);
    el.text.textContent = q.text;
    el.options.innerHTML = '';
    q.options.forEach((opt) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = `option${q.selected === opt.key ? ' is-selected' : ''}`;
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', String(q.selected === opt.key));
      b.dataset.key = opt.key;
      const k = document.createElement('span');
      k.className = 'option-key';
      k.textContent = opt.key;
      const t = document.createElement('span');
      t.textContent = opt.text;
      b.append(k, t);
      b.addEventListener('click', () => choose(opt.key));
      el.options.appendChild(b);
    });
    el.prev.disabled = index === 0;
    const last = index === questions.length - 1;
    el.next.innerHTML = last ? 'Review &amp; finish' : 'Next <svg class="icon icon-arrow" aria-hidden="true"><use href="' + iconHref('arrow-right') + '"></use></svg>';
    const flagged = flags.has(q.position);
    el.flag.classList.toggle('is-active', flagged);
    el.flag.setAttribute('aria-pressed', String(flagged));
    $('span', el.flag).textContent = flagged ? 'Flagged' : 'Flag';
    renderPalette();
  }
  function iconHref(name) {
    const any = $('use');
    return any ? any.getAttribute('href').replace(/#.*$/, `#${name}`) : `#${name}`;
  }

  function go(i, direction) {
    if (i < 0 || i >= questions.length || i === index) return;
    const dir = direction || (i > index ? 'next' : 'prev');
    if (reduceMotion) { index = i; renderQuestion(); return; }
    el.stage.classList.remove('is-entering-next', 'is-entering-prev');
    el.stage.classList.add(`is-leaving-${dir}`);
    setTimeout(() => {
      index = i;
      renderQuestion();
      el.stage.classList.remove('is-leaving-next', 'is-leaving-prev');
      void el.stage.offsetWidth;
      el.stage.classList.add(`is-entering-${dir}`);
    }, 200);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ------------------------------------------------------------------ answers
  function setSaveState(state, text) {
    el.saveState.className = `save-state is-${state}`;
    el.saveText.textContent = text;
  }

  async function choose(key) {
    if (submitting) return;
    const q = questions[index];
    const newValue = q.selected === key ? null : key; // clicking again clears the answer
    const previous = q.selected;
    q.selected = newValue;
    $$('.option', el.options).forEach((b) => {
      const sel = b.dataset.key === newValue;
      b.classList.toggle('is-selected', sel);
      b.setAttribute('aria-checked', String(sel));
    });
    renderPalette();
    await save(q, newValue, previous);
  }

  async function save(q, value, previous, attemptNo = 1) {
    pendingSaves++;
    setSaveState('saving', 'Saving…');
    try {
      const data = await request('answer', { position: q.position, choice: value });
      setRemaining(data.remaining);
      pendingSaves--;
      if (pendingSaves === 0) setSaveState('saved', 'Saved');
    } catch (err) {
      pendingSaves--;
      if (err.message === 'redirecting' || err.message === 'unauthenticated') return;
      if ((!err.status || err.status >= 500 || err.status === 429) && attemptNo < 4) {
        setSaveState('saving', 'Retrying…');
        setTimeout(() => save(q, value, previous, attemptNo + 1), 1000 * attemptNo);
        return;
      }
      q.selected = previous;
      if (questions[index] === q) renderQuestion(); else renderPalette();
      setSaveState('error', err.message || 'Not saved');
    }
  }

  function toggleFlag() {
    const pos = questions[index].position;
    if (flags.has(pos)) flags.delete(pos); else flags.add(pos);
    try { localStorage.setItem(flagKey, JSON.stringify([...flags])); } catch (_) { /* ignore */ }
    renderQuestion();
  }

  // ------------------------------------------------------------------ modals
  function openModal(name) {
    const m = $(`[data-modal="${name}"]`);
    m.hidden = false;
    requestAnimationFrame(() => m.classList.add('is-open'));
    return m;
  }
  function closeModal(m) {
    m.classList.remove('is-open');
    setTimeout(() => { m.hidden = true; }, 250);
  }
  $$('[data-modal-close]').forEach((b) => b.addEventListener('click', () => closeModal(b.closest('.modal'))));

  function openFinish() {
    const unanswered = questions.filter((q) => !q.selected).length;
    $('[data-finish-summary]').textContent = unanswered
      ? `You have ${unanswered} unanswered question${unanswered > 1 ? 's' : ''}${flags.size ? ` and ${flags.size} flagged for review` : ''}. Unanswered questions score 0.`
      : `All ${questions.length} questions are answered${flags.size ? ` (${flags.size} flagged for review)` : ''}.`;
    openModal('finish');
    $('[data-confirm-finish]').focus();
  }

  async function submit(auto) {
    if (submitting) return;
    submitting = true;
    const btn = $('[data-confirm-finish]');
    btn && btn.classList.add('is-loading');
    // wait briefly for in-flight answer saves
    for (let i = 0; i < 30 && pendingSaves > 0; i++) await new Promise((r) => setTimeout(r, 100));
    for (let tryNo = 1; tryNo <= 5; tryNo++) {
      try {
        const data = await request('submit', { auto: !!auto });
        try { localStorage.removeItem(flagKey); } catch (_) { /* ignore */ }
        window.location.href = data.redirect;
        return;
      } catch (err) {
        if (err.message === 'redirecting') return;
        await new Promise((r) => setTimeout(r, 1500 * tryNo));
      }
    }
    submitting = false;
    btn && btn.classList.remove('is-loading');
    alert('We could not reach the server. Your answers are saved; please check your connection and try again.');
  }

  function timeUp() {
    submitting = false;
    openModal('timeup');
    submit(true);
  }

  // ------------------------------------------------------------------ side panel (mobile)
  function closeSide() {
    el.side.classList.remove('is-open');
    el.paletteToggle && el.paletteToggle.setAttribute('aria-expanded', 'false');
  }
  el.paletteToggle && el.paletteToggle.addEventListener('click', () => {
    const open = el.side.classList.toggle('is-open');
    el.paletteToggle.setAttribute('aria-expanded', String(open));
  });

  // ------------------------------------------------------------------ events
  el.prev.addEventListener('click', () => go(index - 1, 'prev'));
  el.next.addEventListener('click', () => (index === questions.length - 1 ? openFinish() : go(index + 1, 'next')));
  el.flag.addEventListener('click', toggleFlag);
  el.finish.addEventListener('click', () => { closeSide(); openFinish(); });
  $('[data-confirm-finish]').addEventListener('click', () => submit(false));
  document.addEventListener('keydown', (e) => {
    if (!questions.length || submitting || e.altKey || e.ctrlKey || e.metaKey) return;
    if ($('.modal.is-open')) {
      if (e.key === 'Escape') $$('.modal.is-open').forEach((m) => m.dataset.modal === 'finish' && closeModal(m));
      return;
    }
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
    const map = { a: 'A', b: 'B', c: 'C', d: 'D', 1: 'A', 2: 'B', 3: 'C', 4: 'D' };
    if (map[e.key.toLowerCase()]) { e.preventDefault(); choose(map[e.key.toLowerCase()]); }
    else if (e.key === 'ArrowRight') { go(index + 1, 'next'); }
    else if (e.key === 'ArrowLeft') { go(index - 1, 'prev'); }
    else if (e.key.toLowerCase() === 'f') { toggleFlag(); }
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });

  // ------------------------------------------------------------------ boot
  (async function init() {
    try {
      const state = await request('state');
      questions = state.questions;
      setRemaining(state.remaining);
      // resume on the first unanswered question
      const firstOpen = questions.findIndex((q) => !q.selected);
      index = firstOpen === -1 ? 0 : firstOpen;
      el.loading.hidden = true;
      el.exam.hidden = false;
      renderQuestion();
      setInterval(renderTimer, 250);
      setInterval(sync, 60000);
    } catch (err) {
      if (err.message === 'redirecting' || err.message === 'unauthenticated') return;
      $('p', el.loading).textContent = 'Unable to load the exam. Please check your connection and reload the page.';
      $('.loader', el.loading).hidden = true;
    }
  })();
})();
