import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { NewOrder, Order, ShippingRates } from '../models/order';

// Orders of the logged-in customer. The API only returns the orders of the user in the session (RS7)
@Injectable({ providedIn: 'root' })
export class OrderService {
  private http = inject(HttpClient);
  private apiUrl = '/api/orders';

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
}
