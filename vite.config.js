import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Bootstrap's Sass isn't used here (we import compiled CSS), but if you
    // later switch to customizing Bootstrap via Sass, add the scss entry and
    // a sass preprocessor here.
});
