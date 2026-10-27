<?php
require_once __DIR__ . '/config.php';

function addCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
}

function jsonResponse(array $data, int $httpCode = 200): void {
    addCorsHeaders();
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function createJwt(array $payload): string {
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $header . '.' . $payloadEncoded, JWT_SECRET, true);
    return $header . '.' . $payloadEncoded . '.' . base64UrlEncode($signature);
}

function verifyJwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;
    $expected = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true);
    $actual = base64UrlDecode($signature);

    if (!hash_equals($expected, $actual)) {
        return null;
    }

    $decoded = json_decode(base64UrlDecode($payload), true);
    if (!is_array($decoded)) {
        return null;
    }
    if (isset($decoded['exp']) && (int) $decoded['exp'] < time()) {
        return null;
    }

    return $decoded;
}

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function requireAuth(): array {
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(['ok' => false, 'message' => 'Authentication required'], 401);
    }

    $payload = verifyJwt($token);
    if (!$payload) {
        jsonResponse(['ok' => false, 'message' => 'Invalid or expired token'], 401);
    }

    return $payload;
}

function requireAdmin(): void {
    $user = requireAuth();
    if (($user['role'] ?? '') !== 'ADMIN') {
        jsonResponse(['ok' => false, 'message' => 'Admin access only'], 403);
    }
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function dbError(\Throwable $e): void {
    jsonResponse([
        'ok' => false,
        'message' => 'Server error',
        'debug' => $e->getMessage(),
    ], 500);
}
