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

            $recentOrders = $pdo->query('SELECT o.*, u.name AS user_name, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();

            jsonResponse([
                'ok' => true,
                'metrics' => [
                    'totalUsers' => (int) $totalUsers,
                    'totalProducts' => (int) $totalProducts,
                    'totalOrders' => (int) $totalOrders,
                    'totalRevenue' => (float) $totalRevenue,
                ],
                'recentOrders' => $recentOrders,
            ]);
        } catch (\Throwable $e) {
            dbError($e);
        }
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
}
