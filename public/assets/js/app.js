/* Prospec front-end behavior. Loaded at the end of <body>; no dependencies. */
(function () {
  'use strict';

  var csrfToken = document.body.dataset.t;

  // Filters and status selects submit as soon as they change.
  document.querySelectorAll('select[data-auto]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });

  // Searches can take up to a minute: disable the button and explain why.
  document.querySelectorAll('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button');
      if (button) {
        setTimeout(function () { button.disabled = true; button.textContent = 'Analisando os sites... pode levar até 1 minuto'; }, 0);
      }
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) { if (!confirm(form.dataset.confirm)) event.preventDefault(); });
  });

  // City autocomplete (accessible combobox).
  (function () {
    var input = document.querySelector('.ac input');
    if (!input) return;
    var list = document.getElementById('ac-lista');
    var timer, requestSeq = 0, selected = -1;

    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); selected = -1; }
    function highlight(index) {
      var items = list.children;
      if (!items.length) return;
      selected = (index + items.length) % items.length;
      for (var k = 0; k < items.length; k++) items[k].setAttribute('aria-selected', k === selected ? 'true' : 'false');
    }
    function choose(text) { input.value = text; close(); input.focus(); }
    function show(cities) {
      list.textContent = '';
      if (!cities.length) return close();
      cities.forEach(function (city) {
        var item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.textContent = city;
        item.addEventListener('mousedown', function (event) { event.preventDefault(); choose(city); });
        list.appendChild(item);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true'); selected = -1;
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var value = input.value.trim();
      if (value.length < 2) return close();
      timer = setTimeout(function () {
        var mine = ++requestSeq; // ignore answers that arrive after a newer request
        fetch('?cid=' + encodeURIComponent(value), { credentials: 'same-origin' })
          .then(function (response) { return response.ok ? response.json() : []; })
          .then(function (cities) { if (mine === requestSeq) show(Array.isArray(cities) ? cities : []); })
          .catch(close);
      }, 200);
    });
    input.addEventListener('keydown', function (event) {
      if (list.hidden) return;
      if (event.key === 'ArrowDown') { event.preventDefault(); highlight(selected + 1); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); highlight(selected - 1); }
      else if (event.key === 'Enter' && selected >= 0) { event.preventDefault(); choose(list.children[selected].textContent); }
      else if (event.key === 'Escape') close();
    });
    input.addEventListener('blur', close);
  })();

  function contactedForm(leadId) {
    var data = new FormData();
    data.append('t', csrfToken);
    data.append('a', 'contatou');
    data.append('id', leadId);
    return data;
  }

  // Queue: opening WhatsApp marks the lead as contacted and loads the next one.
  document.querySelectorAll('a.fila-send').forEach(function (link) {
    link.addEventListener('click', function () {
      fetch('', { method: 'POST', body: contactedForm(link.dataset.fid), keepalive: true, credentials: 'same-origin' })
        .catch(function () {})
        .then(function () { setTimeout(function () { location.reload(); }, 300); });
    });
  });

  // Lead cards: opening WhatsApp records the contact without leaving the page.
  document.querySelectorAll('a.wa[data-id]').forEach(function (link) {
    link.addEventListener('click', function () {
      if (navigator.sendBeacon) navigator.sendBeacon('', contactedForm(link.dataset.id));
    });
  });

  // Audit report: copy the summary and print.
  (function () {
    var copyButton = document.getElementById('copiar');
    var summary = document.getElementById('resumo-txt');
    var printButton = document.getElementById('imprimir');
    if (copyButton && summary) {
      copyButton.addEventListener('click', function () {
        var done = function () {
          copyButton.textContent = 'Copiado';
          setTimeout(function () { copyButton.textContent = 'Copiar mensagem'; }, 1800);
        };
        var fallback = function () { summary.select(); document.execCommand('copy'); done(); };
        if (navigator.clipboard) navigator.clipboard.writeText(summary.value).then(done).catch(fallback);
        else fallback();
      });
    }
    if (printButton) printButton.addEventListener('click', function () { window.print(); });
  })();
})();
