import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, effect, inject, signal } from '@angular/core';
import { Observable, catchError, forkJoin, map, of, throwError } from 'rxjs';
import { ShippingRates, shippingCostFor } from '../models/order';
import { Product } from '../models/product';
import { OrderService } from './order.service';
import { ProductService } from './product.service';

// Key of the cart in the browser storage (RS20: the cart is not kept in a cookie)
const STORAGE_KEY = 'midori-kissa-cart';
// Same limit of units per product as the API
const MAX_QUANTITY = 99;

// Product in the cart. Name, price and photo are kept only to show the cart quickly:
// the cart page updates them and the server always uses the prices of the database (RS15)
export interface CartLine {
  productId: number;
  name: string;
  price: number;
  image: string | null;
  stock: number;
  quantity: number;
}

const round = (amount: number) => Math.round(amount * 100) / 100;

// Cart of the shop, also for visitors without a session (RF1, RF16–RF19)
@Injectable({ providedIn: 'root' })
export class CartService {
  private productService = inject(ProductService);
  private orderService = inject(OrderService);

  readonly lines = signal<CartLine[]>(this.readStorage());
  readonly count = computed(() => this.lines().reduce((sum, line) => sum + line.quantity, 0));
  readonly subtotal = computed(() => round(this.lines().reduce((sum, line) => sum + line.price * line.quantity, 0)));

  // Shipping rates of the configuration (RF19)
  readonly rates = signal<ShippingRates | null>(null);
  readonly shippingCost = computed(() => {
    const rates = this.rates();
    return rates && this.lines().length > 0 ? shippingCostFor(this.subtotal(), rates) : 0;
  });
  readonly total = computed(() => round(this.subtotal() + this.shippingCost()));
  // Amount that is missing to get free shipping (0 if it is already free)
  readonly missingForFreeShipping = computed(() => {
    const rates = this.rates();
    return rates ? Math.max(0, round(rates.freeFrom - this.subtotal())) : 0;
  });

  // Side panel with the cart summary, opened after adding a product (RI7)
  readonly isPanelOpen = signal(false);
  // Last product added and units added (0 if there were no more units to add)
  readonly lastAdded = signal<{ name: string; quantity: number } | null>(null);

  constructor() {
    // Every change is saved in the browser, so the cart stays when navigating or logging in (RF18)
    effect(() => this.writeStorage(this.lines()));

    this.orderService.getShippingRates().subscribe({
      next: (rates) => this.rates.set(rates),
      error: (error) => console.error('No se han podido cargar los gastos de envío', error),
    });
  }

  // Units of a product that are already in the cart
  quantityOf(productId: number): number {
    return this.lines().find((line) => line.productId === productId)?.quantity ?? 0;
  }

  // Adds units without going over the stock (RF16) and opens the side panel (RI7).
  // Returns the units really added
  add(product: Product, quantity: number): number {
    const inCart = this.quantityOf(product.id);
    const added = Math.max(0, Math.min(quantity, product.stock - inCart, MAX_QUANTITY - inCart));

    if (added > 0) {
      const line: CartLine = {
        productId: product.id,
        name: product.name,
        price: product.price,
        image: product.image ?? product.images?.[0]?.path ?? null,
        stock: product.stock,
        quantity: inCart + added,
      };
      this.lines.update((lines) =>
        inCart > 0 ? lines.map((current) => (current.productId === product.id ? line : current)) : [...lines, line],
      );
    }

    this.lastAdded.set({ name: product.name, quantity: added });
    this.isPanelOpen.set(true);
    return added;
  }

  // Changes the units of a product, between 1 and the available stock (RF17)
  setQuantity(productId: number, quantity: number): void {
    this.lines.update((lines) =>
      lines.map((line) => {
        if (line.productId !== productId) {
          return line;
        }
        const max = Math.min(line.stock, MAX_QUANTITY);
        return { ...line, quantity: Math.min(Math.max(Math.trunc(quantity) || 1, 1), max) };
      }),
    );
  }

  remove(productId: number): void {
    this.lines.update((lines) => lines.filter((line) => line.productId !== productId));
  }

  clear(): void {
    this.lines.set([]);
  }

  // Updates names, prices and stock with the data of the shop. Products that are no longer
  // for sale are removed and quantities above the stock are reduced.
  // Returns texts that explain the changes to the customer
  refresh(): Observable<string[]> {
    const lines = this.lines();
    if (lines.length === 0) {
      return of([]);
    }

    const requests = lines.map((line) =>
      this.productService.getProduct(line.productId).pipe(
        map((product): { line: CartLine; product: Product | null } => ({ line, product })),
        // 404: the product does not exist or is withdrawn
        catchError((error: HttpErrorResponse) =>
          error.status === 404 ? of({ line, product: null }) : throwError(() => error),
        ),
      ),
    );

    return forkJoin(requests).pipe(
      map((results) => {
        const messages: string[] = [];
        const updated: CartLine[] = [];

        for (const { line, product } of results) {
          if (!product || product.stock === 0) {
            messages.push(`«${line.name}» ya no está disponible y se ha quitado del carrito.`);
            continue;
          }
          const quantity = Math.min(line.quantity, product.stock);
          if (quantity < line.quantity) {
            const units = product.stock === 1 ? 'queda 1 unidad' : `quedan ${product.stock} unidades`;
            messages.push(`Solo ${units} de «${product.name}», así que se ha cambiado la cantidad.`);
          }
          if (product.price !== line.price) {
            messages.push(`El precio de «${product.name}» ha cambiado.`);
          }
          updated.push({
            productId: product.id,
            name: product.name,
            price: product.price,
            image: product.images?.[0]?.path ?? line.image,
            stock: product.stock,
            quantity,
          });
        }

        this.lines.set(updated);
        return messages;
      }),
    );
  }

  // The storage can be empty, damaged or blocked by the browser: in that case the cart starts empty
  private readStorage(): CartLine[] {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
      if (!Array.isArray(saved)) {
        return [];
      }
      return saved.filter(
        (line) =>
          Number.isInteger(line?.productId) &&
          Number.isInteger(line?.quantity) &&
          line.quantity > 0 &&
          typeof line.name === 'string' &&
          typeof line.price === 'number' &&
          Number.isInteger(line.stock),
      );
    } catch {
      return [];
    }
  }

  private writeStorage(lines: CartLine[]): void {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    } catch (error) {
      console.error('No se ha podido guardar el carrito', error);
    }
  }
}
