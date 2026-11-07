import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, ValidationErrors } from '@angular/forms';

const PASSWORD_MIN_LENGTH = 8;

// Same password rule as the backend (RS2): at least 8 characters, a letter and a number
export function passwordValidator(control: AbstractControl): ValidationErrors | null {
  const value: string = control.value ?? '';
  if (value === '') {
    return null; // Validators.required handles empty fields
  }

  const isValid = value.length >= PASSWORD_MIN_LENGTH && /[A-Za-z]/.test(value) && /[0-9]/.test(value);
  return isValid ? null : { password: true };
}

// Message shown under a form field, with the same texts as the backend
export function getFieldError(control: AbstractControl | null): string {
  const errors = control?.errors;
  if (!errors) {
    return '';
  }
  if (errors['required']) {
    return 'Este campo es obligatorio.';
  }
  if (errors['email']) {
    return 'El email no es válido.';
  }
  if (errors['maxlength']) {
    return `Máximo ${errors['maxlength'].requiredLength} caracteres.`;
  }
  if (errors['password']) {
    return 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
  }
  // Error sent by the API for this field (422)
  if (errors['server']) {
    return errors['server'];
  }
  return 'El valor no es válido.';
}

// Message shown when a request to the API fails
export function getRequestError(error: HttpErrorResponse): string {
  if (error.status === 0 || error.status >= 500) {
    return 'No se ha podido conectar con la tienda. Inténtalo de nuevo en unos minutos.';
  }
  if (typeof error.error?.error === 'string') {
    return error.error.error;
  }
  return 'Ha ocurrido un error. Inténtalo de nuevo.';
}
