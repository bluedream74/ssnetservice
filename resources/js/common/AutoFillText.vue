<template>
    <div>
        <vue-tags-input
            v-model="keyword"
            :tags="keywords"
            :autocomplete-items="allKeywords"
            @tags-changed="newTags => keywords = newTags"
            :placeholder="placeholder"
            :add-only-from-autocomplete="onlyAutoComplete ? true : false"
            />
        <input type="hidden" :name="`${name}[id][]`" v-for="(keyword, index) in keywords" :key="`id_${index}`" :value="keyword.id ? keyword.id : -1" />
        <input type="hidden" :name="`${name}[name][]`" v-for="(keyword, index) in keywords" :key="`name_${index}`" :value="keyword.text" />
    </div>
</template>
 
<script>
import VueTagsInput from '@johmun/vue-tags-input';

export default {
    props: ['paramKeywords', 'oldValues', 'name', 'placeholder', 'onlyAutoComplete'],
    components: {
        VueTagsInput,
    },
    data() {
        return {
            keyword: '',
            keywords: [],
            allKeywords: []
        }
    },
    mounted() {
        let keywords = JSON.parse(this.paramKeywords);
        this.allKeywords = keywords.map(item => {
            return {
                text: item.name,
                id: item.id
            }
        })

        if (this.oldValues) {
            keywords = JSON.parse(this.oldValues);
            this.keywords = keywords.map(item => {
                return {
                    text: item.name,
                    id: item.id
                }
            })
        }
    }
}
</script>
<style scoped lang="scss">
.vue-tags-input {
    max-width: 100%;
}
</style>
