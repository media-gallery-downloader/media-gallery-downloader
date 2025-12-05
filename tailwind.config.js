/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./vendor/filament/**/*.blade.php",
        "./app/Filament/**/*.php",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
