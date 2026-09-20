import { api } from '../core/api.js';
import { money, dateTime, statusText } from '../core/format.js';
import PriceSummary from '../components/PriceSummary.js';
const { ref, onMounted } = Vue;
export default {
  components: { PriceSummary },
  props: { id: { type: String, required: true } },
  setup(props) {
    const order = ref(null), loading = ref(true), error = ref(''), cancelError = ref(''), confirming = ref(false), busy = ref(false);
    async function load() { loading.value = true; error.value = '';
      try { order.value = await api('/orders/' + props.id); } catch (e) { error.value = e.message; } finally { loading.value = false; }
    }
    async function cancel() { busy.value = true; cancelError.value = '';
      try { order.value = await api('/orders/' + props.id + '/cancel', { method: 'POST', body: {} }); confirming.value = false; }
      catch (e) { cancelError.value = e.message; } finally { busy.value = false; }
    }
    onMounted(load); return { order, loading, error, cancelError, confirming, busy, load, cancel, money, dateTime, statusText };
  },
  template: `
    <p v-if="loading" class="loading" role="status" data-testid="order-loading">Načítavam detail objednávky…</p>
    <div v-else-if="error" class="alert error" role="alert" data-testid="order-error">{{ error }} <button class="text-button" @click="load">Skúsiť znova</button></div>
    <template v-else-if="order">
      <div class="page-heading"><p class="eyebrow">03 / DETAIL OBJEDNÁVKY</p><h1>Objednávka {{ order.status === 'cancelled' ? 'bola zrušená.' : 'je vytvorená.' }}</h1><p>Vytvorená {{ dateTime(order.created_at) }} · Časové pásmo Europe/Bratislava</p></div>
      <section class="order-reference panel"><div><span class="eyebrow">ČÍSLO OBJEDNÁVKY</span><strong class="order-number" data-testid="order-number">{{ order.order_number }}</strong><small>UUID: <code data-testid="order-id">{{ order.id }}</code></small></div><span class="badge large" :class="order.status" :data-status="order.status" data-testid="order-status">{{ statusText(order.status) }}</span></section>
      <div class="two-column">
        <section class="panel"><h2>Objednané produkty</h2><div class="table-scroll"><table><thead><tr><th>Produkt</th><th>Kusov</th><th>Cena / ks</th><th>Spolu</th></tr></thead><tbody>
          <tr v-for="item in order.items" :key="item.sku" :data-testid="'order-item-' + item.sku"><td><strong>{{ item.name }}</strong><small class="block muted">{{ item.sku }}</small></td><td :data-testid="'order-quantity-' + item.sku">{{ item.quantity }}</td><td class="nowrap">{{ money(item.unit_price_cents) }}</td><td class="nowrap"><strong>{{ money(item.line_total_cents) }}</strong></td></tr>
        </tbody></table></div><h3 class="section-gap">Kontaktné údaje</h3><div class="contact-details"><p><strong>{{ order.customer_name }}</strong><br>{{ order.customer_email }}</p><p v-if="order.delivery_method === 'courier'">{{ order.address_line }}<br>{{ order.postal_code }} {{ order.city }}</p><p v-else>Osobný odber · výdajné miesto TestLab</p></div><p v-if="order.note" class="order-note" data-testid="order-note-text">{{ order.note }}</p>
        </section>
        <aside class="panel summary-panel"><h2>Finálny súhrn</h2><PriceSummary :quote="order" prefix="order" />
          <p class="fine-print">Údaje načítané z detailu objednávky cez API. Peňažné hodnoty sú v databáze uložené v centoch.</p>
          <button v-if="order.status === 'created' && !confirming" class="button danger full" @click="confirming = true" data-testid="cancel-order">Zrušiť objednávku</button>
          <div v-if="confirming" class="alert warning"><strong>Naozaj chcete objednávku zrušiť?</strong><p>Objednané množstvo sa vráti na sklad.</p><div class="button-row"><button class="button danger small" @click="cancel" :disabled="busy" data-testid="confirm-cancel">{{ busy ? 'Ruším…' : 'Áno, zrušiť' }}</button><button class="button secondary small" @click="confirming = false" :disabled="busy" data-testid="keep-order">Ponechať</button></div></div>
          <div v-if="cancelError" class="alert error" role="alert" data-testid="cancel-error">{{ cancelError }}</div>
          <a class="text-link" href="#/orders">← Všetky objednávky</a>
        </aside>
      </div>
    </template>`
};
