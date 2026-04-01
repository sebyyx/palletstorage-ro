// Formularul trimite datele prin fetch() la contact.php (PHP mail)

document.addEventListener('DOMContentLoaded', () => {

  async function renderPdfThumbs() {
    if (typeof pdfjsLib === 'undefined') return;

    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const canvases = document.querySelectorAll('.cert-card__canvas[data-pdf]');

    for (const canvas of canvases) {
      const pdfUrl = canvas.getAttribute('data-pdf');
      if (!pdfUrl) continue;

      try {
        const loadingTask = pdfjsLib.getDocument({ url: pdfUrl, disableWorker: false });
        const pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);

        const deviceScale = window.devicePixelRatio > 1 ? 2 : 1.5;
        const baseViewport = page.getViewport({ scale: 1 });
        const targetWidth = canvas.clientWidth || 320;
        const scale = (targetWidth / baseViewport.width) * deviceScale;
        const viewport = page.getViewport({ scale });

        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        const ctx = canvas.getContext('2d', { alpha: false });
        if (!ctx) continue;

        await page.render({ canvasContext: ctx, viewport }).promise;
      } catch (error) {
        try {
          const fallbackTask = pdfjsLib.getDocument({ url: pdfUrl, disableWorker: true });
          const pdf = await fallbackTask.promise;
          const page = await pdf.getPage(1);
          const baseViewport = page.getViewport({ scale: 1 });
          const targetWidth = canvas.clientWidth || 320;
          const scale = targetWidth / baseViewport.width;
          const viewport = page.getViewport({ scale });
          canvas.width = Math.floor(viewport.width);
          canvas.height = Math.floor(viewport.height);
          const ctx = canvas.getContext('2d', { alpha: false });
          if (!ctx) continue;
          await page.render({ canvasContext: ctx, viewport }).promise;
        } catch (fallbackError) {
          console.error('PDF thumbnail render error:', pdfUrl, error, fallbackError);
        }
      }
    }
  }

  renderPdfThumbs();


  const form = document.getElementById('contact-form');
  if (!form) return;

  const fields = {
    name:    form.querySelector('#name'),
    phone:   form.querySelector('#phone'),
    email:   form.querySelector('#email'),
    pallets: form.querySelector('#pallets'),
    message: form.querySelector('#message'),
  };

  const btn    = form.querySelector('#submit-btn');
  const status = document.getElementById('form-status');

  // ── Validări ────────────────────────────────────────────────────────────
  function validatePhone(val) {
    const clean = val.replace(/[\s\-\(\)\.]/g, '');
    return /^(\+40|0040|0)(7[0-9]{8}|[23][0-9]{8})$/.test(clean);
  }

  function validateEmail(val) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val.trim());
  }

  function setError(field, msg) {
    field.classList.add('input--error');
    field.setAttribute('aria-invalid', 'true');
    let err = field.parentElement.querySelector('.field-error');
    if (!err) {
      err = document.createElement('span');
      err.className = 'field-error';
      err.setAttribute('role', 'alert');
      field.parentElement.appendChild(err);
    }
    err.textContent = msg;
  }

  function clearError(field) {
    field.classList.remove('input--error');
    field.removeAttribute('aria-invalid');
    const err = field.parentElement.querySelector('.field-error');
    if (err) err.remove();
  }

  function validateAll() {
    let ok = true;

    if (!fields.name.value.trim()) {
      setError(fields.name, 'Introduceți numele sau firma.');
      ok = false;
    } else {
      clearError(fields.name);
    }

    if (!fields.phone.value.trim()) {
      setError(fields.phone, 'Introduceți numărul de telefon.');
      ok = false;
    } else if (!validatePhone(fields.phone.value)) {
      setError(fields.phone, 'Număr invalid. Exemplu: 0730 238 240');
      ok = false;
    } else {
      clearError(fields.phone);
    }

    if (!fields.email.value.trim()) {
      setError(fields.email, 'Introduceți adresa de email.');
      ok = false;
    } else if (!validateEmail(fields.email.value)) {
      setError(fields.email, 'Adresă de email invalidă. Exemplu: nume@firma.ro');
      ok = false;
    } else {
      clearError(fields.email);
    }

    return ok;
  }

  // ── Validare live la ieșire din câmp ────────────────────────────────────
  fields.phone.addEventListener('blur', () => {
    if (fields.phone.value.trim() && !validatePhone(fields.phone.value)) {
      setError(fields.phone, 'Număr invalid. Exemplu: 0730 238 240');
    } else if (fields.phone.value.trim()) {
      clearError(fields.phone);
    }
  });

  fields.email.addEventListener('blur', () => {
    if (fields.email.value.trim() && !validateEmail(fields.email.value)) {
      setError(fields.email, 'Adresă de email invalidă. Exemplu: nume@firma.ro');
    } else if (fields.email.value.trim()) {
      clearError(fields.email);
    }
  });

  // ── Submit ───────────────────────────────────────────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    status.className = 'form-status';
    status.textContent = '';

    if (!validateAll()) return;

    btn.disabled = true;
    btn.textContent = 'Se trimite…';

    const body = new FormData();
    body.append('name',    fields.name.value.trim());
    body.append('phone',   fields.phone.value.trim());
    body.append('email',   fields.email.value.trim());
    body.append('pallets', fields.pallets.value || '');
    body.append('message', fields.message.value.trim());

    try {
      const res  = await fetch('contact.php', { method: 'POST', body });
      const data = await res.json();

      if (data.success) {
        status.className = 'form-status form-status--success';
        status.innerHTML =
          '<strong>Mesaj trimis!</strong> Echipa PalletStorage te va contacta cât mai curând. ' +
          'Ai primit și tu un email de confirmare.';
        form.reset();
      } else {
        throw new Error(data.message || 'Eroare necunoscută');
      }

    } catch (err) {
      status.className = 'form-status form-status--error';
      status.innerHTML =
        'A apărut o eroare la trimitere. Încearcă din nou sau sună direct la ' +
        '<a href="tel:+40730238240" style="color:inherit;text-decoration:underline">0730 238 240</a>.';
    } finally {
      btn.disabled = false;
      btn.textContent = 'Trimite cererea';
    }
  });

});
