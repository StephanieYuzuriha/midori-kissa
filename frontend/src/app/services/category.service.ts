import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { Category, CategoryData } from '../models/category';

@Injectable({ providedIn: 'root' })
export class CategoryService {
  private http = inject(HttpClient);
  private adminUrl = '/api/admin/categories';

  getCategories(): Observable<Category[]> {
    return this.http
      .get<{ categories: Category[] }>('/api/categories')
      .pipe(map((response) => response.categories));
  }

  // Admin panel: categories with the number of products of each one (RF31)
  getAdminCategories(): Observable<Category[]> {
    return this.http
      .get<{ categories: Category[] }>(this.adminUrl)
      .pipe(map((response) => response.categories));
  }

  createCategory(data: CategoryData): Observable<Category> {
    return this.http.post<{ category: Category }>(this.adminUrl, data).pipe(map((response) => response.category));
  }

  updateCategory(id: number, data: CategoryData): Observable<Category> {
    return this.http
      .put<{ category: Category }>(`${this.adminUrl}/${id}`, data)
      .pipe(map((response) => response.category));
  }

  // The API answers 409 if the category has products (RF31)
  deleteCategory(id: number): Observable<void> {
    return this.http.delete<unknown>(`${this.adminUrl}/${id}`).pipe(map(() => undefined));
  }
}
