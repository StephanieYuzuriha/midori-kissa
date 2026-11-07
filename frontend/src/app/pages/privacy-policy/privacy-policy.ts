import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

// Informative page with the privacy policy (RS19). It will also be linked from the footer (RI2)
@Component({
  selector: 'app-privacy-policy',
  imports: [RouterLink],
  templateUrl: './privacy-policy.html',
})
export class PrivacyPolicy {}
