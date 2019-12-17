<template>
    <div class="login-top">
        <div class="top-box">
            <span @click="backFun"><b class="iconfont icon-flow"></b></span>
            <span class="title">{{dataObj.title}}</span>
            <nuxt-link class="f12" :to="dataObj.link">{{dataObj.text}}</nuxt-link>
        </div>
    </div>
</template>
<style lang="scss" scoped>
    .top-box{
        padding: 10px;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items:center;
        font-size: $f14;
        font-weight: bold;
        border-bottom:1px solid $border-color;
    }
</style>
<script>
export default {
    props:['dataObj'],
    methods:{
        /**
         * 回退键
         */
        backFun () {
            if(this.dataObj.goback){
                this.$router.go(this.dataObj.goback);
            }else{
                this.$router.go(-1);
            }
            
        }
    }
}
</script>