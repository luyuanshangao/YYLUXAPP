import request from '@/plugins/axios'
//banner
export const getHomeHeader = (data) => {
    return getCartInfo({
        url: '/app/index/getHeadrIndex',
        method:'post',
        data:data
    })
}