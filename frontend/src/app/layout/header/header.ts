import { Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

// Provisional header: shop name and account links (RF6, RF7)
@Component({
  selector: 'app-header',
  imports: [RouterLink],
  templateUrl: './header.html',
})
export class Header {
  protected auth = inject(AuthService);
  private router = inject(Router);

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigateByUrl('/'),
      error: (error) => console.error('No se ha podido cerrar la sesión', error),
    });
  }
}
