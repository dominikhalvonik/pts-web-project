import { api, requestId } from '../core/api.js';
import { state, cartBody, refreshQuote, clearCart, attemptKey } from '../core/store.js';
import { navigate } from '../core/router.js';
import PriceSummary from '../components/PriceSummary.js';
const { reactive, ref, onMounted } = Vue;
export default {
  components: { PriceSummary },
  setup() {
    const form = reactive({ name: state.user?.name || '', email: state.user?.email || '', address: '', city: '', postal_code: '', note: '', terms: false });
    const busy = ref(false), error = ref('');
    onMounted(refreshQuote);
    // Same payload -> same idempotency key, including a retry after reload or a lost response.
    let memoryAttempt = null;
    function getAttempt(body) {
      const signature = JSON.stringify(body);
      let saved = memoryAttempt;
      try { saved = JSON.parse(sessionStorage.getItem(attemptKey()) || 'null') || saved; } catch { /* in-memory fallback */ }
      if (saved?.signature === signature && typeof saved.key === 'string') return saved;
      const fresh = { signature, key: requestId() }; memoryAttempt = fresh;
      try { sessionStorage.setItem(attemptKey(), JSON.stringify(fresh)); } catch { /* in-memory retry still works */ }
      return fresh;
    }
    async function submit() {
      if (busy.value || !state.quote || state.quoteLoading) return;
      busy.value = true; error.value = '';
      const body = { ...cartBody(), customer: { name: form.name, email: form.email, address: form.address, city: form.city, postal_code: form.postal_code },
        note: form.note, accept_terms: form.terms, expected_total_cents: state.quote.total_cents };
      const attempt = getAttempt(body);
      try {
        const order = await api('/orders', { method: 'POST', body, headers: { 'Idempotency-Key': attempt.key } });
        try { sessionStorage.removeItem(attemptKey()); } catch { /* optional browser storage */ }
        clearCart(); state.notice = 'Objednávka bola úspešne vytvorená.'; navigate('/orders/' + order.id);
      } catch (e) {
        error.value = e.message;
        if (e.code === 'PRICE_CHANGED' || e.code === 'OUT_OF_STOCK') await refreshQuote();
        if (e.status === 401) error.value += ' Otvorte prihlásenie a potom skontrolujte objednávky.';
      } finally { busy.value = false; }
    }
    return { state, form, busy, error, submit, refreshQuote };
  },
  template: `
    <div class="page-heading"><p class="eyebrow">02 / POTVRDENIE OBJEDNÁVKY</p><h1>Takmer hotovo.</h1><p>Vyplňte simulované kontaktné údaje a odošlite objednávku.</p></div>
    <section v-if="!state.cart.length" class="panel empty-state"><h2>Najskôr pridajte produkt do košíka.</h2><a href="#/catalog" class="button primary">Prejsť do katalógu</a></section>
    <form v-else class="two-column" @submit.prevent="submit" data-testid="checkout-form">
      <section class="panel"><h2>Kontaktné údaje</h2><p class="small muted">Nepoužívajte skutočné osobné údaje.</p>
        <fieldset :disabled="busy"><div class="form-grid">
          <label>Meno a priezvisko<input v-model="form.name" required maxlength="100" autocomplete="off" data-testid="customer-name"></label>
          <label>E-mail<input v-model="form.email" type="email" required maxlength="190" autocomplete="off" data-testid="customer-email"></label>
          <template v-if="state.delivery === 'courier'">
            <label class="span-two">Ulica a číslo<input v-model="form.address" required maxlength="190" autocomplete="off" placeholder="Testovacia 12" data-testid="customer-address"></label>
            <label>Mesto<input v-model="form.city" required maxlength="100" autocomplete="off" data-testid="customer-city"></label>
            <label>PSČ<input v-model="form.postal_code" required maxlength="6" pattern="[0-9]{3} ?[0-9]{2}" title="Päť číslic, napríklad 010 01" autocomplete="off" data-testid="customer-postal-code"></label>
          </template>
          <p v-else class="alert info span-two">Osobný odber: simulované výdajné miesto TestLab. Adresa nie je potrebná.</p>
          <label class="span-two">Poznámka <span class="muted">(nepovinná)</span><textarea v-model="form.note" maxlength="500" rows="3" data-testid="order-note"></textarea></label>
        </div>
        <label class="check-label terms"><input v-model="form.terms" required type="checkbox" data-testid="accept-terms"><span>Rozumiem, že ide o simulovanú objednávku na výučbové účely.</span></label></fieldset>
        <a href="#/cart" class="text-link">← Späť do košíka</a>
      </section>
      <aside class="panel summary-panel"><h2>Skontrolujte objednávku</h2>
        <p class="small muted">{{ state.delivery === 'courier' ? 'Doručenie kuriérom' : 'Osobný odber' }} · Simulovaná platba</p>
        <p v-if="state.quoteLoading" role="status">Overujem cenovú ponuku…</p>
        <div v-if="state.quoteError" class="alert error" role="alert" data-testid="checkout-quote-error">{{ state.quoteError }} <a href="#/cart">Upraviť košík</a></div>
        <PriceSummary v-if="state.quote" :quote="state.quote" />
        <div v-if="error" class="alert error" role="alert" data-testid="checkout-error">{{ error }}</div>
        <button class="button primary full" type="submit" :disabled="busy || !state.quote || state.quoteLoading" data-testid="place-order">{{ busy ? 'Odosielam objednávku…' : 'Vytvoriť testovaciu objednávku' }}</button>
        <p class="fine-print">Žiadna suma nebude účtovaná. Pri výpadku siete najskôr skontrolujte <a href="#/orders">históriu objednávok</a>. Opakovaný nezmenený pokus používa rovnaký identifikátor.</p>
      </aside>
    </form>`
};
