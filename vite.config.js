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
    // laravel-vite-plugin sets `publicDir: false`, so the dev server serves
    // nothing from `public/`. app.css asks for `/fonts/*.woff2`, and in dev the
    // browser loads that stylesheet from the Vite origin (:5173), so the font
    // URLs resolve against :5173 too and 404 — the page silently renders in the
    // fallback family because `font-display: swap` hides the failure. A build
    // does not have the problem: the app origin serves both the CSS and
    // `public/fonts`.
    publicDir: 'public',
    build: {
        // `publicDir` above makes `vite build` copy `public/` into the output
        // directory, which is `public/build` — the whole tree into a folder
        // inside itself. The dev server is the only thing that needs the public
        // directory, so turn the copy off.
        copyPublicDir: false,
    },
    server: {
        // Bind-mounted source does not deliver filesystem events into a Linux
        // container from a Windows or macOS host, so Vite never learns a Blade
        // file changed: Tailwind keeps serving the CSS it generated at start-up
        // and a newly used utility class silently renders as nothing (the class
        // is in the HTML, the rule is missing from the stylesheet — which looks
        // like a caching problem in the browser, and no amount of Ctrl+F5 fixes
        // it). Polling is the only watcher that works across that boundary.
        //
        // Verified against Tailwind 4 on 2026-08-01: v4 scans for classes itself
        // rather than reading a `content` array, so this setting had to be
        // re-checked rather than assumed. Adding a previously unused utility to
        // a Blade file in the dev container made its rule appear in the served
        // stylesheet within the poll interval — the polling watcher still drives
        // v4's scanner, and the failure described above is still what you get
        // without it.
        //
        // Gated on VITE_USE_POLLING, which docker-compose.dev.yml sets, so a
        // local `npm run dev` keeps the cheap native watcher.
        //
        // The interval is a deliberate trade: polling walks every watched file
        // each pass, so a short one keeps a CPU core busy all day for a project
        // this size. 60s means an edit can take up to a minute to reach the
        // browser — refresh again if it hasn't landed yet, rather than assuming
        // it is broken.
        watch: {
            // Vite already ignores .git, node_modules and the build output. These
            // are the rest of the tree that no entry point, Tailwind source or HMR
            // input reaches, so watching them only buys walk cost and false
            // reloads.
            //
            // Cost is not file count — it is which filesystem the files sit on.
            // `vendor` holds more files than everything below put together, but it
            // is an anonymous volume on the container's own disk, and a pass over
            // it is too cheap to measure. The paths below are on the Windows/macOS
            // bind mount, where a pass costs seconds.
            //
            // `storage` dominates, and it grows back: storage/app/exports keeps one
            // zip or epub per export, and the test suite writes there too. Measured
            // on 2026-08-07, a bind-mount pass over a storage tree of ~19k files
            // took 14s on its own. Treat that as an order of magnitude, not a
            // constant — the number tracks how much junk has piled up since the
            // last clear-out.
            //
            // > [!WARNING]
            // > This is the "page hangs on load" symptom. A cold start walks the
            // > tree before it serves the first stylesheet, so the browser blocks
            // > on app.css with no error anywhere. The polling watcher then
            // > re-walks the same tree every interval. If dev feels slow again,
            // > measure the walk before you suspect the dependencies.
            //
            // .idea and bootstrap/cache earn their place for churn, not size —
            // PhpStorm and artisan rewrite them constantly, and each write was a
            // spurious full page reload.
            //
            // `vendor` is deliberately absent: app.css has an @source glob into
            // laravel/framework's pagination views, and a pass there is too cheap
            // to be worth the risk.
            ignored: [
                '**/storage/**',
                '**/.claude/**',
                '**/.specs/**',
                '**/.idea/**',
                '**/bootstrap/cache/**',
                '**/.phpunit.result.cache',
            ],
            ...(process.env.VITE_USE_POLLING === 'true'
                ? { usePolling: true, interval: 60_000 }
                : {}),
        },
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
