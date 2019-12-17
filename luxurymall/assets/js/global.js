const GLOBAL = {
    validation:{
        isPhone:function(string){
            return /^\d{11}$/.test(string)
        },
        isEmpty:function(string){
            if(!string){
                return true;
            }else{
                return false;
            }
        },
        isRegistPassword:function(string){
            return /^(?![^a-zA-Z]+$)(?!\D+$).{6,20}$/.test(string);
        }
    }
}
export default GLOBAL