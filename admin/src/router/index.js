import { createWebHistory, createRouter } from "vue-router";
import Home from "../pages/index";
import AgentAllow from "../pages/agent-allow";

const routes = [
    {
        path: "/",
        name: "Home",
        component: Home,
    },
    {
        path: "/agent-allow",
        name: "Agent-allow",
        component: AgentAllow,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
