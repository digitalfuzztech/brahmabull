import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        rollupOptions: {
            preserveEntrySignatures: 'strict',
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chat/e2ee/index.js',
                'resources/js/chat/e2ee/team-messenger.js',
            ],
            refresh: true,
        }),
    ],
});
