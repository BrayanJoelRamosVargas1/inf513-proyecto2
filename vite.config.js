import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
export default defineConfig({
    base: process.env.VITE_ASSET_BASE || '/build/',
    plugins: [laravel({input: ['resources/js/portal/main.jsx'], refresh: true}), react()],
    test: {environment: 'jsdom', include: ['resources/js/portal/**/*.test.jsx']},
});
