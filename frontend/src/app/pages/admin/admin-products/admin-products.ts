import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal, viewChild } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Category } from '../../../models/category';
import { Product } from '../../../models/product';
import { AuthService } from '../../../services/auth.service';
import { CategoryService } from '../../../services/category.service';
import { ProductService } from '../../../services/product.service';
import { ConfirmDialog } from '../../../shared/confirm-dialog/confirm-dialog';
import { getRequestError } from '../../../shared/form-messages';
import { handleExpiredSession } from '../../../shared/session';

// Time that a success message stays on screen (RI10)
const SUCCESS_MESSAGE_MS = 4000;
const MAX_STOCK = 99999;

// Admin panel: list of products with search and filters (RI11). From here the administrator
// publishes or hides products (RF30), changes the stock (RF32) and deletes products
@Component({
  selector: 'app-admin-products',
  imports: [ReactiveFormsModule, RouterLink, CurrencyPipe, ConfirmDialog],
  templateUrl: './admin-products.html',
})
export class AdminProducts implements OnInit {
  private productService = inject(ProductService);
  private categoryService = inject(CategoryService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  protected filtersForm = this.fb.group({
    search: [''],
    category: [''],
    status: [''],
    lowStock: [false],
  });

  protected categories = signal<Category[]>([]);
  protected products = signal<Product[]>([]);
  protected total = signal(0);
  protected page = signal(1);
  protected pages = signal(0);
  protected isLoading = signal(true);
  protected errorMessage = signal('');
  protected successMessage = signal('');

  // Product waiting for the confirmation to be deleted (RI13)
  protected productToDelete = signal<Product | null>(null);
  private deleteDialog = viewChild.required<ConfirmDialog>('deleteDialog');

  ngOnInit(): void {
    this.categoryService.getCategories().subscribe({
      next: (categories) => this.categories.set(categories),
      // Without categories the filter only shows "Todas"; the list still works
      error: () => this.categories.set([]),
    });
    this.loadProducts();
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.loadProducts();
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.loadProducts();
  }

  protected toggleActive(product: Product): void {
    this.productService.setProductActive(product.id, !product.isActive).subscribe({
      next: (saved) => {
        this.replaceProduct(saved);
        this.showSuccess(saved.isActive ? `«${saved.name}» ya se ve en la tienda.` : `«${saved.name}» se ha ocultado de la tienda.`);
      },
      error: (error: HttpErrorResponse) => this.showError(error),
    });
  }

  // Saves the stock when the field changes. Whole numbers between 0 and 99999, as in the API
  protected changeStock(product: Product, value: string, input: HTMLInputElement): void {
    const stock = Number(value);
    if (value === '' || !Number.isInteger(stock) || stock < 0 || stock > MAX_STOCK) {
      this.errorMessage.set(`El stock de «${product.name}» debe ser un número entero entre 0 y ${MAX_STOCK}.`);
      input.value = String(product.stock);
      return;
    }
    if (stock === product.stock) {
      return;
    }

    this.productService.updateProductStock(product.id, stock).subscribe({
      next: (saved) => {
        this.replaceProduct(saved);
        this.showSuccess(`Stock de «${saved.name}» actualizado: ${saved.stock} unidades.`);
      },
      error: (error: HttpErrorResponse) => {
        input.value = String(product.stock);
        this.showError(error);
      },
    });
  }

  protected askDelete(product: Product): void {
    this.productToDelete.set(product);
    this.deleteDialog().open();
  }

  protected deleteProduct(): void {
    const product = this.productToDelete();
    if (!product) {
      return;
    }

    this.productService.deleteProduct(product.id).subscribe({
      next: () => {
        this.showSuccess(`«${product.name}» se ha eliminado.`);
        this.loadProducts();
      },
      // 409: the product has orders, so the API asks to hide it instead (RF30)
      error: (error: HttpErrorResponse) => this.showError(error),
    });
  }

  private loadProducts(): void {
    const filters = this.filtersForm.getRawValue();
    this.isLoading.set(true);

    this.productService
      .getAdminProducts({
        search: filters.search.trim(),
        category: filters.category,
        status: filters.status,
        stock: filters.lowStock ? 'low' : '',
        page: String(this.page()),
      })
      .subscribe({
        next: (result) => {
          this.products.set(result.products);
          this.total.set(result.total);
          this.pages.set(result.pages);
          this.isLoading.set(false);
        },
        error: (error: HttpErrorResponse) => {
          this.isLoading.set(false);
          this.showError(error);
        },
      });
  }

  // Puts the saved product in the list. The stock warning comes from the API when the stock changes
  private replaceProduct(saved: Product): void {
    this.products.update((products) =>
      products.map((product) =>
        product.id === saved.id ? { ...product, ...saved, lowStock: saved.lowStock ?? product.lowStock } : product,
      ),
    );
  }

  private showSuccess(text: string): void {
    this.errorMessage.set('');
    this.successMessage.set(text);
    setTimeout(() => this.successMessage.set(''), SUCCESS_MESSAGE_MS);
  }

  private showError(error: HttpErrorResponse): void {
    if (handleExpiredSession(error, this.auth, this.router, '/admin/productos')) {
      return;
    }
    this.successMessage.set('');
    this.errorMessage.set(getRequestError(error));
  }
}
