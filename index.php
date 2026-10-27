<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class AdminController {
    public static function overview(): void {
        requireAdmin();

        try {
            $pdo = getDb();
            $totalUsers = $pdo->query('SELECT COUNT(*) AS total FROM users')->fetch()['total'];
            $totalProducts = $pdo->query('SELECT COUNT(*) AS total FROM products')->fetch()['total'];
            $totalOrders = $pdo->query('SELECT COUNT(*) AS total FROM orders')->fetch()['total'];
            $totalRevenue = $pdo->query('SELECT COALESCE(SUM(total), 0) AS total FROM orders')->fetch()['total'];
            $pendingOrders = $pdo->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'PENDING'")->fetch()['total'];
            $lowStock = $pdo->query('SELECT COUNT(*) AS total FROM products WHERE stock < 5')->fetch()['total'];

            $recentOrders = $pdo->query('SELECT o.*, u.name AS user_name, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();

            $topProducts = $pdo->query('SELECT p.id, p.name, p.price, COUNT(oi.id) AS total_sold, SUM(oi.quantity) AS quantity_sold FROM products p LEFT JOIN order_items oi ON oi.product_id = p.id GROUP BY p.id, p.name, p.price ORDER BY quantity_sold DESC, total_sold DESC LIMIT 5')->fetchAll();

            $salesTrend = $pdo->query("SELECT DATE(created_at) AS date, SUM(total) AS revenue FROM orders WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC")->fetchAll();

            jsonResponse([
                'ok' => true,
                'metrics' => [
                    'totalUsers' => (int) $totalUsers,
                    'totalProducts' => (int) $totalProducts,
                    'totalOrders' => (int) $totalOrders,
                    'totalRevenue' => (float) $totalRevenue,
                    'pendingOrders' => (int) $pendingOrders,
                    'lowStockProducts' => (int) $lowStock,
                ],
                'recentOrders' => $recentOrders,
                'topProducts' => $topProducts,
                'salesTrend' => $salesTrend,
            ]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function dashboard(): void {
        self::overview();
    }

    public static function listUsers(): void {
        requireAdmin();

        try {
            $pdo = getDb();
            $stmt = $pdo->query('SELECT id, name, email, phone, role, is_active, created_at FROM users ORDER BY created_at DESC');
            jsonResponse(['ok' => true, 'users' => $stmt->fetchAll()]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function updateUserRole(): void {
        requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $role = strtoupper(trim($input['role'] ?? ''));

        if (!$id || !in_array($role, ['CUSTOMER', 'ADMIN'], true)) {
            jsonResponse(['ok' => false, 'message' => 'Valid user id and role are required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            $stmt->execute([':role' => $role, ':id' => $id]);
            jsonResponse(['ok' => true, 'message' => 'User role updated']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function listOrders(): void {
        requireAdmin();

        try {
            $pdo = getDb();
            $stmt = $pdo->query('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC');
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

    public static function getOrderById(): void {
        requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Order id is required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();

            if (!$order) {
                jsonResponse(['ok' => false, 'message' => 'Order not found'], 404);
            }

            $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $itemStmt->execute([':order_id' => $id]);
            $order['items'] = $itemStmt->fetchAll();

            jsonResponse(['ok' => true, 'order' => $order]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function updateOrderStatus(): void {
        requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'Order id is required'], 400);
        }

        $allowedStatuses = ['PENDING', 'PAID', 'PACKED', 'SHIPPED', 'DELIVERED', 'CANCELLED'];
        $allowedPaymentStatuses = ['PENDING', 'SUCCESS', 'FAILED', 'REFUNDED'];

        $status = isset($input['status']) ? strtoupper(trim((string) $input['status'])) : null;
        $paymentStatus = isset($input['paymentStatus']) ? strtoupper(trim((string) $input['paymentStatus'])) : null;

        if ($status && !in_array($status, $allowedStatuses, true)) {
            jsonResponse(['ok' => false, 'message' => 'Invalid order status'], 400);
        }

        if ($paymentStatus && !in_array($paymentStatus, $allowedPaymentStatuses, true)) {
            jsonResponse(['ok' => false, 'message' => 'Invalid payment status'], 400);
        }

        try {
            $pdo = getDb();
            $updates = [];
            $params = [':id' => $id];

            if ($status) {
                $updates[] = 'status = :status';
                $params[':status'] = $status;
            }

            if ($paymentStatus) {
                $updates[] = 'payment_status = :payment_status';
                $params[':payment_status'] = $paymentStatus;
            }

            if (empty($updates)) {
                jsonResponse(['ok' => false, 'message' => 'No status changes provided'], 400);
            }

            $sql = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            jsonResponse(['ok' => true, 'message' => 'Order status updated successfully']);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function listContacts(): void {
        requireAdmin();

        try {
            $pdo = getDb();
            $stmt = $pdo->query('SELECT c.*, u.name AS user_name FROM contacts c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC');
            jsonResponse(['ok' => true, 'contacts' => $stmt->fetchAll()]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }
}
