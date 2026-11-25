import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { Account } from './pages/account/account';
import { Cart } from './pages/cart/cart';
import { Catalog } from './pages/catalog/catalog';
import { Home } from './pages/home/home';
import { Login } from './pages/login/login';
import { PrivacyPolicy } from './pages/privacy-policy/privacy-policy';
import { ProductDetail } from './pages/product-detail/product-detail';
import { Register } from './pages/register/register';

// URLs in Spanish because the customers see them (RI16)
export const routes: Routes = [
  { path: '', component: Home, title: 'Midori Kissa | Matcha y té japonés' },
  { path: 'tienda', component: Catalog, title: 'Tienda | Midori Kissa' },
  // No title here: the page sets it with the product name when it arrives
  { path: 'producto/:slug', component: ProductDetail },
  { path: 'iniciar-sesion', component: Login, title: 'Iniciar sesión | Midori Kissa' },
  { path: 'crear-cuenta', component: Register, title: 'Crear cuenta | Midori Kissa' },
  { path: 'carrito', component: Cart, title: 'Carrito | Midori Kissa' },
  { path: 'mi-cuenta', component: Account, canActivate: [authGuard], title: 'Mi cuenta | Midori Kissa' },
  { path: 'politica-de-privacidad', component: PrivacyPolicy, title: 'Política de privacidad | Midori Kissa' },
  { path: '**', redirectTo: '' },
];
