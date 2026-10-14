document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm || 'Hapus data penjualan ini?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.alert-close').forEach((button) => {
    button.addEventListener('click', () => button.closest('.alert')?.remove());
});

// Modal Tambah / Edit Penjualan
const saleModal = document.querySelector('#sale-modal');
const qtyInput = document.getElementById('input-quantity');
const priceInput = document.getElementById('input-unit-price');
const subtotalPreview = document.getElementById('subtotal-preview');

function updateSubtotalPreview() {
    if (!subtotalPreview || !qtyInput || !priceInput) return;
    const qty = Math.max(0, parseInt(qtyInput.value, 10) || 0);
    const price = Math.max(0, parseFloat(priceInput.value) || 0);
    const total = qty * price;
    subtotalPreview.textContent = 'Rp ' + total.toLocaleString('id-ID');
}

qtyInput?.addEventListener('input', updateSubtotalPreview);
priceInput?.addEventListener('input', updateSubtotalPreview);
updateSubtotalPreview();

// Pilihan Cepat Harga (Price Presets)
document.querySelectorAll('.preset-pill').forEach((btn) => {
    btn.addEventListener('click', () => {
        if (priceInput) {
            priceInput.value = btn.dataset.price;
            updateSubtotalPreview();
            priceInput.focus();
        }
    });
});

function closeSaleModal() {
    if (window.location.search.includes('form=') || window.location.search.includes('edit=')) {
        window.location.href = 'index.php';
    } else {
        saleModal?.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
    }
}

const btnTambah = document.getElementById('btn-tambah');
if (btnTambah && saleModal) {
    btnTambah.addEventListener('click', (e) => {
        if (window.location.search === '' || window.location.search === '?') {
            e.preventDefault();
            const form = document.getElementById('sale-form');
            if (form) {
                const idInput = form.querySelector('input[name="id"]');
                if (idInput && idInput.value !== '') {
                    form.reset();
                    idInput.value = '';
                }
                const modalTitle = document.getElementById('modal-title');
                if (modalTitle) modalTitle.textContent = 'Tambah penjualan';
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Simpan penjualan';
            }
            saleModal.classList.add('is-visible');
            document.body.classList.add('modal-open');
            saleModal.querySelector('input[name="customer_name"]')?.focus();
            updateSubtotalPreview();
        }
    });
}

if (saleModal?.classList.contains('is-visible')) {
    document.body.classList.add('modal-open');
    saleModal.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
}

saleModal?.addEventListener('click', (event) => {
    if (event.target === saleModal) {
        closeSaleModal();
    }
});

// Modal Struk / Nota Penjualan
const invoiceModal = document.querySelector('#invoice-modal');

function closeInvoiceModal() {
    invoiceModal?.classList.remove('is-visible');
    if (!saleModal?.classList.contains('is-visible')) {
        document.body.classList.remove('modal-open');
    }
}

document.querySelectorAll('.invoice-button').forEach((btn) => {
    btn.addEventListener('click', () => {
        const d = btn.dataset;
        document.getElementById('inv-modal-number').textContent = d.invoice || 'INV-0000';
        document.getElementById('inv-modal-date').textContent = d.date || '-';
        document.getElementById('inv-modal-customer').textContent = d.customer || '-';
        document.getElementById('inv-modal-payment').textContent = d.payment || '-';
        document.getElementById('inv-modal-flower').textContent = d.flower || '-';
        document.getElementById('inv-modal-qty').textContent = (d.qty || '1') + ' item';
        document.getElementById('inv-modal-price').textContent = d.price || 'Rp 0';
        document.getElementById('inv-modal-total').textContent = d.total || 'Rp 0';
        document.getElementById('inv-modal-grand-total').textContent = d.total || 'Rp 0';

        const notesEl = document.getElementById('inv-modal-notes');
        if (notesEl) {
            notesEl.textContent = d.notes ? 'Catatan: ' + d.notes : '';
            notesEl.style.display = d.notes ? 'block' : 'none';
        }

        invoiceModal?.classList.add('is-visible');
        document.body.classList.add('modal-open');
    });
});

document.getElementById('btn-close-invoice')?.addEventListener('click', closeInvoiceModal);
document.getElementById('btn-close-invoice-x')?.addEventListener('click', closeInvoiceModal);
document.getElementById('btn-print-invoice')?.addEventListener('click', () => {
    window.print();
});

invoiceModal?.addEventListener('click', (event) => {
    if (event.target === invoiceModal) {
        closeInvoiceModal();
    }
});

// Auto-submit filter on change for clean UX
document.querySelectorAll('.filter-select').forEach((select) => {
    select.addEventListener('change', () => {
        select.closest('form')?.submit();
    });
});

// Global Escape Key Listener
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (invoiceModal?.classList.contains('is-visible')) {
            closeInvoiceModal();
        } else if (saleModal?.classList.contains('is-visible')) {
            closeSaleModal();
        }
    }
});


