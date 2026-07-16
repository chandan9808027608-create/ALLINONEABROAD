<?php
require_once __DIR__ . '/config.php';

session_start();

if (empty($_SESSION['orders_admin'])) {
    header('Location: orders.php');
    exit;
}

$conn = connectDb();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header('Location: products.php');
    exit;
}

$addResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'add_product') {
    $allowedCategories = ['luggage', 'kitchen', 'bedding'];
    $allowedExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $maxBytes = 5 * 1024 * 1024;

    $name = trim((string)($_POST['name'] ?? ''));
    $category = strtolower(trim((string)($_POST['category'] ?? '')));
    $price = is_numeric($_POST['price'] ?? null) ? (float)$_POST['price'] : null;
    $origRaw = $_POST['original_price'] ?? '';
    $origPrice = is_numeric($origRaw) && (float)$origRaw > 0 ? (float)$origRaw : null;
    $stock = is_numeric($_POST['stock'] ?? null) ? (int)$_POST['stock'] : null;
    $description = trim((string)($_POST['description'] ?? ''));
    $badge = trim((string)($_POST['badge'] ?? ''));
    $rating = is_numeric($_POST['rating'] ?? null) ? max(1, min(5, (int)$_POST['rating'])) : 5;
    $reviews = is_numeric($_POST['reviews'] ?? null) ? max(0, (int)$_POST['reviews']) : 0;

    $error = null;
    if ($name === '') {
        $error = 'Product name is required.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Category must be luggage, kitchen, or bedding.';
    } elseif ($price === null || $price <= 0) {
        $error = 'Price must be a positive number.';
    } elseif ($stock === null || $stock < 0) {
        $error = 'Stock must be zero or a positive whole number.';
    } elseif (!isset($_FILES['imageFile']) || $_FILES['imageFile']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose a product image to upload.';
    } elseif ($_FILES['imageFile']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Image upload failed. Please try again.';
    } elseif ($_FILES['imageFile']['size'] > $maxBytes) {
        $error = 'Image is too large — please keep it under 5MB.';
    } else {
        $ext = strtolower((string)pathinfo($_FILES['imageFile']['name'], PATHINFO_EXTENSION));
        $imageInfo = @getimagesize($_FILES['imageFile']['tmp_name']);
        if (!isset($allowedExt[$ext]) || $imageInfo === false || $imageInfo['mime'] !== $allowedExt[$ext]) {
            $error = 'Image must be a real JPG, PNG, WEBP, or GIF file.';
        }
    }

    if ($error === null) {
        $stmt = $conn->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'A product named "' . $name . '" already exists. Use a different name, or manage it via the catalog table below.';
        }
        $stmt->close();
    }

    if ($error === null) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        $filename = ($slug !== '' ? $slug : 'product') . '-' . substr(uniqid(), -6) . '.' . $ext;
        $destPath = __DIR__ . '/images/products/' . $filename;

        if (!move_uploaded_file($_FILES['imageFile']['tmp_name'], $destPath)) {
            $error = 'Could not save the uploaded image. Check that images/products/ is writable.';
        } else {
            $image = 'images/products/' . $filename;
            $stmt = $conn->prepare('INSERT INTO products (name, category, price, original_price, stock, image, description, badge, rating, reviews) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('ssddisssii', $name, $category, $price, $origPrice, $stock, $image, $description, $badge, $rating, $reviews);
            if (!$stmt->execute()) {
                $error = 'Database error while saving the product.';
                @unlink($destPath);
            }
            $stmt->close();
        }
    }

    $addResult = $error === null ? ['success' => true, 'name' => $name] : ['success' => false, 'message' => $error];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['stockFile'])) {
    $results = ['added' => 0, 'updated' => 0, 'errors' => []];
    $file = $_FILES['stockFile'];
    $allowedCategories = ['luggage', 'kitchen', 'bedding'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results['errors'][] = 'Upload failed. Please choose a file and try again.';
    } elseif (strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $results['errors'][] = 'Please upload a .csv file. In Excel or Google Sheets, use "Save As / Download → CSV".';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            $results['errors'][] = 'Could not read the uploaded file.';
        } else {
            $header = fgetcsv($handle);
            if ($header === false) {
                $results['errors'][] = 'The file appears to be empty.';
            } else {
                $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
                $required = ['name', 'category', 'price', 'stock', 'image'];
                $missing = array_values(array_diff($required, $header));
                if ($missing) {
                    $results['errors'][] = 'Missing required column(s): ' . implode(', ', $missing) . '. Download the template below to see the expected format.';
                } else {
                    $rowNum = 1;
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;
                        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                            continue;
                        }
                        if (count($row) !== count($header)) {
                            $results['errors'][] = "Row $rowNum: number of columns doesn't match the header row.";
                            continue;
                        }
                        $data = array_combine($header, $row);

                        $name = trim((string)($data['name'] ?? ''));
                        $category = strtolower(trim((string)($data['category'] ?? '')));
                        $price = is_numeric($data['price'] ?? null) ? (float)$data['price'] : null;
                        $origRaw = $data['original_price'] ?? '';
                        $origPrice = is_numeric($origRaw) && (float)$origRaw > 0 ? (float)$origRaw : null;
                        $stock = is_numeric($data['stock'] ?? null) ? (int)$data['stock'] : null;
                        $image = trim((string)($data['image'] ?? ''));
                        $description = trim((string)($data['description'] ?? ''));
                        $badge = trim((string)($data['badge'] ?? ''));
                        $rating = isset($data['rating']) && is_numeric($data['rating']) ? max(1, min(5, (int)$data['rating'])) : 5;
                        $reviews = isset($data['reviews']) && is_numeric($data['reviews']) ? max(0, (int)$data['reviews']) : 0;

                        if ($name === '' || !in_array($category, $allowedCategories, true) || $price === null || $price <= 0 || $stock === null || $stock < 0 || $image === '') {
                            $results['errors'][] = "Row $rowNum ($name): invalid or missing value — check name, category (luggage/kitchen/bedding), price, stock, and image.";
                            continue;
                        }

                        if (!preg_match('#^https?://#i', $image)) {
                            $image = 'images/products/' . ltrim($image, '/');
                        }

                        $stmt = $conn->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
                        $stmt->bind_param('s', $name);
                        $stmt->execute();
                        $stmt->store_result();
                        $exists = $stmt->num_rows > 0;
                        $stmt->close();

                        $stmt = $conn->prepare('INSERT INTO products (name, category, price, original_price, stock, image, description, badge, rating, reviews) VALUES (?,?,?,?,?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), original_price=VALUES(original_price), stock=VALUES(stock), image=VALUES(image), description=VALUES(description), badge=VALUES(badge), rating=VALUES(rating), reviews=VALUES(reviews)');
                        $stmt->bind_param('ssddisssii', $name, $category, $price, $origPrice, $stock, $image, $description, $badge, $rating, $reviews);
                        if ($stmt->execute()) {
                            $exists ? $results['updated']++ : $results['added']++;
                        } else {
                            $results['errors'][] = "Row $rowNum ($name): database error while saving.";
                        }
                        $stmt->close();
                    }
                }
            }
            fclose($handle);
        }
    }
}

$products = [];
$result = $conn->query('SELECT id, name, category, price, original_price, stock, image FROM products ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Products — ALL IN ONE ABROAD</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #111827; background: #f3f4f6; }

    .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 28px; display: flex; align-items: center; gap: 16px; }
    .logo { line-height: 1; }
    .logo-top { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.18em; color: #6b7280; text-transform: uppercase; }
    .logo-bottom { display: block; font-size: 17px; font-weight: 900; letter-spacing: 0.04em; color: #f97316; margin-top: 2px; }
    .topbar-title { font-size: 14px; font-weight: 600; color: #6b7280; padding-left: 16px; border-left: 1px solid #e5e7eb; }
    .nav-link { font-size: 13px; font-weight: 600; color: #6b7280; padding: 8px 14px; border-radius: 8px; border: 1.5px solid #e5e7eb; transition: background 0.15s; text-decoration: none; }
    .nav-link:hover { background: #f3f4f6; }
    .nav-link.active { background: #111827; color: #fff; border-color: #111827; }
    .logout-link { margin-left: auto; }

    .wrap { max-width: 1100px; margin: 0 auto; padding: 28px; }

    .panel { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
    .panel-head { padding: 18px 22px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .panel-head h2 { font-size: 16px; font-weight: 800; }
    .panel-body { padding: 22px; }

    .upload-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    input[type="file"] { font-size: 13px; }
    .btn { display: inline-flex; align-items: center; gap: 6px; background: #f97316; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: background 0.15s; text-decoration: none; }
    .btn:hover { background: #ea6c0a; }
    .btn-outline { background: transparent; color: #111827; border: 1.5px solid #e5e7eb; }
    .btn-outline:hover { background: #f3f4f6; }
    .btn-danger { background: transparent; color: #dc2626; border: 1.5px solid #fecaca; padding: 6px 12px; font-size: 12px; }
    .btn-danger:hover { background: #fef2f2; }

    .help-text { font-size: 12.5px; color: #6b7280; margin-top: 14px; line-height: 1.7; }
    .help-text code { background: #f3f4f6; padding: 1px 6px; border-radius: 4px; font-size: 12px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { margin-bottom: 14px; }
    .form-group label { font-size: 12px; font-weight: 600; color: #111827; display: block; margin-bottom: 6px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 13px; font-family: inherit; outline: none; transition: border-color 0.2s; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #f97316; }
    .form-group textarea { resize: vertical; min-height: 60px; }
    .alert { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
    .alert-success { background: #dcfce7; color: #16a34a; }
    .alert-error { background: #fef2f2; color: #dc2626; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }

    .summary { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 14px; }
    .summary-item { font-size: 13px; font-weight: 700; }
    .summary-item.added { color: #16a34a; }
    .summary-item.updated { color: #2563eb; }
    .summary-item.errors { color: #dc2626; }
    .error-list { background: #fef2f2; border-radius: 8px; padding: 12px 16px; font-size: 12.5px; color: #991b1b; max-height: 200px; overflow-y: auto; }
    .error-list li { margin-bottom: 4px; }

    .table-scroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 700px; }
    th { background: #f9fafb; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7280; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; vertical-align: middle; }
    tbody tr:hover { background: #fafafa; }
    tbody tr:last-child td { border-bottom: none; }
    .prod-thumb { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; background: #f3f4f6; }
    .prod-name-cell { display: flex; align-items: center; gap: 10px; }
    .cat-pill { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 2px 8px; border-radius: 100px; background: #f3f4f6; color: #6b7280; }
    .stock-pill { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 100px; }
    .stock-ok { background: #dcfce7; color: #16a34a; }
    .stock-low { background: #fef3c7; color: #b45309; }
    .stock-out { background: #fee2e2; color: #dc2626; }

    .empty { text-align: center; padding: 48px 24px; color: #6b7280; font-size: 13px; }

    @media (max-width: 640px) {
      .wrap { padding: 16px; }
    }
  </style>
</head>
<body>

  <div class="topbar">
    <div class="logo"><span class="logo-top">ALL IN ONE</span><span class="logo-bottom">ABROAD</span></div>
    <div class="topbar-title">Admin</div>
    <a href="orders.php" class="nav-link">Orders</a>
    <a href="products.php" class="nav-link active">Products</a>
    <a href="banner.php" class="nav-link">Banner</a>
    <a href="orders.php?logout=1" class="nav-link logout-link">Log out</a>
  </div>

  <div class="wrap">

    <div class="panel">
      <div class="panel-head">
        <h2>Import Stock from Spreadsheet</h2>
        <a href="products_template.csv" class="btn btn-outline" download>⬇ Download CSV Template</a>
      </div>
      <div class="panel-body">
        <?php if ($results): ?>
          <div class="summary">
            <span class="summary-item added"><?= $results['added'] ?> added</span>
            <span class="summary-item updated"><?= $results['updated'] ?> updated</span>
            <?php if ($results['errors']): ?><span class="summary-item errors"><?= count($results['errors']) ?> issue(s)</span><?php endif; ?>
          </div>
          <?php if ($results['errors']): ?>
            <ul class="error-list">
              <?php foreach ($results['errors'] as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="upload-row" style="<?= $results ? 'margin-top:18px;' : '' ?>">
          <input type="file" name="stockFile" accept=".csv" required/>
          <button type="submit" class="btn">Upload &amp; Import</button>
        </form>

        <div class="help-text">
          Upload a <code>.csv</code> file (export from Excel or Google Sheets as CSV — not <code>.xlsx</code>).<br/>
          Required columns: <code>name</code>, <code>category</code> (must be <code>luggage</code>, <code>kitchen</code>, or <code>bedding</code>), <code>price</code>, <code>stock</code>, <code>image</code>.<br/>
          Optional columns: <code>original_price</code> (for showing a discount), <code>description</code>, <code>badge</code> (e.g. "Best Seller"), <code>rating</code> (1–5), <code>reviews</code>.<br/>
          For <code>image</code>, use either a filename already uploaded to <code>images/products/</code> (e.g. <code>my-photo.jpg</code>) or a full <code>https://</code> image URL.<br/>
          Re-uploading a spreadsheet updates existing products that match by name, and adds any new ones — nothing is deleted automatically.
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2>Add a Single Product</h2>
      </div>
      <div class="panel-body">
        <?php if ($addResult): ?>
          <?php if ($addResult['success']): ?>
            <div class="alert alert-success">"<?= htmlspecialchars($addResult['name']) ?>" was added to the catalog.</div>
          <?php else: ?>
            <div class="alert alert-error"><?= htmlspecialchars($addResult['message']) ?></div>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="formAction" value="add_product"/>
          <div class="form-grid">
            <div class="form-group"><label>Product Name *</label><input type="text" name="name" required/></div>
            <div class="form-group">
              <label>Category *</label>
              <select name="category" required>
                <option value="">Select…</option>
                <option value="luggage">Luggage</option>
                <option value="kitchen">Kitchen</option>
                <option value="bedding">Bedding</option>
              </select>
            </div>
            <div class="form-group"><label>Price (Rs.) *</label><input type="number" name="price" min="0.01" step="0.01" required/></div>
            <div class="form-group"><label>Original Price (optional, for discount)</label><input type="number" name="original_price" min="0" step="0.01"/></div>
            <div class="form-group"><label>Stock Quantity *</label><input type="number" name="stock" min="0" step="1" required/></div>
            <div class="form-group"><label>Badge (optional, e.g. "Best Seller")</label><input type="text" name="badge" maxlength="50"/></div>
            <div class="form-group"><label>Rating (1–5, optional)</label><input type="number" name="rating" min="1" max="5" step="1" value="5"/></div>
            <div class="form-group"><label>Reviews Count (optional)</label><input type="number" name="reviews" min="0" step="1" value="0"/></div>
          </div>
          <div class="form-group"><label>Description (optional)</label><textarea name="description" maxlength="255"></textarea></div>
          <div class="form-group"><label>Product Image * (JPG, PNG, WEBP, or GIF — max 5MB)</label><input type="file" name="imageFile" accept=".jpg,.jpeg,.png,.webp,.gif" required/></div>
          <button type="submit" class="btn">Add Product</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2>Current Catalog (<?= count($products) ?>)</h2>
      </div>
      <?php if (empty($products)): ?>
        <div class="empty">No products yet. Upload a spreadsheet above to add your first items — or start from <a href="products_seed.csv" download>the current live catalog as a CSV</a> to seed it in one click.</div>
      <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <?php
                $stock = (int)$p['stock'];
                $stockClass = $stock === 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-ok');
              ?>
              <tr>
                <td>
                  <div class="prod-name-cell">
                    <img class="prod-thumb" src="<?= htmlspecialchars($p['image']) ?>" alt="" onerror="this.style.visibility='hidden'"/>
                    <span><?= htmlspecialchars($p['name']) ?></span>
                  </div>
                </td>
                <td><span class="cat-pill"><?= htmlspecialchars($p['category']) ?></span></td>
                <td>Rs. <?= number_format((float)$p['price'], 2) ?><?php if ($p['original_price']): ?> <span style="color:#9ca3af;text-decoration:line-through;font-size:12px;">Rs. <?= number_format((float)$p['original_price'], 2) ?></span><?php endif; ?></td>
                <td><span class="stock-pill <?= $stockClass ?>"><?= $stock ?> in stock</span></td>
                <td>
                  <form method="post" onsubmit="return confirm('Remove this product from the catalog?');">
                    <input type="hidden" name="formAction" value="delete"/>
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"/>
                    <button type="submit" class="btn btn-danger">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</body>
</html>
