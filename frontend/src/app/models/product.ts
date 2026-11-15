// Product photo (Media::toArray())
export interface ProductImage {
  path: string;
  altText: string;
}

// Product as returned by the API (Product::toArray())
export interface Product {
  id: number;
  categoryId: number;
  categoryName: string;
  name: string;
  description: string;
  price: number;
  weightGrams: number | null;
  grade: 'ceremonial' | 'culinary' | null;
  originRegion: string | null;
  cultivar: string | null;
  preparation: string | null;
  stock: number;
  // First photo, used in the catalog cards
  image: string | null;
  // All the photos, only in the product page
  images?: ProductImage[];
}

// One page of the catalog
export interface ProductPage {
  products: Product[];
  total: number;
  page: number;
  pages: number;
}

// Text shown for each matcha grade
export const GRADE_LABELS = {
  ceremonial: 'Ceremonial',
  culinary: 'Culinario',
};
