<template>
    <div class="page">
        <header>
            <h1>Настройки</h1>
            <button @click="logout">Выйти</button>
        </header>

        <form @submit.prevent="save">
            <label>
                Ссылка на карточку организации в Яндекс.Картах
                <input
                    v-model="url"
                    type="url"
                    placeholder="https://yandex.ru/maps/org/..."
                    required
                >
            </label>
            <button :disabled="loading">
                {{ loading ? 'Сохраняем...' : 'Сохранить и начать парсинг' }}
            </button>
            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="success" class="success">{{ success }}</p>
        </form>

        <div v-if="org" class="status">
            <h2>Текущая организация</h2>
            <p><b>Название:</b> {{ org.name || '—' }}</p>
            <p><b>Статус парсинга:</b> {{ org.parse_status }}</p>
            <p v-if="org.parse_error" class="error">
                <b>Ошибка:</b> {{ org.parse_error }}
            </p>
            <p v-if="org.rating">
                <b>Рейтинг:</b> {{ org.rating }} ({{ org.ratings_count }} оценок,
                {{ org.reviews_count }} отзывов)
            </p>
            <router-link to="/reviews">Перейти к отзывам</router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const url = ref('');
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const org = ref(null);
let timer = null;

async function loadOrg() {
    try {
        const { data } = await window.axios.get('/organization');
        org.value = data.organization;
        if (org.value) url.value = org.value.yandex_url;
    } catch (e) {
        // не критично
    }
}

async function save() {
    loading.value = true;
    error.value = null;
    success.value = null;
    try {
        const { data } = await window.axios.post('/organization', {
            yandex_url: url.value,
        });
        org.value = data.organization;
        success.value = 'Ссылка сохранена, парсинг запущен.';
    } catch (e) {
        error.value = e.response?.data?.message
            || e.response?.data?.errors?.yandex_url?.[0]
            || 'Ошибка сохранения';
    } finally {
        loading.value = false;
    }
}

async function logout() {
    await window.axios.post('/logout');
    router.push('/login');
}

onMounted(() => {
    loadOrg();
    timer = setInterval(async () => {
        if (org.value && ['queued', 'parsing'].includes(org.value.parse_status)) {
            await loadOrg();
        }
    }, 3000);
});

onUnmounted(() => clearInterval(timer));
</script>

<style scoped>
.page { max-width: 640px; margin: 40px auto; font-family: sans-serif; }
header { display: flex; justify-content: space-between; align-items: center; }
label { display: block; margin: 12px 0 6px; }
input { width: 100%; padding: 8px; box-sizing: border-box; }
button { margin-top: 12px; padding: 10px 16px; }
.error { color: #c00; }
.success { color: #080; }
.status { margin-top: 32px; padding-top: 16px; border-top: 1px solid #eee; }
</style>