import { state, persistCart, removeFromCart, setQuantity, cartSignature, refreshQuote } from '../core/store.js';
import { money } from '../core/format.js';
import PriceSummary from '../components/PriceSummary.js';
const { ref, watch } = Vue;
export default {
  components: { PriceSummary },
  setup() {
    const couponInput = ref(state.coupon);
    watch(cartSignature, refreshQuote, { immediate: true });
    function applyCoupon() { state.coupon = couponInput.value.trim().toUpperCase(); persistCart(); }
    function quantityChanged(item, event) {
      setQuantity(item.sku, Number(event.target.value)); event.target.value = item.quantity;
    }
    const line = item => state.quote?.items.find(p => p.sku === item.sku);
    return { state, money, couponInput, applyCoupon, removeFromCart, quantityChanged, persistCart, refreshQuote, line };
  },
  template: `
    <div class="page-heading"><p class="eyebrow">01 / VÝBER PRODUKTOV</p><h1>Váš košík</h1><p>Upravte množstvo, zvoľte dopravu a skontrolujte cenovú ponuku.</p></div>
    <section v-if="!state.cart.length" class="panel empty-state" data-testid="cart-empty"><h2>Košík je zatiaľ prázdny.</h2><p>Začnite výberom produktu z katalógu.</p><a class="button primary" href="#/catalog">Prejsť do katalógu</a></section>
    <div v-else class="two-column">
      <section class="panel"><h2>Vybrané produkty</h2>
        <article v-for="item in state.cart" :key="item.sku" class="cart-item" :data-testid="'cart-item-' + item.sku">
          <div class="item-symbol" aria-hidden="true">{{ item.sku.split('-')[0] }}</div>
          <div class="item-title"><h3>{{ item.name }}</h3><span class="muted small">{{ item.sku }} · {{ money(line(item)?.unit_price_cents ?? item.price_cents) }} / ks</span></div>
          <label class="quantity-label"><span class="sr-only">Množstvo: {{ item.name }}</span><input :value="item.quantity" @change="quantityChanged(item, $event)" type="number" min="1" max="20" step="1" :data-testid="'quantity-' + item.sku"></label>
          <strong class="line-price">{{ money(line(item)?.line_total_cents ?? item.price_cents * item.quantity) }}</strong>
          <button class="remove-button" @click="removeFromCart(item.sku)" :aria-label="'Odstrániť ' + item.name" :data-testid="'remove-' + item.sku">×</button>
        </article>
        <a class="text-link" href="#/catalog">← Pokračovať vo výbere</a>
      </section>
      <aside class="panel summary-panel"><h2>Súhrn objednávky</h2>
        <label>Spôsob doručenia<select v-model="state.delivery" @change="persistCart" data-testid="delivery-method"><option value="courier">Kuriér</option><option value="pickup">Osobný odber</option></select></label>
        <form class="coupon-row" @submit.prevent="applyCoupon"><label>Zľavový kód<input v-model="couponInput" maxlength="32" placeholder="STUDENT10" data-testid="coupon-input"></label><button class="button secondary" type="submit" data-testid="coupon-apply">Použiť</button></form>
        <p class="small muted">Kuriér: 4,90 €. Od 100 € po zľave zdarma. Osobný odber je vždy zdarma.</p>
        <p v-if="state.quoteLoading" role="status" data-testid="quote-loading">Prepočítavam cenu…</p>
        <div v-if="state.quoteError" class="alert error" role="alert" data-testid="quote-error">{{ state.quoteError }} <button class="text-button" @click="refreshQuote">Obnoviť</button></div>
        <PriceSummary v-if="state.quote" :quote="state.quote" />
        <a v-if="state.quote && !state.quoteLoading && state.user" class="button primary full" href="#/checkout" data-testid="proceed-checkout">Pokračovať k objednávke →</a>
        <div v-else-if="!state.user" class="alert info">Na dokončenie sa <a href="#/login" data-testid="cart-login">prihláste</a>. Po prihlásení sa načíta košík príslušného účtu.</div>
        <p class="fine-print">Konečnú sumu aj dostupnosť produktov overuje server. Táto ponuka nerezervuje sklad.</p>
      </aside>
    </div>`
};
