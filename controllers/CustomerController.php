<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class CustomerController {
    public static function getCart(): void {
        $user = requireAuth();

        try {
            $pdo = getDb();
            $cartStmt = $pdo->prepare('SELECT * FROM carts WHERE user_id = :user_id LIMIT 1');
            $cartStmt->execute([':user_id' => $user['userId']]);
            $cart = $cartStmt->fetch();

            if (!$cart) {
                jsonResponse(['ok' => true, 'cart' => ['items' => []]]);
            }

            $itemStmt = $pdo->prepare('SELECT ci.*, p.name, p.slug, p.price, (SELECT url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = :cart_id');
            $itemStmt->execute([':cart_id' => $cart['id']]);
            $items = $itemStmt->fetchAll();

            jsonResponse(['ok' => true, 'cart' => ['id' => (int) $cart['id'], 'items' => $items]]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function addToCart(): void {
        $user = requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $productId = (int) ($input['productId'] ?? 0);
        $quantity = max(1, (int) ($input['quantity'] ?? 1));

        if (!$productId) {
            jsonResponse(['ok' => false, 'message' => 'Product ID is required'], 400);
        }

        try {
            $pdo = getDb();
            $cartStmt = $pdo->prepare('SELECT * FROM carts WHERE user_id = :user_id LIMIT 1');
            $cartStmt->execute([':user_id' => $user['userId']]);
            $cart = $cartStmt->fetch();

            $cartId = $cart['id'] ?? null;
            if (!$cartId) {
                $insertCart = $pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)');
                $insertCart->execute([':user_id' => $user['userId']]);
                $cartId = (int) $pdo->lastInsertId();
            }

            $exists = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id LIMIT 1');
            $exists->execute([':cart_id' => $cartId, ':product_id' => $productId]);

            if ($exists->fetch()) {
                $update = $pdo->prepare('UPDATE cart_items SET quantity = quantity + :qty WHERE cart_id = :cart_id AND product_id = :product_id');
                $update->execute([':qty' => $quantity, ':cart_id' => $cartId, ':product_id' => $productId]);
            } else {
                $insert = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)');
                $insert->execute([':cart_id' => $cartId, ':product_id' => $productId, ':quantity' => $quantity]);
            }

            jsonResponse(['ok' => true, 'message' => 'Item added to cart']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function updateCartItem(): void {
        $user = requireAuth();
        $id = (int) ($_GET['itemId'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $quantity = max(1, (int) ($input['quantity'] ?? 1));

        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Cart item id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('UPDATE cart_items ci JOIN carts c ON c.id = ci.cart_id SET ci.quantity = :quantity WHERE ci.id = :id AND c.user_id = :user_id');
            $stmt->execute([':quantity' => $quantity, ':id' => $id, ':user_id' => $user['userId']]);
            jsonResponse(['ok' => true, 'message' => 'Cart item updated']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function removeCartItem(): void {
        $user = requireAuth();
        $id = (int) ($_GET['itemId'] ?? 0);
        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Cart item id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE ci.id = :id AND c.user_id = :user_id');
            $stmt->execute([':id' => $id, ':user_id' => $user['userId']]);
            jsonResponse(['ok' => true, 'message' => 'Cart item removed']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function createOrder(): void {
        $user = requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $pdo = getDb();
            $cartStmt = $pdo->prepare('SELECT * FROM carts WHERE user_id = :user_id LIMIT 1');
            $cartStmt->execute([':user_id' => $user['userId']]);
            $cart = $cartStmt->fetch();

            if (!$cart) {
                jsonResponse(['ok' => false, 'message' => 'Cart is empty'], 400);
            }

            $itemsStmt = $pdo->prepare('SELECT ci.*, p.name, p.price FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = :cart_id');
            $itemsStmt->execute([':cart_id' => $cart['id']]);
            $items = $itemsStmt->fetchAll();

            if (empty($items)) {
                jsonResponse(['ok' => false, 'message' => 'Cart is empty'], 400);
            }

            $total = 0.0;
            foreach ($items as $item) {
                $total += ((float) $item['price']) * (int) $item['quantity'];
            }

            $pdo->beginTransaction();
            $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, total, shipping_address, payment_method, payment_status, status, notes) VALUES (:user_id, :total, :shipping_address, :payment_method, :payment_status, :status, :notes)');
            $orderStmt->execute([
                ':user_id' => $user['userId'],
                ':total' => $total,
                ':shipping_address' => $input['shippingAddress'] ?? null,
                ':payment_method' => $input['paymentMethod'] ?? 'BANK_TRANSFER',
                ':payment_status' => 'PENDING',
                ':status' => 'PENDING',
                ':notes' => $input['notes'] ?? null,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, title, quantity, price) VALUES (:order_id, :product_id, :title, :quantity, :price)');
                $insertItem->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':title' => $item['name'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price'],
                ]);
            }

            $deleteItems = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $deleteItems->execute([':cart_id' => $cart['id']]);
            $pdo->commit();

            jsonResponse(['ok' => true, 'message' => 'Order created successfully', 'orderId' => $orderId], 201);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            dbError($e);
        }
    }

    public static function getOrders(): void {
        $user = requireAuth();

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC');
            $stmt->execute([':user_id' => $user['userId']]);
            $orders = $stmt->fetchAll();

            foreach ($orders as &$order) {
                $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
                $itemStmt->execute([':order_id' => $order['id']]);
                $order['items'] = $itemStmt->fetchAll();
            }

            jsonResponse(['ok' => true, 'orders' => $orders]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function createReview(): void {
        $user = requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $productId = (int) ($input['productId'] ?? 0);
        $rating = (int) ($input['rating'] ?? 0);

        if (!$productId || !$rating) {
            jsonResponse(['ok' => false, 'message' => 'Product ID and rating are required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (:user_id, :product_id, :rating, :comment) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)');
            $stmt->execute([
                ':user_id' => $user['userId'],
                ':product_id' => $productId,
                ':rating' => $rating,
                ':comment' => trim($input['comment'] ?? ''),
            ]);

            jsonResponse(['ok' => true, 'message' => 'Review saved successfully']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function getProductReviews(): void {
        $productId = (int) ($_GET['productId'] ?? 0);
        if (!$productId) {
            jsonResponse(['ok' => false, 'message' => 'Product id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = :product_id ORDER BY r.created_at DESC');
            $stmt->execute([':product_id' => $productId]);
            jsonResponse(['ok' => true, 'reviews' => $stmt->fetchAll()]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function submitContact(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');

        if (!$name || !$email || !$subject || !$message) {
            jsonResponse(['ok' => false, 'message' => 'Please complete all required fields'], 400);
        }

        $userId = null;
        $token = getBearerToken();
        if ($token) {
            $decoded = verifyJwt($token);
            if ($decoded) {
                $userId = $decoded['userId'] ?? null;
            }
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('INSERT INTO contacts (user_id, name, email, phone, subject, message) VALUES (:user_id, :name, :email, :phone, :subject, :message)');
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $name,
                ':email' => $email,
                ':phone' => trim($input['phone'] ?? '') ?: null,
                ':subject' => $subject,
                ':message' => $message,
            ]);

            jsonResponse(['ok' => true, 'message' => 'Message sent successfully'], 201);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }
}
