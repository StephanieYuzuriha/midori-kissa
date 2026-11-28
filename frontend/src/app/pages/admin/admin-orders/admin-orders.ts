import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ORDER_STATUS, Order, OrderStatus } from '../../../models/order';
import { AuthService } from '../../../services/auth.service';
import { OrderService } from '../../../services/order.service';
import { getFieldError, getRequestError, setServerErrors } from '../../../shared/form-messages';
import { handleExpiredSession } from '../../../shared/session';

// Admin panel: list of orders, newest first, filtered by status and dates (RF33, RI11, RI12)
@Component({
  selector: 'app-admin-orders',
  imports: [ReactiveFormsModule, RouterLink, CurrencyPipe, DatePipe],
  templateUrl: './admin-orders.html',
})
export class AdminOrders implements OnInit {
  private orderService = inject(OrderService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  protected readonly status = ORDER_STATUS;
  protected readonly statuses = Object.keys(ORDER_STATUS) as OrderStatus[];

  protected filtersForm = this.fb.group({
    status: [''],
    from: [''],
    to: [''],
  });

  protected orders = signal<Order[]>([]);
  protected total = signal(0);
  protected page = signal(1);
  protected pages = signal(0);
  protected isLoading = signal(true);
  protected errorMessage = signal('');

  ngOnInit(): void {
    this.loadOrders();
  }

  protected isInvalid(field: string): boolean {
    const control = this.filtersForm.get(field);
    return !!control && control.invalid && control.touched;
  }

  protected errorFor(field: string): string {
    return getFieldError(this.filtersForm.get(field));
  }

  protected applyFilters(): void {
    const { from, to } = this.filtersForm.getRawValue();
    // Same check as the API: the end date cannot be before the start date
    if (from && to && from > to) {
      this.filtersForm.controls.to.setErrors({ server: 'La fecha final no puede ser anterior a la inicial.' });
      this.filtersForm.controls.to.markAsTouched();
      return;
    }
    this.page.set(1);
    this.loadOrders();
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.loadOrders();
  }

  private loadOrders(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.orderService.getAdminOrders({ ...this.filtersForm.getRawValue(), page: String(this.page()) }).subscribe({
      next: (result) => {
        this.orders.set(result.orders);
        this.total.set(result.total);
        this.pages.set(result.pages);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
        if (handleExpiredSession(error, this.auth, this.router, '/admin/pedidos') || setServerErrors(this.filtersForm, error)) {
          return;
        }
        this.errorMessage.set(getRequestError(error));
      },
    });
  }
}
