import { Router } from 'express';

import { protect } from '../middleware/auth.js';
import {
  getCart,
  addToCart,
  updateCartItem,
  removeCartItem,
  createOrder,
  getOrders,
} from '../controllers/customer.controller.js';
import { createReview, getProductReviews, submitContact } from '../controllers/customer.controller.js';

const router = Router();

router.get('/cart', protect, getCart);
router.post('/cart/items', protect, addToCart);
router.put('/cart/items/:itemId', protect, updateCartItem);
router.delete('/cart/items/:itemId', protect, removeCartItem);
router.post('/orders', protect, createOrder);
router.get('/orders', protect, getOrders);

router.post('/reviews', protect, createReview);
router.get('/reviews/:productId', getProductReviews);
router.post('/contacts', submitContact);

export default router;
