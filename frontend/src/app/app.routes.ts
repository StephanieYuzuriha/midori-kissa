import { Routes } from '@angular/router';
import { Home } from './pages/home/home';

// URLs in Spanish because the customers see them (RI16)
export const routes: Routes = [
  { path: '', component: Home, title: 'Midori Kissa | Matcha y té japonés' },
  { path: '**', redirectTo: '' },
];
