import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { GRADE_LABELS, Product, productSlug, productUrl } from '../../models/product';
import { ProductService } from '../../services/product.service';
import { getRequestError } from '../../shared/form-messages';

// Product page: /producto/1-matcha-ceremonial-uji-okumidori (RF14, RF15, RI6).
// The number at the start of the URL identifies the product; the text is only descriptive
@Component({
  selector: 'app-product-detail',
  imports: [RouterLink, CurrencyPipe],
  templateUrl: './product-detail.html',
})
export class ProductDetail {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private title = inject(Title);
  private productService = inject(ProductService);

  protected readonly gradeLabels = GRADE_LABELS;

  protected product = signal<Product | null>(null);
  protected images = computed(() => this.product()?.images ?? []);
  // Position of the photo shown large in the gallery
  protected selectedImage = signal(0);
  protected isLoading = signal(true);
  protected notFound = signal(false);
  protected errorMessage = signal('');

  private slug = '';
  private request?: Subscription;

  constructor() {
    this.route.paramMap.subscribe((params) => this.loadProduct(params.get('slug') ?? ''));
  }

  protected selectImage(index: number): void {
    this.selectedImage.set(index);
  }

  private loadProduct(slug: string): void {
    this.slug = slug;

    // Only the text of the URL has changed (for example, after correcting it): nothing to load
    const current = this.product();
    const id = this.getId(slug);
    if (current && current.id === id) {
      this.correctUrl(current);
      return;
    }

    this.request?.unsubscribe();
    this.product.set(null);
    this.selectedImage.set(0);
    this.notFound.set(false);
    this.errorMessage.set('');

    // A URL without a number at the start cannot be a product
    if (id === null) {
      this.showNotFound();
      return;
    }

    this.isLoading.set(true);
    this.title.setTitle('Midori Kissa');
    this.request = this.productService.getProduct(id).subscribe({
      next: (product) => {
        this.product.set(product);
        this.title.setTitle(`${product.name} | Midori Kissa`);
        this.isLoading.set(false);
        this.correctUrl(product);
      },
      error: (error: HttpErrorResponse) => {
        if (error.status === 404) {
          this.showNotFound();
        } else {
          this.errorMessage.set(getRequestError(error));
          this.isLoading.set(false);
        }
      },
    });
  }

  // "12-matcha-uji" → 12. Returns null if the URL does not start with a number
  private getId(slug: string): number | null {
    const match = /^([0-9]+)(-|$)/.exec(slug);
    return match ? Number(match[1]) : null;
  }

  // If the text of the URL is old or wrong, it is replaced by the right one, without a new history entry
  private correctUrl(product: Product): void {
    if (this.slug !== `${product.id}-${productSlug(product.name)}`) {
      this.router.navigateByUrl(productUrl(product), { replaceUrl: true });
    }
  }

  private showNotFound(): void {
    this.notFound.set(true);
    this.isLoading.set(false);
    this.title.setTitle('Producto no encontrado | Midori Kissa');
  }
}
