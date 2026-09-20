import { api } from '../core/api.js';
import { money } from '../core/format.js';
import { state, addToCart, inCart } from '../core/store.js';
const { ref, onMounted, onBeforeUnmount } = Vue;
export default {
  setup() {
    const products = ref([]), loading = ref(false), error = ref(''), q = ref(''), category = ref(''), inStock = ref(false);
    let sequence = 0, controller;
    async function load() {
      const n = ++sequence; controller?.abort(); controller = new AbortController(); loading.value = true; error.value = '';
      try { const data = await api('/products', { query: { q: q.value, category: category.value, in_stock: inStock.value ? '1' : '0' }, signal: controller.signal });
        if (n === sequence) products.value = data.products;
      } catch (e) { if (n === sequence) error.value = e.message; }
      finally { if (n === sequence) loading.value = false; }
    }
    onMounted(load); onBeforeUnmount(() => { sequence++; controller?.abort(); });
    return { state, products, loading, error, q, category, inStock, load, money, addToCart, inCart };
  },
  template: `
    <section class="hero">
      <div><p class="eyebrow">VYBERTE. OBJEDNAJTE. OVERTE.</p><h1>Technika pre váš<br><span>pracovný deň.</span></h1>
        <p class="hero-copy">Od prvého kliknutia až po uloženú objednávku.<br>Malý obchod, skutočný priestor na testovanie.</p>
        <a class="button primary" href="#/cart" data-testid="hero-cart">Prejsť do košíka <span aria-hidden="true">→</span></a>
      </div>
      <div class="hero-aside"><div class="hero-art" aria-hidden="true"><div class="art-screen"><span>TL</span><i></i></div><div class="art-base"></div><div class="art-keyboard"></div><div class="art-mouse"></div></div>
        <div class="hero-label"><span class="status-dot"></span> Navrhnuté na učenie, nie na skutočné nákupy.</div>
      </div>
    </section>
    <div class="benefits"><div><span class="benefit-symbol">01</span><span><strong>Jednoduchá objednávka</strong><small>Bez registrácie a reálnych platieb</small></span></div><div><span class="benefit-symbol">02</span><span><strong>Doprava od 100 € zdarma</strong><small>Hranica platí po uplatnení zľavy</small></span></div><div><span class="benefit-symbol">03</span><span><strong>Kód STUDENT10</strong><small>10 % zľava na hodnotu produktov</small></span></div></div>
    <section aria-labelledby="catalog-title">
      <div class="section-heading"><div><p class="eyebrow">KATALÓG PRODUKTOV</p><h2 id="catalog-title">Vyberte si z ponuky</h2></div><span class="muted" data-testid="product-count">{{ products.length }} produktov v zobrazení</span></div>
      <form class="filters" @submit.prevent="load">
        <label class="search-field"><span class="sr-only">Vyhľadávanie</span><input v-model="q" type="search" placeholder="Hľadať názov alebo SKU…" maxlength="100" data-testid="search-input"></label>
        <label><span class="sr-only">Kategória</span><select v-model="category" data-testid="category-filter"><option value="">Všetky kategórie</option>Periférie</option><option>Príslušenstvo</option><option>Monitory</option></select></label>
        <label class="check-label"><input v-model="inStock" type="checkbox" data-testid="in-stock-filter"> Len skladom</label>
        <button class="button secondary" type="submit" data-testid="search-submit" :disabled="loading">Filtrovať</button>
      </form>
      <p v-if="loading" class="loading" role="status" data-testid="catalog-loading">Načítavam produkty…</p>
      <div v-else-if="error" class="alert error" role="alert" data-testid="catalog-error">{{ error }} <button @click="load" class="text-button">Skúsiť znova</button></div>
      <p v-else-if="!products.length" class="empty-state" data-testid="catalog-empty">Žiadny produkt nezodpovedá zvoleným filtrom.</p>
      <div v-else class="product-grid" data-testid="catalog-ready">
        <article v-for="product in products" :key="product.sku" class="product-card" :data-testid="'product-card-' + product.sku">
          <div class="product-visual" :class="'tone-' + product.sku.split('-')[0].toLowerCase()"><span class="visual-category">{{ product.category }}</span><span class="product-monogram" aria-hidden="true">{{ product.sku.split('-')[0] }}</span><span class="visual-code">{{ product.sku }}</span></div>
          <div class="product-content"><p class="stock-label" :class="{ unavailable: product.stock === 0 }" :data-testid="'stock-' + product.sku" :data-stock="product.stock"><span class="status-dot"></span>{{ product.stock ? 'Skladom · ' + product.stock + ' ks' : 'Vypredané' }}</p>
            <h3>{{ product.name }}</h3><p class="product-description">{{ product.description }}</p>
            <div class="product-bottom"><strong class="product-price" :data-testid="'price-' + product.sku" :data-cents="product.price_cents">{{ money(product.price_cents) }}</strong>
              <button class="button primary small" @click="addToCart(product)" :disabled="product.stock === 0 || inCart(product.sku) >= Math.min(20, product.stock)" :data-testid="'add-to-cart-' + product.sku">{{ product.stock ? '+ Do košíka' : 'Nedostupné' }}</button>
            </div>
          </div>
        </article>
      </div>
    </section>`
};
