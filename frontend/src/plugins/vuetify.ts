import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#1565C0',
          secondary: '#0D47A1',
          accent: '#2196F3',
          success: '#2E7D32',
          warning: '#F57F17',
          error: '#C62828',
          info: '#0277BD',
          background: '#F5F7FA',
          surface: '#FFFFFF',
        },
      },
    },
  },
})
