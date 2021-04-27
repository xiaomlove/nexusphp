import axios from "./axios";

const api = {
    login: (params = {}) => {
        return axios.post('login', params);
    },
    logout: (params = {}) => {
        return axios.post('logout');
    },
    listAllowAgent: (params = {}) => {
        return axios.get('agent-allow', {params: params});
    },
    storeAllowAgent: (params = {}) => {
        return axios.post('agent-allow', params);
    },
    updateAllowAgent: (id, params = {}) => {
        return axios.put('agent-allow/' + id, params);
    },
    getAllowAgent: (id) => {
        return axios.get('agent-allow/' + id);
    },
    deleteAllowAgent: (id) => {
        return axios.delete('agent-allow/' + id);
    },

    listUser: (params = {}) => {
        return axios.get('user', {params: params});
    },
    getUser: (id, params = {}) => {
        return axios.get('user/' + id, {params: params});
    },
    getUserBase: (params = {}) => {
        return axios.get('user-base', {params: params});
    },
    storeUser: (params = {}) => {
        return axios.post('user', params);
    },

    listExam: (params = {}) => {
        return axios.get('exam', {params: params});
    },
    listExamIndex: (params = {}) => {
        return axios.get('exam-indexes', {params: params});
    },
    storeExam: (params = {}) => {
        return axios.post('exam', params);
    },
    updateExam: (id, params = {}) => {
        return axios.put('exam/' + id, params);
    },
    getExam: (id) => {
        return axios.get('exam/' + id);
    },
    deleteExam: (id) => {
        return axios.delete('exam/' + id);
    },
    listClass: (params = {}) => {
        return axios.get('user-classes', {params: params});
    },
    listExamUser: (params = {}) => {
        return axios.get('exam-users', {params: params});
    },
    deleteExamUser: (id) => {
        return axios.delete('exam-users/' + id);
    },
}

export default api
