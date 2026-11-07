import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { getFieldError, getRequestError, passwordValidator } from '../../shared/form-messages';

// "Crear cuenta" page (RF4). Same length limits as the backend
@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register.html',
})
export class Register {
  private auth = inject(AuthService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  protected form = this.fb.group({
    firstName: ['', [Validators.required, Validators.maxLength(50)]],
    lastName: ['', [Validators.required, Validators.maxLength(100)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(150)]],
    password: ['', [Validators.required, passwordValidator]],
  });
  protected errorMessage = signal('');
  protected emailExists = signal(false);
  protected isLoading = signal(false);

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

    this.isLoading.set(true);
    this.errorMessage.set('');
    this.emailExists.set(false);

    this.auth.register(this.form.getRawValue()).subscribe({
      // The API logs the new customer in, so we go to the shop
      next: () => this.router.navigateByUrl('/'),
      error: (error: HttpErrorResponse) => {
        this.showErrors(error);
        this.isLoading.set(false);
      },
    });
  }

  private showErrors(error: HttpErrorResponse): void {
    // Errors of each field, checked again in the server (RS9)
    if (error.status === 422 && error.error?.errors) {
      for (const [field, message] of Object.entries(error.error.errors)) {
        this.form.get(field)?.setErrors({ server: message });
      }
      return;
    }

    // The email is already registered: offer to log in (RF3)
    this.emailExists.set(error.status === 409);
    this.errorMessage.set(getRequestError(error));
  }
}
