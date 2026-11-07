import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

// HTTP methods that change data and must carry the CSRF token (RS11)
const METHODS_WITH_TOKEN = ['POST', 'PUT', 'PATCH', 'DELETE'];

// Adds the CSRF token only to requests that change data in our own API,
// never to external services
export const csrfInterceptor: HttpInterceptorFn = (request, next) => {
  const isOwnApi = request.url.startsWith('/api/');
  if (!isOwnApi || !METHODS_WITH_TOKEN.includes(request.method)) {
    return next(request);
  }

  const token = inject(AuthService).csrfToken;
  return next(request.clone({ setHeaders: { 'X-CSRF-Token': token } }));
};
