import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'fs';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const isDevServer = mode === 'development';
    const useHttps = env.VITE_HTTPS === 'true';

    // Logic to find certs whether in Docker or WSL
    const getCertPath = (file) => {
        const internalPath = `/etc/nginx/certs/${file}`; // Path inside Docker
        const externalPath = path.resolve(__dirname, '../certs/', file); // Path in WSL

        if (fs.existsSync(internalPath)) return internalPath;
        if (fs.existsSync(externalPath)) return externalPath;
        return null;
    };

    const serverConfig = isDevServer && useHttps
        ? {
            https: {
                key: fs.readFileSync(getCertPath('localhost.key')),
                cert: fs.readFileSync(getCertPath('localhost.crt')),
            },
            host: '0.0.0.0', // Essential for Docker/WSL networking
            port: 5173,
            hmr: { host: 'localhost', protocol: 'wss' },
            watch: { usePolling: true, interval: 100 },
        }
        : {
            host: 'localhost',
            port: 5173,
        };

    return {
        server: serverConfig,
        plugins: [
            laravel({
                input: 'resources/js/app.tsx',
                refresh: true,
            }),
            react(),
        ],
    };
});
