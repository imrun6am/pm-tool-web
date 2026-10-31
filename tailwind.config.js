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
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                pop: '0 10px 30px -12px rgb(15 23 42 / 0.25)',
                // Button depth: hairline lift at rest, indigo-tinted glow on hover.
                btn: '0 1px 2px 0 rgb(15 23 42 / 0.05)',
                'btn-primary': '0 1px 2px 0 rgb(15 23 42 / 0.12), 0 2px 5px -1px rgb(79 70 229 / 0.28)',
                'btn-primary-hover': '0 6px 18px -4px rgb(79 70 229 / 0.5), 0 2px 6px -2px rgb(79 70 229 / 0.35)',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.25s ease-out both',
            },
        },
    },

    plugins: [forms],
};
