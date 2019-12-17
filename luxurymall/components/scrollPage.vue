<template>
    <div>
        <product-list :data-list="productList"></product-list>
    </div>
</template>
<script>
import productList from '~/components/productList.vue';
import {getProductList} from '~/api/components'
export default {
    components: {
        productList
    },
    props: ['dataParam','dataUrl','dataAjax'],
    data () {
        return {
            productList:[],
            currpage:1,
            categoryCountPage:''
        }
    },
    mounted () {
        window.addEventListener('scroll', this.scrollPageLoad, false);
        this.getProduct();
    },
    watch:{
        dataAjax(val){
            this.getProduct();
            this.productList = [];
        }
    },
    methods:{
        getProduct () {
            let newParam;
            let param = {
                page:this.currpage,
            }
            if(this.dataParam){
                newParam = {...param,...this.dataParam}
                if(this.dataParam.page){
                    this.currpage = this.dataParam.page++;
                }
            }else{
                newParam = {...param}
            }
            
            this.$indicator.open();
            getProductList(this.dataUrl,newParam)
            .then(data => {
                let _data = data.data
                let _list = _data.data;
                if(_list && _list.length !== 0){
                    _list.map((item,index) => {
                        this.productList.push(item);
                    });
                }
                this.categoryCountPage = _data.last_page;
                this.$indicator.close();
            })
            .catch(error => {
                this.$indicator.close();
            })
        },
        productPageLoad() {
            this.currpage++; 
            this.getProduct();

        },
        scrollPageLoad () {
            let vm = this;
            let scrollTop = document.documentElement.scrollTop||document.body.scrollTop; //变量scrollTop是滚动条滚动时，距离顶部的距离
            let windowHeight = document.documentElement.clientHeight || document.body.clientHeight; //变量windowHeight是可视区的高度
            let scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight; //变量scrollHeight是滚动条的总高度
            if(scrollTop+windowHeight == scrollHeight){ //滚动条到底部的条件
                if(vm.currpage < vm.categoryCountPage && vm.categoryCountPage !== 1){
                    vm.productPageLoad();
                }
            }   
            
        },
    }
}
</script>