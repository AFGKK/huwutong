/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/public/**/*.blade.php',
        './resources/views/public/partials/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: 'var(--pg-primary-50, #eff6ff)',
                    100: 'var(--pg-primary-100, #dbeafe)',
                    200: 'var(--pg-primary-200, #bfdbfe)',
                    300: 'var(--pg-primary-300, #93c5fd)',
                    400: 'var(--pg-primary-400, #60a5fa)',
                    500: 'var(--pg-primary-500, #3b82f6)',
                    600: 'var(--pg-primary, #2563eb)',
                    700: 'var(--pg-primary-700, #1d4ed8)',
                    800: 'var(--pg-primary-800, #1e40af)',
                    900: 'var(--pg-primary-900, #1e3a8a)',
                },
            },
        },
    },
    plugins: [],
};
