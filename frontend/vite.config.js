import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  envDir: '../backend',
  publicDir: false,
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
  },
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      publicDirectory: '../backend/public',
      refresh: ['resources/views/**', '../backend/app/**', '../backend/routes/**'],
    }),
  ],
});
