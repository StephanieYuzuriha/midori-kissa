import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { Product, ProductImage, ProductPage } from '../models/product';

// Catalog filters. Empty values are not sent to the API
export interface CatalogFilters {
  category: string;
  search: string;
  sort: string;
  page: string;
}

// Filters of the product list in the admin panel. status: '' | 'active' | 'hidden'; stock: '' | 'low'
export interface AdminProductFilters {
  search: string;
  category: string;
  status: string;
  stock: string;
  page: string;
}

// Data of the product form in the admin panel (RF29)
export interface ProductData {
  categoryId: number | null;
  name: string;
  description: string;
  price: number | null;
  weightGrams: number | null;
  grade: 'ceremonial' | 'culinary' | null;
  originRegion: string;
  cultivar: string;
  preparation: string;
  stock: number | null;
  isActive: boolean;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private http = inject(HttpClient);
  private apiUrl = '/api/products';
  private adminUrl = '/api/admin/products';

  getProducts(filters: CatalogFilters): Observable<ProductPage> {
    return this.http.get<ProductPage>(this.apiUrl, { params: toParams(filters) });
  }

  // Product with all its photos. The API answers 404 if it does not exist or is withdrawn
  getProduct(id: number): Observable<Product> {
    return this.http.get<{ product: Product }>(`${this.apiUrl}/${id}`).pipe(map((response) => response.product));
  }

  // Admin panel: all the products, hidden ones included (RF32)
  getAdminProducts(filters: AdminProductFilters): Observable<ProductPage> {
    return this.http.get<ProductPage>(this.adminUrl, { params: toParams(filters) });
  }

  // Any product with its photos, also a hidden one
  getAdminProduct(id: number): Observable<Product> {
    return this.http.get<{ product: Product }>(`${this.adminUrl}/${id}`).pipe(map((response) => response.product));
  }

  createProduct(data: ProductData): Observable<Product> {
    return this.http.post<{ product: Product }>(this.adminUrl, data).pipe(map((response) => response.product));
  }

  updateProduct(id: number, data: ProductData): Observable<Product> {
    return this.http.put<{ product: Product }>(`${this.adminUrl}/${id}`, data).pipe(map((response) => response.product));
  }

  // The photo goes as a form with the file, so the server can check its real type (RS12)
  uploadProductImage(id: number, file: File, altText: string): Observable<ProductImage> {
    const form = new FormData();
    form.append('image', file);
    form.append('altText', altText);
    return this.http
      .post<{ image: ProductImage }>(`${this.adminUrl}/${id}/images`, form)
      .pipe(map((response) => response.image));
  }

  deleteProductImage(imageId: number): Observable<void> {
    return this.http.delete<unknown>(`/api/admin/images/${imageId}`).pipe(map(() => undefined));
  }

  // Publishes or hides the product in the shop (RF30)
  setProductActive(id: number, isActive: boolean): Observable<Product> {
    return this.http
      .put<{ product: Product }>(`${this.adminUrl}/${id}/active`, { isActive })
      .pipe(map((response) => response.product));
  }

  updateProductStock(id: number, stock: number): Observable<Product> {
    return this.http
      .put<{ product: Product }>(`${this.adminUrl}/${id}/stock`, { stock })
      .pipe(map((response) => response.product));
  }

  // The API answers 409 if the product has orders: then it can only be hidden (RF30)
  deleteProduct(id: number): Observable<void> {
    return this.http.delete<unknown>(`${this.adminUrl}/${id}`).pipe(map(() => undefined));
  }
}

// Query string with the filters that have a value
function toParams(filters: object): HttpParams {
  let params = new HttpParams();
  for (const [name, value] of Object.entries(filters)) {
    if (value !== '') {
      params = params.set(name, value);
    }
  }
  return params;
}
