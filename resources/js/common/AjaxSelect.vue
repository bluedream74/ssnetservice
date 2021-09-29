<template>
    <div class="ajax-select">
        <v-select :options="options" :filter="filterOptions" v-model="selectedValue">
            <template slot="no-options">
                {{ label }}を入力してください
            </template>
            <template slot="option" slot-scope="option">
                <div class="d-center">
                    <img :src='option.avatar_url'/> 
                    {{ option.name }}
                </div>
            </template>
            <template slot="selected-option" slot-scope="option">
                <div class="selected d-center">
                    <img :src='option.avatar_url'/> 
                    {{ option.name }}
                </div>
            </template>
        </v-select>
        <input type="hidden" :name="paramName" :value="selectedValue ? selectedValue.id : undefined" />
    </div>
</template>
 
<script>
    import vSelect from 'vue-select';
    import 'vue-select/dist/vue-select.css';

    export default {
        props: ['paramOptions', 'paramValue', 'paramName', 'label'],
        components: {
            vSelect
        },
        data() {
            return {
                options: [],
                selectedValue: undefined
            }
        },
        methods: {
            fetchOptions() {

            },
            filterOptions(values, search) {
                return this.options.filter(item => item.name.includes(search))
            }
        },
        mounted() {
            let data = JSON.parse(this.paramOptions);
            this.options = data.map(item => {
                item.label = item.id;
                return item;
            })

            if (this.paramValue) {
                this.selectedValue = this.options.filter(item => item.id === parseInt(this.paramValue))[0];
            }

            this.$nextTick(function() {
                $('.vs--searchable .vs__dropdown-toggle').attr('style', 'min-height: 34px;');
            })
        },
        computed: {
            
        },
        filters: {
        }
    }
</script>
<style scoped lang="scss">
.ajax-select {
    width: 100% !important;
    height: auto !important;
    
    img {
        width: 32px;
        height: 32px;
        object-fit: cover;
        border-radius: 100%;
        margin-right: 1rem;
    }

    .d-center {
        display: flex;
        align-items: center;
    }

    .selected img {
        width: 22px;
        height: 22px;
        margin-right: 0.5rem;
    }

    .v-select .dropdown li {
        border-bottom: 1px solid rgba(112, 128, 144, 0.1);
    }

    .v-select .dropdown li:last-child {
        border-bottom: none;
    }

    .v-select .dropdown li a {
        padding: 10px 20px;
        width: 100%;
        font-size: 1.25em;
        color: #3c3c3c;
    }

    .v-select .dropdown-menu .active > a {
        color: #fff;
    }
}
</style>