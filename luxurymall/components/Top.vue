<template>
    <div class='top-box'>
        <div class="title">{{dataTitle}}</div>
    </div>
</template>
<style lang="scss" scoped>
    .top-box{
        height:44px;
        line-height: 44px;
        font-size: 1.6rem;
        font-weight: bold;
        letter-spacing:0.2rem;
        background: $white;
        .title{
            padding: 0 10px;
            @include tcenter();
        }
    }
</style>
<script>
export default {
    props:["dataTitle"],

}
</script>