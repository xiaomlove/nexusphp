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
            @selection-change="handleSelectionChange">
            <el-table-column
                type="selection"
            >
            </el-table-column>
            <el-table-column
                label="ID"
                prop="id"
                width="60"
            >
            </el-table-column>
            <el-table-column
                label="系列"
                prop="family"
            >
            </el-table-column>
            <el-table-column
                label="Agent 起始"
                prop="agent_start"
            >
            </el-table-column>
            <el-table-column
                label="Agent 模式串"
                prop="agent_pattern"
            ></el-table-column>
            <el-table-column
                label="Agent 匹配次数"
                prop="agent_match_num"
                width="80px"
            ></el-table-column>

            <el-table-column
                prop="peer_id_start"
                label="Peer ID 起始"
            >
            </el-table-column>
            <el-table-column
                prop="peer_id_pattern"
                label="Peer ID 模式串"
            >
            </el-table-column>
            <el-table-column
                label="Peer ID 匹配次数"
                prop="peer_id_match_num"
                width="80px"
            ></el-table-column>
            <el-table-column
                label="操作"
                width="100"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleEdit(scope.row.id)">修改</a>
                    <el-popconfirm
                        title="确定删除吗？"
                        @confirm="handleDeleteOne(scope.row.id)"
                    >
                        <template #reference>
                            <a style="cursor: pointer">删除</a>
                        </template>
                    </el-popconfirm>
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
        })
        onMounted(() => {
            // getCarousels()
            listAgentAllow()
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

        const listAgentAllow = () => {
            state.loading = true
            api.listAllowAgent({page: state.currentPage}).then(res => {
                state.tableData = res.data
                state.total = res.meta.total
                state.currentPage = res.meta.current_page
                state.pageSize = res.meta.per_page
                state.loading = false
            })
        }
        // 添加轮播项
        const handleAdd = () => {
            state.type = 'add'
            // addGood.value.open()
            router.push({
                name: "agent-allow-form"
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
                listAgentAllow()
            })
        }
        const changePage = (val) => {
            state.currentPage = val
            listAgentAllow()
        }
        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
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
