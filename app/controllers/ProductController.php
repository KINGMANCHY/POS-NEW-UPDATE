<?php
/**
 * app/controllers/ProductController.php
 * -----------------------------------------------------------------------
 * AJAX CRUD endpoint for Products, including image upload.
 * Same ?action=list|get|create|update|delete pattern as the other
 * modules, but create/update accept multipart/form-data (FormData in
 * JS) instead of a plain serialized form, since they may include a file.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ProductController
{
    private Product $productModel;
    private Category $categoryModel;
    private Supplier $supplierModel;
    private User $userModel;

    public function __construct()
    {
        $this->productModel  = new Product();
        $this->categoryModel = new Category();
        $this->supplierModel = new Supplier();
        $this->userModel     = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireLogin();

        switch ($_REQUEST['action'] ?? '') {
            case 'list':      $this->list(); break;
            case 'get':       $this->get(); break;
            case 'create':    $this->create(); break;
            case 'update':    $this->update(); break;
            case 'delete':    $this->delete(); break;
            case 'form_data': $this->formData(); break; // category/supplier dropdown options
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $categoryId = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null;

        $result = $this->productModel->paginate(
            Security::sanitize(trim($_GET['search'] ?? '')),
            $categoryId,
            Security::sanitize($_GET['sort_by'] ?? 'product_name'),
            Security::sanitize($_GET['sort_dir'] ?? 'ASC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );

        // Add a ready-to-use image URL to each row so the view never
        // has to know the upload path structure.
        foreach ($result['rows'] as &$row) {
            $row['image_url'] = $row['image_path'] ? UPLOAD_URL . $row['image_path'] : null;
        }
        unset($row);

        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $product = $id > 0 ? $this->productModel->find($id) : null;
        if (!$product) {
            Helper::jsonResponse(false, 'Product not found.', [], 404);
        }
        $product['image_url'] = $product['image_path'] ? UPLOAD_URL . $product['image_path'] : null;
        Helper::jsonResponse(true, '', ['product' => $product]);
    }

    /** Category + Supplier options for the modal's <select> fields. */
    private function formData(): void
    {
        $db = Database::getConnection();
        try { $units = $db->query("SELECT unit_name FROM UnitMeasures WHERE is_active = 1 ORDER BY unit_name")->fetchAll(); }
        catch (Exception $e) { $units = [['unit_name' => 'pc']]; }
        try { $brands = $db->query("SELECT brand_name FROM Brands WHERE is_active = 1 ORDER BY brand_name")->fetchAll(); }
        catch (Exception $e) { $brands = []; }
        Helper::jsonResponse(true, '', [
            'categories' => $this->categoryModel->allActive(),
            'suppliers'  => $this->supplierModel->allActive(),
            'suggested_code' => Helper::generateCode('PRD'),
            'units' => $units,
            'brands' => $brands,
        ]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();

        [$data, $error] = $this->validateInput(null);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        [$imagePath, $uploadError] = $this->handleImageUpload();
        if ($uploadError) {
            Helper::jsonResponse(false, $uploadError, [], 422);
        }
        $data['image_path'] = $imagePath;

        $id = $this->productModel->create($data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'PRODUCT_CREATE', "Created product #{$id}: {$data['product_name']}");

        Helper::jsonResponse(true, 'Product created successfully.', ['product_id' => $id]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['product_id'] ?? 0);
        $existing = $id > 0 ? $this->productModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Product not found.', [], 404);
        }

        [$data, $error] = $this->validateInput($id);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        [$imagePath, $uploadError] = $this->handleImageUpload();
        if ($uploadError) {
            Helper::jsonResponse(false, $uploadError, [], 422);
        }

        if ($imagePath) {
            // A new image was uploaded - remove the old file so we don't
            // silently accumulate orphaned files on every edit.
            $this->deleteImageFile($existing['image_path']);
            $data['image_path'] = $imagePath;
        } elseif (!empty($_POST['remove_image'])) {
            $this->deleteImageFile($existing['image_path']);
            $data['image_path'] = null;
        } else {
            $data['image_path'] = $existing['image_path']; // keep the current image
        }

        $this->productModel->update($id, $data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'PRODUCT_UPDATE', "Updated product #{$id}: {$data['product_name']}");

        Helper::jsonResponse(true, 'Product updated successfully.');
    }

    private function delete(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['product_id'] ?? 0);
        $existing = $id > 0 ? $this->productModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Product not found.', [], 404);
        }

        if ($this->productModel->isInUse($id)) {
            Helper::jsonResponse(false, 'This product appears in past sales and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $this->productModel->delete($id);
        $this->deleteImageFile($existing['image_path']);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'PRODUCT_DELETE', "Deleted product #{$id}: {$existing['product_name']}");

        Helper::jsonResponse(true, 'Product deleted successfully.');
    }

    // -------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------

    private function validateInput(?int $excludeId): array
    {
        $data = [
            'category_id'     => (int) ($_POST['category_id'] ?? 0) ?: null,
            'supplier_id'     => (int) ($_POST['supplier_id'] ?? 0) ?: null,
            'product_code'    => Security::sanitize(trim($_POST['product_code'] ?? '')),
            'barcode'         => Security::sanitize(trim($_POST['barcode'] ?? '')),
            'qr_code'         => Security::sanitize(trim($_POST['qr_code'] ?? '')),
            'product_name'    => Security::sanitize(trim($_POST['product_name'] ?? '')),
            'brand'           => Security::sanitize(trim($_POST['brand'] ?? '')),
            'cost_price'      => (float) ($_POST['cost_price'] ?? 0),
            'selling_price'   => (float) ($_POST['selling_price'] ?? 0),
            'tax_rate'        => (float) ($_POST['tax_rate'] ?? 0),
            'discount_rate'   => (float) ($_POST['discount_rate'] ?? 0),
            'unit'            => Security::sanitize(trim($_POST['unit'] ?? 'pc')) ?: 'pc',
            'stock_alert_qty' => (int) ($_POST['stock_alert_qty'] ?? 10),
            'expiration_date' => Security::sanitize(trim($_POST['expiration_date'] ?? '')),
            'is_active'       => !empty($_POST['is_active']),
        ];

        if ($data['product_name'] === '' || mb_strlen($data['product_name']) < 2) {
            return [$data, 'Product name is required (minimum 2 characters).'];
        }
        if ($data['product_code'] === '') {
            return [$data, 'Product code is required.'];
        }
        if ($this->productModel->codeExists($data['product_code'], $excludeId)) {
            return [$data, 'That product code is already in use.'];
        }
        if ($data['barcode'] !== '' && $this->productModel->barcodeExists($data['barcode'], $excludeId)) {
            return [$data, 'That barcode is already assigned to another product.'];
        }
        if ($data['cost_price'] < 0 || $data['selling_price'] < 0) {
            return [$data, 'Prices cannot be negative.'];
        }
        if ($data['tax_rate'] < 0 || $data['tax_rate'] > 100 || $data['discount_rate'] < 0 || $data['discount_rate'] > 100) {
            return [$data, 'Tax and discount rates must be between 0 and 100.'];
        }
        if ($data['stock_alert_qty'] < 0) {
            return [$data, 'Stock alert quantity cannot be negative.'];
        }

        return [$data, null];
    }

    // -------------------------------------------------------------
    // Image upload
    // -------------------------------------------------------------

    /**
     * Validates and stores an uploaded product image, if one was sent.
     * Returns [storedFilename|null, errorMessage|null]. Both null means
     * "no file was uploaded" (not an error - image is optional).
     */
    private function handleImageUpload(): array
    {
        if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Image upload failed (error code ' . $file['error'] . ').'];
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $maxMb = round(MAX_UPLOAD_SIZE / (1024 * 1024), 1);
            return [null, "Image is too large. Maximum size is {$maxMb}MB."];
        }

        // Never trust the client-supplied MIME type - inspect the actual
        // file content on disk.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMime, ALLOWED_IMAGE_TYPES, true)) {
            return [null, 'Only JPEG, PNG, or WEBP images are allowed.'];
        }

        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$realMime] ?? 'jpg';
        $filename = 'prod_' . bin2hex(random_bytes(12)) . '.' . $extension;

        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)) {
            return [null, 'Could not save the uploaded image. Check that the uploads folder is writable.'];
        }

        return [$filename, null];
    }

    private function deleteImageFile(?string $filename): void
    {
        if ($filename && is_file(UPLOAD_PATH . $filename)) {
            @unlink(UPLOAD_PATH . $filename);
        }
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new ProductController())->dispatch();
}
