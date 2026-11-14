import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

// Only lets logged-in users into the page. If nobody is logged in, it goes to the login page
// and comes back afterwards. The backend checks the session again in every request (RS6)
export const authGuard: CanActivateFn = (_route, state) => {
  if (inject(AuthService).user()) {
    return true;
  }
  return inject(Router).createUrlTree(['/iniciar-sesion'], { queryParams: { returnUrl: state.url } });
};
