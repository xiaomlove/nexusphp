<template>
    <el-row :gutter="10" type="flex" start="start">
        <el-col :span="12">
            <el-card class="box-card">
                <template #header>
                    <div class="card-header">
                        <span>卡片名称</span>
                        <el-button class="button" type="text">操作按钮</el-button>
                    </div>
                </template>
                <div v-for="o in 4" :key="o" class="text item">
                    {{'列表内容 ' + o }}
                </div>
            </el-card>
        </el-col>
        <el-col :span="12">
            <el-card class="box-card">
                <template #header>
                    <div class="card-header">
                        <span>卡片名称</span>
                        <el-button class="button" type="text">操作按钮</el-button>
                    </div>
                </template>
                <div v-for="o in 4" :key="o" class="text item">
                    {{'列表内容 ' + o }}
                </div>
            </el-card>
        </el-col>
    </el-row>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import AgentAllowForm from './form.vue'
import axios from '@/utils/axios'
import api from '@/utils/api'

export default {
    name: 'Swiper',
    components: {

    },
    setup() {
        const router = useRouter()
        const multipleTable = ref(null)
        const addGood = ref(null)
        const state = reactive({
            loading: false,
            tableData: [], // 数据列表
            multipleSelection: [], // 选中项
            total: 0, // 总条数
            currentPage: 1, // 当前页
            pageSize: 10, // 分页大小
            type: 'add', // 操作类型
            sortField: 'id',
            sortType: 'desc'
        })
        onMounted(() => {
            // getCarousels()
            listUser()
        })
        // 获取轮播图列表
        const getCarousels = () => {
            // state.loading = true
            // axios.get('/carousels', {
            //     params: {
            //         pageNumber: state.currentPage,
            //         pageSize: state.pageSize
            //     }
            // }).then(res => {
            //     state.tableData = res.list
            //     state.total = res.totalCount
            //     state.currentPage = res.currPage
            //     state.loading = false
            // })
        }

        const listUser = () => {
            state.loading = true
            let params = {
                page: state.currentPage,
                sort_field: state.sortField,
                sort_type: state.sortType
            }
            api.listUser(params).then(res => {
                state.tableData = res.data
                state.total = res.meta.total
                state.currentPage = res.meta.current_page
                state.pageSize = res.meta.per_page
                state.loading = false
            })
        }
        const handleSortChange = (sort) => {
            console.log(sort)
            state.sortField = sort.prop
            state.sortType = sort.order
            listUser()
        }
        // 添加轮播项
        const handleAdd = () => {
            state.type = 'add'
            // addGood.value.open()
            router.push({
                name: "user-form"
            })
        }
        // 修改轮播图
        const handleEdit = (id) => {
            console.log("id", id)
            router.push({
                name: "agent-allow-form",
                query: {id: id}
            })
        }
        // 选择项
        const handleSelectionChange = (val) => {
            state.multipleSelection = val
        }
        // 批量删除
        const handleDelete = () => {
            if (!state.multipleSelection.length) {
                ElMessage.error('请选择项')
                return
            }
            axios.delete('/carousels', {
                data: {
                    ids: state.multipleSelection.map(i => i.carouselId)
                }
            }).then(() => {
                ElMessage.success('删除成功')
                getCarousels()
            })
        }
        // 单个删除
        const handleDeleteOne = (id) => {
            api.deleteAllowAgent(id).then(() => {
                ElMessage.success('删除成功')
                listUser()
            })
        }
        const changePage = (val) => {
            state.currentPage = val
            listUser()
        }
        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
            handleSortChange,
            addGood,
            handleAdd,
            handleEdit,
            handleDelete,
            handleDeleteOne,
            getCarousels,
            changePage
        }
    }
}
</script>

<style scoped>
.swiper-container {
    min-height: 100%;
}
.el-card.is-always-shadow {
    min-height: 100%!important;
}
</style>
