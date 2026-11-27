import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormGroup, ValidationErrors } from '@angular/forms';

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

// Same postal code rule as the backend: 5 digits. Empty is allowed because the field is optional
export function postalCodeValidator(control: AbstractControl): ValidationErrors | null {
  const value: string = control.value ?? '';
  return value === '' || /^[0-9]{5}$/.test(value) ? null : { postalCode: true };
}

// Whole number (stock, grams). Empty is left to Validators.required
export function integerValidator(control: AbstractControl): ValidationErrors | null {
  const value = control.value;
  return value === null || value === '' || Number.isInteger(value) ? null : { integer: true };
}

// Amount in euros with two decimals at most, as the price column of the database
export function priceValidator(control: AbstractControl): ValidationErrors | null {
  const value = control.value;
  return value === null || value === '' || Math.round(value * 100) / 100 === value ? null : { decimals: true };
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
  if (errors['postalCode']) {
    return 'El código postal debe tener 5 cifras.';
  }
  if (errors['integer']) {
    return 'Escribe un número entero.';
  }
  if (errors['decimals']) {
    return 'Como máximo dos decimales.';
  }
  if (errors['min']) {
    return `El valor mínimo es ${errors['min'].min}.`;
  }
  if (errors['max']) {
    return `El valor máximo es ${errors['max'].max}.`;
  }
  // Error sent by the API for this field (422)
  if (errors['server']) {
    return errors['server'];
  }
  return 'El valor no es válido.';
}

// Shows the errors that the API sends for each field (422) under the matching form field.
// Returns false if the error is not a validation error.
export function setServerErrors(form: FormGroup, error: HttpErrorResponse): boolean {
  if (error.status !== 422 || !error.error?.errors) {
    return false;
  }

  for (const [field, message] of Object.entries(error.error.errors)) {
    const control = form.get(field);
    control?.setErrors({ server: message });
    control?.markAsTouched();
  }
  return true;
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
