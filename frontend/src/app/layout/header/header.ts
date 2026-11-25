import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { CartService } from '../../services/cart.service';

// Provisional header: shop name, cart and account links (RF6, RF7)
@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './header.html',
})
export class Header {
  protected auth = inject(AuthService);
  protected cart = inject(CartService);
  private router = inject(Router);

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigateByUrl('/'),
      error: (error) => console.error('No se ha podido cerrar la sesión', error),
    });
  }
}
