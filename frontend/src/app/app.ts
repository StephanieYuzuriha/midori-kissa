import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CartPanel } from './layout/cart-panel/cart-panel';
import { Header } from './layout/header/header';

@Component({
  imports: [RouterOutlet, Header, CartPanel],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {}
