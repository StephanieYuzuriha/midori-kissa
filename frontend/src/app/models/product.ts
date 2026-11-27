// Product photo (Media::toArray())
export interface ProductImage {
  id: number;
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
  // false = hidden: it does not appear in the shop (RF30)
  isActive: boolean;
  // First photo, used in the catalog cards
  image: string | null;
  // Fewer than 5 units, only in the admin list (RF32)
  lowStock?: boolean;
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

// Text of the product URL, made from the name: "Chasen de bambú" → "chasen-de-bambu"
export function productSlug(name: string): string {
  return name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // removes the accents
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

// Address of the product page: /producto/1-matcha-ceremonial-uji-okumidori (RI16).
// The number identifies the product; the text only describes it
export function productUrl(product: Pick<Product, 'id' | 'name'>): string {
  return `/producto/${product.id}-${productSlug(product.name)}`;
}
