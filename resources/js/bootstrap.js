import axios from 'axios';
import { overridePatch } from './method-override';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.interceptors.request.use(overridePatch);
