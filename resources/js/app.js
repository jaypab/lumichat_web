import './bootstrap';
import './chat';
import Alpine from 'alpinejs';
import 'driver.js/dist/driver.css';
import { driver } from 'driver.js';

window.driver = driver; // expose for the tour partial
window.Alpine = Alpine;

Alpine.start();
