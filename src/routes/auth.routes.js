import { prisma } from '../lib/prisma.js';

export const getAdminOverview = async (req, res, next) => {
  try {
    const [totalUsers, totalProducts, totalOrders, totalRevenue] = await Promise.all([
      prisma.user.count(),
      prisma.product.count(),
      prisma.order.count(),
      prisma.order.aggregate({ _sum: { total: true } }),
    ]);

    const recentOrders = await prisma.order.findMany({
      take: 5,
      orderBy: { createdAt: 'desc' },
      include: { user: { select: { id: true, name: true, email: true } } },
    });

    return res.json({
      ok: true,
      metrics: {
        totalUsers,
        totalProducts,
        totalOrders,
        totalRevenue: totalRevenue._sum.total || 0,
      },
      recentOrders,
    });
  } catch (error) {
    next(error);
  }
};

export const listUsers = async (req, res, next) => {
  try {
    const users = await prisma.user.findMany({
      select: {
        id: true,
        name: true,
        email: true,
        phone: true,
        role: true,
        isActive: true,
        createdAt: true,
      },
      orderBy: { createdAt: 'desc' },
    });

    return res.json({ ok: true, users });
  } catch (error) {
    next(error);
  }
};

export const updateUserRole = async (req, res, next) => {
  try {
    const { role } = req.body;

    const user = await prisma.user.update({
      where: { id: Number(req.params.id) },
      data: { role },
    });

    return res.json({ ok: true, user });
  } catch (error) {
    next(error);
  }
};
