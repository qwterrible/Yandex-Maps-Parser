<template>
    <div class="page">
        <header>
            <h1>Отзывы</h1>
            <router-link to="/settings">← Настройки</router-link>
        </header>

        <div v-if="summary" class="summary">
            <p><b>{{ summary.name }}</b></p>
            <p>
                Рейтинг: <b>{{ summary.rating ?? '—' }}</b> ·
                Оценок: <b>{{ summary.ratings_count ?? 0 }}</b> ·
                Отзывов: <b>{{ summary.reviews_count ?? 0 }}</b>
            </p>
        </div>

        <p v-if="loading">Загрузка...</p>
        <p v-if="error" class="error">{{ error }}</p>

        <ul v-if="reviews.length" class="reviews">
            <li v-for="r in reviews" :key="r.id">
                <div class="head">
                    <b>{{ r.author || 'Аноним' }}</b>
                    <span class="rating">{{ r.rating ?? '—' }}/5</span>
                </div>
                <div class="date">{{ formatDate(r.published_at) }}</div>
                <div class="text">{{ r.text }}</div>
            </li>
        </ul>
        <p v-else-if="!loading">Отзывов пока нет.</p>

        <div v-if="meta && meta.last_page > 1" class="pagination">
            <button :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">
                ← Назад
            </button>
            <span>Страница {{ meta.current_page }} из {{ meta.last_page }}</span>
            <button :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">
                Вперёд →
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const reviews = ref([]);
const meta = ref(null);
const summary = ref(null);
const loading = ref(false);
const error = ref(null);

async function load(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await window.axios.get('/reviews', { params: { page } });
        reviews.value = data.data;
        meta.value = data.meta;
        summary.value = data.summary;
    } catch (e) {
        error.value = e.response?.data?.message || 'Ошибка загрузки отзывов';
    } finally {
        loading.value = false;
    }
}

function go(page) {
    if (page < 1 || (meta.value && page > meta.value.last_page)) return;
    load(page);
}

function formatDate(s) {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
}

onMounted(() => load(1));
</script>

<style scoped>
.page { max-width: 720px; margin: 40px auto; font-family: sans-serif; }
header { display: flex; justify-content: space-between; align-items: center; }
.summary { padding: 12px; background: #f6f6f6; border-radius: 6px; margin: 16px 0; }
.reviews { list-style: none; padding: 0; }
.reviews li { border-bottom: 1px solid #eee; padding: 16px 0; }
.head { display: flex; justify-content: space-between; }
.rating { color: #e8a000; font-weight: bold; }
.date { color: #888; font-size: 12px; margin: 4px 0; }
.text { white-space: pre-wrap; }
.pagination { display: flex; justify-content: center; gap: 12px; align-items: center; margin: 24px 0; }
.error { color: #c00; }
</style>