import axios from "./axios";

const baseUrl = 'http://nexus-php8.tinyhd.net/api/';


const api = {
    listAllowAgent: (params = {}) => {
        return axios.get(baseUrl + 'agent-allow', {params: params});
    },
    storeAllowAgent: (params = {}) => {
        return axios.post(baseUrl + 'agent-allow', params);
    },
    updateAllowAgent: (id, params = {}) => {
        return axios.put(baseUrl + 'agent-allow/' + id, params);
    },
    getAllowAgent: (id) => {
        return axios.get(baseUrl + 'agent-allow/' + id);
    },
    deleteAllowAgent: (id) => {
        return axios.delete(baseUrl + 'agent-allow/' + id);
    },

    listUser: (params = {}) => {
        return axios.get(baseUrl + 'user', {params: params});
    },
    storeUser: (params = {}) => {
        return axios.post(baseUrl + 'user', params);
    },

    listExam: (params = {}) => {
        return axios.get(baseUrl + 'exam', {params: params});
    },
    storeExam: (params = {}) => {
        return axios.post(baseUrl + 'exam', params);
    },
    updateExam: (id, params = {}) => {
        return axios.put(baseUrl + 'exam/' + id, params);
    },
    getExam: (id) => {
        return axios.get(baseUrl + 'exam/' + id);
    },
    deleteExam: (id) => {
        return axios.delete(baseUrl + 'exam/' + id);
    },
    listClass: (params = {}) => {
        return axios.get(baseUrl + 'class', {params: params});
    },
}

export default api
