import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { NewOrder, Order, OrderPage, OrderStatus, ShippingRates } from '../models/order';

// Filters of the order list in the admin panel. Dates in the format YYYY-MM-DD
export interface AdminOrderFilters {
  status: string;
  from: string;
  to: string;
  page: string;
}

// Orders of the logged-in customer. The API only returns the orders of the user in the session (RS7).
// The admin methods use the /api/admin routes, which check the role in the server (RS6)
@Injectable({ providedIn: 'root' })
export class OrderService {
  private http = inject(HttpClient);
  private apiUrl = '/api/orders';
  private adminUrl = '/api/admin/orders';

  getShippingRates(): Observable<ShippingRates> {
    return this.http.get<ShippingRates>('/api/shipping');
  }

  // The API answers 409 if a product is sold out or has fewer units than requested
  createOrder(order: NewOrder): Observable<Order> {
    return this.http.post<{ order: Order }>(this.apiUrl, order).pipe(map((response) => response.order));
  }

  getMyOrders(): Observable<Order[]> {
    return this.http.get<{ orders: Order[] }>(`${this.apiUrl}/mine`).pipe(map((response) => response.orders));
  }

  getOrder(orderNumber: string): Observable<Order> {
    return this.http.get<{ order: Order }>(`${this.apiUrl}/${orderNumber}`).pipe(map((response) => response.order));
  }

  // Admin panel: all the orders with filters by status and dates (RF33)
  getAdminOrders(filters: AdminOrderFilters): Observable<OrderPage> {
    let params = new HttpParams();
    for (const [name, value] of Object.entries(filters)) {
      if (value !== '') {
        params = params.set(name, value);
      }
    }
    return this.http.get<OrderPage>(this.adminUrl, { params });
  }

  getAdminOrder(orderNumber: string): Observable<Order> {
    return this.http.get<{ order: Order }>(`${this.adminUrl}/${orderNumber}`).pipe(map((response) => response.order));
  }

  // The API answers 409 if the order cannot change to that status (RF34)
  changeOrderStatus(orderNumber: string, status: OrderStatus): Observable<Order> {
    return this.http
      .put<{ order: Order }>(`${this.adminUrl}/${orderNumber}/status`, { status })
      .pipe(map((response) => response.order));
  }
}
