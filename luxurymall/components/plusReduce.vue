<template>
    <div class="plus-reduce" :class="dataObj ? 'disabled':''">
        <span class="reduce-btn iconfont icon-jian1" @click="reduceValue"></span>
        <input type="number" class="quantity-text" :disabled="(dataObj && dataObj.dataInputdisabled) ? 'disabled':false" v-model="quantityValue" @change="changInputVal"/>
        <span class="plus-btn iconfont icon-jia3" @click="addValue"></span>
    </div>
</template>
<script>
export default {
    name: "my-plus-reduce",
    props: ['dataInputvalue','dataProductlist','dataObj','dataAddmaxval'],
    data () { 
        return {
            quantityValue:1
        }
    },
    created () {
        this.$nextTick(() => {
            if(this.dataInputvalue){
                this.quantityValue = this.dataInputvalue;
            }
           
        });
    },
    mounted () {
        this.getInputValue('load');
    },
    methods: {
        addValue (nocallBack) {
            if(this.dataObj && this.dataObj.dataAdddisabled === 'disabled'){
                return false;
            }
            //判断是否触发回调函数，如果不触发就传参数nocallBack
            if(!nocallBack || nocallBack !== 'nocallBack'){
                this.$emit('cb',{'value':this.quantityValue+1,'btnType':'add'});
            }
            //如果超出最大值，就不往下加了，详情页面会用到
            if(this.dataAddmaxval && this.quantityValue >= parseInt(this.dataAddmaxval)){
                return false;
            }
            this.quantityValue++;
            //this.getInputValue('add');
            
        },
        reduceValue (nocallBack) {
            if(this.dataObj && this.dataObj.dataReducedisabled === 'disabled'){
                return false;
            }
           if(this.quantityValue > 1){
               this.quantityValue--;
           } 
            //判断是否触发回调函数，如果不触发就传参数nocallBack
            if(!nocallBack || nocallBack !== 'nocallBack'){
                this.getInputValue('reduct');
            }
        },
        /* param 1 判断是否触发回调函数，如果不触发就传参数nocallBack
        *  param 2 改变的数量
        */
        changInputVal (nocallBack,qty) {
            
            if(parseInt(this.quantityValue) === 0 || !this.quantityValue){
                this.quantityValue = 1;
            }else if(this.dataAddmaxval && this.quantityValue > parseInt(this.dataAddmaxval)){
                this.quantityValue = this.dataAddmaxval;
            }else if(qty){
                this.quantityValue = qty;
            }
              //判断是否触发回调函数，如果不触发就传参数nocallBack
            if(!nocallBack || nocallBack !== 'nocallBack'){
                this.getInputValue('quantyInput');
            }
        },
        getInputValue (btnType) {
           this.$emit('cb',{'value':this.quantityValue,'btnType':btnType});
        }
    }
}
</script>
<style lang="scss" scoped> 
    .plus-reduce{
        display: flex;
        justify-content:space-between;
    }
    .reduce-btn,.plus-btn{
        display: inline-block;
        vertical-align:middle;
        width:30px;
        height:25px;
        line-height:25px;
        text-align: center;   
        border: 1px solid $gray-4;
    }
    .quantity-text{
        width:40px;
        text-align: center;
        padding:0 5px;
        height:30px;
        line-height:30px;
        box-sizing: border-box;
        border:1px solid #f2f2f2;
    }
</style>