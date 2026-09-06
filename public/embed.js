/**
 * CRM Embeddable Lead Form Widget
 *
 * Usage:
 *   <script src="https://your-crm-domain.com/embed.js" data-crm-key="PREFIX.SECRET" async></script>
 *   <div data-crm-form="lead"></div>
 *
 * The key on the <script> tag must be an "embed" type credential (see
 * Settings > Integrations in the CRM) — it can only create leads and is
 * safe to publish in client-side code. Never use a "full" API key here.
 */
(function () {
  'use strict';

  function currentScript() {
    return document.currentScript || (function () {
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length - 1];
    })();
  }

  var script = currentScript();
  var embedKey = script.getAttribute('data-crm-key');
  var origin = new URL(script.src).origin;

  if (!embedKey) {
    console.error('[CRM Embed] Missing data-crm-key attribute on the embed.js <script> tag.');
    return;
  }

  function buildForm(container) {
    container.innerHTML =
      '<form class="crm-embed-form">' +
      '  <div style="position:absolute;left:-9999px" aria-hidden="true">' +
      '    <input type="text" name="website_url_confirm" tabindex="-1" autocomplete="off">' +
      '  </div>' +
      '  <div><label>First Name<br><input name="first_name" required></label></div>' +
      '  <div><label>Last Name<br><input name="last_name" required></label></div>' +
      '  <div><label>Phone<br><input name="phone" type="tel"></label></div>' +
      '  <div><label>Email<br><input name="email" type="email"></label></div>' +
      '  <div><label>Message<br><textarea name="message"></textarea></label></div>' +
      '  <button type="submit">Submit</button>' +
      '  <div class="crm-embed-status" role="status"></div>' +
      '</form>';

    var form = container.querySelector('form');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitForm(form, container);
    });
  }

  function submitForm(form, container) {
    var statusEl = container.querySelector('.crm-embed-status');
    var submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    statusEl.textContent = 'Sending...';

    var payload = {
      first_name: form.first_name.value,
      last_name: form.last_name.value,
      phone: form.phone.value,
      email: form.email.value,
      message: form.message.value,
      website_url_confirm: form.website_url_confirm.value, // honeypot
      source: 'website',
      page_url: window.location.href,
      referrer: document.referrer || '',
      utm_source: getParam('utm_source'),
      utm_medium: getParam('utm_medium'),
      utm_campaign: getParam('utm_campaign'),
      utm_term: getParam('utm_term'),
      utm_content: getParam('utm_content'),
      consent: true,
    };

    fetch(origin + '/api/v1/public/leads', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Embed-Key': embedKey,
      },
      body: JSON.stringify(payload),
    })
      .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
      .then(function (result) {
        if (result.ok && result.body.success) {
          form.reset();
          form.style.display = 'none';
          statusEl.textContent = result.body.message || 'Thank you! We\'ll be in touch shortly.';
        } else {
          statusEl.textContent = (result.body && result.body.error) || 'Something went wrong. Please try again.';
          submitBtn.disabled = false;
        }
      })
      .catch(function () {
        statusEl.textContent = 'Network error. Please try again.';
        submitBtn.disabled = false;
      });
  }

  function getParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name) || '';
  }

  function init() {
    var containers = document.querySelectorAll('[data-crm-form="lead"]');
    for (var i = 0; i < containers.length; i++) {
      buildForm(containers[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
