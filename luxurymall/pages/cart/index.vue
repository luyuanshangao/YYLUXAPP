<template>
  <div class="main">
    <Top data-title="购物车"></Top>
    <div v-if="cartInfoShow">
        <section class="empty-cart-wrap" v-if="Object.keys(cartInfo).length === 0">
            <div class="empty-cart-img">
                <img src="~assets/image/empty-cart.png" alt=""/>
            </div>
            <div class="mt20 gray f14">
                购物袋还是空的，去挑选几件中意的商品吧
            </div>
        </section>
        <section v-else class="cart-group-box">
            <div class="cart-group-wrap" v-for="(item,key) in cartInfo" :key="key">
                <div class="store-box">{{item.StoreName}}</div>
                <ul class="cart-product-list">
                    <li v-for="(pItem,pKey) in item.ProductInfo" :key="pKey">
                        <div class="product-checkbox">
                            <input type="checkbox"/>
                        </div>
                        <div class="img-box">
                            <img :src="pItem.ProductImg" alt=""/>
                        </div>
                        <div class="product-info">
                            <a :href="pItem.LinkUrl">{{pItem.ProductTitle}}</a>
                            <p class="gray mt5">{{pItem.attr}}</p>
                            <p class="sale-price"><span>￥</span>{{pItem.price}}</p>
                        </div>
                        <div class="product-number">
                           <span> X 1</span>
                            <cart-plus-reduce class="cart-plus-reduce" :data-inputvalue="pItem.Qty" :data-obj="plusReduceDisabled" :data-addmaxval="pItem.maxval"></cart-plus-reduce>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
    </div>
    <Footer data-curr="cart"></Footer>
  </div>
</template>

<script>
import Top from '~/components/Top.vue'
import Footer from '~/components/footer.vue'
import cartPlusReduce from '~/components/cartPlusReduce.vue'

export default {
  // async asyncData ({$axios}) {
  //  const { data } = await $axios.get(`http://appapi.yunyou.com/app/index/getHeadrIndex`) //https://api.myjson.com/bins/g1bd8
  //   return {bannerData: data.data.banner}
  // },
 
  components: {
    Top,
    Footer,
    cartPlusReduce
  },
  head:{
    link: [
      // { rel: 'stylesheet', href:'/css/index.scss' }
    ]
  },
  data(){
    return{
        cartInfoShow:true, //控制页面一进来只展示空购物车或者有数据购物车，防止出现闪烁
        cartInfo:[
            {
                StoreID:"333",
                StoreName: "AAA",
                ProductInfo:[{
                    ProductTitle:"Champion 冠军潮牌 连Champion 冠军潮牌 连Champion 冠军潮牌 连",
                    ProductImg: "//img.dxcdn.com//newprdimgs/20191209/64251575888676_210x210.jpg",
                    Qty: 1,
                    LinkUrl: "//m.dx.com/p/2718107",
                    attr:'黑色：S',
                    price:'13500.00',
                    Stock:'5'
                },
                {
                    ProductTitle:"Champion 冠军潮牌 连Champion 冠军潮牌 连Champion 冠军潮牌 连",
                    ProductImg: "//img.dxcdn.com//newprdimgs/20191209/64251575888676_210x210.jpg",
                    Qty: 2,
                    LinkUrl: "//m.dx.com/p/2718107",
                    attr:'黑色：S',
                    price:'13500.00',
                    Stock:'4'
                }]
            },
            {
                StoreID:"444",
                StoreName: "BBB",
                ProductInfo:[{
                    ProductTitle:"Champion 冠军潮牌 连Champion 冠军潮牌 连Champion 冠军潮牌 连",
                    ProductImg: "//img.dxcdn.com//newprdimgs/20191209/64251575888676_210x210.jpg",
                    Qty: 1,
                    LinkUrl: "//m.dx.com/p/2718107",
                    attr:'黑色：S',
                    price:'13500.00',
                    Stock:'5'
                },
                {
                    ProductTitle:"Champion 冠军潮牌 连Champion 冠军潮牌 连Champion 冠军潮牌 连",
                    ProductImg: "//img.dxcdn.com//newprdimgs/20191209/64251575888676_210x210.jpg",
                    Qty: 2,
                    LinkUrl: "//m.dx.com/p/2718107",
                    attr:'黑色：S',
                    price:'13500.00',
                    Stock:'10'
                }]
            }
        ], //产品属性
    }
  },
  mounted() {
  },
  methods:{
    
  }
}
</script>
<style lang="scss" src="../../assets/css/page/cart/index.scss" scoped></style>