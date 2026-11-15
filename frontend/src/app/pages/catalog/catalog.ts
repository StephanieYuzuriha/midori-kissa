import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, ParamMap, Router, RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { Category } from '../../models/category';
import { GRADE_LABELS, Product } from '../../models/product';
import { CategoryService } from '../../services/category.service';
import { ProductService } from '../../services/product.service';
import { getRequestError } from '../../shared/form-messages';

// Sort options: value in the URL (in Spanish), value for the API and text for the customer (RF13)
const SORT_OPTIONS = [
  { value: 'novedad', api: 'newest', label: 'Novedades' },
  { value: 'precio-asc', api: 'price_asc', label: 'Precio: de menor a mayor' },
  { value: 'precio-desc', api: 'price_desc', label: 'Precio: de mayor a menor' },
];
const DEFAULT_SORT = 'novedad';

// Shop catalog with category filter, search, sorting and pages (RF10–RF13).
// The filters are kept in the URL (/tienda?categoria=1&buscar=uji&orden=precio-asc&pagina=2),
// so the back button and shared links keep the same results
@Component({
  selector: 'app-catalog',
  imports: [ReactiveFormsModule, RouterLink, CurrencyPipe],
  templateUrl: './catalog.html',
})
export class Catalog {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);
  private productService = inject(ProductService);
  private categoryService = inject(CategoryService);

  protected readonly sortOptions = SORT_OPTIONS;
  protected readonly gradeLabels = GRADE_LABELS;

  protected filtersForm = this.fb.group({
    category: [''],
    search: [''],
    sort: [DEFAULT_SORT],
  });
  protected categories = signal<Category[]>([]);
  protected products = signal<Product[]>([]);
  protected total = signal(0);
  protected page = signal(1);
  protected pages = signal(0);
  protected isLoading = signal(true);
  protected errorMessage = signal('');

  private request?: Subscription;

  constructor() {
    this.categoryService.getCategories().subscribe({
      next: (categories) => this.categories.set(categories),
      error: (error: HttpErrorResponse) => this.errorMessage.set(getRequestError(error)),
    });

    // The category and the order are applied as soon as they change; the search, when it is submitted
    this.filtersForm.controls.category.valueChanges.subscribe(() => this.applyFilters());
    this.filtersForm.controls.sort.valueChanges.subscribe(() => this.applyFilters());

    // Every change in the URL (a filter or the page) loads the products again
    this.route.queryParamMap.subscribe((params) => this.loadProducts(params));
  }

  applyFilters(): void {
    const { category, search, sort } = this.filtersForm.getRawValue();

    // Without "pagina", so changing a filter always goes back to the first page
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {
        categoria: category || null,
        buscar: search.trim() || null,
        orden: sort === DEFAULT_SORT ? null : sort,
      },
    });
  }

  // Page numbers for the pagination links: [1, 2, ...]
  protected pageNumbers(): number[] {
    return Array.from({ length: this.pages() }, (_, index) => index + 1);
  }

  // Value of "pagina" in the URL. Page 1 is left out so the URL stays short
  protected pageParam(page: number): number | null {
    return page === 1 ? null : page;
  }

  private loadProducts(params: ParamMap): void {
    const category = params.get('categoria') ?? '';
    const search = params.get('buscar') ?? '';
    const sort = params.get('orden') ?? DEFAULT_SORT;
    const page = params.get('pagina') ?? '';

    // Keep the form in line with the URL (for example, after using the back button).
    // emitEvent: false, so this does not apply the filters again
    this.filtersForm.setValue({ category, search, sort }, { emitEvent: false });

    this.isLoading.set(true);
    this.errorMessage.set('');

    // If a previous request has not finished yet, it is cancelled so it cannot show old results
    this.request?.unsubscribe();
    this.request = this.productService
      .getProducts({
        category,
        search,
        // An unknown order is sent as it is, and the API answers that it is not valid
        sort: SORT_OPTIONS.find((option) => option.value === sort)?.api ?? sort,
        page,
      })
      .subscribe({
        next: (result) => {
          this.products.set(result.products);
          this.total.set(result.total);
          this.page.set(result.page);
          this.pages.set(result.pages);
          this.isLoading.set(false);
        },
        error: (error: HttpErrorResponse) => {
          this.products.set([]);
          this.errorMessage.set(
            error.status === 422 ? 'Los filtros de la búsqueda no son válidos.' : getRequestError(error),
          );
          this.isLoading.set(false);
        },
      });
  }
}
