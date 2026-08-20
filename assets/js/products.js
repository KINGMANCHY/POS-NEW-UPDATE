/**
 * assets/js/products.js
 * -----------------------------------------------------------------------
 * Drives views/products.php via app/controllers/ProductController.php.
 * Handles list rendering (with thumbnails + low-stock badges), category
 * filtering, and the add/edit modal including image upload with a
 * drag-and-drop / click-to-browse preview.
 */
(function ($) {
    'use strict';

    const ENDPOINT = (window.APP_URL || '') + '/app/controllers/ProductController.php';
    let state = { search: '', categoryId: '', sortBy: 'product_name', sortDir: 'ASC', page: 1, perPage: 10 };
    let searchDebounce = null;
    let selectedFile = null;

    function escapeHtml(str) { return $('<div>').text(str == null ? '' : str).html(); }

    function formatMoney(value) {
        const n = Number(value || 0);
        return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ---------------------------------------------------------------
    // List rendering
    // ---------------------------------------------------------------

    function renderRows(rows) {
        const $body = $('#productTableBody');
        $body.empty();

        if (!rows.length) {
            $body.html('<tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            const thumb = row.image_url
                ? `<img src="${row.image_url}" class="pos-thumb" alt="">`
                : `<div class="pos-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>`;

            const statusBadge = Number(row.is_active) === 1
                ? '<span class="badge pos-badge-success">Active</span>'
                : '<span class="badge pos-badge-muted">Inactive</span>';

            const qty = Number(row.quantity_on_hand);
            const alertQty = Number(row.stock_alert_qty);
            let stockBadge = `<span class="badge pos-badge-muted">${qty} ${escapeHtml(row.unit)}</span>`;
            if (qty <= 0) stockBadge = `<span class="badge pos-badge-danger">Out of stock</span>`;
            else if (qty <= alertQty) stockBadge = `<span class="badge pos-badge-warning">${qty} ${escapeHtml(row.unit)} &middot; Low</span>`;

            $body.append($(`
                <tr>
                    <td>${thumb}</td>
                    <td>
                        <div class="fw-medium">${escapeHtml(row.product_name)}</div>
                        <div class="text-muted small font-monospace">${escapeHtml(row.product_code)}${row.brand ? ' &middot; ' + escapeHtml(row.brand) : ''}</div>
                    </td>
                    <td class="text-muted">${escapeHtml(row.category_name) || '&mdash;'}</td>
                    <td class="font-monospace">${formatMoney(row.selling_price)}</td>
                    <td>${stockBadge}</td>
                    <td>${statusBadge}</td>
                    <td class="text-end">
                        <button class="btn btn-sm pos-icon-btn-table btn-edit" data-id="${row.product_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm pos-icon-btn-table text-danger btn-delete" data-id="${row.product_id}" data-name="${escapeHtml(row.product_name)}" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `));
        });
    }

/*    function renderPagination(result) {
        const $pg = $('#productPagination').empty();
        $('#productPageInfo').text(
            result.total === 0 ? 'No results' :
            `Showing ${(result.page - 1) * result.per_page + 1}-${Math.min(result.page * result.per_page, result.total)} of ${result.total}`
        );
        if (result.total_pages <= 1) return;

        const addPage = (label, page, disabled, active) => {
            $pg.append(`<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${label}</a></li>`);
        };
        addPage('&laquo;', result.page - 1, result.page <= 1, false);
        for (let p = 1; p <= result.total_pages; p++) addPage(p, p, false, p === result.page);
        addPage('&raquo;', result.page + 1, result.page >= result.total_pages, false);
    }*/

    function renderPagination(result) {
        const $pg = $('#productPagination').empty();

        $('#productPageInfo').text(
            result.total === 0 ? 'No results' :
            `Showing ${(result.page - 1) * result.per_page + 1}-${Math.min(result.page * result.per_page, result.total)} of ${result.total}`
        );

        if (result.total_pages <= 1) return;

        const addPage = (label, page, disabled = false, active = false) => {
            $pg.append(`
                <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${page}">${label}</a>
                </li>
            `);
        };

        const addDots = () => {
            $pg.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        };

        // Previous
        addPage('&laquo;', result.page - 1, result.page <= 1);

        const total = result.total_pages;
        const current = result.page;

        if (total <= 5) {
            // 1 2 3 4 5
            for (let p = 1; p <= total; p++) {
                addPage(p, p, false, p === current);
            }
        } else {
            // Always show first page
            addPage(1, 1, false, current === 1);

            if (current > 3) addDots();

            // Pages around current
            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);

            for (let p = start; p <= end; p++) {
                addPage(p, p, false, p === current);
            }

            if (current < total - 2) addDots();

            // Always show last page
            addPage(total, total, false, current === total);
        }

        // Next
        addPage('&raquo;', result.page + 1, result.page >= total);
    }

    function loadList() {
        $('#productTableBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');
        $.ajax({
            url: ENDPOINT, method: 'GET', dataType: 'json',
            data: {
                action: 'list', search: state.search, category_id: state.categoryId,
                sort_by: state.sortBy, sort_dir: state.sortDir, page: state.page, per_page: state.perPage,
            },
        }).done(function (res) {
            if (res.success) { renderRows(res.result.rows); renderPagination(res.result); }
            else { $('#productTableBody').html(`<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(res.message)}</td></tr>`); }
        }).fail(function () {
            $('#productTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Could not load products. Please try again.</td></tr>');
        });
    }

    // ---------------------------------------------------------------
    // Dropdown data (categories / suppliers / suggested code)
    // ---------------------------------------------------------------

    function loadFormData(callback) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'form_data' } }).done(function (res) {
            if (!res.success) return;

            const $catFilter = $('#productCategoryFilter');
            const $catSelect = $('#productCategory');
            $catFilter.find('option:not(:first)').remove();
            $catSelect.find('option:not(:first)').remove();
            res.categories.forEach(function (c) {
                $catFilter.append(`<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`);
                $catSelect.append(`<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`);
            });

            const $supSelect = $('#productSupplier');
            $supSelect.find('option:not(:first)').remove();
            res.suppliers.forEach(function (s) {
                $supSelect.append(`<option value="${s.supplier_id}">${escapeHtml(s.supplier_name)}</option>`);
            });

            const $brand = $('#productBrand').empty().append('<option value="">No brand</option>');
            (res.brands || []).forEach(function (b) { $brand.append(`<option value="${escapeHtml(b.brand_name)}">${escapeHtml(b.brand_name)}</option>`); });
            const $unit = $('#productUnit').empty();
            (res.units || [{ unit_name: 'pc' }]).forEach(function (u) { $unit.append(`<option value="${escapeHtml(u.unit_name)}">${escapeHtml(u.unit_name)}</option>`); });

            if (typeof callback === 'function') callback(res);
        });
    }

    // ---------------------------------------------------------------
    // Image upload UI
    // ---------------------------------------------------------------

    function setImagePreview(src) {
        if (src) {
            $('#imagePreview').attr('src', src).show();
            $('#uploadIcon').hide();
            $('#btnRemoveImage').removeClass('d-none');
        } else {
            $('#imagePreview').attr('src', '').hide();
            $('#uploadIcon').show();
            $('#btnRemoveImage').addClass('d-none');
        }
    }

    function handleFileSelect(file) {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Please choose a JPEG, PNG, or WEBP image.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('Image is too large. Maximum size is 2MB.');
            return;
        }
        selectedFile = file;
        $('#removeImage').val('0');
        const reader = new FileReader();
        reader.onload = function (e) { setImagePreview(e.target.result); };
        reader.readAsDataURL(file);
    }

    // ---------------------------------------------------------------
    // Modal form
    // ---------------------------------------------------------------

    function resetForm() {
        $('#productForm')[0].reset();
        $('#productId').val('');
        $('#removeImage').val('0');
        $('#productIsActive').prop('checked', true);
        $('#productFormAlert').addClass('d-none').text('');
        $('#productModalTitle').text('Add Product');
        selectedFile = null;
        $('#productImage').val('');
        setImagePreview(null);
    }

    function openEdit(id) {
        $.ajax({ url: ENDPOINT, method: 'GET', dataType: 'json', data: { action: 'get', id: id } }).done(function (res) {
            if (!res.success) { alert(res.message || 'Product not found.'); return; }
            const p = res.product;
            resetForm();
            $('#productModalTitle').text('Edit Product');
            $('#productId').val(p.product_id);
            $('#productName').val(p.product_name);
            $('#productBrand').val(p.brand);
            $('#productCode').val(p.product_code);
            $('#productBarcode').val(p.barcode);
            $('#productCategory').val(p.category_id || '');
            $('#productSupplier').val(p.supplier_id || '');
            $('#productCost').val(p.cost_price);
            $('#productPrice').val(p.selling_price);
            $('#productTax').val(p.tax_rate);
            $('#productDiscount').val(p.discount_rate);
            $('#productUnit').val(p.unit);
            $('#productAlertQty').val(p.stock_alert_qty);
            $('#productExpirationDate').val(p.expiration_date || '');
            $('#productIsActive').prop('checked', Number(p.is_active) === 1);
            setImagePreview(p.image_url || null);
            new bootstrap.Modal('#productModal').show();
        });
    }

    $(function () {
        loadFormData();
        loadList();

        $('#btnAddProduct').on('click', resetForm);

        $('#productSearch').on('input', function () {
            clearTimeout(searchDebounce);
            const value = $(this).val();
            searchDebounce = setTimeout(function () { state.search = value; state.page = 1; loadList(); }, 350);
        });

        $('#productCategoryFilter').on('change', function () { state.categoryId = $(this).val(); state.page = 1; loadList(); });
        $('#productPerPage').on('change', function () { state.perPage = parseInt($(this).val(), 10); state.page = 1; loadList(); });

        $(document).on('click', '.pos-sortable', function () {
            const col = $(this).data('sort');
            if (!col) return;
            if (state.sortBy === col) { state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC'; }
            else { state.sortBy = col; state.sortDir = 'ASC'; }
            loadList();
        });

        $(document).on('click', '#productPagination a.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10);
            if (!isNaN(page) && page >= 1) { state.page = page; loadList(); }
        });

        $(document).on('click', '#productTableBody .btn-edit', function () { openEdit($(this).data('id')); });

        $(document).on('click', '#productTableBody .btn-delete', function () {
            const id = $(this).data('id'), name = $(this).data('name');
            if (!confirm(`Delete product "${name}"? This cannot be undone.`)) return;
            $.ajax({ url: ENDPOINT, method: 'POST', dataType: 'json', data: { action: 'delete', product_id: id } })
                .done(function (res) { if (res.success) loadList(); else alert(res.message); })
                .fail(function (jq) { alert((jq.responseJSON && jq.responseJSON.message) || 'Could not delete product.'); });
        });

        // Generate a code client-side: PRD-YYYYMMDD-XXXX
        $('#btnGenerateCode').on('click', function () {
            const d = new Date();
            const ymd = d.getFullYear().toString() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0');
            const rand = Math.random().toString(16).slice(2, 6).toUpperCase();
            $('#productCode').val(`PRD-${ymd}-${rand}`);
        });

        // Upload box: click to browse, drag & drop
        $('#uploadBox').on('click', function () { $('#productImage').trigger('click'); });
        $('#uploadBox').on('dragover', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
        $('#uploadBox').on('dragleave', function () { $(this).removeClass('dragover'); });
        $('#uploadBox').on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            if (e.originalEvent.dataTransfer.files.length) {
                handleFileSelect(e.originalEvent.dataTransfer.files[0]);
            }
        });
        $('#productImage').on('change', function () { handleFileSelect(this.files[0]); });

        $('#btnRemoveImage').on('click', function () {
            selectedFile = null;
            $('#productImage').val('');
            $('#removeImage').val('1');
            setImagePreview(null);
        });

        $('#productForm').on('submit', function (e) {
            e.preventDefault();
            $('#productFormAlert').addClass('d-none').text('');

            const id = $('#productId').val();
            const action = id ? 'update' : 'create';
            const $btn = $('#productSaveBtn');
            $btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

            const formData = new FormData(this);
            formData.append('action', action);
            if (selectedFile) {
                formData.set('image', selectedFile);
            }

            $.ajax({
                url: ENDPOINT, method: 'POST', dataType: 'json',
                data: formData, processData: false, contentType: false,
            }).done(function (res) {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                    loadList();
                } else {
                    $('#productFormAlert').removeClass('d-none').text(res.message);
                }
            }).fail(function (jq) {
                $('#productFormAlert').removeClass('d-none').text((jq.responseJSON && jq.responseJSON.message) || 'Something went wrong. Please try again.');
            }).always(function () {
                $btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            });
        });
    });
})(jQuery);
