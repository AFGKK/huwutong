/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts}',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: 'var(--pg-primary-50, #f8fafc)',
                    100: 'var(--pg-primary-100, #f1f5f9)',
                    200: 'var(--pg-primary-200, #e2e8f0)',
                    300: 'var(--pg-primary-300, #cbd5e1)',
                    400: 'var(--pg-primary-400, #94a3b8)',
                    500: 'var(--pg-primary-500, #64748b)',
                    600: 'var(--pg-primary, #0f172a)',
                    700: 'var(--pg-primary-700, #0f172a)',
                    800: 'var(--pg-primary-800, #020617)',
                    900: 'var(--pg-primary-900, #020617)',
                },
            },
        },
    },
    plugins: [],
};
