import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ORDER_STATUS, Order } from '../../models/order';
import { AuthService } from '../../services/auth.service';
import { OrderService } from '../../services/order.service';
import { getRequestError } from '../../shared/form-messages';

// Detail of an order: products, units, prices, shipping address and payment method (RF27).
// The same page is the confirmation after ordering, with the last step marked (RF25, RI8)
@Component({
  selector: 'app-order-detail',
  imports: [RouterLink, CurrencyPipe, DatePipe],
  templateUrl: './order-detail.html',
})
export class OrderDetail {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private orderService = inject(OrderService);
  private auth = inject(AuthService);

  protected readonly status = ORDER_STATUS;
  // true in /pedido-confirmado/..., false in Mis pedidos
  protected readonly isConfirmation = this.route.snapshot.data['confirmation'] === true;

  protected order = signal<Order | null>(null);
  protected isLoading = signal(true);
  protected notFound = signal(false);
  protected errorMessage = signal('');

  constructor() {
    this.route.paramMap.subscribe((params) => this.loadOrder(params.get('orderNumber') ?? ''));
  }

  private loadOrder(orderNumber: string): void {
    this.isLoading.set(true);
    this.orderService.getOrder(orderNumber).subscribe({
      next: (order) => {
        this.order.set(order);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        // The server session has expired: log in again and come back to this page
        if (error.status === 401) {
          this.auth.loadSession().subscribe(() => {
            this.router.navigate(['/iniciar-sesion'], { queryParams: { returnUrl: this.router.url } });
          });
          return;
        }
        // 404: the order does not exist or belongs to another customer (RS7)
        if (error.status === 404) {
          this.notFound.set(true);
        } else {
          this.errorMessage.set(getRequestError(error));
        }
        this.isLoading.set(false);
      },
    });
  }
}
