require('./bootstrap');
import Swal from 'sweetalert2/dist/sweetalert2.js';
import 'sweetalert2/src/sweetalert2.scss';
import Loading from "vue-loading-overlay";
import "vue-loading-overlay/dist/vue-loading.css";

window.Vue = require('vue');

window.toastr = Swal.mixin({
    showConfirmButton: false,
    timer: 3000,
    position: 'center',
    toast: false
});

const Flash = () => import('./common/Flash.vue');
const CommonLoading = () => import('./common/Loading.vue');

Vue.component('loading', Loading);
Vue.component('flash', Flash);
Vue.component('common-loading', CommonLoading);

const app = new Vue({
    el: '#page-wrapper'
});