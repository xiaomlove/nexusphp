<template>
  <img alt="Vue logo" src="./assets/logo.png" />
  <HelloWorld msg="Hello Vue 3 + Vite" />
    <el-button type="primary"> Element UI </el-button>
    <div>
        <el-row>
            <el-col :span="12">Left</el-col>
            <el-col :span="12">Right</el-col>
        </el-row>
        <el-row>
            <el-form>
                <el-form-item label="测试" label-width="120px">
                    <el-checkbox-group v-model="checkList">
                        <el-checkbox label="复选框 A"></el-checkbox>
                        <el-checkbox label="复选框 B"></el-checkbox>
                        <el-checkbox label="复选框 C"></el-checkbox>
                        <el-checkbox label="禁用" disabled></el-checkbox>
                        <el-checkbox label="选中且禁用" disabled></el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
            </el-form>
        </el-row>
    </div>
</template>

<script>
import HelloWorld from './components/HelloWorld.vue'

export default {
    components: {
        HelloWorld
    },
    setup(props, context) {
        console.log(props, context)
        const checkList = [];
        return {
            checkList
        }
    }
}


// This starter template is using Vue 3 experimental <script setup> SFCs
// Check out https://github.com/vuejs/rfcs/blob/script-setup-2/active-rfcs/0000-script-setup.md
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  margin-top: 60px;
}
</style>
