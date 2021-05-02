import axios from "./axios";

const api = {
    login: (params = {}) => {
        return axios.post('login', params);
    },
    logout: (params = {}) => {
        return axios.post('logout');
    },
    listAllowAgent: (params = {}) => {
        return axios.get('agent-allows', {params: params});
    },
    storeAllowAgent: (params = {}) => {
        return axios.post('agent-allows', params);
    },
    updateAllowAgent: (id, params = {}) => {
        return axios.put('agent-allows/' + id, params);
    },
    getAllowAgent: (id) => {
        return axios.get('agent-allows/' + id);
    },
    deleteAllowAgent: (id) => {
        return axios.delete('agent-allows/' + id);
    },

    listUser: (params = {}) => {
        return axios.get('users', {params: params});
    },
    getUser: (id, params = {}) => {
        return axios.get('users/' + id, {params: params});
    },
    getUserBase: (params = {}) => {
        return axios.get('user-base', {params: params});
    },
    storeUser: (params = {}) => {
        return axios.post('users', params);
    },
    listUserMatchExams: (params = {}) => {
        return axios.get('user-match-exams', {params: params});
    },

    listExam: (params = {}) => {
        return axios.get('exams', {params: params});
    },
    listExamIndex: (params = {}) => {
        return axios.get('exam-indexes', {params: params});
    },
    storeExam: (params = {}) => {
        return axios.post('exams', params);
    },
    updateExam: (id, params = {}) => {
        return axios.put('exams/' + id, params);
    },
    getExam: (id) => {
        return axios.get('exams/' + id);
    },
    deleteExam: (id) => {
        return axios.delete('exams/' + id);
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
    storeExamUser: (params) => {
        return axios.post('exam-users', params);
    },
}

export default api
