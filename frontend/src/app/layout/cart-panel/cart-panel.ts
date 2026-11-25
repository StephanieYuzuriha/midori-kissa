import { CurrencyPipe } from '@angular/common';
import { Component, ElementRef, effect, inject, viewChild } from '@angular/core';
import { NavigationStart, Router, RouterLink } from '@angular/router';
import { productUrl } from '../../models/product';
import { CartLine, CartService } from '../../services/cart.service';

// Side panel with the cart summary that opens after adding a product, without leaving the page (RI7).
// It uses the Bootstrap offcanvas styles; Angular opens and closes it, so Bootstrap's JavaScript is not needed
@Component({
  selector: 'app-cart-panel',
  imports: [RouterLink, CurrencyPipe],
  templateUrl: './cart-panel.html',
  host: { '(document:keydown.escape)': 'close()' },
})
export class CartPanel {
  protected cart = inject(CartService);
  protected readonly productUrl = productUrl;

  private panel = viewChild.required<ElementRef<HTMLElement>>('panel');
  // Element that had the focus before opening, to give it back when closing
  private previousFocus: HTMLElement | null = null;

  constructor() {
    // When the panel opens, the keyboard focus goes into it
    effect(() => {
      if (this.cart.isPanelOpen()) {
        this.previousFocus = document.activeElement as HTMLElement | null;
        setTimeout(() => this.panel().nativeElement.focus());
      }
    });

    // Going to another page closes the panel
    inject(Router).events.subscribe((event) => {
      if (event instanceof NavigationStart) {
        this.cart.isPanelOpen.set(false);
      }
    });
  }

  // One unit more or less, between 1 and the stock (RF17)
  protected changeQuantity(line: CartLine, change: number): void {
    this.cart.setQuantity(line.productId, line.quantity + change);
    // The "added" message is no longer up to date
    this.cart.lastAdded.set(null);
  }

  protected remove(line: CartLine): void {
    this.cart.remove(line.productId);
    this.cart.lastAdded.set(null);
    // The button disappears with the product, so the focus goes back to the panel
    this.panel().nativeElement.focus();
  }

  close(): void {
    if (!this.cart.isPanelOpen()) {
      return;
    }
    this.cart.isPanelOpen.set(false);
    this.previousFocus?.focus();
  }
}
