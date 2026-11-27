import { Component, ElementRef, Injector, afterNextRender, inject, input, output, viewChild } from '@angular/core';

let nextId = 0;

// Confirmation window before important actions, such as deleting a product (RI13).
// It uses the <dialog> element of the browser: it keeps the keyboard focus inside,
// closes with Escape and gives the focus back to the button that opened it
@Component({
  selector: 'app-confirm-dialog',
  templateUrl: './confirm-dialog.html',
})
export class ConfirmDialog {
  readonly title = input.required<string>();
  readonly message = input.required<string>();
  readonly confirmLabel = input('Confirmar');
  readonly confirmed = output<void>();

  protected readonly titleId = `confirm-title-${nextId++}`;
  private dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');
  private injector = inject(Injector);

  // Waits until the message is updated on screen (for example, the name of the product)
  // before showing the window, so it never shows the text of the previous time
  open(): void {
    afterNextRender(() => this.dialog().nativeElement.showModal(), { injector: this.injector });
  }

  protected close(): void {
    this.dialog().nativeElement.close();
  }

  protected confirm(): void {
    this.close();
    this.confirmed.emit();
  }
}
