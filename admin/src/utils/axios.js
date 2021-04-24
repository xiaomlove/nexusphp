import axios from 'axios'
import { ElMessage } from 'element-plus'
import {localGet} from "./index";

axios.defaults.baseURL = 'http://nexus-php8.tinyhd.net/api'
axios.defaults.withCredentials = true
axios.defaults.headers['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers['Content-Type'] = 'application/json'
axios.defaults.headers['Accept'] = 'application/json'
axios.defaults.headers['Authorization'] = 'Bearer ' + localGet('token')

// 请求拦截器，内部根据返回值，重新组装，统一管理。
axios.interceptors.response.use(res => {
    if (typeof res.data !== 'object') {
        ElMessage.error('Server Error 1')
        return Promise.reject(res)
    }
    if (res.data.ret && res.data.ret != 0) {
        ElMessage.error(res.data.msg)
        return Promise.reject(res.data)
    }
    return res.data
}, error => {
    ElMessage.error(error.response.data.msg || 'Server Error 2')
    return Promise.reject(error)
})

export default axios
