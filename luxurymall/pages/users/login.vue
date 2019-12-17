<template>
    <div class="main">
        <login-top :data-obj="loginTopData"></login-top>
        <div class="login-register-box">
            <div class="form-box" v-for="(item,key) in registUserInfo" :key="key">
                <input :type="item.type" :placeholder="item.placeholder" v-model="item.value" @keyup="validateForm(item,key)"/>
                <div class="error-tips" v-show="item.error">{{item.errorTip}}</div>
            </div>
            <div class="mt10 btn block-btn btn-black" v-if="isAvailabBtn" @click="loginSubmit">{{"登陆"}}</div>
            <div class="mt10 btn block-btn btn-gray" v-else>{{"登陆"}}</div>
            <div class="login-type-box">
                <nuxt-link class="yellow" to='/users/quickLogin'>{{"短信快捷登陆"}}</nuxt-link>
                <nuxt-link to='/users/forgetPassword/send'>{{"忘记密码"}}</nuxt-link>
            </div>
        </div>
        <ul class="login-type-list">
            <li><i class="iconfont icon-qq mr5 tmiddle"></i>{{"QQ登陆"}}</li>
            <li><i class="iconfont icon-weixin mr5 tmiddle"></i>{{"微信登陆"}}</li>
        </ul>
    </div>
</template>
<style src="../../assets/css/page/users/login-register.scss" lang="scss" scoped></style>
<script>
import loginTop from '~/components/loginTop.vue'
import GLOBAL from '~/assets/js/global.js'
import {customerLogin} from '~/api/users'
import cookie from '~/assets/js/cookie'
export default {
    components:{
        loginTop
    },
    data (){
        return {
            isAvailabBtn:false, //注册按钮是否可用
            loginTopData:{title:'登陆',link:'/users/register',text:'注册'},
            registUserInfo:{
                phone: {name: "phone",value:'',type:'text',errorTip:"",placeholder:'手机号', error: false },
                password:{name: "password",value:'',type:'password', placeholder:'密码',errorTip:"", error: false },
            }
        }
    },
    methods:{
        /**
         * 表单验证
         */
        validateForm (item,key){
            if(item.name === 'phone'){
                if(!GLOBAL.validation.isPhone(item.value) || GLOBAL.validation.isEmpty(this.registUserInfo.password.value)){
                    this.isAvailabBtn = false;
                    return false;
                }
            }else if(item.name === 'password'){
                if(GLOBAL.validation.isEmpty(item.value) || !GLOBAL.validation.isPhone(this.registUserInfo.phone.value)){
                    this.isAvailabBtn = false;
                    return false
                }
            }
            this.isAvailabBtn = true;
        },
        /**
         * 登陆
         */
        loginSubmit() {
            let registUserInfo = this.registUserInfo;
            let postData = {
                Telephone:registUserInfo.phone.value,
                Password:registUserInfo.password.value
            }
            customerLogin(postData)
            .then(data => {
                let _data = data.data;
                if(_data.code == 200){
                    cookie.set('token',_data.data.token,168);//168是按照小时算 7天是168个小时
                    this.$router.push('/');
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