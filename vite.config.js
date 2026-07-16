import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';

function collectModuleAssetInputs() {
    const modulesRoot = path.resolve('app/Modules');

    if (!fs.existsSync(modulesRoot)) {
        return [];
    }

    return fs.readdirSync(modulesRoot, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .flatMap((entry) => {
            const assetsDir = path.join(modulesRoot, entry.name, 'Resources', 'assets');

            if (!fs.existsSync(assetsDir)) {
                return [];
            }

            return walkFiles(assetsDir)
                .filter((file) => file.endsWith('.js') || file.endsWith('.css'))
                .map((file) => path.relative(process.cwd(), file).replaceAll('\\', '/'));
        });
}

function walkFiles(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const fullPath = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            return walkFiles(fullPath);
        }

        return [fullPath];
    });
}

const moduleAssetInputs = collectModuleAssetInputs();

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/classic/main.css',
                'resources/css/wapro/home.css',
                'resources/js/app.js',
                'resources/js/wapro/home.js',
                'resources/js/wapro/auth.js',
                'resources/js/components/frontend-menu-builder.js',
                'resources/js/classic/index.js',
                ...moduleAssetInputs,
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            external: (id) => id.startsWith('/assets/images/previews/'),
            output: {
                manualChunks: {
                    apexcharts: ['apexcharts'],
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
