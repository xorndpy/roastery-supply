// Validasi form auth tanpa tooltip bawaan browser: pesan inline di bawah field.
const msg = (el) => {
    const v = el.validity;
    if (v.valueMissing) return 'Wajib diisi.';
    if (v.typeMismatch && el.type === 'email') return 'Format email tidak valid. Contoh: nama@gmail.com';
    if (v.tooShort) return `Minimal ${el.minLength} karakter.`;
    return v.valid ? '' : (el.validationMessage || 'Isian tidak valid.');
};
const show = (el) => {
    const box = el.closest('.auth-field')?.parentElement; if (!box) return;
    box.querySelector('.auth-error')?.remove();
    const text = msg(el);
    el.toggleAttribute('aria-invalid', !!text);
    if (text) { el.setAttribute('aria-invalid', 'true'); const p = document.createElement('p'); p.className = 'auth-error'; p.setAttribute('role', 'alert'); p.textContent = text; el.closest('.auth-field').after(p); }
    else el.removeAttribute('aria-invalid');
};
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
        const fields = [...form.querySelectorAll('.auth-input')]; if (!fields.length) return;
        form.noValidate = true;
        fields.forEach((el) => {
            el.addEventListener('blur', () => { if (el.value !== '') show(el); });
            el.addEventListener('input', () => { if (el.hasAttribute('aria-invalid')) show(el); });
        });
    });
});
document.addEventListener('submit', (e) => {
    const fields = [...e.target.querySelectorAll('.auth-input')]; if (!fields.length) return;
    let first = null;
    fields.forEach((el) => { show(el); if (!first && !el.checkValidity()) first = el; });
    if (first) { e.preventDefault(); e.stopImmediatePropagation(); first.focus(); }
}, true);
