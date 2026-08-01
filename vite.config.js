import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        // Tailwind 4 compiles the stylesheet inside Vite; there is no PostCSS
        // config any more. Must come after laravel() so it sees the CSS entry
        // point the Laravel plugin declares.
        tailwindcss(),
    ],
    server: {
        // Bind-mounted source does not deliver filesystem events into a Linux
        // container from a Windows or macOS host, so Vite never learns a Blade
        // file changed: Tailwind keeps serving the CSS it generated at start-up
        // and a newly used utility class silently renders as nothing (the class
        // is in the HTML, the rule is missing from the stylesheet — which looks
        // like a caching problem in the browser, and no amount of Ctrl+F5 fixes
        // it). Polling is the only watcher that works across that boundary.
        //
        // Gated on VITE_USE_POLLING, which docker-compose.dev.yml sets, so a
        // local `npm run dev` keeps the cheap native watcher.
        //
        // The interval is a deliberate trade: polling walks every watched file
        // each pass, so a short one keeps a CPU core busy all day for a project
        // this size. 60s means an edit can take up to a minute to reach the
        // browser — refresh again if it hasn't landed yet, rather than assuming
        // it is broken.
        watch: process.env.VITE_USE_POLLING === 'true'
            ? { usePolling: true, interval: 60_000 }
            : undefined,
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
