const { ref } = Vue;
function read() {
  const path = location.hash.slice(1) || '/catalog';
  return path.startsWith('/') ? path : '/catalog';
}
export const route = ref(read());
window.addEventListener('hashchange', () => { route.value = read(); window.scrollTo(0, 0); });
export function navigate(path) { location.hash = path; }
