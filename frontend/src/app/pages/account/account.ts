import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, WritableSignal, inject, signal } from '@angular/core';
import { FormGroup, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { User } from '../../models/user';
import { AuthService } from '../../services/auth.service';
import {
  getFieldError,
  getRequestError,
  postalCodeValidator,
  setServerErrors,
} from '../../shared/form-messages';

// Time that a success message stays on screen (RI10)
const SUCCESS_MESSAGE_MS = 4000;

// "Mi cuenta" page: personal data and shipping address (RF8, RF9).
// Same length limits as the backend
@Component({
  selector: 'app-account',
  imports: [ReactiveFormsModule],
  templateUrl: './account.html',
})
export class Account implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private fb = inject(NonNullableFormBuilder);

  protected isLoading = signal(true);
  protected loadError = signal('');
  protected email = signal('');

  // Personal data and shipping address
  protected profileForm = this.fb.group({
    firstName: ['', [Validators.required, Validators.maxLength(50)]],
    lastName: ['', [Validators.required, Validators.maxLength(100)]],
    phone: ['', Validators.maxLength(20)],
    address: ['', Validators.maxLength(200)],
    postalCode: ['', postalCodeValidator],
    city: ['', Validators.maxLength(100)],
    province: ['', Validators.maxLength(100)],
  });
  protected profileMessage = signal('');
  protected profileError = signal('');
  protected isSavingProfile = signal(false);

  // The data comes from the API, so the page always shows what is saved in the database
  ngOnInit(): void {
    this.auth.getProfile().subscribe({
      next: (user) => {
        this.fillProfileForm(user);
        this.isLoading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        if (this.handleExpiredSession(error)) {
          return;
        }
        this.loadError.set(getRequestError(error));
        this.isLoading.set(false);
      },
    });
  }

  // Shows the error only after the user has left the field or submitted the form
  protected isInvalid(form: FormGroup, field: string): boolean {
    const control = form.get(field);
    return !!control && control.invalid && control.touched;
  }

  protected errorFor(form: FormGroup, field: string): string {
    return getFieldError(form.get(field));
  }

  saveProfile(): void {
    if (this.profileForm.invalid) {
      this.profileForm.markAllAsTouched();
      return;
    }

    this.isSavingProfile.set(true);
    this.profileError.set('');

    this.auth.updateProfile(this.profileForm.getRawValue()).subscribe({
      next: (user) => {
        this.fillProfileForm(user);
        this.showSuccess(this.profileMessage, 'Datos guardados.');
        this.isSavingProfile.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.isSavingProfile.set(false);
        if (this.handleExpiredSession(error) || setServerErrors(this.profileForm, error)) {
          return;
        }
        this.profileError.set(getRequestError(error));
      },
    });
  }

  private fillProfileForm(user: User): void {
    this.email.set(user.email);
    this.profileForm.reset({
      firstName: user.firstName,
      lastName: user.lastName,
      phone: user.phone ?? '',
      address: user.address ?? '',
      postalCode: user.postalCode ?? '',
      city: user.city ?? '',
      province: user.province ?? '',
    });
  }

  private showSuccess(message: WritableSignal<string>, text: string): void {
    message.set(text);
    setTimeout(() => message.set(''), SUCCESS_MESSAGE_MS);
  }

  // 401 = the server session has expired. We refresh the session state (user and CSRF token)
  // and go to the login page, which brings the user back here. Returns true if it was a 401
  private handleExpiredSession(error: HttpErrorResponse): boolean {
    if (error.status !== 401) {
      return false;
    }

    this.auth.loadSession().subscribe(() => {
      this.router.navigate(['/iniciar-sesion'], { queryParams: { returnUrl: '/mi-cuenta' } });
    });
    return true;
  }
}
