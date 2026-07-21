import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Vite binds 0.0.0.0 in Docker (see docker/supervisord.dev.conf) so the
        // container's port mapping can reach it, but the browser can't fetch
        // assets from 0.0.0.0 as an origin — advertise localhost instead so the
        // generated <script>/<link> tags point somewhere the browser can load.
        origin: 'http://localhost:5173',
        // Vite's CORS response otherwise echoes back `server.origin` itself
        // instead of reflecting the requesting page's origin, so the app
        // (served from :8000 by nginx) gets blocked fetching :5173 assets —
        // explicitly allow the app's origin.
        cors: {
            origin: 'http://localhost:8000',
        },
    },
});
