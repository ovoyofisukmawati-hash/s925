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
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `GET /api/catalog/categories`
- `GET /api/catalog/products`
- `GET /api/catalog/products?id=1`
- `POST /api/catalog/products` (admin)
- `PUT /api/catalog/products?id=1` (admin)
- `DELETE /api/catalog/products?id=1` (admin)
- `GET /api/customer/cart`
- `POST /api/customer/cart`
- `POST /api/customer/orders`
- `GET /api/customer/orders`
- `POST /api/customer/reviews`
- `GET /api/customer/reviews?productId=1`
- `POST /api/customer/contacts`
- `GET /api/admin/overview`
- `GET /api/admin/users`
- `PATCH /api/admin/users?id=2`

## Catatan
Backend ini dibuat untuk frontend agar mudah dipanggil dengan JSON dan JWT. Untuk production, ganti secret key, atur CORS, dan gunakan konfigurasi MySQL yang aman.
