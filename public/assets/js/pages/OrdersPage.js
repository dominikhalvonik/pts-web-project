import { api } from '../core/api.js';
import { money, dateTime, statusText } from '../core/format.js';
const { ref, onMounted } = Vue;
export default {
  setup() {
    const orders = ref([]), error = ref(''), loading = ref(true), page = ref(1), total = ref(0);
    async function load(target = page.value) {
      loading.value = true; error.value = '';
      try { const d = await api('/orders', { query: { page: target } }); orders.value = d.orders; page.value = d.page; total.value = d.total; }
      catch (e) { error.value = e.message; } finally { loading.value = false; }
    }
    onMounted(() => load());
    return { orders, error, loading, page, total, load, money, dateTime, statusText };
  },
  template: `
    <div class="page-heading"><p class="eyebrow">HISTÓRIA NÁKUPOV</p><h1>Vaše objednávky</h1><p>Prehľad objednávok prihláseného používateľa.</p></div>
    <p v-if="loading" class="loading" role="status" data-testid="orders-loading">Načítavam objednávky…</p>
    <div v-else-if="error" class="alert error" role="alert" data-testid="orders-error">{{ error }} <button class="text-button" @click="load()">Skúsiť znova</button></div>
    <section v-else-if="!orders.length" class="panel empty-state" data-testid="orders-empty"><h2>Zatiaľ tu nie je žiadna objednávka.</h2><p>Vaša prvá objednávka sa zobrazí po úspešnom odoslaní.</p><a href="#/catalog" class="button primary">Prejsť do katalógu</a></section>
    <section v-else class="panel" data-testid="orders-ready"><div class="table-scroll"><table><thead><tr><th>Objednávka</th><th>Vytvorená</th><th>Stav</th><th>Suma</th><th><span class="sr-only">Detail</span></th></tr></thead><tbody>
      <tr v-for="order in orders" :key="order.id" :data-testid="'order-row-' + order.id"><td><a :href="'#/orders/' + order.id" class="order-number">{{ order.order_number }}</a></td><td>{{ dateTime(order.created_at) }}</td><td><span class="badge" :class="order.status">{{ statusText(order.status) }}</span></td><td class="nowrap"><strong>{{ money(order.total_cents) }}</strong></td><td><a :href="'#/orders/' + order.id" class="text-link">Detail →</a></td></tr>
    </tbody></table></div><div class="pagination"><button class="button secondary small" @click="load(page - 1)" :disabled="page <= 1" data-testid="orders-prev">Predchádzajúca</button><span>Strana {{ page }} · {{ total }} objednávok</span><button class="button secondary small" @click="load(page + 1)" :disabled="page * 20 >= total" data-testid="orders-next">Nasledujúca</button></div></section>`
};
