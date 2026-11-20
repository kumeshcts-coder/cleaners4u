/* Cleaners4u: Google address/suburb suggestions for the step 2 suburb boxes (Places API (New)).
   Usage: C4UPlaces.attach(['#suburb', '#otherSub']). Without a key in config.php, or if Google fails to load,
   the boxes keep the built-in suburb list (<datalist>). */
(function (w, d) {
  'use strict';
  var MELBOURNE = { lat: -37.8136, lng: 144.9631 };
  var LOGO = 'https://maps.gstatic.com/mapfiles/api-3/images/powered-by-google-on-white3_hdpi.png';
  var PIN = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>';
  var ready = null, uid = 0;

  function css() {
    var s = d.createElement('style');
    s.textContent =
      '.pl-wrap{position:relative;display:block}' +
      '.pl-list{position:absolute;left:0;top:calc(100% + 4px);z-index:50;width:100%;margin:0;padding:0;list-style:none;background:#fff;border:1px solid #D5DCE5;border-radius:6px;box-shadow:0 10px 28px rgba(20,35,63,.14);overflow:hidden;font:400 15px/1.3 Barlow,Arial,sans-serif}' +
      '.pl-list[hidden]{display:none}' +
      '.pl-opt{display:flex;align-items:center;gap:10px;padding:11px 14px;border-top:1px solid #EEF1F5;cursor:pointer;color:#14233F;white-space:nowrap;overflow:hidden}' +
      '.pl-opt:first-child{border-top:0}' +
      '.pl-opt svg{flex:none;width:16px;height:16px;fill:#9AA3AF}' +
      '.pl-opt .pl-t{overflow:hidden;text-overflow:ellipsis}' +
      '.pl-opt b{font-weight:700}' +
      '.pl-opt small{margin-left:6px;font-size:13px;color:#667085}' +
      '.pl-opt[aria-selected=true],.pl-opt:hover{background:#E9F6F2}' +
      '.pl-foot{display:flex;justify-content:flex-end;padding:6px 10px;border-top:1px solid #EEF1F5}' +
      '.pl-foot img{height:16px;width:auto}';
    d.head.appendChild(s);
  }

  function load() {
    if (ready) return ready;
    ready = fetch('/places-key.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (cfg) {
        if (!cfg || !cfg.key) throw new Error('no key');
        return new Promise(function (resolve, reject) {
          var cb = '__c4uPlaces' + Date.now();
          w[cb] = function () {
            google.maps.importLibrary('places').then(resolve, reject);
            try { delete w[cb]; } catch (e) {}
          };
          var s = d.createElement('script');
          s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.key) +
            '&v=weekly&loading=async&language=en-AU&region=AU&callback=' + cb;
          s.async = true;
          s.onerror = reject;
          d.head.appendChild(s);
        });
      });
    return ready;
  }

  function esc(t) {
    return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  /* Bold the typed part, like Google's own widget. */
  function bold(ft) {
    var t = ft.text, out = '', at = 0;
    (ft.matches || []).forEach(function (m) {
      out += esc(t.slice(at, m.startOffset)) + '<b>' + esc(t.slice(m.startOffset, m.endOffset)) + '</b>';
      at = m.endOffset;
    });
    return out + esc(t.slice(at));
  }

  /* "Point Cook VIC, Australia" -> "Point Cook VIC"; keeps only characters the server accepts. */
  function tidy(t) {
    return t.replace(/,\s*Australia$/i, '').replace(/[^\p{L}\p{M}0-9 ,.'’()\-\/]/gu, '').replace(/\s+/g, ' ').trim().slice(0, 80);
  }

  function attachOne(input, lib) {
    var listId = input.getAttribute('list');
    input.removeAttribute('list'); /* Google's list replaces the built-in one */

    var wrap = d.createElement('span');
    wrap.className = 'pl-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var box = d.createElement('div');
    box.className = 'pl-list';
    box.hidden = true;
    box.id = 'pl-list-' + (++uid);
    box.innerHTML = '<ul role="listbox" style="margin:0;padding:0;list-style:none"></ul><div class="pl-foot"><img src="' + LOGO + '" alt="powered by Google"></div>';
    wrap.appendChild(box);
    var ul = box.firstChild;

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', box.id);

    var token = new lib.AutocompleteSessionToken(), items = [], active = -1, timer = 0, seq = 0, failed = false;

    function close() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }

    function highlight(i) {
      var opts = ul.children;
      if (active > -1 && opts[active]) opts[active].setAttribute('aria-selected', 'false');
      active = i;
      if (i > -1 && opts[i]) { opts[i].setAttribute('aria-selected', 'true'); input.setAttribute('aria-activedescendant', opts[i].id); }
    }

    function choose(i) {
      var p = items[i];
      if (!p) return;
      input.value = tidy(p.text.text);
      close();
      token = new lib.AutocompleteSessionToken(); /* one session per chosen place */
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fallback() {
      failed = true;
      close();
      if (listId) input.setAttribute('list', listId);
    }

    function search() {
      var q = input.value.trim(), mine = ++seq;
      if (failed || q.length < 2) { close(); return; }
      lib.AutocompleteSuggestion.fetchAutocompleteSuggestions({
        input: q,
        sessionToken: token,
        includedRegionCodes: ['au'],
        locationBias: { center: MELBOURNE, radius: 50000 },
        language: 'en-AU',
        region: 'au'
      }).then(function (res) {
        if (mine !== seq) return; /* an older reply arrived late */
        items = (res.suggestions || []).map(function (s) { return s.placePrediction; }).filter(Boolean).slice(0, 5);
        if (!items.length || d.activeElement !== input) { close(); return; }
        ul.innerHTML = items.map(function (p, i) {
          var sec = p.secondaryText ? p.secondaryText.text : '';
          return '<li class="pl-opt" role="option" aria-selected="false" id="' + box.id + '-' + i + '" data-i="' + i + '">' + PIN +
            '<span class="pl-t">' + bold(p.mainText || p.text) + (sec ? '<small>' + esc(sec) + '</small>' : '') + '</span></li>';
        }).join('');
        active = -1;
        box.style.width = Math.max(input.offsetWidth, 240) + 'px'; /* match the box, which may be narrower than its row */
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }).catch(function (e) {
        if (w.console) console.warn('Address suggestions unavailable, using the suburb list.', e);
        fallback();
      });
    }

    input.addEventListener('input', function (e) {
      if (!e.isTrusted) return; /* our own change after choosing */
      clearTimeout(timer);
      timer = setTimeout(search, 180);
    });
    input.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      var n = ul.children.length;
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % n); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight((active - 1 + n) % n); }
      else if (e.key === 'Enter' && active > -1) { e.preventDefault(); choose(active); }
      else if (e.key === 'Escape' || e.key === 'Tab') { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    /* mousedown so the choice lands before the input loses focus */
    ul.addEventListener('mousedown', function (e) {
      var li = e.target.closest('.pl-opt');
      if (li) { e.preventDefault(); choose(+li.getAttribute('data-i')); }
    });
  }

  w.C4UPlaces = {
    attach: function (selectors) {
      var inputs = selectors.map(function (s) { return d.querySelector(s); }).filter(Boolean);
      if (!inputs.length || !w.fetch || !w.Promise) return;
      load().then(function (lib) {
        css();
        inputs.forEach(function (el) { attachOne(el, lib); });
      }).catch(function () { /* no key or blocked: the built-in suburb list stays */ });
    }
  };
})(window, document);
