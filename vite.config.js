
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ESTILOS GENERALES
                'resources/css/app.css',

                // LANDING PAGE NUTRILIADO
                'resources/css/nutriliado-public.css',

                // LOGIN
                'resources/css/nutriliado-login.css',

                // RECUPERACION DE CONTRASENA
                'resources/css/nutriliado-forgot.css',

                // CAPITAN DE MESEROS
                'resources/css/capitan.css',

                // PANEL PRINCIPAL - DASHBOARD
                'resources/css/dashboard_visual.css',

                // JAVASCRIPT GENERAL
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
