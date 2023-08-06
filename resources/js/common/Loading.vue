<template>
    <div>
        <span id="showLoading" @click="isLoading = true"></span>
        <span id="hideLoading" @click="isLoading = false"></span>
        <loading :active.sync="isLoading"
            :can-cancel="false"
            color="#1B1B1B"
            :width="36"
            :height="36"
            :is-full-page="true"></loading>
    </div>
</template>
 
<script>
    export default {
        props: [],
        data() {
            return {
                isLoading: false
            }
        }
    }
</script>