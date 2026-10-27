<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class CatalogController {
    public static function listCategories(): void {
        try {
            $pdo = getDb();
            $stmt = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.created_at DESC');
            jsonResponse(['ok' => true, 'categories' => $stmt->fetchAll()]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function createCategory(): void {
        requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        if (!$name) {
            jsonResponse(['ok' => false, 'message' => 'Category name is required'], 400);
        }

        try {
            $pdo = getDb();
            $slug = slugify($name);
            $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)');
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description ?: null,
            ]);

            jsonResponse([
                'ok' => true,
                'category' => ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'slug' => $slug],
            ], 201);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function listProducts(): void {
        $query = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1';
        $params = [];
        $where = [];

        if (!empty($_GET['category'])) {
            $where[] = 'c.slug = :category';
            $params[':category'] = $_GET['category'];
        }
        if (!empty($_GET['featured']) && $_GET['featured'] === 'true') {
            $where[] = 'p.is_featured = 1';
        }
        if (!empty($_GET['search'])) {
            $where[] = '(p.name LIKE :search OR p.description LIKE :search2)';
            $params[':search'] = '%' . $_GET['search'] . '%';
            $params[':search2'] = '%' . $_GET['search'] . '%';
        }
        if (!empty($where)) {
            $query .= ' AND ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY p.created_at DESC';

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            foreach ($products as &$product) {
                $imageStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :product_id ORDER BY id ASC');
                $imageStmt->execute([':product_id' => $product['id']]);
                $product['images'] = $imageStmt->fetchAll();

                $reviewStmt = $pdo->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id = :product_id');
                $reviewStmt->execute([':product_id' => $product['id']]);
                $review = $reviewStmt->fetch();
                $product['avg_rating'] = (float) ($review['avg_rating'] ?? 0);
                $product['total_reviews'] = (int) ($review['total_reviews'] ?? 0);
            }

            jsonResponse(['ok' => true, 'products' => $products]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function getProductById(): void {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Product id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $product = $stmt->fetch();

            if (!$product) {
                jsonResponse(['ok' => false, 'message' => 'Product not found'], 404);
            }

            $imageStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :product_id ORDER BY id ASC');
            $imageStmt->execute([':product_id' => $id]);
            $product['images'] = $imageStmt->fetchAll();

            $reviewStmt = $pdo->prepare('SELECT r.*, u.name AS user_name FROM reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.product_id = :product_id ORDER BY r.created_at DESC');
            $reviewStmt->execute([':product_id' => $id]);
            $product['reviews'] = $reviewStmt->fetchAll();

            $ratingStmt = $pdo->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id = :product_id');
            $ratingStmt->execute([':product_id' => $id]);
            $rating = $ratingStmt->fetch();
            $product['avg_rating'] = (float) ($rating['avg_rating'] ?? 0);
            $product['total_reviews'] = (int) ($rating['total_reviews'] ?? 0);

            jsonResponse(['ok' => true, 'product' => $product]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function createProduct(): void {
        requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        $price = (float) ($input['price'] ?? 0);
        $categoryId = (int) ($input['categoryId'] ?? 0);

        if (!$name || !$price || !$categoryId) {
            jsonResponse(['ok' => false, 'message' => 'Name, price, and categoryId are required'], 400);
        }

        try {
            $pdo = getDb();
            $slug = trim($input['slug'] ?? slugify($name));
            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, compare_price, stock, is_featured, is_active) VALUES (:category_id, :name, :slug, :description, :price, :compare_price, :stock, :is_featured, :is_active)');
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $input['description'] ?? null,
                ':price' => $price,
                ':compare_price' => isset($input['comparePrice']) ? (float) $input['comparePrice'] : null,
                ':stock' => (int) ($input['stock'] ?? 0),
                ':is_featured' => !empty($input['isFeatured']) ? 1 : 0,
                ':is_active' => !isset($input['isActive']) || $input['isActive'] !== false ? 1 : 0,
            ]);

            jsonResponse(['ok' => true, 'message' => 'Product created successfully'], 201);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function updateProduct(): void {
        requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Product id is required'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $updates = [];
        $params = [':id' => $id];

        foreach (['name' => 'name', 'slug' => 'slug', 'description' => 'description', 'price' => 'price', 'comparePrice' => 'compare_price', 'stock' => 'stock', 'categoryId' => 'category_id', 'isFeatured' => 'is_featured', 'isActive' => 'is_active'] as $key => $column) {
            if (array_key_exists($key, $input)) {
                $updates[] = $column . ' = :' . $column;
                if ($key === 'comparePrice') {
                    $params[':' . $column] = (float) $input[$key];
                } elseif ($key === 'stock') {
                    $params[':' . $column] = (int) $input[$key];
                } elseif ($key === 'categoryId') {
                    $params[':' . $column] = (int) $input[$key];
                } elseif ($key === 'isFeatured') {
                    $params[':' . $column] = !empty($input[$key]) ? 1 : 0;
                } elseif ($key === 'isActive') {
                    $params[':' . $column] = $input[$key] ? 1 : 0;
                } elseif ($key === 'price') {
                    $params[':' . $column] = (float) $input[$key];
                } else {
                    $params[':' . $column] = $input[$key];
                }
            }
        }

        if (empty($updates)) {
            jsonResponse(['ok' => false, 'message' => 'No fields to update'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = :id');
            $stmt->execute($params);
            jsonResponse(['ok' => true, 'message' => 'Product updated successfully']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function deleteProduct(): void {
        requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Product id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            jsonResponse(['ok' => true, 'message' => 'Product deleted successfully']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function getFeaturedProducts(): void {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND is_featured = 1 ORDER BY created_at DESC LIMIT 8');
            $stmt->execute();
            $products = $stmt->fetchAll();

            foreach ($products as &$product) {
                $img = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY id ASC LIMIT 1');
                $img->execute([':id' => $product['id']]);
                $product['image'] = $img->fetch();
            }

            jsonResponse(['ok' => true, 'products' => $products]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }
}
