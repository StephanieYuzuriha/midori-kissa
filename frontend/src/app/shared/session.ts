import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

// 401 = the server session has expired. Refreshes the session state (user and CSRF token) and
// goes to the login page, which brings the user back to returnUrl. Returns true if it was a 401
export function handleExpiredSession(
  error: HttpErrorResponse,
  auth: AuthService,
  router: Router,
  returnUrl: string,
): boolean {
  if (error.status !== 401) {
    return false;
  }

  auth.loadSession().subscribe(() => {
    router.navigate(['/iniciar-sesion'], { queryParams: { returnUrl } });
  });
  return true;
}
