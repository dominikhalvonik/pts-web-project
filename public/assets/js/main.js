import { state, cartCount, initialize, logout } from './core/store.js';
import { route, navigate } from './core/router.js';
import CatalogPage from './pages/CatalogPage.js';
import LoginPage from './pages/LoginPage.js';
import CartPage from './pages/CartPage.js';
import CheckoutPage from './pages/CheckoutPage.js';
import OrdersPage from './pages/OrdersPage.js';
import OrderPage from './pages/OrderPage.js';
const { createApp, computed, ref, watch, onMounted } = Vue;
const NotFound = { template: '<section class="panel empty-state"><h1>Stránka neexistuje.</h1><a class="button primary" href="#/catalog">Späť do katalógu</a></section>' };
createApp({
  setup() {
    const logoutBusy = ref(false);
    const page = computed(() => {
      if (route.value === '/catalog' || route.value === '/') return CatalogPage;
      if (route.value === '/login') return LoginPage;
      if (route.value === '/cart') return CartPage;
      if (route.value === '/checkout') return CheckoutPage;
      if (route.value === '/orders') return OrdersPage;
      if (/^\/orders\/[a-f0-9-]{36}$/.test(route.value)) return OrderPage;
      return NotFound;
    });
    const pageProps = computed(() => route.value.startsWith('/orders/') ? { id: route.value.split('/')[2] } : {});
    watch([route, () => state.ready, () => state.user?.id], () => {
      if (!state.ready) return;
      if ((route.value === '/checkout' || route.value.startsWith('/orders')) && !state.user) navigate('/login');
      document.title = ({ '/catalog': 'Katalóg', '/cart': 'Košík', '/checkout': 'Objednávka', '/orders': 'Objednávky', '/login': 'Prihlásenie' }[route.value] || 'Detail') + ' · TestLab Shop';
    });
    async function doLogout() {
      logoutBusy.value = true;
      try { await logout(); } catch (e) { state.notice = e.message; } finally { logoutBusy.value = false; }
    }
    onMounted(initialize);
    return { state, route, cartCount, page, pageProps, initialize, logoutBusy, doLogout };
  },
  template: `
    <a class="skip-link" href="#main-content" @click.prevent="$refs.main.focus()">Preskočiť na obsah</a>
    <div class="environment-bar"><div class="container"><span><span class="status-dot"></span> VÝUČBOVÉ PROSTREDIE</span><span>Pokročilé testovanie softvéru <b> / </b> Aplikovaná informatika</span></div></div>
    <header class="site-header"><div class="container header-content"><a class="brand" href="#/catalog" aria-label="TestLab Shop – katalóg"><span class="brand-mark">TL</span><span>TestLab<span class="brand-light"> Shop</span></span></a>
      <nav aria-label="Hlavná navigácia"><a href="#/catalog" :class="{ active: route === '/catalog' || route === '/' }" data-testid="nav-catalog">Katalóg</a><a href="#/cart" :class="{ active: route === '/cart' }" data-testid="nav-cart">Košík <span class="cart-badge" data-testid="cart-count">{{ cartCount }}</span></a><a href="#/orders" :class="{ active: route.startsWith('/orders') }" data-testid="nav-orders">Objednávky</a></nav>
      <div class="account-area" v-if="state.ready"><template v-if="state.user"><div class="account-name"><span class="avatar">{{ state.user.name.charAt(0) }}</span><span data-testid="current-user">{{ state.user.name }}</span></div><button class="text-button" @click="doLogout" :disabled="logoutBusy" data-testid="logout-button">Odhlásiť</button></template><a v-else href="#/login" class="button secondary small" data-testid="nav-login">Prihlásiť sa</a></div>
    </div></header>
    <main id="main-content" ref="main" tabindex="-1" class="container main-content">
      <div v-if="state.notice" class="notice" role="status" aria-live="polite" data-testid="notification"><span>{{ state.notice }}</span><button @click="state.notice = ''" aria-label="Zavrieť oznam">×</button></div>
      <section v-if="state.startupError" class="panel startup-error"><h1>Aplikácia sa nepripojila k serveru.</h1><p class="alert error" role="alert" data-testid="startup-error">{{ state.startupError }}</p><p>Overte PHP, rozšírenie pdo_mysql, údaje pripojenia a import databázy podľa README.</p><button class="button primary" @click="initialize" data-testid="startup-retry">Skúsiť znova</button></section>
      <p v-else-if="!state.ready" class="loading" role="status">Pripájam sa k aplikácii…</p>
      <component v-else :is="page" :key="route + ':' + (state.user?.id || 'guest')" v-bind="pageProps" />
    </main>
    <footer class="site-footer"><div class="container"><div><strong>TestLab Shop</strong><p>Jedna aplikácia. Viac spôsobov, ako overiť jej správnosť.</p></div><div><span>PHP · Vue.js · MariaDB</span><small>Simulované objednávky. Žiadne reálne platby.</small></div></div></footer>`
}).mount('#app');
