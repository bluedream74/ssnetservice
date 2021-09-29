<template>
    <div :class="`alert alert-${type}`" role="alert">
        {{ message }}
        
        <button type="button" class="close" data-dismiss="alert" aria-label="閉じる">
            <span>&times;</span>
        </button>
    </div>
</template>
 
<script>
    export default {
        props: ['message', 'type'],
        data() {
            return {
                
            }
        }
    }
</script>