# S925 PHP + MySQL API

Native PHP backend untuk frontend aplikasi S925.

## Cara pakai
1. Buat database MySQL bernama `s925_db`
2. Import file `schema.sql`
3. Jalankan server:
   `php -S localhost:8000`
4. Base URL API:
   `http://localhost:8000/api`

## Admin default
- Email: `admin@s925.com`
- Password: `password`

## Endpoint utama

### 1. Auth

#### 1.1 Register
- Method: `POST`
- URL: `/api/auth/register`
- Body:
```json
{
  "name": "Rizki",
  "email": "rizki@example.com",
  "password": "123456",
  "phone": "081234567890"
}
```
- Response `201`:
```json
{
  "ok": true,
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "Rizki",
    "email": "rizki@example.com",
    "phone": "081234567890",
    "role": "CUSTOMER"
  },
  "token": "eyJ..."
}
```

#### 1.2 Login
- Method: `POST`
- URL: `/api/auth/login`
- Body:
```json
{
  "email": "rizki@example.com",
  "password": "123456"
}
```
- Response:
```json
{
  "ok": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Rizki",
    "email": "rizki@example.com",
    "phone": "081234567890",
    "role": "CUSTOMER"
  },
  "token": "eyJ..."
}
```

#### 1.3 Profile user login
- Method: `GET`
- URL: `/api/auth/me`
- Header:
```http
Authorization: Bearer <token>
```
- Response:
```json
{
  "ok": true,
  "user": {
    "id": 1,
    "name": "Rizki",
    "email": "rizki@example.com",
    "phone": "081234567890",
    "role": "CUSTOMER",
    "is_active": 1,
    "created_at": "2026-10-27 08:00:00"
  }
}
```

### 2. Catalog

#### 2.1 List categories
- Method: `GET`
- URL: `/api/catalog/categories`
- Response:
```json
{
  "ok": true,
  "categories": [
    {
      "id": 1,
      "name": "Perhiasan",
      "slug": "perhiasan",
      "description": "Collection perhiasan",
      "product_count": 5
    }
  ]
}
```

#### 2.2 Create category
- Method: `POST`
- URL: `/api/catalog/categories`
- Role: admin
- Header:
```http
Authorization: Bearer <admin_token>
```
- Body:
```json
{
  "name": "Cincin",
  "description": "Cincin emas dan silver"
}
```

#### 2.3 List products
- Method: `GET`
- URL: `/api/catalog/products`
- Query params opsional:
  - `category`
  - `featured=true`
  - `search=keyword`
- Contoh:
  - `/api/catalog/products?featured=true`
  - `/api/catalog/products?category=perhiasan`
  - `/api/catalog/products?search=cincin`
- Response:
```json
{
  "ok": true,
  "products": [
    {
      "id": 1,
      "category_id": 1,
      "category_name": "Perhiasan",
      "category_slug": "perhiasan",
      "name": "Cincin Silver 925",
      "slug": "cincin-silver-925",
      "description": "Cincin premium silver 925",
      "price": "500000.00",
      "compare_price": "700000.00",
      "stock": 10,
      "is_featured": 1,
      "is_active": 1,
      "images": [
        {
          "id": 1,
          "product_id": 1,
          "url": "https://example.com/image.jpg",
          "alt_text": "Cincin silver"
        }
      ],
      "avg_rating": 4.5,
      "total_reviews": 2
    }
  ]
}
```

#### 2.4 Get product by id
- Method: `GET`
- URL: `/api/catalog/products?id=1`
- Response:
```json
{
  "ok": true,
  "product": {
    "id": 1,
    "name": "Cincin Silver 925",
    "description": "...",
    "price": "500000.00",
    "compare_price": "700000.00",
    "stock": 10,
    "images": [],
    "reviews": [],
    "avg_rating": 0,
    "total_reviews": 0
  }
}
```

#### 2.5 Create product
- Method: `POST`
- URL: `/api/catalog/products`
- Role: admin
- Header:
```http
Authorization: Bearer <admin_token>
```
- Body:
```json
{
  "name": "Anting Silver 925",
  "slug": "anting-silver-925",
  "description": "Anting premium silver 925",
  "price": 450000,
  "comparePrice": 600000,
  "stock": 12,
  "categoryId": 1,
  "isFeatured": true,
  "isActive": true
}
```

#### 2.6 Update product
- Method: `PUT`
- URL: `/api/catalog/products?id=1`
- Role: admin
- Body:
```json
{
  "name": "Anting Silver 925 Premium",
  "price": 480000,
  "stock": 8
}
```

#### 2.7 Delete product
- Method: `DELETE`
- URL: `/api/catalog/products?id=1`
- Role: admin

#### 2.8 Featured products
- Method: `GET`
- URL: `/api/catalog/featured`

### 3. Customer

#### 3.1 Get cart
- Method: `GET`
- URL: `/api/customer/cart`
- Header:
```http
Authorization: Bearer <token>
```

#### 3.2 Add item to cart
- Method: `POST`
- URL: `/api/customer/cart`
- Header:
```http
Authorization: Bearer <token>
```
- Body:
```json
{
  "productId": 1,
  "quantity": 2
}
```

#### 3.3 Update cart item quantity
- Method: `PUT`
- URL: `/api/customer/cart?itemId=1`
- Header:
```http
Authorization: Bearer <token>
```
- Body:
```json
{
  "quantity": 3
}
```

#### 3.4 Remove cart item
- Method: `DELETE`
- URL: `/api/customer/cart?itemId=1`
- Header:
```http
Authorization: Bearer <token>
```

#### 3.5 Create order
- Method: `POST`
- URL: `/api/customer/orders`
- Header:
```http
Authorization: Bearer <token>
```
- Body:
```json
{
  "shippingAddress": "Jl. Merdeka No. 12, Jakarta",
  "paymentMethod": "BANK_TRANSFER",
  "notes": "Tolong kirim hari ini"
}
```

#### 3.6 Get orders
- Method: `GET`
- URL: `/api/customer/orders`
- Header:
```http
Authorization: Bearer <token>
```

#### 3.7 Create review
- Method: `POST`
- URL: `/api/customer/reviews`
- Header:
```http
Authorization: Bearer <token>
```
- Body:
```json
{
  "productId": 1,
  "rating": 5,
  "comment": "Produk bagus dan cepat sampai"
}
```

#### 3.8 Get product reviews
- Method: `GET`
- URL: `/api/customer/reviews?productId=1`

#### 3.9 Submit contact form
- Method: `POST`
- URL: `/api/customer/contacts`
- Body:
```json
{
  "name": "Rizki",
  "email": "rizki@example.com",
  "phone": "081234567890",
  "subject": "Pertanyaan Produk",
  "message": "Apakah stok masih ada?"
}
```

### 4. Admin

#### 4.1 Dashboard overview
- Method: `GET`
- URL: `/api/admin/overview`
- Role: admin
- Header:
```http
Authorization: Bearer <admin_token>
```

#### 4.2 List users
- Method: `GET`
- URL: `/api/admin/users`
- Role: admin

#### 4.3 Update user role
- Method: `PATCH`
- URL: `/api/admin/users?id=2`
- Role: admin
- Body:
```json
{
  "role": "ADMIN"
}
```

## Status code umum
- `200 OK`
- `201 Created`
- `400 Bad Request`
- `401 Unauthorized`
- `403 Forbidden`
- `404 Not Found`
- `409 Conflict`
- `500 Internal Server Error`

## Catatan
Backend ini dibuat untuk frontend agar mudah dipanggil dengan JSON dan JWT. Untuk production, ganti secret key, atur CORS, dan gunakan konfigurasi MySQL yang aman.

## Struktur project
- `config.php`
- `db.php`
- `helpers.php`
- `schema.sql`
- `index.php`
- `controllers/AuthController.php`
- `controllers/CatalogController.php`
- `controllers/CustomerController.php`
- `controllers/AdminController.php`

## Arsip / catatan
- Akses API via: `http://localhost:8000/api`
- Admin default:
  - Email: `admin@s925.com`
  - Password: `password`
