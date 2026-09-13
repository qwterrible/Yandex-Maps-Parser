<template>
    <div class="page">
        <h1>Вход</h1>
        <form @submit.prevent="submit">
            <label>
                Email
                <input v-model="email" type="email" required>
            </label>
            <label>
                Пароль
                <input v-model="password" type="password" required>
            </label>
            <button :disabled="loading">
                {{ loading ? 'Входим...' : 'Войти' }}
            </button>
            <p v-if="error" class="error">{{ error }}</p>
        </form>
        <p class="hint">admin@example.com / admin</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const email = ref('admin@example.com');
const password = ref('admin');
const loading = ref(false);
const error = ref(null);

async function submit() {
    loading.value = true;
    error.value = null;
    try {
        await window.axios.get('http://localhost/laraveltz/yandex-maps-parser/public/sanctum/csrf-cookie', { baseURL: '' });
        await window.axios.post('/login', {
            email: email.value,
            password: password.value,
        });
        router.push('/settings');
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.email?.[0]
            || 'Ошибка входа';
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.page { max-width: 360px; margin: 80px auto; font-family: sans-serif; }
label { display: block; margin: 12px 0 6px; }
input { width: 100%; padding: 8px; box-sizing: border-box; }
button { margin-top: 12px; padding: 10px 16px; }
.error { color: #c00; margin-top: 12px; }
.hint { color: #888; font-size: 12px; margin-top: 20px; }
</style>