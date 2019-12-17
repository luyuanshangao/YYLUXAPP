<template>
  <div class="main">
    <Top :data-title="title"></Top>
    <ul class="second-category-list">
        <li v-for="(item,key) in list" :key="key">
            <nuxt-link :to="'/category/list?id='+item.categoryId+'&'+'name='+item.categoryName">
                <div class="left-box">
                    <span><img :src="item.src" alt=""></span>
                    <span>{{item.categoryName}}</span>
                    
                </div>
                <div class="right-box">
                    <b class="iconfont icon-youjiantou1"></b>
                </div>
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
      title:this.$route.query.name ? this.$route.query.name:'',
      list:[
        {categoryId:'1', categoryName:'全部服饰',level:'2',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'2', categoryName:'连衣裙',level:'2',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'3', categoryName:'夹克',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'4', categoryName:'针织衫',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"},
        {categoryId:'5', categoryName:'上衣',level:'1',src:"https://img.dxcdn.com//productimages/sku_600418_1_70x70.jpg"}
        
      ]
    }
  },
  mounted() {
  },
  methods:{
      
  }
}
</script>
<style lang="scss" src="../../assets/css/page/category/category.scss" scoped></style>
