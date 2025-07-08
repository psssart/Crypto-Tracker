import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'fs';

const isDevServer = process.env.NODE_ENV !== 'production';
const isDocker = process.env.VITE_HTTPS === 'true';

// Dev server settings depending on the environment
const serverConfig = isDevServer && isDocker
    ? {
        https: {
            key: fs.readFileSync('/etc/vite/certs/localhost.key'),
            cert: fs.readFileSync('/etc/vite/certs/localhost.crt'),
        },
        host: true,
        port: 5173,
        hmr: { host: 'localhost', protocol: 'wss' },
        watch: { usePolling: true, interval: 100 },
    }
    : {
        host: 'localhost',
        port: 5173,
        open: true,
    };

export default defineConfig({
    server: serverConfig,
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
});
