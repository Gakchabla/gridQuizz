<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { apiFetch } from '../api/client'

const router = useRouter()

const sessions = ref([])
const gameInProgress = ref({})
const loading = ref(true)
const error = ref(null)

const newSession = ref({ name: '', revealDuration: 10, shuffled: false })
const creating = ref(false)

async function loadSessions() {
  loading.value = true
  try {
    const data = await apiFetch('/api/sessions')
    sessions.value = data.member
    await Promise.all(
      data.member.map(async (session) => {
        // One request per session (filtered collection) instead of one per
        // question — a 9-player session alone has 54 questions.
        const questions = await apiFetch(`/api/questions?session=${encodeURIComponent(session['@id'])}`)
        gameInProgress.value[session.id] = questions.member.some((q) => q.number !== null)
      }),
    )
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function createSession() {
  creating.value = true
  try {
    const session = await apiFetch('/api/sessions', {
      method: 'POST',
      body: JSON.stringify(newSession.value),
    })
    router.push({ name: 'session-edit', params: { id: session.id } })
  } catch (e) {
    error.value = e.message
  } finally {
    creating.value = false
  }
}

onMounted(loadSessions)
</script>

<template>
  <div class="page">
    <h1>🎯 Grid Quizz</h1>

    <section class="card">
      <h2>Sessions</h2>
      <p v-if="loading" class="empty-state">Chargement...</p>
      <p v-else-if="error" class="empty-state">Erreur : {{ error }}</p>
      <ul v-else-if="sessions.length" class="list">
        <li v-for="session in sessions" :key="session.id" class="list-item">
          <div>
            <strong>{{ session.name }}</strong>
            <div class="list-item-meta">
              {{ session.revealDuration }}s d'affichage — {{ session.shuffled ? 'ordre aléatoire' : 'ordre fixe' }}
            </div>
          </div>
          <div class="list-item-actions">
            <router-link class="btn btn-sm" :to="{ name: 'session-edit', params: { id: session.id } }">
              Éditer
            </router-link>
            <router-link
              v-if="gameInProgress[session.id]"
              class="btn btn-danger btn-sm"
              :to="{ name: 'session-play', params: { id: session.id }, query: { new: '1' } }"
            >
              ↺ Nouvelle partie
            </router-link>
            <router-link class="btn btn-primary btn-sm" :to="{ name: 'session-play', params: { id: session.id } }">
              ▶ Jouer
            </router-link>
          </div>
        </li>
      </ul>
      <p v-else class="empty-state">Aucune session pour le moment.</p>
    </section>

    <section class="card">
      <h2>Nouvelle session</h2>
      <form @submit.prevent="createSession">
        <label class="field">
          Nom
          <input v-model="newSession.name" type="text" required />
        </label>
        <div class="field-row">
          <label class="field">
            Durée d'affichage (s)
            <input v-model.number="newSession.revealDuration" type="number" min="1" required />
          </label>
          <label class="field field-checkbox">
            <input v-model="newSession.shuffled" type="checkbox" />
            Ordre aléatoire
          </label>
        </div>
        <button class="btn btn-primary" type="submit" :disabled="creating">Créer la session</button>
      </form>
    </section>
  </div>
</template>
