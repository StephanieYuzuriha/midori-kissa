import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal, viewChild } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Category } from '../../../models/category';
import { Product, ProductImage } from '../../../models/product';
import { AuthService } from '../../../services/auth.service';
import { CategoryService } from '../../../services/category.service';
import { ProductData, ProductService } from '../../../services/product.service';
import { ConfirmDialog } from '../../../shared/confirm-dialog/confirm-dialog';
import {
  getFieldError,
  getRequestError,
  integerValidator,
  priceValidator,
  setServerErrors,
} from '../../../shared/form-messages';
import { handleExpiredSession } from '../../../shared/session';

// Time that a success message stays on screen (RI10)
const SUCCESS_MESSAGE_MS = 4000;

// Photos: same rules as the API (RS12)
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const MAX_IMAGES = 6;

// Admin panel: form to create or edit a product with all its data and photos (RF29).
// The photos are added once the product exists, because they belong to it
@Component({
  selector: 'app-admin-product-form',
  imports: [ReactiveFormsModule, RouterLink, ConfirmDialog],
  templateUrl: './admin-product-form.html',
})
export class AdminProductForm implements OnInit {
  private productService = inject(ProductService);
  private categoryService = inject(CategoryService);
  private auth = inject(AuthService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  protected readonly maxImages = MAX_IMAGES;

  // Same limits as the backend
  protected form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(150)]],
    categoryId: this.fb.control<number | null>(null, Validators.required),
    description: ['', [Validators.required, Validators.maxLength(5000)]],
    price: this.fb.control<number | null>(null, [Validators.required, Validators.min(0.01), Validators.max(999999.99), priceValidator]),
    weightGrams: this.fb.control<number | null>(null, [Validators.min(1), Validators.max(65535), integerValidator]),
    grade: this.fb.control<'' | 'ceremonial' | 'culinary'>(''),
    originRegion: ['', Validators.maxLength(100)],
    cultivar: ['', Validators.maxLength(100)],
    preparation: ['', Validators.maxLength(2000)],
    stock: this.fb.control<number | null>(0, [Validators.required, Validators.min(0), Validators.max(99999), integerValidator]),
    isActive: [false],
  });

  protected productId = signal<number | null>(null);
  protected categories = signal<Category[]>([]);
  protected images = signal<ProductImage[]>([]);
  protected isLoading = signal(false);
  protected isSaving = signal(false);
  protected notFound = signal(false);
  protected errorMessage = signal('');
  protected successMessage = signal('');

  // Upload of a new photo
  protected altText = this.fb.control('', [Validators.required, Validators.maxLength(150)]);
  protected selectedFile = signal<File | null>(null);
  protected fileError = signal('');
  protected isUploading = signal(false);
  protected imageToDelete = signal<ProductImage | null>(null);
  private deleteDialog = viewChild.required<ConfirmDialog>('deleteDialog');

  ngOnInit(): void {
    this.categoryService.getCategories().subscribe({
      next: (categories) => this.categories.set(categories),
      error: (error: HttpErrorResponse) => this.showError(error),
    });

    // Message left by the creation of the product, before coming to its edit page
    const message = history.state?.message;
    if (typeof message === 'string') {
      this.showSuccess(message);
    }

    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (id) {
      this.productId.set(id);
      this.loadProduct(id);
    }
  }

  protected isInvalid(field: string): boolean {
    const control = this.form.get(field);
    return !!control && control.invalid && control.touched;
  }

  protected errorFor(field: string): string {
    return getFieldError(this.form.get(field));
  }

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const values = this.form.getRawValue();
    const data: ProductData = { ...values, grade: values.grade || null };
    const id = this.productId();
    const request = id ? this.productService.updateProduct(id, data) : this.productService.createProduct(data);

    this.isSaving.set(true);
    this.errorMessage.set('');
    request.subscribe({
      next: (product) => {
        this.isSaving.set(false);
        if (id) {
          this.fillForm(product);
          this.showSuccess('Cambios guardados.');
        } else {
          this.router.navigate(['/admin/productos', product.id], {
            state: { message: 'Producto creado. Ahora puedes añadir sus fotos.' },
          });
        }
      },
      error: (error: HttpErrorResponse) => {
        this.isSaving.set(false);
        if (!setServerErrors(this.form, error)) {
          this.showError(error);
        }
      },
    });
  }

  // Checks the file before sending it; the server checks it again (RS12)
  protected selectFile(input: HTMLInputElement): void {
    const file = input.files?.[0] ?? null;
    this.selectedFile.set(file);
    this.fileError.set('');
    if (file && !IMAGE_TYPES.includes(file.type)) {
      this.fileError.set('La foto debe ser JPG, PNG o WebP.');
    } else if (file && file.size > IMAGE_MAX_BYTES) {
      this.fileError.set('La foto no puede ocupar más de 2 MB.');
    }
  }

  protected uploadImage(fileInput: HTMLInputElement): void {
    const id = this.productId();
    const file = this.selectedFile();
    this.altText.markAsTouched();
    if (!file) {
      this.fileError.set('Selecciona una foto.');
    }
    if (!id || !file || this.fileError() || this.altText.invalid) {
      return;
    }

    this.isUploading.set(true);
    this.productService.uploadProductImage(id, file, this.altText.value.trim()).subscribe({
      next: (image) => {
        this.isUploading.set(false);
        this.images.update((images) => [...images, image]);
        this.altText.reset();
        fileInput.value = '';
        this.selectedFile.set(null);
        this.showSuccess('Foto añadida.');
      },
      error: (error: HttpErrorResponse) => {
        this.isUploading.set(false);
        const errors = error.status === 422 ? error.error?.errors : null;
        if (errors?.image) {
          this.fileError.set(errors.image);
        }
        if (errors?.altText) {
          this.altText.setErrors({ server: errors.altText });
        }
        if (!errors) {
          this.showError(error);
        }
      },
    });
  }

  protected altTextError(): string {
    return getFieldError(this.altText);
  }

  protected askDeleteImage(image: ProductImage): void {
    this.imageToDelete.set(image);
    this.deleteDialog().open();
  }

  protected deleteImage(): void {
    const image = this.imageToDelete();
    if (!image) {
      return;
    }

    this.productService.deleteProductImage(image.id).subscribe({
      next: () => {
        this.images.update((images) => images.filter((item) => item.id !== image.id));
        this.showSuccess('Foto eliminada.');
      },
      error: (error: HttpErrorResponse) => this.showError(error),
    });
  }

  private loadProduct(id: number): void {
    this.isLoading.set(true);
    this.productService.getAdminProduct(id).subscribe({
      next: (product) => {
        this.fillForm(product);
        this.images.set(product.images ?? []);
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

  private fillForm(product: Product): void {
    this.form.reset({
      name: product.name,
      categoryId: product.categoryId,
      description: product.description,
      price: product.price,
      weightGrams: product.weightGrams,
      grade: product.grade ?? '',
      originRegion: product.originRegion ?? '',
      cultivar: product.cultivar ?? '',
      preparation: product.preparation ?? '',
      stock: product.stock,
      isActive: product.isActive,
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
