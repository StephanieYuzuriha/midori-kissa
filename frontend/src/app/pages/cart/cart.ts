import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { productUrl } from '../../models/product';
import { CartLine, CartService } from '../../services/cart.service';
import { getRequestError } from '../../shared/form-messages';

// Cart page: change units, remove products and see the shipping costs and the total
// without reloading the page (RF17, RF19)
@Component({
  selector: 'app-cart',
  imports: [RouterLink, CurrencyPipe],
  templateUrl: './cart.html',
})
export class Cart implements OnInit {
  protected cart = inject(CartService);
  protected readonly productUrl = productUrl;

  protected isLoading = signal(true);
  // Changes found when checking the cart with the shop (prices, stock, withdrawn products)
  protected notices = signal<string[]>([]);
  protected errorMessage = signal('');

  // The cart is checked with the current data of the shop before showing it
  ngOnInit(): void {
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

  protected changeQuantity(line: CartLine, value: string, input: HTMLInputElement): void {
    this.cart.setQuantity(line.productId, Number(value));
    // The field shows the corrected value if it was out of range
    input.value = String(this.cart.quantityOf(line.productId));
  }

  protected remove(line: CartLine): void {
    this.cart.remove(line.productId);
  }
}
