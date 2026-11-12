import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                deep: '#0c4a46',
                brand: { DEFAULT: '#14a39a', dark: '#0f8d85' },
                mint: '#5eead4',
                soft: '#e3f4f2',
                canvas: '#f2f6f5',
                ink: '#12302d',
                mute: '#6b8480',
                line: '#dde7e5',
                expense: '#e0674f',
                income: '#14a39a',
            },
        },
    },

    plugins: [forms],
};
