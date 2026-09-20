import { api, setCsrf } from './api.js';
import { navigate } from './router.js';
const { reactive, computed } = Vue;
export const state = reactive({ ready: false, user: null, startupError: '', cart: [],
  coupon: '', delivery: 'courier', quote: null, quoteError: '', quoteLoading: false, notice: '', revision: 0 });
const prefix = 'testlab-shop:' + new URL('./', location.href).pathname;
let quoteSequence = 0;
const key = () => prefix + ':cart:' + (state.user?.id || 'guest');
export const cartCount = computed(() => state.cart.reduce((sum, item) => sum + item.quantity, 0));
export function cartBody() {
  return { items: state.cart.map(({ sku, quantity }) => ({ sku, quantity })),
    coupon_code: state.coupon, delivery_method: state.delivery };
}
export function cartSignature() { return JSON.stringify(cartBody()) + ":" + state.revision; }
function loadCart() {
  try {
    const saved = JSON.parse(localStorage.getItem(key()) || '{}');
    state.cart = Array.isArray(saved.items) ? saved.items.filter(p =>
      typeof p.sku === 'string' && /^[A-Z0-9-]{1,32}$/.test(p.sku) &&
      Number.isInteger(p.quantity) && p.quantity >= 1 && p.quantity <= 20 &&
      typeof p.name === 'string' && Number.isInteger(p.price_cents) && p.price_cents > 0).slice(0, 30) : [];
    const seen = new Set(); state.cart = state.cart.filter(p => !seen.has(p.sku) && seen.add(p.sku));
    state.coupon = typeof saved.coupon === 'string' ? saved.coupon.slice(0,32) : '';
    state.delivery = ['courier','pickup'].includes(saved.delivery) ? saved.delivery : 'courier';
  } catch { state.cart = []; state.coupon = ''; state.delivery = 'courier'; }
  invalidateQuote();
}
export function persistCart() {
  state.revision++; invalidateQuote();
  try { localStorage.setItem(key(), JSON.stringify({ items: state.cart, coupon: state.coupon, delivery: state.delivery })); }
  catch { state.notice = 'Prehliadač neumožnil uložiť košík. Po obnovení stránky sa môže vymazať.'; }
}
function invalidateQuote() { quoteSequence++; state.quote = null; state.quoteError = ''; state.quoteLoading = false; }
export async function initialize() {
  state.startupError = ''; state.ready = false;
  try { const data = await api('/auth/me'); state.user = data.user; setCsrf(data.csrf_token); loadCart(); state.ready = true; }
  catch (error) { state.startupError = error.message; }
}
export async function login(email, password) {
  const data = await api('/auth/login', { method: 'POST', body: { email, password } });
  state.user = data.user; setCsrf(data.csrf_token); loadCart(); state.notice = 'Prihlásenie bolo úspešné.';
  navigate('/catalog');
}
export async function logout() {
  await api('/auth/logout', { method: 'POST', body: {} });
  state.user = null; loadCart();
  const data = await api('/auth/me'); setCsrf(data.csrf_token);
  state.notice = 'Boli ste odhlásený.'; navigate('/login');
}
export function inCart(sku) { return state.cart.find(p => p.sku === sku)?.quantity || 0; }
export function addToCart(product) {
  const current = state.cart.find(p => p.sku === product.sku);
  if (inCart(product.sku) >= Math.min(product.stock, 20)) { state.notice = 'Dosiahli ste dostupné množstvo alebo limit 20 kusov.'; return; }
  if (current) current.quantity++;
  else state.cart.push({ sku: product.sku, name: product.name, price_cents: product.price_cents, quantity: 1 });
  persistCart(); state.notice = `${product.name}: pridané do košíka.`;
}
export function setQuantity(sku, quantity) {
  const item = state.cart.find(p => p.sku === sku);
  if (!item) return;
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 20) { state.notice = 'Množstvo musí byť celé číslo od 1 do 20.'; return; }
  item.quantity = quantity; persistCart();
}
export function removeFromCart(sku) { state.cart = state.cart.filter(p => p.sku !== sku); persistCart(); }
export function clearCart() { state.cart = []; state.coupon = ''; state.delivery = 'courier'; persistCart(); }
export async function refreshQuote() {
  const sequence = ++quoteSequence;
  if (!state.cart.length) { state.quote = null; state.quoteError = ''; state.quoteLoading = false; return null; }
  state.quoteLoading = true; state.quoteError = ''; state.quote = null;
  const body = cartBody();
  try {
    const quote = await api('/cart/quote', { method: 'POST', body });
    if (sequence !== quoteSequence) return null;
    state.quote = quote; return quote;
  } catch (error) { if (sequence === quoteSequence) state.quoteError = error.message; return null; }
  finally { if (sequence === quoteSequence) state.quoteLoading = false; }
}
export function attemptKey() { return prefix + ':attempt:' + (state.user?.id || 'guest'); }
