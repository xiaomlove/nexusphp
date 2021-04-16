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
}

export default api
