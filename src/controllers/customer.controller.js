import { prisma } from '../lib/prisma.js';

export const getCart = async (req, res, next) => {
  try {
    const cart = await prisma.cart.findUnique({
      where: { userId: req.user.userId },
      include: {
        items: {
          include: {
            product: {
              include: { images: true },
            },
          },
        },
      },
    });

    if (!cart) {
      return res.json({ ok: true, cart: { items: [] } });
    }

    return res.json({ ok: true, cart });
  } catch (error) {
    next(error);
  }
};

export const addToCart = async (req, res, next) => {
  try {
    const { productId, quantity = 1 } = req.body;

    if (!productId) {
      return res.status(400).json({ ok: false, message: 'Product ID is required' });
    }

    let cart = await prisma.cart.findUnique({ where: { userId: req.user.userId } });

    if (!cart) {
      cart = await prisma.cart.create({ data: { userId: req.user.userId } });
    }

    const item = await prisma.cartItem.upsert({
      where: {
        cartId_productId: {
          cartId: cart.id,
          productId: Number(productId),
        },
      },
      update: {
        quantity: { increment: Number(quantity) },
      },
      create: {
        cartId: cart.id,
        productId: Number(productId),
        quantity: Number(quantity),
      },
    });

    return res.status(201).json({ ok: true, message: 'Item added to cart', item });
  } catch (error) {
    next(error);
  }
};

export const updateCartItem = async (req, res, next) => {
  try {
    const { quantity } = req.body;
    const itemId = Number(req.params.itemId);

    const item = await prisma.cartItem.update({
      where: { id: itemId },
      data: { quantity: Number(quantity) },
    });

    return res.json({ ok: true, item });
  } catch (error) {
    next(error);
  }
};

export const removeCartItem = async (req, res, next) => {
  try {
    const itemId = Number(req.params.itemId);

    await prisma.cartItem.delete({ where: { id: itemId } });

    return res.json({ ok: true, message: 'Cart item removed' });
  } catch (error) {
    next(error);
  }
};

export const createOrder = async (req, res, next) => {
  try {
    const { shippingAddress, paymentMethod, notes } = req.body;

    const cart = await prisma.cart.findUnique({
      where: { userId: req.user.userId },
      include: {
        items: {
          include: { product: true },
        },
      },
    });

    if (!cart || cart.items.length === 0) {
      return res.status(400).json({ ok: false, message: 'Cart is empty' });
    }

    const total = cart.items.reduce((sum, item) => sum + item.product.price * item.quantity, 0);

    const order = await prisma.order.create({
      data: {
        userId: req.user.userId,
        total,
        shippingAddress,
        paymentMethod: paymentMethod || 'BANK_TRANSFER',
        paymentStatus: 'PENDING',
        notes,
        items: {
          create: cart.items.map((item) => ({
            productId: item.productId,
            title: item.product.name,
            quantity: item.quantity,
            price: item.product.price,
          })),
        },
      },
      include: {
        items: true,
      },
    });

    await prisma.cartItem.deleteMany({ where: { cartId: cart.id } });

    return res.status(201).json({ ok: true, order });
  } catch (error) {
    next(error);
  }
};

export const getOrders = async (req, res, next) => {
  try {
    const orders = await prisma.order.findMany({
      where: { userId: req.user.userId },
      include: { items: true },
      orderBy: { createdAt: 'desc' },
    });

    return res.json({ ok: true, orders });
  } catch (error) {
    next(error);
  }
};
