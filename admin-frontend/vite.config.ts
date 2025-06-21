import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';
// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  base: '/admin/',
  server: {
    port: 3002, // 管理画面用に3002ポートを使用
    proxy: {
      '/api': {
        target: 'http://localhost:8000', // APIサーバーのURL
        changeOrigin: true,
      },
    },
  },
});
