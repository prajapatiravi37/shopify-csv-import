<template>
    <div class="space-y-8">
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Import Dashboard</h1>
                <p class="mt-2 text-slate-600">Track uploads, product statuses, and import logs in real time.</p>
            </div>
            <button
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="refreshAll"
            >
                Refresh
            </button>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ card.label }}</p>
                <p class="mt-2 text-3xl font-bold" :class="card.color">{{ card.value }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold">Uploads</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">File</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Progress</th>
                            <th class="px-6 py-3 font-medium">Success</th>
                            <th class="px-6 py-3 font-medium">Failed</th>
                            <th class="px-6 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="upload in uploads" :key="upload.id" class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ upload.original_filename }}</td>
                            <td class="px-6 py-4">
                                <span :class="statusClass(upload.status)">{{ upload.status }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-28 rounded-full bg-slate-200">
                                        <div
                                            class="h-2 rounded-full bg-indigo-500"
                                            :style="{ width: progress(upload) + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-slate-500">{{ progress(upload) }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-emerald-600">{{ upload.successful_rows }}</td>
                            <td class="px-6 py-4 text-red-600">{{ upload.failed_rows }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ formatDate(upload.created_at) }}</td>
                        </tr>
                        <tr v-if="!uploads.length">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">No uploads yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold">Products</h2>
                <select v-model="productStatus" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="fetchProducts">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="successful">Successful</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Title</th>
                            <th class="px-6 py-3 font-medium">Handle</th>
                            <th class="px-6 py-3 font-medium">SKU</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Shopify ID</th>
                            <th class="px-6 py-3 font-medium">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="product in products" :key="product.id">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ product.title }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ product.handle }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ product.variant_sku }}</td>
                            <td class="px-6 py-4">
                                <span :class="statusClass(product.status)">
                                    {{ product.status }}
                                    <span v-if="product.was_updated" class="ml-1 text-xs text-amber-600">(updated)</span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ product.shopify_product_id ?? '—' }}</td>
                            <td class="px-6 py-4 text-red-600">{{ product.error_message ?? '—' }}</td>
                        </tr>
                        <tr v-if="!products.length">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">No product imports found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold">Import Logs</h2>
                <select v-model="logLevel" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="fetchLogs">
                    <option value="">All levels</option>
                    <option value="info">Info</option>
                    <option value="error">Error</option>
                </select>
            </div>
            <div class="divide-y divide-slate-100">
                <div v-for="log in logs" :key="log.id" class="px-6 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span :class="logLevelClass(log.level)">{{ log.level }}</span>
                        <span class="text-sm font-medium text-slate-800">{{ log.event }}</span>
                        <span class="text-xs text-slate-400">{{ formatDate(log.created_at) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ log.message }}</p>
                </div>
                <div v-if="!logs.length" class="px-6 py-8 text-center text-slate-500">No logs yet.</div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const uploads = ref([]);
const products = ref([]);
const logs = ref([]);
const productStatus = ref('');
const logLevel = ref('');
let pollTimer = null;

const summaryCards = computed(() => {
    const pending = products.value.filter((item) => item.status === 'pending').length;
    const processing = products.value.filter((item) => item.status === 'processing').length;
    const successful = products.value.filter((item) => item.status === 'successful').length;
    const failed = products.value.filter((item) => item.status === 'failed').length;

    return [
        { label: 'Pending', value: pending, color: 'text-slate-700' },
        { label: 'Processing', value: processing, color: 'text-amber-600' },
        { label: 'Successful', value: successful, color: 'text-emerald-600' },
        { label: 'Failed', value: failed, color: 'text-red-600' },
    ];
});

function statusClass(status) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize';
    const map = {
        pending: 'bg-slate-100 text-slate-700',
        processing: 'bg-amber-100 text-amber-800',
        successful: 'bg-emerald-100 text-emerald-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
    };

    return `${base} ${map[status] ?? 'bg-slate-100 text-slate-700'}`;
}

function logLevelClass(level) {
    const base = 'rounded-full px-2 py-0.5 text-xs font-semibold uppercase';
    return level === 'error'
        ? `${base} bg-red-100 text-red-700`
        : `${base} bg-sky-100 text-sky-700`;
}

function progress(upload) {
    if (!upload.total_rows) {
        return 0;
    }

    return Math.round((upload.processed_rows / upload.total_rows) * 100);
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

async function fetchUploads() {
    const { data } = await window.axios.get('/api/uploads');
    uploads.value = data.data ?? [];
}

async function fetchProducts() {
    const params = {};
    if (productStatus.value) {
        params.status = productStatus.value;
    }

    const { data } = await window.axios.get('/api/products', { params });
    products.value = data.data ?? [];
}

async function fetchLogs() {
    const params = {};
    if (logLevel.value) {
        params.level = logLevel.value;
    }

    const { data } = await window.axios.get('/api/logs', { params });
    logs.value = data.data ?? [];
}

async function refreshAll() {
    await Promise.all([fetchUploads(), fetchProducts(), fetchLogs()]);
}

onMounted(async () => {
    await refreshAll();
    pollTimer = setInterval(refreshAll, 5000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>
