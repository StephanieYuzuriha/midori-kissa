import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

// Provisional home page, until the catalog is ready
@Component({
  selector: 'app-home',
  imports: [RouterLink],
  templateUrl: './home.html',
})
export class Home {}
