
export default {
  mode: 'universal',
  env:{
    mode:process.env.mode
  },
  /*
  ** Headers of the page
  */
  head: {
    title: process.env.npm_package_name || '',
    meta: [
      { charset: 'utf-8' },
      { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      { hid: 'description', name: 'description', content: process.env.npm_package_description || '' }
    ],
    link: [
      { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }
    ]
  },
  /*
  ** Customize the progress-bar color
  */
  loading: { color: '#fff' },
  /*
  ** Global CSS
  */
  css: [
    '@/static/css/font/iconfont.css',
    'element-ui/lib/theme-chalk/index.css',
    'swiper/dist/css/swiper.css',
    'mint-ui/lib/style.css',
    '@/assets/css/global.scss'
  ],
  /*
  ** Plugins to load before mounting the App
  */
  plugins: [
    '@/plugins/element-ui',
    '@/plugins/mint-ui',
    '@/plugins/axios',
    {src: '@/plugins/vue-awesome-swiper', ssr:false}
  ],
  /*
  ** Nuxt.js dev-modules
  */
  buildModules: [
  ],
  /*
  ** Nuxt.js modules
  */
  modules: [
    '@nuxtjs/style-resources',
    '@nuxtjs/axios'
  ],
  axios: {
    proxy: true // Can be also an object with default options
  },
  proxy: {
    '/api': {
      target: 'https://suggest.taobao.com',
      pathRewrite: {
        '^/api/': '/',
      }
    },
    '/ajaxurl': {
      target: 'http://appapi.yunyou.com',
      pathRewrite: {
        '^/ajaxurl/': '/',
      }
    }
  },
  styleResources: {
    scss: './assets/css/global.scss',
    // sass: ...
  },
  /*
  ** Build configuration
  */
  build: {
    transpile: [/^element-ui/],
    /*
    ** You can extend webpack config here
    */
    extend (config, ctx) {
    }
  }
}
