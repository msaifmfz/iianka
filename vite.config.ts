import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerHost = env.VITE_DEV_SERVER_HOST || '127.0.0.1';
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || '5173');
    const devServerOrigin = env.VITE_DEV_SERVER_ORIGIN || undefined;
    const hmrHost = env.VITE_HMR_HOST || undefined;
    const hmrProtocol = env.VITE_HMR_PROTOCOL || undefined;
    const hmrClientPort = env.VITE_HMR_CLIENT_PORT ? Number(env.VITE_HMR_CLIENT_PORT) : undefined;
    const appHost = env.APP_URL ? new URL(env.APP_URL).hostname : undefined;
    const devServerOriginHost = devServerOrigin ? new URL(devServerOrigin).hostname : undefined;
    const allowedHosts = [
        'localhost',
        '127.0.0.1',
        appHost,
        devServerOriginHost,
        hmrHost,
        ...(env.VITE_ALLOWED_HOSTS ? env.VITE_ALLOWED_HOSTS.split(',').map((host) => host.trim()) : []),
    ].filter((host): host is string => Boolean(host));

    return {
        server: {
            host: devServerHost,
            port: devServerPort,
            strictPort: true,
            cors: true,
            origin: devServerOrigin,
            allowedHosts,
            hmr: hmrHost || hmrProtocol || hmrClientPort
                ? {
                    host: hmrHost,
                    protocol: hmrProtocol,
                    clientPort: hmrClientPort ?? devServerPort,
                }
                : undefined,
        },
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
            }),
            inertia(),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
