<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CatalogController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/AdminController.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    addCorsHeaders();
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($uri, '/')), fn($segment) => $segment !== ''));

if (empty($segments) || $segments[0] !== 'api') {
    jsonResponse(['ok' => false, 'message' => 'Not found'], 404);
}

$module = $segments[1] ?? null;
$method = $segments[2] ?? null;

switch ($module) {
    case 'auth':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'register') { AuthController::register(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'login') { AuthController::login(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'me') { AuthController::me(); }
        break;

    case 'catalog':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'categories') { CatalogController::listCategories(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'categories') { CatalogController::createCategory(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'products') { CatalogController::listProducts(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'products') { CatalogController::createProduct(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'featured') { CatalogController::getFeaturedProducts(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($segments) >= 4 && $segments[2] === 'products' && is_numeric($segments[3])) {
            $_GET['id'] = $segments[3];
            CatalogController::getProductById();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($segments) >= 4 && $segments[2] === 'products' && is_numeric($segments[3])) {
            $_GET['id'] = $segments[3];
            CatalogController::updateProduct();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && count($segments) >= 4 && $segments[2] === 'products' && is_numeric($segments[3])) {
            $_GET['id'] = $segments[3];
            CatalogController::deleteProduct();
        }
        break;

    case 'customer':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'cart') { CustomerController::getCart(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'cart') { CustomerController::addToCart(); }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $method === 'cart' && isset($_GET['itemId'])) { CustomerController::updateCartItem(); }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $method === 'cart' && isset($_GET['itemId'])) { CustomerController::removeCartItem(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'orders') { CustomerController::createOrder(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'orders') { CustomerController::getOrders(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'reviews') { CustomerController::createReview(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'reviews') { CustomerController::getProductReviews(); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $method === 'contacts') { CustomerController::submitContact(); }
        break;

    case 'admin':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'overview') { AdminController::overview(); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $method === 'users') { AdminController::listUsers(); }
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && $method === 'users') { AdminController::updateUserRole(); }
        break;
}

jsonResponse(['ok' => false, 'message' => 'Route not found'], 404);
