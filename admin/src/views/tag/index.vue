<template>
    <el-card class="">
        <template #header>
            <div class="nexus-table-header">
                <div class="left">

                </div>
                <div class="right">
                    <el-button type="primary" icon="Plus" @click="handleAdd">Add</el-button>
                </div>
            </div>
        </template>
        <el-table
            v-loading="loading"
            ref="multipleTable"
            :data="tableData"
            tooltip-effect="dark"
            @selection-change="handleSelectionChange">
            <el-table-column
                type="selection"
                width="55">
            </el-table-column>
            <el-table-column
                prop="id"
                label="Id"
                width="50"
            >
            </el-table-column>
            <el-table-column
                prop="name"
                label="Name"
            ></el-table-column>

            <el-table-column
                prop="color"
                label="Background color"
            >
            </el-table-column>

            <el-table-column
                prop="font_color"
                label="Font color"
            >
            </el-table-column>

            <el-table-column
                prop="font_size"
                label="Font size"
            >
            </el-table-column>

            <el-table-column
                prop="margin"
                label="Margin"
            >
            </el-table-column>

            <el-table-column
                prop="padding"
                label="Padding"
            >
            </el-table-column>

            <el-table-column
                prop="border_radius"
                label="Border radius"
            >
            </el-table-column>

            <el-table-column
                prop="priority"
                label="Priority"
            >
            </el-table-column>

            <el-table-column
                prop="updated_at"
                label="Updated at"
            ></el-table-column>

            <el-table-column
                label="Action"
                width="120"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleEdit(scope.row.id)">Edit</a>
                    <el-popconfirm
                        title="Confirm Delete ?"
                        @confirm="handleDelete(scope.row.id)"
                    >
                        <template #reference>
                            <a style="cursor: pointer">Delete</a>
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
            :page-size="perPage"
            :current-page="currentPage"
            @current-change="changePage"
        />
    </el-card>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import api from '../../utils/api'
import { useTable, renderTableData } from '../../utils/table'

export default {
    name: 'TagTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()
        let extraData = reactive({
            agentAllows: []
        });

        onMounted(() => {
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listTag(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handleAdd = () => {
            router.push({ name: 'tag-form' })
        }
        const handleEdit = (id) => {
            router.push({ path: '/tag-form', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteTag(id)
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleSelectionChange = (val) => {
            state.multipleSelection = val
        }
        const changePage = (val) => {
            state.query.page = val
            fetchTableData()
        }

        const handleReset = () => {
            state.query.family_id = '';
        }

        return {
            ...toRefs(state),
            extraData,
            multipleTable,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            fetchTableData,
            changePage,
            handleReset,
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
