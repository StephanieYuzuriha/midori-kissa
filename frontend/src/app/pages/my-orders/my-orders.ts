import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { ORDER_STATUS, Order } from '../../models/order';
import { AuthService } from '../../services/auth.service';
import { OrderService } from '../../services/order.service';
import { getRequestError } from '../../shared/form-messages';

// Order history of the customer: date, number, amount and status (RF26, RI12)
@Component({
  selector: 'app-my-orders',
  imports: [RouterLink, CurrencyPipe, DatePipe],
  templateUrl: './my-orders.html',
})
export class MyOrders implements OnInit {
  private orderService = inject(OrderService);
  private auth = inject(AuthService);
  private router = inject(Router);

  protected readonly status = ORDER_STATUS;
  protected orders = signal<Order[]>([]);
  protected isLoading = signal(true);
  protected errorMessage = signal('');

  ngOnInit(): void {
    this.orderService.getMyOrders().subscribe({
      next: (orders) => {
        this.orders.set(orders);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        // The server session has expired: log in again and come back here
        if (error.status === 401) {
          this.auth.loadSession().subscribe(() => {
            this.router.navigate(['/iniciar-sesion'], { queryParams: { returnUrl: '/mi-cuenta/pedidos' } });
          });
          return;
        }
        this.errorMessage.set(getRequestError(error));
        this.isLoading.set(false);
      },
    });
  }
}
