import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, of, switchMap, tap } from 'rxjs';
import { NewOrder } from '../../models/order';
import { AuthService } from '../../services/auth.service';
import { CartService } from '../../services/cart.service';
import { OrderService } from '../../services/order.service';
import {
  getFieldError,
  getRequestError,
  passwordValidator,
  postalCodeValidator,
  setServerErrors,
} from '../../shared/form-messages';

// Step 1 of the checkout: shipping data (RF20, RI8). A visitor without an account creates it here
// adding only a password to the shipping data (RF2). Then the order is created as "pending payment";
// the card payment with Stripe is added in T16
@Component({
  selector: 'app-checkout',
  imports: [ReactiveFormsModule, RouterLink, CurrencyPipe],
  templateUrl: './checkout.html',
})
export class Checkout implements OnInit {
  protected auth = inject(AuthService);
  protected cart = inject(CartService);
  private orderService = inject(OrderService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  // Same rules and limits as the backend
  protected form = this.fb.group({
    firstName: ['', [Validators.required, Validators.maxLength(50)]],
    lastName: ['', [Validators.required, Validators.maxLength(100)]],
    phone: ['', [Validators.required, Validators.maxLength(20)]],
    address: ['', [Validators.required, Validators.maxLength(200)]],
    postalCode: ['', [Validators.required, postalCodeValidator]],
    city: ['', [Validators.required, Validators.maxLength(100)]],
    province: ['', [Validators.required, Validators.maxLength(100)]],
    // Only to create the account during the checkout (RF2). Disabled if the customer is logged in
    email: ['', [Validators.required, Validators.email, Validators.maxLength(150)]],
    password: ['', [Validators.required, passwordValidator]],
  });

  protected isLoading = signal(true);
  protected isSaving = signal(false);
  protected errorMessage = signal('');
  // 409 when creating the account: the email is already registered (RF3)
  protected emailExists = signal(false);
  // Changes found when checking the cart (prices, stock, withdrawn products)
  protected notices = signal<string[]>([]);

  ngOnInit(): void {
    const user = this.auth.user();
    if (user) {
      // The form starts with the saved address of the customer (RF20)
      this.form.patchValue({
        firstName: user.firstName,
        lastName: user.lastName,
        phone: user.phone ?? '',
        address: user.address ?? '',
        postalCode: user.postalCode ?? '',
        city: user.city ?? '',
        province: user.province ?? '',
      });
      this.disableAccountFields();
    }

    // The cart is checked with the shop before ordering, as in the cart page
    this.cart.refresh().subscribe({
      next: (notices) => {
        this.notices.set(notices);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.errorMessage.set(getRequestError(error));
        this.isLoading.set(false);
      },
    });
  }

  // Shows the error only after the user has left the field or submitted the form
  protected isInvalid(field: string): boolean {
    const control = this.form.get(field);
    return !!control && control.invalid && control.touched;
  }

  protected errorFor(field: string): string {
    return getFieldError(this.form.get(field));
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.errorMessage.set('');
    this.emailExists.set(false);

    this.createAccountIfNeeded()
      .pipe(switchMap(() => this.orderService.createOrder(this.newOrder())))
      .subscribe({
        next: (order) => {
          this.cart.clear();
          this.router.navigate(['/pedido-confirmado', order.orderNumber], { replaceUrl: true });
        },
        error: (error: HttpErrorResponse) => this.showError(error),
      });
  }

  // A visitor without a session creates the account with the same data (RF2)
  private createAccountIfNeeded(): Observable<unknown> {
    if (this.auth.user()) {
      return of(null);
    }
    const data = this.form.getRawValue();
    return this.auth.register(data).pipe(tap(() => this.disableAccountFields()));
  }

  // Only the product ids and the units are sent: the server calculates the prices (RS15)
  private newOrder(): NewOrder {
    const data = this.form.getRawValue();
    return {
      items: this.cart.lines().map((line) => ({ productId: line.productId, quantity: line.quantity })),
      shipping: {
        name: `${data.firstName.trim()} ${data.lastName.trim()}`,
        address: data.address,
        postalCode: data.postalCode,
        city: data.city,
        province: data.province,
        phone: data.phone,
      },
    };
  }

  private disableAccountFields(): void {
    this.form.controls.email.disable();
    this.form.controls.password.disable();
  }

  private showError(error: HttpErrorResponse): void {
    this.isSaving.set(false);

    // The account could not be created because the email already exists (RF3)
    if (error.status === 409 && !this.auth.user()) {
      this.emailExists.set(true);
      return;
    }
    // Errors by field. The order API calls the full name "name"
    if (error.status === 422) {
      const errors = error.error?.errors ?? {};
      if (errors.name) {
        this.form.controls.firstName.setErrors({ server: errors.name });
        this.form.controls.firstName.markAsTouched();
      }
      if (errors.items) {
        this.errorMessage.set('El carrito no es válido. Vuelve al carrito y revísalo.');
      }
      if (setServerErrors(this.form, error)) {
        return;
      }
    }
    // The session has expired: the session state is loaded again and the account fields come back
    if (error.status === 401) {
      this.auth.loadSession().subscribe(() => {
        this.form.controls.email.enable();
        this.form.controls.password.enable();
      });
      this.errorMessage.set('Tu sesión ha caducado. Inicia sesión de nuevo para terminar el pedido.');
      return;
    }
    // 409: a product is sold out or has fewer units. Other errors: connection or server
    this.errorMessage.set(getRequestError(error));
  }
}
