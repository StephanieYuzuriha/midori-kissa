import { registerLocaleData } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import localeEs from '@angular/common/locales/es';
import {
  ApplicationConfig,
  LOCALE_ID,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideRouter, withInMemoryScrolling } from '@angular/router';
import { routes } from './app.routes';
import { csrfInterceptor } from './interceptors/csrf.interceptor';
import { AuthService } from './services/auth.service';

// Prices and numbers in Spanish format: 28,50 €
registerLocaleData(localeEs);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    // Each new page starts at the top (also when changing the catalog page)
    provideRouter(routes, withInMemoryScrolling({ scrollPositionRestoration: 'enabled' })),
    { provide: LOCALE_ID, useValue: 'es-ES' },
    provideHttpClient(withInterceptors([csrfInterceptor])),
    // Load the session before showing the shop (user name in the header and CSRF token)
    provideAppInitializer(() => inject(AuthService).loadSession()),
  ],
};
