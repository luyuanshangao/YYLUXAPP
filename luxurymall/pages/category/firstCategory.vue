<template>
  <div class="main">
    <Top data-title="商品分类"></Top>
    <ul class="first-tabs">
      <li v-for="(item,key) in tabsList" :key="key" :class="item.isCurr ? 'curr':''" @click="toggleCategory(item)">
        {{item.text}}
      </li>
    </ul>
    <ul class="first-category-list">
      <li v-for="(item,key) in list" :key="key">
        <nuxt-link :to="'/category/secondCategory?id='+item.categoryId+'&'+'name='+item.categoryName">
          <span>{{item.categoryName}}</span>
          <span><img :src="item.src" alt=""></span>
        </nuxt-link>
      </li>
    </ul>
    <Footer data-curr="category"></Footer>
  </div>
</template>

<script>
import Top from '~/components/Top.vue'
import Footer from '~/components/footer.vue'

export default {
  components: {
    Top,
    Footer
  },
  head:{
    link: [
      // { rel: 'stylesheet', href:'/css/index.scss' }
    ]
  },
  data(){
    return{
      tabsList:[
        {type:'0',text:'女士',isCurr:true},
        {type:'1',text:'男士',isCurr:false},
      ],
      list:[
        {categoryId:'1', categoryName:'就近单品',level:'2',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'2', categoryName:'热销单品',level:'2',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'3', categoryName:'服装',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'4', categoryName:'鞋帽',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'箱包',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"}
        
      ]
    }
  },
  mounted() {
   
  },
  methods:{
      toggleCategory(items){
        this.tabsList.map((item,key) =>{
          item.isCurr = false;
        })
        items.isCurr = true;
      }
  }
}
</script>
<style lang="scss" src="../../assets/css/page/category/category.scss" scoped></style>