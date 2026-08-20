<?php
/**
 * views/categories.php
 * -----------------------------------------------------------------------
 * Content partial for categories.php. Rendered inside the app shell.
 * The table body is filled entirely by AJAX (assets/js/categories.js) -
 * this file only lays out the toolbar, table skeleton, and modal form.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0">Categories</h1>
        <p class="text-muted mb-0">Organize your products into categories.</p>
    </div>
    <button class="btn pos-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal" id="btnAddCategory">
        <i class="bi bi-plus-lg me-1"></i> Add Category
    </button>
</div>

<ul class="nav nav-tabs mb-3" id="catalogTabs">
    <li class="nav-item"><button class="nav-link active" type="button" data-catalog-tab="categories">Categories</button></li>
    <li class="nav-item"><button class="nav-link" type="button" data-catalog-tab="units">UOM / Units</button></li>
    <li class="nav-item"><button class="nav-link" type="button" data-catalog-tab="brands">Brands</button></li>
</ul>

<div id="categoriesPane" class="card pos-card border-0 shadow-sm">
    <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="input-group pos-input-group" style="max-width: 320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="categorySearch" placeholder="Search categories...">
            </div>
            <select class="form-select pos-select" id="categoryPerPage" style="max-width: 140px;">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table pos-table align-middle">
                <thead>
                    <tr>
                        <th class="pos-sortable" data-sort="category_name">Name <i class="bi bi-arrow-down-up"></i></th>
                        <th>Description</th>
                        <th class="pos-sortable" data-sort="is_active">Status <i class="bi bi-arrow-down-up"></i></th>
                        <th class="pos-sortable" data-sort="created_at">Created <i class="bi bi-arrow-down-up"></i></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="categoryPageInfo"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="categoryPagination"></ul></nav>
        </div>

    </div>
</div>

<div id="unitsPane" class="card pos-card border-0 shadow-sm d-none"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h6 mb-0">Unit of Measure</h2><p class="text-muted small mb-0">Examples: pc, pcs, gal, ext.</p></div><button class="btn pos-btn-primary btn-sm" type="button" data-catalog-add="unit"><i class="bi bi-plus-lg me-1"></i>Add Unit</button></div>
    <div class="table-responsive"><table class="table pos-table align-middle mb-0"><thead><tr><th>Unit</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody id="unitTableBody"></tbody></table></div>
</div></div>
<div id="brandsPane" class="card pos-card border-0 shadow-sm d-none"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h6 mb-0">Brands</h2><p class="text-muted small mb-0">Brands can be selected when creating or editing products.</p></div><button class="btn pos-btn-primary btn-sm" type="button" data-catalog-add="brand"><i class="bi bi-plus-lg me-1"></i>Add Brand</button></div>
    <div class="table-responsive"><table class="table pos-table align-middle mb-0"><thead><tr><th>Brand</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody id="brandTableBody"></tbody></table></div>
</div></div>

<div class="modal fade" id="catalogModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form id="catalogForm"><div class="modal-header"><h5 class="modal-title" id="catalogModalTitle">Catalog entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger d-none" id="catalogAlert"></div><input type="hidden" name="type" id="catalogType"><input type="hidden" name="id" id="catalogId"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" id="catalogName" maxlength="100" required></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="catalogActive" checked><label class="form-check-label" for="catalogActive">Active</label></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn pos-btn-primary" type="submit">Save</button></div></form></div></div></div>

<!-- Add / Edit modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="categoryForm" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::escape($csrfToken) ?>">
                <input type="hidden" id="categoryId" name="category_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="categoryFormAlert" class="alert alert-danger d-none"></div>

                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="category_name" maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3" maxlength="255"></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="categoryIsActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="categoryIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pos-btn-primary" id="categorySaveBtn">
                        <span class="btn-label">Save Category</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
