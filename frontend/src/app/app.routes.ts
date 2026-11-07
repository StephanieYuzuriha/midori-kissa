import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { Login } from './pages/login/login';
import { PrivacyPolicy } from './pages/privacy-policy/privacy-policy';
import { Register } from './pages/register/register';

// URLs in Spanish because the customers see them (RI16)
export const routes: Routes = [
  { path: '', component: Home, title: 'Midori Kissa | Matcha y té japonés' },
  { path: 'iniciar-sesion', component: Login, title: 'Iniciar sesión | Midori Kissa' },
  { path: 'crear-cuenta', component: Register, title: 'Crear cuenta | Midori Kissa' },
  { path: 'politica-de-privacidad', component: PrivacyPolicy, title: 'Política de privacidad | Midori Kissa' },
  { path: '**', redirectTo: '' },
];
