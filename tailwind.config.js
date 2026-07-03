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
                sans: ['Atkinson Hyperlegible Next', ...defaultTheme.fontFamily.sans],
            },

            // Project theme palette. `ocean` is the primary brand blue (replaces
            // the Breeze default indigo for focus rings / links / active chrome),
            // `navy` is the deep base used for primary buttons, `aqua` is the
            // light-blue tint, and `sun`/`flame` are the yellow/orange accents.
            colors: {
                ocean: {
                    50: '#ecf7fb',
                    100: '#cfeaf2',
                    200: '#a4d8e7',
                    300: '#6dc0d8',
                    400: '#40acc9',
                    500: '#219ebc',
                    600: '#1b809a',
                    700: '#18697e',
                    800: '#185767',
                    900: '#184a58',
                    950: '#0c2f3a',
                },
                aqua: {
                    50: '#f2f9fc',
                    100: '#ddeff7',
                    200: '#c2e4f1',
                    300: '#8ecae6',
                    400: '#63b4db',
                    500: '#429cc9',
                    600: '#327ea8',
                    700: '#2b6688',
                    800: '#285571',
                    900: '#26485f',
                },
                navy: {
                    50: '#ecf6fb',
                    100: '#d5e8f1',
                    500: '#0a5a7d',
                    600: '#074a68',
                    700: '#054157',
                    800: '#033a4d',
                    900: '#023047',
                    950: '#011f2f',
                },
                sun: {
                    50: '#fff9e6',
                    100: '#fff0c2',
                    300: '#ffd766',
                    400: '#ffc933',
                    500: '#ffb703',
                    600: '#d99b00',
                    700: '#a67700',
                },
                flame: {
                    50: '#fff3e6',
                    100: '#ffe0c2',
                    300: '#ffb866',
                    400: '#fd9d33',
                    500: '#fb8500',
                    600: '#d67100',
                    700: '#a85800',
                },
            },
        },
    },

    plugins: [forms],
};
