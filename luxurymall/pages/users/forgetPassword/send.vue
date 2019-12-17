<template>
    <div class="main">
        <login-top :data-obj="loginTopData"></login-top>
        <div class="login-register-box">
            <div class="form-box" v-for="(item,key) in registUserInfo" :key="key">
                <input :type="item.type" :placeholder="item.placeholder" v-model="item.value" @keyup="validateForm(item,key)"/>
                <div class="error-tips" v-show="item.error">{{item.errorTip}}</div>
            </div>
            <div class="mt10 gray">{{"当前只支持手机号找回密码"}}</div>
            <div class="mt10 btn block-btn btn-black" v-if="isAvailabBtn" @click="sendSubmit">{{"发送"}}</div>
            <div class="mt10 btn block-btn btn-gray" v-else>{{"发送"}}</div>
            
        </div>
    </div>
</template>
<style src="../../../assets/css/page/users/login-register.scss" lang="scss" scoped></style>
<script>
import loginTop from '~/components/loginTop.vue'
import GLOBAL from '~/assets/js/global.js'
import {sendTelephone} from '~/api/users'
import cookie from '~/assets/js/cookie'
export default {
    components:{
        loginTop
    },
    data (){
        return {
            isAvailabBtn:false, //注册按钮是否可用
            loginTopData:{title:'找回密码',link:'/users/login',text:''},
            registUserInfo:{
                phone: {name: "phone",value:'',type:'text',errorTip:"",placeholder:'手机号', error: false }
            }
        }
    },
    methods:{
        /**
         * 注册表单验证
         */
        validateForm (item,key){
           
            if(!GLOBAL.validation.isPhone(item.value)){
                this.isAvailabBtn = false;
                return false;
            }
            this.isAvailabBtn = true;
        },
        /**
         *校验手机账号是否存在
         */
        sendSubmit() {
            let registUserInfo = this.registUserInfo;
            let postData = {
                Telephone:registUserInfo.phone.value
            }
            sendTelephone(postData)
            .then(data => {
                let _data = data.data;

                if(_data.code == 200){
                    if(_data.data == 1){
                        this.$router.push('/users/forgetPassword/setPassword?telephone="'+postData.Telephone+'"');
                    }else{
                        this.$toast({
                            message:_data.msg
                        });
                    }
                }else{
                    this.$toast({
                        message:_data.msg
                    });
                }
            });
        }
    }
}
</script>