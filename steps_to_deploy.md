I want to deploy my App Laravel vuejs3 to Hostinger (shared hosted); what i did:
Switching to root domain https://tujjar.shop will simplify everything:

🔧 Configuration Updates
1. Update Vite Config

vite.config.ts
`import { fileURLToPath } from 'node:url'
import laravel from 'laravel-vite-plugin'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

// https://vitejs.dev/config/
export default defineConfig({
  // Remove base and subfolder plugin - we're at root now
  build: {
    chunkSizeWarningLimit: 5000,
    rollupOptions: {
      output: {
        chunkFileNames: 'assets/[name]-[hash].js',
        entryFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        manualChunks: undefined,
      },
    },
  },
  plugins: [
    VueRouter({
      getRouteName: routeNode => {
        return getPascalCaseRouteName(routeNode)
          .replace(/([a-z\d])([A-Z])/g, '$1-$2')
          .toLowerCase()
      },
      routesFolder: 'resources/ts/pages',
    }),
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag === 'swiper-container' || tag === 'swiper-slide',
        },
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    laravel({
      input: ['resources/ts/main.ts'],
      refresh: true,
      buildDirectory: 'build',
    }),
    // Remove createSubfolderPlugin - not needed for root domain
    vueJsx(),
    vuetify({
      styles: {
        configFile: 'resources/styles/variables/_vuetify.scss',
      },
    }),
    MetaLayouts({
      target: './resources/ts/layouts',
      defaultLayout: 'default',
    }),
    Components({
      dirs: ['resources/ts/@core/components', 'resources/ts/views/demos', 'resources/ts/components'],
      dts: true,
      resolvers: [
        componentName => {
          if (componentName === 'VueApexCharts')
            return { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
        },
      ],
    }),
    AutoImport({
      imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './resources/ts/@core/utils',
        './resources/ts/@core/composable/',
        './resources/ts/composables/',
        './resources/ts/utils/',
        './resources/ts/plugins/*/composables/*',
      ],
      vueTemplate: true,
      ignore: ['useCookies', 'useStorage'],
    }),
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [
        fileURLToPath(new URL('./resources/ts/plugins/i18n/locales/**', import.meta.url)),
      ],
    }),
    svgLoader(),
  ],
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@core-scss': fileURLToPath(new URL('./resources/styles/@core', import.meta.url)),
      '@': fileURLToPath(new URL('./resources/ts', import.meta.url)),
      '@themeConfig': fileURLToPath(new URL('./themeConfig.ts', import.meta.url)),
      '@core': fileURLToPath(new URL('./resources/ts/@core', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./resources/ts/@layouts', import.meta.url)),
      '@images': fileURLToPath(new URL('./resources/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(new URL('./resources/styles/variables/_template.scss', import.meta.url)),
      '@db': fileURLToPath(new URL('./resources/ts/plugins/fake-api/handlers/', import.meta.url)),
      '@api-utils': fileURLToPath(new URL('./resources/ts/plugins/fake-api/utils/', import.meta.url)),
    },
  },
  optimizeDeps: {
    exclude: ['vuetify'],
    entries: [
      './resources/ts/**/*.vue',
    ],
  },
  server: {
    hmr: {
      host: 'localhost',
    },
  },
})`

2. Update Router Config (Root Domain)
ressources/ts/plugins/1.router/index.ts
`
import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupRouterGuards } from '@/plugins/router/guards'

const router = createRouter({
  history: createWebHistory('/'), // Root domain - no subfolder
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: routes => setupLayouts(routes as unknown as RouteRecordRaw[]),
})

// Setup route guards
setupRouterGuards(router)

export { router }

export default function (app: App) {
  app.use(router)
}
  `

3. Update Production Environment
`APP_NAME="Arif Style"
APP_ENV=production
APP_KEY=base64:Vlo5AIvZqEf/LFtfk2igVlL+r2h6GBeZDrJNuhEGa8g=
APP_DEBUG=false
APP_URL=https://tujjar.shop

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Update database credentials for new Hostinger account
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u736495871_tujjarshop
DB_USERNAME=u736495871_tujjarshop
DB_PASSWORD=ABDOwahna135795!

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Update for root domain
SANCTUM_STATEFUL_DOMAINS=tujjar.shop,localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=affiliosup@zincolo.com
MAIL_PASSWORD=ABDOwahna135795!
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=affiliosup@zincolo.com
MAIL_FROM_NAME="Affilio Support"

# Security
SESSION_SECURE_COOKIE=true

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
VITE_API_BASE_URL=https://tujjar.shop/api
VITE_MAPBOX_ACCESS_TOKEN=
VITE_APP_BASE_URL=/

# OzonExpress Shipping Configuration
OZONEXPRESS_ENABLED=true
OZONEXPRESS_ID=10611
OZONEXPRESS_KEY=9dbaa5-92a257-e44cba-3df5aa-76a0dd
OZONEXPRESS_BASE_URL=https://api.ozonexpress.ma
OZONEXPRESS_DEBUG_BL=true


# Add these lines to your existing .env
ASSET_URL=https://tujjar.shop
MIX_ASSET_URL=https://tujjar.shop
VITE_ASSET_URL=https://tujjar.shop`

4. Update Local Environment (for development)
`APP_NAME="Arif Style"
APP_ENV=local
APP_KEY=base64:Vlo5AIvZqEf/LFtfk2igVlL+r2h6GBeZDrJNuhEGa8g=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=affiliate_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=affiliosup@zincolo.com
MAIL_PASSWORD=ABDOwahna135795!
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=affiliosup@zincolo.com
MAIL_FROM_NAME="Affilio Support"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_BASE_URL=/
VITE_MAPBOX_ACCESS_TOKEN=


# OzonExpress Shipping Configuration
OZONEXPRESS_ENABLED=true
OZONEXPRESS_ID=10611
OZONEXPRESS_KEY=9dbaa5-92a257-e44cba-3df5aa-76a0dd
OZONEXPRESS_BASE_URL=https://api.ozonexpress.ma
OZONEXPRESS_DEBUG_BL=true


# Add these lines to your existing .env
ASSET_URL=https://tujjar.shop
MIX_ASSET_URL=https://tujjar.shop
VITE_ASSET_URL=https://tujjar.shop`


🚀 Deployment Steps
Step 1: Prepare New Hostinger Account
1. Point domain to Hostinger
 * Update DNS records for tujjar.shop to point to new Hostinger
 * Wait for DNS propagation (up to 24 hours)
2. Create database in new Hostinger
 * Go to Hostinger control panel
 * Create MySQL database
 * Note down: database name, username, password
 
Step 2: Build Application:
`cd starter-kit

# Install dependencies
npm install
composer install

# Build for production
npm run build`

Step 3: Create Hostinger File Structure:

`public_html/                    # Root domain files
├── build/                     # Copy from starter-kit/public/build/
│   ├── manifest.json
│   └── assets/
├── images/                    # Copy from starter-kit/public/images/
├── index.php                  # Standard Laravel index.php
├── favicon.ico               # Copy from starter-kit/public/
├── loader.css                # Copy from starter-kit/public/
└── .htaccess                 # Laravel .htaccess

tujjar_backend/               # Laravel backend (private)
├── app/                      # Copy entire starter-kit/ here
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env                      # Copy from .env.production
├── artisan
├── composer.json
└── package.json`

Step 4: Create Standard Laravel Files:
4.1 Create Standard index.php
`<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Check if application is under maintenance
if (file_exists(__DIR__.'/../tujjar_backend/storage/framework/maintenance.php')) {
    require __DIR__.'/../tujjar_backend/storage/framework/maintenance.php';
}

// Register the auto loader
require __DIR__.'/../tujjar_backend/vendor/autoload.php';

// Run the application
$app = require_once __DIR__.'/../tujjar_backend/bootstrap/app.php';

// Set public path to current directory
$app->usePublicPath(__DIR__);

$kernel = $app->make(Kernel::class);

$response = tap($kernel->handle(
    $request = Request::capture()
))->send();

$kernel->terminate($request, $response);`

4.2 Create Standard .htaccess
`<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>`

Step 5: Upload Files to New Hostinger
`# 1. Upload Laravel backend
tar -czf tujjar_backend.tar.gz \
  --exclude='node_modules' \
  --exclude='public/build' \
  --exclude='public/hot' \
  --exclude='public/storage' \
  --exclude='.env' \
  --exclude='vendor' \
  starter-kit/

# Upload and extract to tujjar_backend/

# 2. Upload public files
scp -r starter-kit/public/build/ user@host:public_html/
scp starter-kit/public/favicon.ico user@host:public_html/
scp starter-kit/public/loader.css user@host:public_html/
scp starter-kit/public/images/ user@host:public_html/

# 3. Upload Laravel files
scp index.php user@host:public_html/
scp .htaccess user@host:public_html/`

Step 6: Permissions to Storage and Cache
`chmod -R 775 tujjar_backend/storage
chmod -R 775 tujjar_backend/bootstrap/cache`

Step 7: Configure Backend
`# SSH into new Hostinger
cd tujjar_backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Setup environment
cp .env.production .env
# Edit .env with your new database credentials

# Generate key and setup
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# Run migrations
php artisan migrate --force
php artisan db:seed --force`



Access FTP:
IP FTP (nom d'hôte)	ftp://82.25.113.138 
Nom d'utilisateur FTP	u736495871.abdowahna
Mot de passe FTP	ABDOwahna135795!
Port FTP	21
Dossier où télécharger les fichiers	root (for can access to tujjar_backend and public_html)