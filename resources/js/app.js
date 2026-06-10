import './bootstrap';
import { createApp } from 'vue';
import UploadApp from './components/UploadApp.vue';
import DashboardApp from './components/DashboardApp.vue';

const uploadEl = document.getElementById('upload-app');
const dashboardEl = document.getElementById('dashboard-app');

if (uploadEl) {
    createApp(UploadApp).mount('#upload-app');
}

if (dashboardEl) {
    createApp(DashboardApp).mount('#dashboard-app');
}
