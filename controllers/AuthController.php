<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

class AuthController {
    public static function register(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $phone = trim($input['phone'] ?? '');

        if (!$name || !$email || !$password) {
            jsonResponse(['ok' => false, 'message' => 'Name, email, and password are required'], 400);
        }

        try {
            $pdo = getDb();
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);
            if ($check->fetch()) {
                jsonResponse(['ok' => false, 'message' => 'Email already registered'], 409);
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone) VALUES (:name, :email, :password, :phone)');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hash,
                ':phone' => $phone ?: null,
            ]);

            $userId = (int) $pdo->lastInsertId();
            $token = createJwt([
                'userId' => $userId,
                'email' => $email,
                'role' => 'CUSTOMER',
                'exp' => time() + 60 * 60 * 24 * 7,
            ]);

            jsonResponse([
                'ok' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => 'CUSTOMER',
                ],
                'token' => $token,
            ], 201);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function login(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(['ok' => false, 'message' => 'Email and password are required'], 400);
        }

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                jsonResponse(['ok' => false, 'message' => 'Invalid credentials'], 401);
            }

            if (!password_verify($password, $user['password'])) {
                jsonResponse(['ok' => false, 'message' => 'Invalid credentials'], 401);
            }

            $token = createJwt([
                'userId' => (int) $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + 60 * 60 * 24 * 7,
            ]);

            jsonResponse([
                'ok' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                ],
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }

    public static function me(): void {
        $user = requireAuth();

        try {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT id, name, email, phone, role, is_active, created_at FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $user['userId']]);
            $data = $stmt->fetch();

            if (!$data) {
                jsonResponse(['ok' => false, 'message' => 'User not found'], 404);
            }

            jsonResponse(['ok' => true, 'user' => $data]);
        } catch (\Throwable $e) {
            dbError($e);
        }
    }
}
