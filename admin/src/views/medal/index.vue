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
                prop="image_large"
                label="Large image"
            >
                <template #default="scope">
                    <el-image :src="scope.row.image_large" style="max-height: 200px" />
                </template>
            </el-table-column>
            <el-table-column
                prop="image_small"
                label="Small image"
            >
                <template #default="scope">
                    <el-image :src="scope.row.image_small" style="max-height: 200px" />
                </template>
            </el-table-column>

            <el-table-column
                prop="get_type_text"
                label="Get type"
            ></el-table-column>

            <el-table-column
                prop="price"
                label="Price(bonus)"
            ></el-table-column>

            <el-table-column
                prop="duration"
                label="Duration(day)"
            ></el-table-column>

            <el-table-column
                label="Action"
                width=""
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
    name: 'MedalTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()

        onMounted(() => {
            console.log('MedalTable onMounted')
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listMedal(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handleAdd = () => {
            router.push({ name: 'medal-form' })
        }
        const handleEdit = (id) => {
            router.push({ path: '/medal-form', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteMedal(id)
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
        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            fetchTableData,
            changePage,
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
