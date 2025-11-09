import './bootstrap';
import './chat';
import Alpine from 'alpinejs';
import 'driver.js/dist/driver.css';
import { driver } from 'driver.js';
import axios from "axios";
window.driver = driver; // expose for the tour partial
window.Alpine = Alpine;

Alpine.start();

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
if (token) axios.defaults.headers.common['X-CSRF-TOKEN'] = token;