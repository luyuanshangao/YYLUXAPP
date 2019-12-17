<template>
    <plus-reduce :data-inputvalue="dataInputvalue" :data-productlist="dataProductlist" :data-obj="dataObj" @cb="changeProductNum" class="fl" :data-addmaxval="dataAddmaxval"></plus-reduce>
</template>
<script>
import plusReduce from '@/components/plusReduce'
export default {
    name:'cartPlusReduce',
    props: ['dataInputvalue','dataProductlist','dataObj','dataAddmaxval'],
    components: {
        plusReduce
    },
    methods: {
        changeProductNum (obj) {
            var vm = this;
            if(this.dataProductlist){
                vm.$set(obj,'Productlist',this.dataProductlist);
                this.$emit('cb',obj);
            }
            
        }
    }
}
</script>
