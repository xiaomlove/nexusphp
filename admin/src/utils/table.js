import {ref, reactive} from 'vue'

const useTable =  () => {
    const state = reactive({
        loading: false,
        query: {
            page: 1,
            sort_field: 'id',
            sort_type: 'desc',
        },
        tableData: [],
        multipleSelection: [],
        total: 0,
        currentPage: 1,
        perPage: 10
    })
    return state
}

const renderTableData = (res, state) => {
    state.tableData = res.data.data
    state.page = res.data.meta.current_page
    state.total = res.data.meta.total
    state.currentPage = res.data.meta.current_page
    state.perPage = res.data.meta.per_page
}

const resetTableSort = (val, state) => {
    console.log('resetTableSort', val)
    state.query.page = 1
    state.query.sort_field = val.prop
    state.query.sort_type = val.order
}

export {
    useTable,
    renderTableData,
    resetTableSort
}
