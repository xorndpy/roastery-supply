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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'Georgia', 'serif'],
            },
            colors: {
                // Brand coklat espresso
                brand: {
                    DEFAULT: '#4a2f21',
                    dark: '#35200f',
                    soft: '#efe6dc',
                },
                // "orange" dipetakan ke copper agar seluruh view lama (admin, checkout, dll) ikut tema baru
                orange: {
                    50: '#fbf3ec', 100: '#f4e3d3', 200: '#ecd0b0', 300: '#d9a97a', 400: '#c48655',
                    500: '#a8643a', 600: '#8f5230', 700: '#734027', 800: '#5a321f', 900: '#402216', 950: '#24150e',
                },
                // "stone" dibuat sedikit lebih hangat
                stone: {
                    50: '#faf7f3', 100: '#f3ece4', 200: '#e6ddd3', 300: '#d3c6b9', 400: '#a8998b',
                    500: '#7a6a5d', 600: '#5f5046', 700: '#4a3d35', 800: '#33281f', 900: '#241a14', 950: '#17100c',
                },
            },
        },
    },

    plugins: [forms],
};
