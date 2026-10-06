import { defineConfig } from 'vite';

/**
 * Builds the preview-only globe bundle (preview/globe-entry.js) into a single
 * IIFE file that is inlined into the static preview. Kept separate from the
 * application build, which compiles the css/js entries for Laravel's Vite
 * manifest and the Filament theme.
 */
export default defineConfig({
    publicDir: false, // never copy the application's public folder into the preview build
    build: {
        outDir: '/tmp/preview-globe',
        emptyOutDir: true,
        minify: true,
        lib: {
            entry: 'preview/globe-entry.js',
            name: 'PreviewGlobe',
            formats: ['iife'],
            fileName: () => 'globe.js',
        },
    },
});
