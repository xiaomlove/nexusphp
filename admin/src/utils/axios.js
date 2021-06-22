import axios from 'axios'
import { ElMessage } from 'element-plus'
import {localGet} from "./index"
import router from '../router/index'

console.log(import.meta.env)

axios.defaults.baseURL = import.meta.env.VITE_BASE_URL || '/api'
axios.defaults.withCredentials = true
axios.defaults.headers['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers['Content-Type'] = 'application/json'
axios.defaults.headers['Accept'] = 'application/json'
axios.defaults.headers['Platform'] = 'admin'
// axios.defaults.headers['Authorization'] = 'Bearer ' + localGet('token')

axios.interceptors.request.use(config => {
    config.headers['Authorization'] = 'Bearer ' + localGet('token')
    return config
}, error => {
    return Promise.reject(error)
})

axios.interceptors.response.use(res => {
    console.log(res)
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
    let res = error.response;
    console.log(res)
    if (res.status == 401) {
        router.push({
            name: 'login'
        })
    }
    ElMessage.error(res.data.msg || 'Server Error 2')
    return Promise.reject(error)
})

export default axios
