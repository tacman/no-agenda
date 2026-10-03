import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['panel'];

  openAll() {
    this.panelTargets.forEach((panel) => { panel.open = true; });
  }

  closeAll() {
    this.panelTargets.forEach((panel) => { panel.open = false; });
  }
}
