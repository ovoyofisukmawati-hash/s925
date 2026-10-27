import { prisma } from '../lib/prisma.js';

export const createReview = async (req, res, next) => {
  try {
    const { productId, rating, comment } = req.body;

    if (!productId || !rating) {
      return res.status(400).json({ ok: false, message: 'Product ID and rating are required' });
    }

    const review = await prisma.review.upsert({
      where: {
        userId_productId: {
          userId: req.user.userId,
          productId: Number(productId),
        },
      },
      update: {
        rating: Number(rating),
        comment,
      },
      create: {
        userId: req.user.userId,
        productId: Number(productId),
        rating: Number(rating),
        comment,
      },
    });

    return res.status(201).json({ ok: true, review });
  } catch (error) {
    next(error);
  }
};

export const getProductReviews = async (req, res, next) => {
  try {
    const reviews = await prisma.review.findMany({
      where: { productId: Number(req.params.productId) },
      include: { user: { select: { id: true, name: true } } },
    });

    return res.json({ ok: true, reviews });
  } catch (error) {
    next(error);
  }
};

export const submitContact = async (req, res, next) => {
  try {
    const { name, email, phone, subject, message } = req.body;

    if (!name || !email || !subject || !message) {
      return res.status(400).json({ ok: false, message: 'Please complete all required fields' });
    }

    const contact = await prisma.contact.create({
      data: {
        userId: req.user?.userId || null,
        name,
        email,
        phone,
        subject,
        message,
      },
    });

    return res.status(201).json({ ok: true, message: 'Message sent successfully', contact });
  } catch (error) {
    next(error);
  }
};
