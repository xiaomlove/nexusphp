<template>
    <el-card class="swiper-container">
        <template #header>
            <div class="header">
                <el-button type="primary" size="small" icon="el-icon-plus" @click="handleAdd">增加</el-button>
                <!--                <el-popconfirm-->
                <!--                    title="确定删除吗？"-->
                <!--                    @confirm="handleDelete"-->
                <!--                >-->
                <!--                    <template #reference>-->
                <!--                        <el-button type="danger" size="small" icon="el-icon-delete">批量删除</el-button>-->
                <!--                    </template>-->
                <!--                </el-popconfirm>-->
            </div>
        </template>
        <el-table
            v-loading="loading"
            ref="multipleTable"
            :data="tableData"
            tooltip-effect="dark"
            style="width: 100%"
            @selection-change="handleSelectionChange"
            @sort-change="handleSortChange"
        >
            <el-table-column
                type="selection"
            >
            </el-table-column>
            <el-table-column
                label="ID"
                prop="id"
                width="60"
                sortable="custom"
            >
            </el-table-column>
            <el-table-column
                label="名称"
                prop="name"
            >
            </el-table-column>
            <el-table-column
                label="开始时间"
                prop="begin"
                sortable="custom"
            >
            </el-table-column>
            <el-table-column
                label="结束时间"
                prop="end"
                sortable="custom"
            ></el-table-column>
            <el-table-column
                prop="filters"
                label="适用用户"
            >
            </el-table-column>
            <el-table-column
                prop="requires"
                label="考核标准"
            >
            </el-table-column>
            <el-table-column
                label="状态"
                prop="status_text"
                sortable="custom"
            ></el-table-column>

            <el-table-column
                label="操作"
                width="100"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleDetail(scope.row.id)">详情</a>
                    <!--                    <el-popconfirm-->
                    <!--                        title="确定删除吗？"-->
                    <!--                        @confirm="handleDeleteOne(scope.row.id)"-->
                    <!--                    >-->
                    <!--                        <template #reference>-->
                    <!--                            <a style="cursor: pointer">删除</a>-->
                    <!--                        </template>-->
                    <!--                    </el-popconfirm>-->
                </template>
            </el-table-column>
        </el-table>
        <!--总数超过一页，再展示分页器-->
        <el-pagination
            background
            layout="prev, pager, next"
            :total="total"
            :page-size="pageSize"
            :current-page="currentPage"
            @current-change="changePage"
        />
    </el-card>
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
            getList()
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

        const getList = () => {
            state.loading = true
            let params = {
                page: state.currentPage,
                sort_field: state.sortField,
                sort_type: state.sortType
            }
            api.listExam(params).then(res => {
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
            getList()
        }
        // 添加轮播项
        const handleAdd = () => {
            state.type = 'add'
            // addGood.value.open()
            router.push({
                name: "exam-form"
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
        // 修改轮播图
        const handleDetail = (id) => {
            console.log("id", id)
            router.push({
                name: "user-detail",
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
                getList()
            })
        }
        const changePage = (val) => {
            state.currentPage = val
            getList()
        }
        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
            handleSortChange,
            addGood,
            handleAdd,
            handleEdit,
            handleDetail,
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
