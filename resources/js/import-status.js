/**
 * Polls the state of one queued or running import on the import page.
 *
 * A stalled import reloads the page, because only the server renders the Resume and Discard forms.
 */

export const POLL_INTERVAL_MS = 3000;

export function registerImportStatus(Alpine, reload = () => window.location.reload()) {
    Alpine.data('importStatus', (url, state, label) => ({
        state,
        label,
        projectUrl: null,
        timer: null,

        init() {
            this.timer = setInterval(() => this.poll(), POLL_INTERVAL_MS);
        },

        destroy() {
            this.stop();
        },

        stop() {
            clearInterval(this.timer);
            this.timer = null;
        },

        async poll() {
            let data;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    return;
                }

                data = await response.json();
            } catch {
                // A network error is temporary. The next tick tries again.
                return;
            }

            this.state = data.state;
            this.label = data.label;

            if (data.state === 'completed') {
                this.stop();
                this.projectUrl = data.project_url;
            } else if (data.state === 'stalled') {
                this.stop();
                reload();
            }
        },
    }));
}
