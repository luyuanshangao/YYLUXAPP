<template>
  <div class="main">
    <Top data-title="首页"></Top>
    <div class="search-wrap">
        <i class="iconfont icon-sousuo search-icon"></i>
        <input class="search-text" type="text" placeholder="第二件五折"/>
        <i class="iconfont icon-xiaoxi new-icon"></i>
    </div>
    <div v-if="bannerData.length !== 0">
        <client-only>
            <swiper :options="bannerSwiperOption" ref="bannerSwiper" class="banner-list">
                <swiper-slide class="list-item" v-for="(item, key) in bannerData" :key="key">
                <a :href="item.src" :title="item.src" target="_blank"><img :alt="item.src" :src="item.src" width="100%"/></a>
                </swiper-slide>
                <div class="banner-swiper-pagination" slot="pagination"></div>
            </swiper>
        </client-only>
    </div>
    <ul class="promise-list">
        <li><span><img src="~assets/image/promise-list1.png"></span><span>满3500元免运费</span></li>
        <li><span><img src="~assets/image/promise-list2.png"></span><span>全球正品</span></li>
        <li><span><img src="~assets/image/promise-list3.png"></span><span>14天无理由免费退货</span></li>
    </ul>
    <ul class="category-list">
      <li v-for="(item,key) in category" :key="key">
        <div class="img-box">
            <nuxt-link :to="'/category/firstCategory?id='+item.class_id"><img :src="item.src" :alt="item.src"/></nuxt-link>
        </div>
        <nuxt-link :to="'/p?id='+item.class_id">{{item.title}}</nuxt-link>
      </li>
    </ul>

    <div class="brand-box">
        <h3>品牌榜单</h3>
        <p class="gray">奢华与温柔不期而遇</p>
        <ul class="brand-list">
            <li v-for="(item,key) in brand" :key="key">
                <div class="brand-bg"><img :src="item.src" alt=""/></div>
                 <div class="brand-item">
                    <ul class="brand-product-list">
                        <li v-for="(citem,ckey) in item.product" :key="ckey">
                            <nuxt-link to="/"><img :src="citem" alt=""></nuxt-link>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
    <div class="home-product-box">
        <div class="product-area">
            <div class="title">
                <span>热销单品</span>
                <nuxt-link class="gray" to=""> more +</nuxt-link>
            </div>
            <client-only>
                <swiper :options="swiperOption" ref="mySwiper" class="hot-list">
                    <swiper-slide class="li" v-for="(item,key) in hotProductList" :key="key">
                        <a :href="'/s/'+item.productId">
                            <div class="img-box">
                                <img :src="item.img" alt=""/>
                            </div>
                            <p class="title">{{item.title}}</p>
                        </a>
                    </swiper-slide>
                </swiper>
            </client-only>
        </div>
        <div class="product-area mt20">
            <div class="title">
                <span>明星热款</span>
                <nuxt-link class="gray" to=""> more +</nuxt-link>
            </div>
            <client-only>
                <swiper :options="swiperOption" ref="mySwiper" class="hot-list">
                    <swiper-slide class="li" v-for="(item,key) in startProductList" :key="key">
                        <a :href="'/s/'+item.productId">
                            <div class="img-box">
                                <img :src="item.img" alt=""/>
                            </div>
                            <p class="title">{{item.title}}</p>
                        </a>
                    </swiper-slide>
                </swiper>
            </client-only>
        </div>
        <div class="ad-banner">
            <nuxt-link to="/">
                <img :src="advertBanner" alt=""/>
            </nuxt-link>
        </div>
    </div>
    <scroll-page :data-param="productListParam" :data-url="productAjaxUrl"></scroll-page>
    <Footer data-curr="home"></Footer>
  </div>
</template>

<script>
import Top from '~/components/Top.vue'
import scrollPage from '~/components/scrollPage.vue'
import Footer from '~/components/footer.vue'
import {getHomeHeader,getHomeCenter} from '~/api/index'

export default {
  // async asyncData ({$axios}) {
  //  const { data } = await $axios.get(`http://appapi.yunyou.com/app/index/getHeadrIndex`) //https://api.myjson.com/bins/g1bd8
  //   return {bannerData: data.data.banner}
  // },
 
  components: {
    Top,
    scrollPage,
    Footer
  },
  head:{
    link: [
      // { rel: 'stylesheet', href:'/css/index.scss' }
    ]
  },
  data(){
    return{
        productAjaxUrl:'/app/Product/getCategorySpuList',
      //banner滚动参数
      bannerSwiperOption: {
        slidesPerView:1,
        autoplay: true,
        pagination: {
          el: '.banner-swiper-pagination'
        },
      },
      bannerData:[],//首页banner
      category:[],//分类
      brand:[], //品牌
      hotProductList:[], //热销单品
      startProductList:[],//明星单品
      swiperOption: { //热词搜索滚动参数
            slidesPerView:3
        },
        advertBanner:'', //广告banner
        productListParam:{firstCategory:'1'}
    }
  },
  mounted() {
    getHomeHeader()
    .then(data => {
      let _data = data.data;
      this.bannerData = _data.data.banner;
      this.category = _data.data.class_img;
      this.brand = _data.data.brand;
    })
    getHomeCenter()
    .then(data => {
      let _data = data.data;
      this.hotProductList = _data.data.top_sellers;
      this.startProductList = _data.data.star_sellers;
      this.advertBanner = _data.data.center_img;
    })
  },
  methods:{
    
  }
}
</script>
<style lang="scss" src="../assets/css/page/index.scss" scoped></style>