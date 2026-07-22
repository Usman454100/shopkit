import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// base matches where this SPA is actually served from once deployed —
// backend/public/superadmin/ on the central domain (see docs/02-ARCHITECTURE.md §4).
export default defineConfig({
  plugins: [react()],
  base: '/superadmin/',
})
