// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',

  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
  ],

  theme: {
    extend: {
      fontFamily: {
        // Make Inter the default sans — prevents Figtree swap/resizing
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
        // Optional: semantic stack for headings
        heading: ['Poppins', 'Inter', ...defaultTheme.fontFamily.sans],
      },
    },
  },

  plugins: [forms],
}
