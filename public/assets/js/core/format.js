const moneyFormatter = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' });
export function money(cents) { return moneyFormatter.format(Number(cents) / 100); }
export function dateTime(utc) {
  if (!utc) return '—';
  const date = new Date(utc.replace(' ', 'T') + (utc.endsWith('Z') ? '' : 'Z'));
  if (Number.isNaN(date.getTime())) return utc;
  return new Intl.DateTimeFormat('sk-SK', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Bratislava' }).format(date);
}
export function statusText(value) { return { created: 'Vytvorená', cancelled: 'Zrušená' }[value] || value; }
