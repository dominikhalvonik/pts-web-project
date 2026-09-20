/** Same-origin JSON API. No secrets or database credentials belong in this file. */
const endpoint = new URL('api/index.php', new URL('./', window.location.href));
let csrfToken = '';
export function setCsrf(token) { csrfToken = typeof token === 'string' ? token : ''; }
export class ApiError extends Error {
  constructor(message, code = 'NETWORK_ERROR', status = 0, details = {}) {
    super(message); this.name = 'ApiError'; this.code = code; this.status = status; this.details = details;
  }
}
export async function api(route, { method = 'GET', body, query = {}, headers = {}, signal } = {}) {
  const url = new URL(endpoint);
  url.searchParams.set('route', route);
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value));
  }
  const controller = new AbortController();
  const abort = () => controller.abort();
  if (signal?.aborted) controller.abort();
  signal?.addEventListener('abort', abort, { once: true });
  const timer = setTimeout(abort, 15000);
  try {
    const h = { Accept: 'application/json', ...headers };
    if (body !== undefined) h['Content-Type'] = 'application/json';
    if (!['GET', 'HEAD'].includes(method) && csrfToken) h['X-CSRF-Token'] = csrfToken;
    const response = await fetch(url, { method, headers: h, credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body), signal: controller.signal });
    let result;
    try { result = await response.json(); }
    catch { throw new ApiError('Server nevrátil JSON. Skontrolujte adresu API a nastavenie PHP.', 'INVALID_RESPONSE', response.status); }
    if (!response.ok || result.error) {
      const e = result.error || {};
      throw new ApiError(e.message || 'Požiadavka zlyhala.', e.code || 'HTTP_ERROR', response.status, e.details || {});
    }
    return result.data;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (error.name === 'AbortError') throw new ApiError('Požiadavka trvala príliš dlho alebo bola zrušená. Pri objednávke najskôr skontrolujte históriu.', 'TIMEOUT');
    throw new ApiError('Spojenie so serverom zlyhalo. Skontrolujte pripojenie; odoslaná objednávka už mohla byť uložená.');
  } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort); }
}
export function requestId() {
  if (crypto.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
  const h = [...b].map(v => v.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;
}
