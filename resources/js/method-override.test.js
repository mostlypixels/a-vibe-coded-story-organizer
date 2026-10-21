import { describe, expect, it } from 'vitest';
import axios from 'axios';
import { OVERRIDE_HEADER, overridePatch } from './method-override';

describe('overridePatch', () => {
    it('sends PATCH as POST with the override header', () => {
        const config = overridePatch({ method: 'patch', headers: {} });

        expect(config.method).toBe('post');
        expect(config.headers[OVERRIDE_HEADER]).toBe('PATCH');
    });

    it('leaves other methods alone', () => {
        for (const method of ['get', 'post', 'put', 'delete']) {
            const config = overridePatch({ method, headers: {} });

            expect(config.method).toBe(method);
            expect(config.headers[OVERRIDE_HEADER]).toBeUndefined();
        }
    });

    it('rewrites a real axios.patch call', async () => {
        const client = axios.create();
        client.interceptors.request.use(overridePatch);
        let sent = null;
        client.defaults.adapter = async (config) => {
            sent = config;

            return { data: {}, status: 200, statusText: 'OK', headers: {}, config };
        };

        await client.patch('/admin/appearance', { ui_scale: 'md' });

        expect(sent.method).toBe('post');
        expect(sent.headers.get(OVERRIDE_HEADER)).toBe('PATCH');
        expect(JSON.parse(sent.data)).toEqual({ ui_scale: 'md' });
    });
});
