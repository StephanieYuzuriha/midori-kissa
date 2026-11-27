import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, inject, signal, viewChild } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { Category } from '../../../models/category';
import { AuthService } from '../../../services/auth.service';
import { CategoryService } from '../../../services/category.service';
import { ConfirmDialog } from '../../../shared/confirm-dialog/confirm-dialog';
import { getFieldError, getRequestError, setServerErrors } from '../../../shared/form-messages';
import { handleExpiredSession } from '../../../shared/session';

// Time that a success message stays on screen (RI10)
const SUCCESS_MESSAGE_MS = 4000;

// Admin panel: create, edit and delete categories (RF31). A category with products
// cannot be deleted, so the list shows how many products each one has
@Component({
  selector: 'app-admin-categories',
  imports: [ReactiveFormsModule, ConfirmDialog],
  templateUrl: './admin-categories.html',
})
export class AdminCategories implements OnInit {
  private categoryService = inject(CategoryService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  // Same limits as the backend
  protected form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(80)]],
    description: ['', Validators.maxLength(500)],
  });

  protected categories = signal<Category[]>([]);
  protected editing = signal<Category | null>(null);
  protected isLoading = signal(true);
  protected isSaving = signal(false);
  protected errorMessage = signal('');
  protected successMessage = signal('');
  protected categoryToDelete = signal<Category | null>(null);

  private deleteDialog = viewChild.required<ConfirmDialog>('deleteDialog');
  private nameInput = viewChild.required<ElementRef<HTMLInputElement>>('nameInput');

  ngOnInit(): void {
    this.loadCategories();
  }

  protected isInvalid(field: string): boolean {
    const control = this.form.get(field);
    return !!control && control.invalid && control.touched;
  }

  protected errorFor(field: string): string {
    return getFieldError(this.form.get(field));
  }

  protected edit(category: Category): void {
    this.editing.set(category);
    this.form.reset({ name: category.name, description: category.description ?? '' });
    // The form is above the list: the focus goes to its first field
    this.nameInput().nativeElement.focus();
  }

  protected cancelEdit(): void {
    this.editing.set(null);
    this.form.reset();
  }

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const editing = this.editing();
    const data = this.form.getRawValue();
    const request = editing
      ? this.categoryService.updateCategory(editing.id, data)
      : this.categoryService.createCategory(data);

    this.isSaving.set(true);
    request.subscribe({
      next: (category) => {
        this.isSaving.set(false);
        this.cancelEdit();
        this.showSuccess(editing ? `Categoría «${category.name}» guardada.` : `Categoría «${category.name}» creada.`);
        this.loadCategories();
      },
      error: (error: HttpErrorResponse) => {
        this.isSaving.set(false);
        if (!setServerErrors(this.form, error)) {
          this.showError(error);
        }
      },
    });
  }

  protected askDelete(category: Category): void {
    this.categoryToDelete.set(category);
    this.deleteDialog().open();
  }

  protected deleteCategory(): void {
    const category = this.categoryToDelete();
    if (!category) {
      return;
    }

    this.categoryService.deleteCategory(category.id).subscribe({
      next: () => {
        if (this.editing()?.id === category.id) {
          this.cancelEdit();
        }
        this.showSuccess(`Categoría «${category.name}» eliminada.`);
        this.loadCategories();
      },
      // 409: the category still has products (RF31)
      error: (error: HttpErrorResponse) => this.showError(error),
    });
  }

  private loadCategories(): void {
    this.categoryService.getAdminCategories().subscribe({
      next: (categories) => {
        this.categories.set(categories);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isLoading.set(false);
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
    if (handleExpiredSession(error, this.auth, this.router, '/admin/categorias')) {
      return;
    }
    this.successMessage.set('');
    this.errorMessage.set(getRequestError(error));
  }
}
