<template>
    <div class="space-y-8">
        <section>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Upload Product CSV</h1>
            <p class="mt-2 max-w-2xl text-slate-600">
                Upload a Shopify-compatible CSV file. Products are validated, queued, and imported asynchronously via the Shopify GraphQL API.
            </p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form @submit.prevent="submitUpload" class="space-y-6">
                <div
                    class="flex min-h-48 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 transition"
                    :class="dragActive ? 'border-indigo-500 bg-indigo-50' : 'border-slate-300 bg-slate-50 hover:border-indigo-400'"
                    @dragenter.prevent="dragActive = true"
                    @dragover.prevent="dragActive = true"
                    @dragleave.prevent="dragActive = false"
                    @drop.prevent="handleDrop"
                    @click="$refs.fileInput.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,text/csv"
                        class="hidden"
                        @change="handleFileSelect"
                    />

                    <div class="text-center">
                        <p class="text-lg font-medium text-slate-800">
                            {{ selectedFile ? selectedFile.name : 'Drag & drop your CSV here' }}
                        </p>
                        <p class="mt-2 text-sm text-slate-500">CSV only, max 10MB</p>
                    </div>
                </div>

                <p v-if="clientError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ clientError }}
                </p>

                <p v-if="successMessage" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ successMessage }}
                </p>

                <p v-if="serverError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ serverError }}
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <button
                        type="submit"
                        :disabled="!selectedFile || uploading"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ uploading ? 'Uploading...' : 'Upload & Start Import' }}
                    </button>

                    <a
                        href="/dashboard"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    >
                        View dashboard
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Required CSV columns</h2>
            <p class="mt-2 text-sm text-slate-600">
                Handle, Title, Variant SKU, Variant Price — plus optional Shopify fields like Body HTML, Vendor, Tags, and images.
            </p>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const MAX_SIZE = 10 * 1024 * 1024;
const selectedFile = ref(null);
const dragActive = ref(false);
const uploading = ref(false);
const clientError = ref('');
const serverError = ref('');
const successMessage = ref('');
const fileInput = ref(null);

function validateFile(file) {
    if (!file) {
        return 'Please select a CSV file.';
    }

    const extension = file.name.split('.').pop()?.toLowerCase();
    if (extension !== 'csv') {
        return 'Only CSV files are allowed.';
    }

    if (file.size > MAX_SIZE) {
        return 'File size must not exceed 10MB.';
    }

    return '';
}

function setFile(file) {
    clientError.value = '';
    serverError.value = '';
    successMessage.value = '';

    const error = validateFile(file);
    if (error) {
        clientError.value = error;
        selectedFile.value = null;
        return;
    }

    selectedFile.value = file;
}

function handleFileSelect(event) {
    const file = event.target.files?.[0];
    setFile(file ?? null);
}

function handleDrop(event) {
    dragActive.value = false;
    const file = event.dataTransfer.files?.[0];
    setFile(file ?? null);
}

async function submitUpload() {
    const error = validateFile(selectedFile.value);
    if (error) {
        clientError.value = error;
        return;
    }

    uploading.value = true;
    serverError.value = '';
    successMessage.value = '';

    const formData = new FormData();
    formData.append('csv_file', selectedFile.value);

    try {
        const response = await window.axios.post('/api/uploads', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        successMessage.value = response.data.message;
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    } catch (err) {
        if (err.response?.status === 422) {
            const errors = err.response.data.errors;
            serverError.value = Object.values(errors).flat().join(' ');
        } else {
            serverError.value = err.response?.data?.message ?? 'Upload failed. Please try again.';
        }
    } finally {
        uploading.value = false;
    }
}
</script>
