import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  // CSS is served from the theme's nested dist directory, not the site root.
  base: './',
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/js/main.js'),
      },
    },
  },
  css: {
    devSourcemap: true,
  },
});
