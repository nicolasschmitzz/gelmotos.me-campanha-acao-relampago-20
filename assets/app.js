(function () {
  'use strict';

  const API_BASE = 'api'; // ajuste se o backend estiver em outro path

  // ---------- Utilidades ----------
  const $  = (sel) => document.querySelector(sel);
  const el = (id) => document.getElementById(id);

  function showError(msg) {
    const box = el('alert-error');
    box.textContent = msg;
    box.hidden = false;
    setTimeout(() => (box.hidden = true), 6000);
  }

  // ---------- UTMIFY: dispara evento AddToCart ----------
  function dispararEventoAddToCart(valor, orderId, nomeProduto) {
    const dadosEvento = {
      value: valor,
      currency: 'BRL',
      content_name: nomeProduto || 'Produto',
      content_type: 'product',
      content_ids: [orderId],
    };

    // Meta Pixel (Facebook/Instagram)
    if (typeof fbq === 'function') {
      fbq('track', 'AddToCart', dadosEvento);
      console.log('[Utmify] AddToCart → Meta Pixel');
    }

    // TikTok Pixel
    if (typeof ttq === 'object' && typeof ttq.track === 'function') {
      ttq.track('AddToCart', {
        value: valor,
        currency: 'BRL',
        content_id: orderId,
        content_type: 'product',
        content_name: nomeProduto || 'Produto',
      });
      console.log('[Utmify] AddToCart → TikTok Pixel');
    }

    // Google Ads / GA4
    if (typeof gtag === 'function') {
      gtag('event', 'add_to_cart', {
        currency: 'BRL',
        value: valor,
        items: [{
          item_id: orderId,
          item_name: nomeProduto || 'Produto',
          price: valor,
          quantity: 1,
        }],
      });
      console.log('[Utmify] AddToCart → Google');
    }

    // Utmify (caso exista um objeto global futuro)
    if (typeof utmify !== 'undefined' && typeof utmify.track === 'function') {
      utmify.track('AddToCart', dadosEvento);
    }
  }

  // ---------- 1) Captura UTMs da URL ----------
  const utmKeys = ['src', 'sck', 'utm_source', 'utm_campaign',
                   'utm_medium', 'utm_content', 'utm_term'];
  const params = new URLSearchParams(window.location.search);

  // Persiste UTMs no sessionStorage para sobreviver a reloads
  const storedUtms = JSON.parse(sessionStorage.getItem('utms') || '{}');
  utmKeys.forEach((k) => {
    const urlVal = params.get(k);
    if (urlVal) storedUtms[k] = urlVal;

    const input = el(k);
    if (input) input.value = storedUtms[k] || '';
  });
  sessionStorage.setItem('utms', JSON.stringify(storedUtms));

  // ---------- 2) Máscaras ----------
  const docInput = el('document');
  docInput?.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    e.target.value = v;
  });

  const phoneInput = el('phone');
  phoneInput?.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10)      v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (v.length > 6)  v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    else if (v.length > 2)  v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    e.target.value = v;
  });

  // ---------- 3) Submit → POST api/checkout.php ----------
  const form = el('checkout-form');
  const btn  = el('submit-btn');
  let   pollTimer = null;

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Gerando PIX…';
    btn.querySelector('.spinner').hidden = false;

    const payload = {
      amount:   el('amount').value,
      name:     el('name').value,
      email:    el('email').value,
      document: el('document').value,
      phone:    el('phone').value,
      utms: {
        src:          el('src').value,
        sck:          el('sck').value,
        utm_source:   el('utm_source').value,
        utm_campaign: el('utm_campaign').value,
        utm_medium:   el('utm_medium').value,
        utm_content:  el('utm_content').value,
        utm_term:     el('utm_term').value,
      },
    };

    try {
      const res = await fetch(`${API_BASE}/checkout.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Falha ao gerar PIX');
      }

      // >>> DISPARA O ADD TO CART AQUI <<<
      const valorNumerico = parseFloat(
        String(payload.amount).replace(/\./g, '').replace(',', '.')
      );
      dispararEventoAddToCart(valorNumerico, data.orderId, 'Produto Digital');

      renderQr(data.orderId, data.pixCode);
      startPolling(data.orderId);
    } catch (err) {
      showError(err.message);
      btn.disabled = false;
      btn.querySelector('.btn-text').textContent = 'Gerar PIX';
      btn.querySelector('.spinner').hidden = true;
    }
  });

  // ---------- 4) Render do QR Code ----------
  function renderQr(orderId, pixCode) {
    form.hidden = true;

    const qr = el('qr-section');
    qr.hidden = false;

    el('qr-image').src =
      'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' +
      encodeURIComponent(pixCode);

    el('pix-code').value = pixCode;
    el('order-id-label').textContent = orderId;

    el('copy-btn').addEventListener('click', async () => {
      const input = el('pix-code');
      try {
        await navigator.clipboard.writeText(input.value);
        el('copy-btn').textContent = 'Copiado!';
        setTimeout(() => (el('copy-btn').textContent = 'Copiar'), 2000);
      } catch {
        input.select();
        document.execCommand('copy');
      }
    });
  }

  // ---------- 5) Polling de status ----------
  function startPolling(orderId) {
    const line = el('status-line');
    clearInterval(pollTimer);

    pollTimer = setInterval(async () => {
      try {
        const res  = await fetch(`${API_BASE}/status.php?order_id=` +
                                 encodeURIComponent(orderId));
        const data = await res.json();

        if (data.status === 'paid') {
          line.textContent = '✅ Pagamento confirmado!';
          line.classList.add('paid');
          clearInterval(pollTimer);
        } else if (data.status === 'failed') {
          line.textContent = '❌ Pagamento recusado.';
          line.style.color = '#ef4444';
          clearInterval(pollTimer);
        }
      } catch (e) {
        console.warn('Erro ao consultar status:', e);
      }
    }, 5000);
  }
})();