<template>
    <div class="product-list-page">
        <ul class="product-list" v-if="dataList && dataList.length !== 0">
            <li v-for="(item,key) in dataList" :key="key">
                <div class="product-list-item">
                    <div class="product-img">
                        <a :href="item.LinkUrl">
                            <img :src="item.FirstProductImage" alt="">
                        </a>
                    </div>
                    <div class="product-info">
                        <a :href="item.LinkUrl">
                            <p class="brand">{{item.brand}}</p>
                            <P class="title">{{item.Title}}</P>
                        </a>
                        <div class="sale-price">
                            {{item.LowPrice}}
                        </div>
     
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
<script>
export default {
    name:'productListPage',
    props: ['dataList'],
    components: {
    },
    data () {
        return {
            currCurrencyCode:''
        }
    },
    created () {
       
    },
    methods: {
    }
}
</script>
<style lang="scss" scoped>
    .product-list{
        display: flex;
        flex-wrap: wrap;
        padding-top: 5px;
        li{
            padding:5px;
            width: 50%;
            box-sizing: border-box;
            .product-list-item{
                .product-img{
                    background: $white;
                    border-radius: 6px;
                    -webkit-border-radius: 6px;
                    -moz-border-radius: 6px;
                    -ms-border-radius: 6px;
                    -o-border-radius: 6px;
                    img{
                        width: 100%;
                        -webkit-border-radius: 6px;
                        -moz-border-radius: 6px;
                        -ms-border-radius: 6px;
                        -o-border-radius: 6px;
                    }
                }
                .product-info{
                    font-size: $f16;
                    margin-top: 10px;
                    .brand{
                        color:$black-6;
                    }
                    .title{
                        margin-top: 5px;
                        color:$black-7;
                        white-space: nowrap;
                        text-overflow: ellipsis;
                        overflow: hidden;
                    }
                    .sale-price{
                        margin-top: 5px;
                        font-weight: bold;
                    }
                }
            }
            
        }
    }
</style>

