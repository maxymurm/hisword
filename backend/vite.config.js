import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

// Normalize __dirname to handle non-ASCII characters in the path (e.g. Arabic folder names)
const __filename = fileURLToPath(import.meta.url);
const __dir = dirname(__filename);

export default defineConfig({
    root: __dir,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dir, 'resources/js'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'react-vendor': ['react', 'react-dom'],
                    'inertia': ['@inertiajs/react'],
                },
                // Content-hash filenames for long-term caching
                chunkFileNames: 'assets/js/[name]-[hash].js',
                assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
            },
        },
        target: 'es2020',
        chunkSizeWarningLimit: 600,
        sourcemap: false,
        minify: 'esbuild',
        cssMinify: true,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
