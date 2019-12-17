<template>
  <div class="main list-page">
    <Top :data-title="title"></Top>
    <client-only>
        <swiper :options="swiperOption" ref="mySwiper" class="brand-list">
            <swiper-slide class="li" v-for="(item,key) in brandList" :key="key">
                <span @click="filter('brand',item)">{{item.text}}</span>
            </swiper-slide>
        </swiper>
    </client-only>
    <ul class="fiter-list">
        <li v-for="(item,key) in filterList" :key="key" :class="item.name">
            <div v-if="item.name !== 'filter'" class="item-box" :class="item.isCurr ? 'curr':''" @click="filter('',item)">
                {{item.text}}
                <span class="icon-box" v-if="item.name === 'price'">
                    <i class="iconfont icon-shangsanjiaoxing" :class="item.lowPrice ? 'curr':''"></i>
                    <i class="iconfont icon-xiasanjiaoxing" :class="!item.lowPrice ? 'curr':''"></i>
                </span>
                <span v-else></span>
            </div>
            <div v-else class="item-box" :class="item.isCurr ? 'curr':''">
                <span>{{item.text}}</span>
                <i class="iconfont icon-shaixuan"></i>
            </div>
        </li>
    </ul>
    <scroll-page :data-param="productListParam" :data-url="productAjaxUrl" :data-ajax="resetAjax"></scroll-page>
    <Footer data-curr="category"></Footer>
  </div>
</template>

<script>
import Top from '~/components/Top.vue'
import scrollPage from '~/components/scrollPage.vue'
import Footer from '~/components/footer.vue'

export default {
  components: {
    Top,
    Footer,
    scrollPage
  },
  head:{
    link: [
      // { rel: 'stylesheet', href:'/css/index.scss' }
    ]
  },
  data(){
    return{
      title:this.$route.query.name ? this.$route.query.name:'',
      swiperOption: { //热词搜索滚动参数
            slidesPerView:5
        },
        brandList:[ //品牌
          {text:'Gucci',isCurr:false},
          {text:'Prada',isCurr:false},
          {text:'Leerboor',isCurr:false},
          {text:'Pandora',isCurr:false},
          {text:'Prada',isCurr:false},
        ],
        filterList:[
            {name:'all',text:'综合',isCurr:true},
            {name:'sales',text:'销量',isCurr:false},
            {name:'price',text:'价格',isCurr:false,lowPrice:false},
            // {name:'filter',text:'筛选',isCurr:false} //后期再补，暂时没有那么着急
        ],
        productListParam:{ //分类参数
            firstCategory:this.$route.query.id ? this.$route.query.id:''
        },
        productAjaxUrl:'/app/Product/getCategorySpuList',
        resetAjax:false
    }
  },
  mounted() {
  },
  methods:{
      /**
       * type 代表筛选的是品牌还是销售筛选
       * item 表示当前筛选的品牌和帅选的类别。
       */
      filter(type,itemCurr){
        let vm = this;
            this.productListParam = {
                firstCategory:this.$route.query.id ? this.$route.query.id:'',
                page:1
            }
        if(type === 'brand'){
            this.brandList.map((item,index) => {
                item.isCurr = false;
                if(item.isCurr){
                    vm.$set(vm.productListParam,'brand',item.name);
                }
            })
            
            }else{
                this.filterList.map((item,index) => {
                    item.isCurr = false;
                })
            }
            itemCurr.isCurr = true;
            this.brandList.filter(item => {
                if(item.isCurr){
                    vm.$set(vm.productListParam,'brand',item.text);
                }
            })
            this.filterList.map(item => {
                if(item.isCurr){
                    switch(item.name){
                        case 'all':
                            vm.$set(vm.productListParam,'saleCount',false);
                            vm.$set(vm.productListParam,'price',false);
                        break;
                        case 'sales':
                            vm.$set(vm.productListParam,'saleCount','true');
                            vm.$set(vm.productListParam,'price',false);
                            break;
                        
                        case 'price':
                            vm.$set(item,'lowPrice',!item.lowPrice);
                            vm.$set(vm.productListParam,'saleCount',false);
                            vm.$set(vm.productListParam,'price',true);
                            vm.$set(vm.productListParam,'lowPrice',item.lowPrice);
                            break;
                        default:
                            break;
                    }
                }
                
            })
            this.resetAjax = !this.resetAjax;
      }
  }
}
</script>
<style lang="scss" src="../../assets/css/page/category/category.scss" scoped></style>
