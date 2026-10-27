import { prisma } from '../lib/prisma.js';

export const listCategories = async (req, res, next) => {
  try {
    const categories = await prisma.category.findMany({
      orderBy: { createdAt: 'desc' },
      include: {
        _count: {
          select: { products: true },
        },
      },
    });

    return res.json({ ok: true, categories });
  } catch (error) {
    next(error);
  }
};

export const createCategory = async (req, res, next) => {
  try {
    const { name, description } = req.body;

    if (!name) {
      return res.status(400).json({ ok: false, message: 'Category name is required' });
    }

    const slug = name
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');

    const category = await prisma.category.create({
      data: {
        name,
        slug,
        description,
      },
    });

    return res.status(201).json({ ok: true, category });
  } catch (error) {
    next(error);
  }
};

export const listProducts = async (req, res, next) => {
  try {
    const { category, featured, search } = req.query;

    const where = {
      isActive: true,
      ...(category && { category: { slug: String(category) } }),
      ...(featured === 'true' && { isFeatured: true }),
      ...(search && {
        OR: [
          { name: { contains: String(search) } },
          { description: { contains: String(search) } },
        ],
      }),
    };

    const products = await prisma.product.findMany({
      where,
      include: {
        category: true,
        images: true,
        reviews: true,
      },
      orderBy: { createdAt: 'desc' },
    });

    return res.json({ ok: true, products });
  } catch (error) {
    next(error);
  }
};

export const getProductById = async (req, res, next) => {
  try {
    const productId = Number(req.params.id);
    const product = await prisma.product.findUnique({
      where: { id: productId },
      include: {
        category: true,
        images: true,
        reviews: {
          include: { user: { select: { id: true, name: true } } },
        },
      },
    });

    if (!product) {
      return res.status(404).json({ ok: false, message: 'Product not found' });
    }

    const avgRating = product.reviews.length
      ? product.reviews.reduce((sum, review) => sum + review.rating, 0) / product.reviews.length
      : 0;

    return res.json({ ok: true, product: { ...product, avgRating } });
  } catch (error) {
    next(error);
  }
};

export const createProduct = async (req, res, next) => {
  try {
    const { name, description, price, comparePrice, stock, categoryId, isFeatured, isActive, slug } = req.body;

    if (!name || !price || !categoryId) {
      return res.status(400).json({ ok: false, message: 'Name, price, and categoryId are required' });
    }

    const productSlug = slug || name.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-');

    const product = await prisma.product.create({
      data: {
        name,
        slug: productSlug,
        description,
        price: Number(price),
        comparePrice: comparePrice ? Number(comparePrice) : null,
        stock: Number(stock || 0),
        isFeatured: Boolean(isFeatured),
        isActive: isActive !== false,
        categoryId: Number(categoryId),
      },
      include: { category: true },
    });

    return res.status(201).json({ ok: true, product });
  } catch (error) {
    next(error);
  }
};

export const updateProduct = async (req, res, next) => {
  try {
    const productId = Number(req.params.id);

    const product = await prisma.product.update({
      where: { id: productId },
      data: {
        ...req.body,
        price: req.body.price !== undefined ? Number(req.body.price) : undefined,
        comparePrice: req.body.comparePrice !== undefined ? Number(req.body.comparePrice) : undefined,
        stock: req.body.stock !== undefined ? Number(req.body.stock) : undefined,
        categoryId: req.body.categoryId !== undefined ? Number(req.body.categoryId) : undefined,
      },
    });

    return res.json({ ok: true, product });
  } catch (error) {
    next(error);
  }
};

export const deleteProduct = async (req, res, next) => {
  try {
    const product = await prisma.product.delete({ where: { id: Number(req.params.id) } });
    return res.json({ ok: true, message: 'Product deleted successfully', product });
  } catch (error) {
    next(error);
  }
};

export const getFeaturedProducts = async (req, res, next) => {
  try {
    const products = await prisma.product.findMany({
      where: { isFeatured: true, isActive: true },
      include: { category: true, images: true },
      take: 8,
    });

    return res.json({ ok: true, products });
  } catch (error) {
    next(error);
  }
};
