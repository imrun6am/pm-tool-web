import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Navigation registry and the semantic Palette hold full color classes.
        './app/Support/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Vibrant "electric" blue. Text-bearing steps are AA on white:
                // 600 = 5.1:1 (4.7:1 on brand-50), 700 = 6.6:1. 500 and below
                // are for fills, gradients and focus rings, never text.
                brand: {
                    50: '#eef5ff',
                    100: '#d9e8ff',
                    200: '#bcd6ff',
                    300: '#8ebcff',
                    400: '#5a99fc',
                    500: '#2f7cf8',
                    600: '#0b63f6',
                    700: '#0a50d0',
                    800: '#0f42a6',
                    900: '#133a84',
                    950: '#0c2452',
                },
                // App-shell sidebar: a light neutral surface beside the
                // slate-50 canvas, PM-tool style. Contrast-checked (WCAG AA).
                sidebar: {
                    DEFAULT: '#ffffff',
                    border: colors.slate[200],
                    text: colors.slate[600],
                    muted: colors.slate[500],
                    hover: colors.slate[100],
                    active: '#eef5ff',
                    'active-text': '#0a50d0',
                },
                // Semantic status aliases.
                success: colors.emerald,
                warning: colors.amber,
                danger: colors.rose,
                info: colors.blue,
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                pop: '0 10px 30px -12px rgb(15 23 42 / 0.25)',
                // Button depth: hairline lift at rest, brand-blue glow on hover.
                btn: '0 1px 2px 0 rgb(15 23 42 / 0.05)',
                'btn-primary': '0 1px 2px 0 rgb(15 23 42 / 0.12), 0 2px 5px -1px rgb(11 99 246 / 0.30)',
                'btn-primary-hover': '0 6px 18px -4px rgb(11 99 246 / 0.5), 0 2px 6px -2px rgb(11 99 246 / 0.35)',
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

    plugins: [
        forms,
        // `sidebar-collapsed:` — styles that apply while the desktop sidebar is
        // collapsed to its icon rail (set pre-paint on <html>, see app layout).
        plugin(({ addVariant }) => {
            addVariant('sidebar-collapsed', 'html[data-sidebar="collapsed"] &');
        }),
    ],
};
