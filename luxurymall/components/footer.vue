<template>
    <div class="footer-box">
        <ul class="footer-list">
            <li v-for="(item,key) in list" :key="key" :class="item.isSelected ? 'curr':''">
                <nuxt-link :to="item.link">
                    <div class="img-box" :class="item.name"></div>
                    <span class="text-span">{{item.text}}</span>
                </nuxt-link>
            </li>
        </ul>
    </div>
</template>
<style lang="scss" scoped>
    .footer-box{
        position:fixed;
        bottom: 0;
        left:0;
        padding: 10px;
        background: $white;
        width: 100%;
        -webkit-z-index:999;
        -moz-z-index:999;
        -ms-z-index:999;
        -o-z-index:999;
        z-index:999;
        .footer-list{
            display: flex;
            justify-content:space-between;
            li{
                .img-box{
                    width: 25px;
                    height:20px;
                    background: url("../assets/image/footer-category.png");
                    margin: 0 auto;
                    margin-bottom:5px;
                    &.home{
                        background-position: 0 0;
                    }
                    &.category{
                        background-position: 0 -29px;
                    }
                    &.cart{
                        background-position: 0 -59px;
                    }
                    &.my{
                        background-position: 3px -90px;
                    }
                }
                .text-span{
                    color: $black-6;
                }
                &.curr{
                    .img-box{
                        &.home{
                            background-position: 0 -124px;
                        }
                        &.category{
                            background-position: 0 -158px;
                        }
                        &.cart{
                        
                             background-position: 0 -188px;
                        }
                        &.my{
                             background-position: 3px -221px;
                        }
                    }
                     .text-span{
                        color: $black;
                    }
                }
                
            }
           
        }
    }
</style>
<script>
export default {
    props:['dataCurr'],
    data(){
        return{
            list:[
                {name:'home',text:'首页',link:'/',isSelected:false},
                {name:'category',text:'分类',link:'/category/firstCategory?id=0',isSelected:false},
                {name:'cart',text:'购物车',link:'/cart',isSelected:false},
                {name:'my',text:'我的',link:'/',isSelected:false},
            ]
        }
    },
    mounted(){
        this.getCurrCategory();
    },
    methods:{
        getCurrCategory (){
            this.list.filter((item,key) => {
                item.isSelected = false;
                if(this.dataCurr === item.name){
                    item.isSelected = true;
                }
                return this.list;
            });
        },
    }
}
</script>