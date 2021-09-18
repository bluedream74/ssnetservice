<template>
    <div class="text-center profile-image-picker" style="overflow: hidden;">
        <file-selector
            accept-extensions=".jpg,.jpeg,.png"
            :multiple="false"
            :max-file-size="15 * 1024 * 1024"
            @validated="handleFilesValidated"
            @changed="changeImage"
            :input-name="name"
            >
            <slot name="top">
                <div class="tutor-thumb-image" v-if="user_image !== '/img/no-image.jpg'">
                    <img :src="user_image" class="img-responsive" v-bind:class="{'obj-contain': isContain === 'true'}" />
                </div>
                <p class="mt-3 mb-3" v-else>
                    <img src="/img/icon_upload.svg" alt="" />
                </p>
            </slot>
            <slot name="default">
                <p class="fs-sm text-center mb-2" style="color: #f00;">※画像サイズ15MBまで</p>
                <button type="button" class="btn btn-sm btn-default">画像を追加する</button>
            </slot>
            <slot name="bottom">
                <p class="mt-3 mb-3">または、画像をドロップしてアップロード</p>
            </slot>
        </file-selector>
    </div>
</template>
 
<script>
    export default {
        props: ['oldImage', 'name', 'isContain'],
        data() {
            return {
                profileImage: undefined,
            }
        },
        created() {
            this.profileImage = this.oldImage
        },
        methods: {
            handleFilesValidated(result, files) {
                if (result === "FILE_SIZE_ERROR") {
                    toastr.fire({
                        type: 'warning',
                        icon: 'warning',
                        text: "15mbまで選択してください。"
                    })
                }
                if (result === "EXTENSION_ERROR") {
                    toastr.fire({
                        type: 'warning',
                        icon: 'warning',
                        text: ".jpg,.jpeg,.pngファイルを選択してください。"
                    })
                }
            },
            changeImage(files) {
                if (!files.length) return;
                this.createImage(files[0]);
            },
            createImage(file) {
                let reader = new FileReader();
                reader.onload = (e) => {
                    this.profileImage = e.target.result;
                    this.$forceUpdate();
                };
                reader.readAsDataURL(file);
            },
        },
        computed: {
            user_image() {
                if (this.profileImage !== undefined) return this.profileImage;

                return "/img/no-image.jpg";
            },
        }
    }
</script>