import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'fs';

export default defineConfig({
    server: {
        https: {
            key: fs.readFileSync('/etc/vite/certs/localhost.key'),
            cert: fs.readFileSync('/etc/vite/certs/localhost.crt'),
        },
        host: true,
        port: 5173,
        hmr: {
            host: 'localhost',
            protocol: 'wss', // WebSocket Secure for HMR with HTTPS
        },
        watch: {
            usePolling: true,    // In Docker on Windows pick up changes by polling
            interval: 100,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
});
