import { createRouter, createWebHistory } from 'vue-router'
import SessionListView from '../views/SessionListView.vue'
import SessionEditView from '../views/SessionEditView.vue'
import GameView from '../views/GameView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'session-list', component: SessionListView },
    { path: '/sessions/:id/edit', name: 'session-edit', component: SessionEditView, props: true },
    { path: '/sessions/:id/play', name: 'session-play', component: GameView, props: true },
  ],
})

export default router
