import { login } from '../core/store.js';
const { ref } = Vue;
export default {
  setup() {
    const email = ref(''), password = ref(''), error = ref(''), busy = ref(false);
    async function submit() {
      busy.value = true; error.value = '';
      try { await login(email.value, password.value); } catch (e) { error.value = e.message; }
      finally { busy.value = false; }
    }
    return { email, password, error, busy, submit };
  },
  template: `
    <section class="login-layout">
      <div class="login-intro"><p class="eyebrow">VÁŠ TESTOVACÍ PRIESTOR</p><h1>Každá objednávka<br>má svoj príbeh.</h1><p>Prihláste sa do výučbovej aplikácie a overte celý proces: od výberu produktu až po jeho uloženie v databáze.</p><div class="login-note"><strong>Len simulované údaje.</strong><p>Nevkladajte skutočné osobné ani platobné údaje. Aplikácia neposiela e-maily a nespracúva platby.</p></div></div>
      <form class="panel login-form" @submit.prevent="submit" data-testid="login-form">
        <p class="eyebrow">TESTLAB SHOP</p><h2>Vitajte späť</h2><p class="muted">Použite účet pridelený vyučujúcim.</p>
        <div v-if="error" class="alert error" role="alert" data-testid="login-error">{{ error }}</div>
        <label>E-mail<input v-model="email" type="email" required maxlength="190" autocomplete="username" placeholder="student@example.test" data-testid="login-email"></label>
        <label>Heslo<input v-model="password" type="password" required minlength="8" maxlength="72" autocomplete="current-password" data-testid="login-password"></label>
        <button class="button primary full" type="submit" :disabled="busy" data-testid="login-submit">{{ busy ? 'Prihlasujem…' : 'Prihlásiť sa' }}</button>
        <p class="small muted">Účty a testovacie dáta spravuje vyučujúci. Verejná registrácia nie je súčasťou tejto aplikácie.</p>
      </form>
    </section>`
};
