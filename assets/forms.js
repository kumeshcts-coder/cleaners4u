/* Cleaners4u quote form: fetches the CSRF token and posts both steps to /insert.php as JSON. */
(function (w) {
  'use strict';
  var HEADERS = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
  var tokenReq = null;

  function token(fresh) {
    if (fresh || !tokenReq) {
      tokenReq = fetch('/form-token.php', { credentials: 'same-origin', cache: 'no-store', headers: HEADERS })
        .then(function (r) { if (!r.ok) throw new Error('token ' + r.status); return r.json(); })
        .then(function (d) { if (!d || !d.token) throw new Error('token'); return d.token; });
      tokenReq.catch(function () { tokenReq = null; });
    }
    return tokenReq;
  }

  function send(fd, fresh) {
    return token(fresh).then(function (t) {
      fd.set('csrf_token', t);
      return fetch('/insert.php', { method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store', headers: HEADERS });
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        d = d && typeof d === 'object' ? d : {};
        d.status = r.status;
        d.ok = r.ok && d.ok === true;
        return d;
      });
    });
  }

  w.C4U = {
    /* Start the session early (first tap in the form) so the submit is quicker. */
    warm: function () { token().catch(function () {}); },
    /* Resolves with the server's JSON ({ok, lead_ref, message, errors}); rejects only on network failure. */
    post: function (fd) {
      return send(fd, false).then(function (d) { return d.status === 419 ? send(fd, true) : d; });
    }
  };
})(window);
