import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ProductPage } from '../models/product';

// Catalog filters. Empty values are not sent to the API
export interface CatalogFilters {
  category: string;
  search: string;
  sort: string;
  page: string;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private http = inject(HttpClient);
  private apiUrl = '/api/products';

  getProducts(filters: CatalogFilters): Observable<ProductPage> {
    let params = new HttpParams();
    for (const [name, value] of Object.entries(filters)) {
      if (value !== '') {
        params = params.set(name, value);
      }
    }
    return this.http.get<ProductPage>(this.apiUrl, { params });
  }
}
