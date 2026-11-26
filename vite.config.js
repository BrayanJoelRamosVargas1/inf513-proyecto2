import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
export default defineConfig({
    base: process.env.VITE_ASSET_BASE || '/build/',
    plugins: [laravel({input: ['resources/js/portal/main.jsx'], refresh: true}), react()],
    // Bound jsdom workers so low-memory development/acceptance machines do not
    // time out while starting all test environments simultaneously.
    test: {environment: 'jsdom', maxWorkers: 2, include: ['resources/js/portal/**/*.test.jsx']},
});
