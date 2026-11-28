import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal, viewChild } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ORDER_STATUS, Order, OrderStatus } from '../../../models/order';
import { AuthService } from '../../../services/auth.service';
import { OrderService } from '../../../services/order.service';
import { ConfirmDialog } from '../../../shared/confirm-dialog/confirm-dialog';
import { getRequestError } from '../../../shared/form-messages';
import { handleExpiredSession } from '../../../shared/session';

// Time that a success message stays on screen (RI10)
const SUCCESS_MESSAGE_MS = 4000;

// Admin panel: detail of an order with the customer data and the change of status (RF34).
// The buttons come from the API (nextStatuses): a paid order is shipped and then delivered,
// and only a pending order can be cancelled, which returns its units to the stock
@Component({
  selector: 'app-admin-order-detail',
  imports: [RouterLink, CurrencyPipe, DatePipe, ConfirmDialog],
  templateUrl: './admin-order-detail.html',
})
export class AdminOrderDetail implements OnInit {
  private orderService = inject(OrderService);
  private auth = inject(AuthService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  protected readonly status = ORDER_STATUS;
  protected order = signal<Order | null>(null);
  protected isLoading = signal(true);
  protected notFound = signal(false);
  protected isChanging = signal(false);
  protected errorMessage = signal('');
  protected successMessage = signal('');

  private cancelDialog = viewChild.required<ConfirmDialog>('cancelDialog');

  ngOnInit(): void {
    const orderNumber = this.route.snapshot.paramMap.get('orderNumber') ?? '';
    this.orderService.getAdminOrder(orderNumber).subscribe({
      next: (order) => {
        this.order.set(order);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        if (error.status === 404) {
          this.notFound.set(true);
          return;
        }
        this.showError(error);
      },
    });
  }

  protected canChangeTo(status: OrderStatus): boolean {
    return this.order()?.nextStatuses?.includes(status) ?? false;
  }

  protected askCancel(): void {
    this.cancelDialog().open();
  }

  protected changeStatus(status: OrderStatus): void {
    const order = this.order();
    if (!order) {
      return;
    }

    this.isChanging.set(true);
    this.orderService.changeOrderStatus(order.orderNumber, status).subscribe({
      next: (saved) => {
        this.isChanging.set(false);
        this.order.set(saved);
        this.showSuccess(
          status === 'cancelled'
            ? 'Pedido cancelado. Sus unidades han vuelto al stock.'
            : `Pedido marcado como «${ORDER_STATUS[status].label}».`,
        );
      },
      // 409: the order has changed in the meantime or cannot change to that status
      error: (error: HttpErrorResponse) => {
        this.isChanging.set(false);
        this.showError(error);
      },
    });
  }

  private showSuccess(text: string): void {
    this.errorMessage.set('');
    this.successMessage.set(text);
    setTimeout(() => this.successMessage.set(''), SUCCESS_MESSAGE_MS);
  }

  private showError(error: HttpErrorResponse): void {
    if (handleExpiredSession(error, this.auth, this.router, this.router.url)) {
      return;
    }
    this.successMessage.set('');
    this.errorMessage.set(getRequestError(error));
  }
}
