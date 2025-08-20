import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/Breastfeeding-Help-Support': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => {
          console.log('Proxying request:', path)
          return path
        }
      }
    }
  }
})