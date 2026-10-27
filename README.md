import { Router } from 'express';

import { protect, requireAdmin } from '../middleware/auth.js';
import { getAdminOverview, listUsers, updateUserRole } from '../controllers/admin.controller.js';

const router = Router();

router.get('/overview', protect, requireAdmin, getAdminOverview);
router.get('/users', protect, requireAdmin, listUsers);
router.patch('/users/:id/role', protect, requireAdmin, updateUserRole);

export default router;
