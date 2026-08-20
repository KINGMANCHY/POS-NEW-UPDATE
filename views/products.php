<?php
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Products</h1>
        <p class="text-muted mb-0">Manage your product catalog, pricing, and stock alerts.</p>
    </div>
    <button class="btn pos-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#productModal" id="btnAddProduct">
        <i class="bi bi-plus-lg me-1"></i> Add Product
    </button>
</div>

<div class="card pos-card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <div class="input-group pos-input-group" style="max-width: 300px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="productSearch" placeholder="Search name, code, barcode, brand...">
                </div>
                <select class="form-select pos-select" id="productCategoryFilter" style="max-width: 200px;">
                    <option value="">All Categories</option>
                </select>
            </div>
            <select class="form-select pos-select" id="productPerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th></th>
                        <th class="pos-sortable" data-sort="product_name">Product <i class="bi bi-arrow-down-up"></i></th>
                        <th>Category</th>
                        <th class="pos-sortable" data-sort="selling_price">Price <i class="bi bi-arrow-down-up"></i></th>
                        <th>Stock</th>
                        <th class="pos-sortable" data-sort="is_active">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="productPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="productPagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="productForm" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::escape($csrfToken) ?>">
                <input type="hidden" id="productId" name="product_id" value="">
                <input type="hidden" id="removeImage" name="remove_image" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="productFormAlert" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Product Image</label>
                            <div class="pos-upload-box" id="uploadBox">
                                <img id="imagePreview" class="pos-upload-preview" alt="Preview">
                                <i class="bi bi-cloud-arrow-up fs-3 text-muted" id="uploadIcon"></i>
                                <p class="small text-muted mb-0 mt-1">Click or drag an image here<br>(JPEG, PNG, WEBP · max 2MB)</p>
                                <input type="file" id="productImage" name="image" accept="image/jpeg,image/png,image/webp" class="d-none">
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 mt-2 d-none" id="btnRemoveImage">Remove image</button>
                        </div>

                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="productName" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="productName" name="product_name" maxlength="150" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="productBrand" class="form-label">Brand</label>
                                    <select class="form-select pos-select" id="productBrand" name="brand"><option value="">No brand</option></select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="productCode" class="form-label">Product Code <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control font-monospace" id="productCode" name="product_code" maxlength="50" required>
                                        <button class="btn btn-outline-secondary" type="button" id="btnGenerateCode" title="Generate code"><i class="bi bi-magic"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="productBarcode" class="form-label">Barcode</label>
                                    <input type="text" class="form-control font-monospace" id="productBarcode" name="barcode" maxlength="50">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="productCategory" class="form-label">Category</label>
                                    <select class="form-select pos-select" id="productCategory" name="category_id">
                                        <option value="">Uncategorized</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="productSupplier" class="form-label">Supplier</label>
                                    <select class="form-select pos-select" id="productSupplier" name="supplier_id">
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="productCost" class="form-label">Cost Price</label>
                            <input type="number" class="form-control" id="productCost" name="cost_price" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="productPrice" class="form-label">Selling Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="productPrice" name="selling_price" min="0" step="0.01" value="0" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="productTax" class="form-label">Tax %</label>
                            <input type="number" class="form-control" id="productTax" name="tax_rate" min="0" max="100" step="0.01" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="productDiscount" class="form-label">Discount %</label>
                            <input type="number" class="form-control" id="productDiscount" name="discount_rate" min="0" max="100" step="0.01" value="0">
                        </div>
                    </div>

                    <div class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label for="productUnit" class="form-label">Unit</label>
                            <select class="form-select pos-select" id="productUnit" name="unit"><option value="pc">pc</option></select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="productAlertQty" class="form-label">Stock Alert Qty</label>
                            <input type="number" class="form-control" id="productAlertQty" name="stock_alert_qty" min="0" value="10">
                        </div>
                        <div class="col-md-5 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="productIsActive" name="is_active" value="1" checked>
                                <label class="form-check-label" for="productIsActive">Active (visible on POS)</label>
                            </div>
                        </div>
                    </div>

                    <div class="row"><div class="col-md-4 mb-3"><label for="productExpirationDate" class="form-label">Expiration Date</label><input type="date" class="form-control" id="productExpirationDate" name="expiration_date"></div></div>
                    <p class="text-muted small mb-0">
                        Initial stock starts at 0 for new products &mdash; adjust quantity from the Inventory module.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pos-btn-primary" id="productSaveBtn">
                        <span class="btn-label">Save Product</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
