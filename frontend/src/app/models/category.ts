// Product category, as returned by the API (Category::toArray())
export interface Category {
  id: number;
  name: string;
  description: string | null;
  // Number of products, only in the admin panel
  productCount?: number;
}

// Data of the category form in the admin panel (RF31)
export interface CategoryData {
  name: string;
  description: string;
}
