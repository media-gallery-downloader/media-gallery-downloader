export default {
    "*.php": ["php ./vendor/bin/pint"],
    "resources/js/**/*.{js,ts}": ["deno fmt", "deno lint --fix"],
};
