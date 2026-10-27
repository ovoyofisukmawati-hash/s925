import { Router } from 'express';

import {
  listCategories,
  createCategory,
  listProducts,
  getProductById,
  createProduct,
  updateProduct,
  deleteProduct,
  getFeaturedProducts,
} from '../controllers/catalog.controller.js';
import { protect, requireAdmin } from '../middleware/auth.js';

const router = Router();

router.get('/categories', listCategories);
router.post('/categories', protect, requireAdmin, createCategory);
router.get('/products', listProducts);
router.get('/products/featured', getFeaturedProducts);
router.get('/products/:id', getProductById);
router.post('/products', protect, requireAdmin, createProduct);
router.put('/products/:id', protect, requireAdmin, updateProduct);
router.delete('/products/:id', protect, requireAdmin, deleteProduct);

export default router;
