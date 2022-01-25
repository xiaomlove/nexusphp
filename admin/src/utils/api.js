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
    getInviteInfo: (params = {}) => {
        return axios.get('user-invite-info', {params: params});
    },
    getUserModComment: (params = {}) => {
        return axios.get('user-mod-comment', {params: params});
    },
    storeUser: (params = {}) => {
        return axios.post('users', params);
    },
    disableUser: (params = {}) => {
        return axios.post('user-disable', params);
    },
    enableUser: (params = {}) => {
        return axios.post('user-enable', params);
    },
    resetPassword: (params = {}) => {
        return axios.post('user-reset-password', params);
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

    listMedal: (params = {}) => {
        return axios.get('medals', {params: params});
    },
    storeMedal: (params = {}) => {
        return axios.post('medals', params);
    },
    updateMedal: (id, params = {}) => {
        return axios.put('medals/' + id, params);
    },
    getMedal: (id) => {
        return axios.get('medals/' + id);
    },
    deleteMedal: (id) => {
        return axios.delete('medals/' + id);
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
    avoidExamUser: (id) => {
        return axios.put('exam-users-avoid', {id});
    },
    recoverExamUser: (id) => {
        return axios.put('exam-users-recover', {id});
    },
    storeExamUser: (params) => {
        return axios.post('exam-users', params);
    },
    storeSetting: (params) => {
        return axios.post('settings', params);
    },
    listSetting: (params) => {
        return axios.get('settings', {params});
    },
    listStatData: () => {
        return axios.get('dashboard/stat-data')
    },
    listLatestUser: () => {
        return axios.get('dashboard/latest-user')
    },
    listLatestTorrent: () => {
        return axios.get('dashboard/latest-torrent')
    },
    listSystemInfo: () => {
        return axios.get('dashboard/system-info')
    },
    removeUserMedal: (id) => {
        return axios.delete('user-medals/' + id);
    },
    storeUserMedal: (params) => {
        return axios.post('user-medals', params);
    },

}

export default api
