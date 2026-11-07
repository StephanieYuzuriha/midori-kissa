import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { PrivacyPolicy } from './pages/privacy-policy/privacy-policy';

// URLs in Spanish because the customers see them (RI16)
export const routes: Routes = [
  { path: '', component: Home, title: 'Midori Kissa | Matcha y té japonés' },
  { path: 'politica-de-privacidad', component: PrivacyPolicy, title: 'Política de privacidad | Midori Kissa' },
  { path: '**', redirectTo: '' },
];
