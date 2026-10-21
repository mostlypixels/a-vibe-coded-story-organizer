/**
 * Sends PATCH as POST with an override header. Laravel reads the header and routes the
 * request as PATCH, so routes and validation stay the same.
 *
 * > [!WARNING]
 * > The o2switch front server resets the connection on a real PATCH request.
 * > The cPanel firewall settings do not change this.
 */
export const OVERRIDE_HEADER = 'X-HTTP-Method-Override';

/** Axios request interceptor. */
export function overridePatch(config) {
    if (config.method?.toLowerCase() !== 'patch') {
        return config;
    }

    config.method = 'post';
    config.headers ??= {};
    config.headers[OVERRIDE_HEADER] = 'PATCH';

    return config;
}
