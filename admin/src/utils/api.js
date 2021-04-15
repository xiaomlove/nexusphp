import axios from "./axios";

const baseUrl = 'http://nexus-php8.tinyhd.net/api/';

const api = {
    listAllowAgent: (params = {}) => {
        return axios.get(baseUrl + 'agent-allow', {params: params});
    }
}

export default api
