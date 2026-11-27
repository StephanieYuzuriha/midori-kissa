import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

// Only lets the administrator into the panel (RF28). A customer goes to the home page and a
// visitor to the login page. The API checks the role again in every request (RS6)
export const adminGuard: CanActivateFn = (_route, state) => {
  const user = inject(AuthService).user();
  if (user?.role === 'admin') {
    return true;
  }
  if (user) {
    return inject(Router).createUrlTree(['/']);
  }
  return inject(Router).createUrlTree(['/iniciar-sesion'], { queryParams: { returnUrl: state.url } });
};
