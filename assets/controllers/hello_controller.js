import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['name', 'output']
    
    connect() {
        console.log('Hello controller connected');
    }
    
    greet() {
        const name = this.nameTarget.value || 'World';
        this.outputTarget.textContent = `Hello, ${name}! 🎉`;
        this.outputTarget.style.display = 'block';
    }
}