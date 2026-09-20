import { money } from '../core/format.js';
export default {
  props: { quote: { type: Object, required: true }, prefix: { type: String, default: 'quote' } },
  setup() { return { money }; },
  template: `
    <div class="price-summary">
      <div><span>Medzisúčet</span><strong :data-testid="prefix + '-subtotal'" :data-cents="quote.subtotal_cents">{{ money(quote.subtotal_cents) }}</strong></div>
      <div><span>Zľava <small v-if="quote.coupon_code">({{ quote.coupon_code }})</small></span><strong :data-testid="prefix + '-discount'" :data-cents="quote.discount_cents">−{{ money(quote.discount_cents) }}</strong></div>
      <div><span>Doprava</span><strong :data-testid="prefix + '-shipping'" :data-cents="quote.shipping_cents">{{ money(quote.shipping_cents) }}</strong></div>
      <div class="grand-total"><span>Celkom</span><strong :data-testid="prefix + '-total'" :data-cents="quote.total_cents">{{ money(quote.total_cents) }}</strong></div>
    </div>`
};
