import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ESTILOS GENERALES
                'resources/css/app.css',

                // LANDING PAGE ORIGINAL
                'resources/css/nutriliado-public.css',

                // PERSONALIZACIÓN DE SEDES
                //'resources/css/nutriliado-sedes.css',

                // LOGIN
                'resources/css/nutriliado-login.css',

                // RECUPERACIÓN DE CONTRASEÑA
                'resources/css/nutriliado-forgot.css',

                // CAPITÁN DE MESEROS
                'resources/css/capitan.css',

                // DASHBOARD
                'resources/css/dashboard_visual.css',

                // JAVASCRIPT
                'resources/js/app.js',
            ],
            refresh: true,
        }),

        tailwindcss(),
    ],

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
